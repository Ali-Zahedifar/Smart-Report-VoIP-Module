<?php

namespace SmartReport\Features\Calls\Models;

use SmartReport\Core\Database;
use SmartReport\Features\Calls\Services\ReferenceRepository;
use SmartReport\Services\CacheService;

class CdrModel
{
    private static $BASE_COLUMNS = ['calldate', 'clid', 'src', 'dst', 'dcontext', 'duration', 'billsec', 'disposition', 'accountcode', 'uniqueid'];

    private static $LEG_COLUMNS = ['channel', 'dstchannel', 'lastapp', 'lastdata', 'sequence'];

    private static $DISPOSITIONS = ['ANSWERED', 'NO ANSWER', 'BUSY', 'FAILED', 'CONGESTION', 'CANCEL'];

    /** Valid direction buckets used by list filters. */
    public static $DIRECTIONS = ['in', 'out', 'int'];

    private static $AUTOMATION_APPS = [
        'VOICEMAIL', 'VOICEMAILMAIN', 'PLAYBACK', 'ANNOUNCEMENT', 'ANSWER',
        'IVR', 'WAIT', 'CONGESTION', 'BUSY', 'HANGUP', 'CONTROLPLAYBACK',
        'WAITEXTEN', 'SENDDTMF', 'READ', 'SAYDIGITS', 'SAYNUMBER', 'WAITFORRING',
        'BACKGROUND',
    ];

    private static $FACTS_CAP = 50000;

    private $db;
    private $columns = [];
    private $legacy = false;

    public function __construct()
    {
        $this->db = Database::external('cdr');
        $this->columns = $this->columns();
        $this->legacy = !in_array('linkedid', $this->columns, true);
    }

    public function columns()
    {
        $cached = CacheService::get('cdr_columns_v1', null);
        if (is_array($cached)) {
            return $cached;
        }
        $cols = [];
        try {
            $rows = $this->db->fetchAll('SHOW COLUMNS FROM cdr');
            foreach ($rows as $row) {
                $field = isset($row['Field']) ? $row['Field'] : (isset($row[0]) ? $row[0] : '');
                $cols[] = (string) $field;
            }
        } catch (\Exception $e) {
            return self::$BASE_COLUMNS;
        }
        CacheService::set('cdr_columns_v1', $cols, 3600);
        return $cols;
    }

    public function hasColumn($column)
    {
        return in_array($column, $this->columns, true);
    }

    public function isLegacy()
    {
        return $this->legacy;
    }

    public function selectColumns()
    {
        $cols = self::$BASE_COLUMNS;
        if (in_array('linkedid', $this->columns, true)) {
            array_unshift($cols, 'linkedid');
        }
        if (in_array('recordingfile', $this->columns, true)) {
            $cols[] = 'recordingfile';
        }
        foreach (self::$LEG_COLUMNS as $col) {
            if (in_array($col, $this->columns, true)) {
                $cols[] = $col;
            }
        }
        return implode(', ', $cols);
    }

    /**
     * @deprecated leg-level listing used only when the CDR table has no linkedid
     */
    public function listings(array $filters, $perPage, $offset)
    {
        $range = $this->rangeParams($filters);
        list($where, $params) = $this->legWhere($filters, $range);
        $total = (int) $this->db->fetchValue('SELECT COUNT(*) FROM cdr WHERE ' . $where, $params);
        $rows = $this->db->fetchAll(
            'SELECT ' . $this->selectColumns() . ' FROM cdr WHERE ' . $where . ' ORDER BY calldate DESC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset,
            $params
        );
        return ['rows' => $rows, 'total' => $total];
    }

    public function findByUniqueId($uniqueid)
    {
        return $this->db->fetchRow(
            'SELECT ' . $this->selectColumns() . ' FROM cdr WHERE uniqueid = ? ORDER BY calldate DESC LIMIT 1',
            [$uniqueid]
        );
    }

    /**
     * Clock-code range used by the employee report (dial 8810-8899 to clock
     * in/out). These calls are tracking signals, not phone calls: they are
     * excluded from call facts so they never pollute direction counts or
     * talk-time stats. Configurable via external.clock_codes {min,max,enabled}.
     */
    public static function isClockCode($dst)
    {
        $digits = self::digits($dst);
        if ($digits === '' || strlen($digits) !== 4) {
            return false;
        }
        static $range = null;
        if ($range === null) {
            $cfg = \SmartReport\Core\Config::get('external.clock_codes', []);
            $cfg = is_array($cfg) ? $cfg : [];
            $range = [
                'enabled' => !isset($cfg['enabled']) || !empty($cfg['enabled']),
                'min' => isset($cfg['min']) ? (int) $cfg['min'] : 8810,
                'max' => isset($cfg['max']) ? (int) $cfg['max'] : 8899,
            ];
        }
        if (!$range['enabled']) {
            return false;
        }
        $num = (int) $digits;
        return $num >= $range['min'] && $num <= $range['max'];
    }

    /**
     * Call facts: one classified row per linkedid. All CDR rows of the range are
     * fetched and folded per call in PHP so the "entry" leg is the real origin
     * (lowest sequence, preferring the row whose uniqueid equals the linkedid).
     * Direction is derived from dcontext + trunk channel + number patterns.
     * Leg enrichment (talk time, exact outcome, recording leg, missed reason)
     * is applied afterwards via applyLegs().
     */
    public function factsInRange(array $filters, $cap = null)
    {
        if ($cap === null) {
            $cap = self::$FACTS_CAP;
        }
        $cap = (int) max(1, $cap);

        if ($this->legacy) {
            return $this->legacyFacts($filters, $cap);
        }

        $range = $this->rangeParams($filters);
        $rowCap = ceil($cap * 4) + 200000;
        $rows = $this->db->fetchAll(
            'SELECT ' . $this->selectColumns() . ' FROM cdr WHERE calldate BETWEEN ? AND ? ORDER BY calldate ASC LIMIT ' . (int) $rowCap,
            $range
        );

        $groups = [];
        foreach ($rows as $row) {
            $lid = isset($row['linkedid']) ? (string) $row['linkedid'] : '';
            if ($lid === '' || $lid === '0' || !isset($row['uniqueid']) || (string) $row['uniqueid'] === '') {
                continue;
            }
            $groups[$lid][] = $row;
        }

        $ref = new ReferenceRepository();
        $ref->ensureLoaded();
        $facts = [];
        foreach ($groups as $lid => $legs) {
            if (count($facts) >= $cap) {
                break;
            }
            $entry = $this->pickOrigin($lid, $legs);
            $entry['linkedid'] = $lid;
            $entry['leg_count'] = count($legs);
            if (self::isClockCode(isset($entry['dst']) ? $entry['dst'] : '')) {
                continue; // clock-code tracking call, not a phone call
            }
            $fact = $this->classifyEntry($ref, $entry, $legs);
            $fact['legs'] = $legs;
            $facts[] = $fact;
        }

        usort($facts, function ($a, $b) {
            $ca = isset($a['calldate']) ? (string) $a['calldate'] : '';
            $cb = isset($b['calldate']) ? (string) $b['calldate'] : '';
            return strcmp($cb, $ca);
        });
        return $facts;
    }

    /**
     * Apply call-level filters (search across all legs, direction, outcome,
     * missed-only) after legs have been merged into the facts via applyLegs().
     */
    public function filterFacts(array $facts, array $filters)
    {
        $src = trim((string) (isset($filters['src']) ? $filters['src'] : ''));
        $dst = trim((string) (isset($filters['dst']) ? $filters['dst'] : ''));
        $clid = trim((string) (isset($filters['clid']) ? $filters['clid'] : ''));
        $disposition = (string) (isset($filters['disposition']) ? $filters['disposition'] : '');
        $direction = (string) (isset($filters['direction']) ? $filters['direction'] : '');
        if (!in_array($direction, ['in', 'out', 'int'], true)) {
            $direction = '';
        }
        $missedOnly = !empty($filters['missed']);

        $out = [];
        foreach ($facts as $fact) {
            if ($direction !== '' && (string) (isset($fact['direction']) ? $fact['direction'] : '') !== $direction) {
                continue;
            }
            if ($missedOnly) {
                $legs = isset($fact['legs']) && is_array($fact['legs']) ? $fact['legs'] : [$fact];
                if (!$this->isMissedCall($fact, $legs)) {
                    continue;
                }
            }
            if ($disposition !== '' && $disposition !== 'ANY') {
                $oc = (string) (isset($fact['outcome']) ? $fact['outcome'] : '');
                if ($oc === '') {
                    $oc = (string) (isset($fact['disposition']) ? $fact['disposition'] : '');
                }
                if (strtoupper($oc) !== strtoupper($disposition)) {
                    continue;
                }
            }
            if ($src !== '' || $dst !== '' || $clid !== '') {
                if (!$this->factMatchesParties($fact, $src, $dst, $clid)) {
                    continue;
                }
            }
            $out[] = $fact;
        }
        return $out;
    }

    public function countFactsInRange(array $filters)
    {
        if ($this->legacy) {
            $range = $this->rangeParams($filters);
            list($where, $params) = $this->legWhere($filters, $range);
            return (int) $this->db->fetchValue('SELECT COUNT(*) FROM cdr WHERE ' . $where, $params);
        }
        $range = $this->rangeParams($filters);
        return (int) $this->db->fetchValue(
            'SELECT COUNT(DISTINCT linkedid) FROM cdr WHERE calldate BETWEEN ? AND ?',
            $range
        );
    }

    public function legsForFacts(array $linkedids)
    {
        $map = [];
        $ids = [];
        foreach ($linkedids as $id) {
            $id = (string) $id;
            if ($id !== '') {
                $ids[$id] = true;
            }
        }
        $ids = array_keys($ids);
        if (empty($ids)) {
            return $map;
        }
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $rows = $this->db->fetchAll(
            'SELECT ' . $this->selectColumns() . ' FROM cdr WHERE linkedid IN (' . $placeholders . ') ORDER BY calldate ASC',
            $ids
        );
        foreach ($rows as $row) {
            $key = isset($row['linkedid']) ? (string) $row['linkedid'] : '';
            if ($key !== '') {
                $map[$key][] = $row;
            }
        }
        return $map;
    }

    /**
     * Merge state of all legs of a call into each fact (talk time uses the best
     * connected segment; outcome/missed become leg-aware; recording disambiguates).
     */
    public function applyLegs(array &$facts, array $legsMap)
    {
        foreach ($facts as &$fact) {
            $linkedid = isset($fact['linkedid']) ? (string) $fact['linkedid'] : '';
            $legs = isset($legsMap[$linkedid]) ? $legsMap[$linkedid] : [];
            if (empty($legs)) {
                $legs = [$fact];
            }

            $humanAnswered = $this->hasHumanLeg($legs);
            $fact['humanAnswered'] = $humanAnswered;
            $fact['legCount'] = count($legs);
            $fact['legs'] = $legs;
            $fact['recordingUniqueid'] = '';
            $fact['recordingfile'] = '';

            $voicemailAnswered = $this->hasVoicemailLeg($legs);

            if ($humanAnswered) {
                $fact['outcome'] = 'ANSWERED';
                $best = $this->bestTalkLeg($legs);
                $fact['talkTime'] = (int) $best;
                foreach ($legs as $leg) {
                    $rf = isset($leg['recordingfile']) ? trim((string) $leg['recordingfile']) : '';
                    if ($rf !== '' && $fact['recordingUniqueid'] === '') {
                        $fact['recordingUniqueid'] = isset($leg['uniqueid']) ? (string) $leg['uniqueid'] : '';
                        $fact['recordingfile'] = $rf;
                    }
                }
            } else {
                $fact['talkTime'] = 0;
                if ($this->hasDisposition($legs, ['BUSY'])) {
                    $fact['outcome'] = 'BUSY';
                } elseif ($this->hasDisposition($legs, ['FAILED', 'CONGESTION'])) {
                    $fact['outcome'] = 'FAILED';
                } elseif ($this->hasDisposition($legs, ['CANCEL'])) {
                    $fact['outcome'] = 'CANCEL';
                } elseif ($voicemailAnswered) {
                    $fact['outcome'] = 'VOICEMAIL';
                } else {
                    $fact['outcome'] = 'NO ANSWER';
                }
                foreach ($legs as $leg) {
                    $rf = isset($leg['recordingfile']) ? trim((string) $leg['recordingfile']) : '';
                    if ($rf !== '' && $fact['recordingUniqueid'] === '') {
                        $fact['recordingUniqueid'] = isset($leg['uniqueid']) ? (string) $leg['uniqueid'] : '';
                        $fact['recordingfile'] = $rf;
                    }
                }
            }

            if ($fact['direction'] === 'in' && !$humanAnswered) {
                if ($voicemailAnswered) {
                    $fact['missedReason'] = 'voicemail';
                } elseif ($this->hasDisposition($legs, ['BUSY'])) {
                    $fact['missedReason'] = 'busy';
                } elseif ($this->hasDisposition($legs, ['FAILED', 'CONGESTION'])) {
                    $fact['missedReason'] = 'failed';
                } elseif ($this->hasDisposition($legs, ['CANCEL'])) {
                    $fact['missedReason'] = 'cancelled';
                } else {
                    $fact['missedReason'] = 'noanswer';
                }
            }
        }
        unset($fact);
    }

    public function todayStats(array $facts, $direction)
    {
        $total = 0;
        $answered = 0;
        $talkTotal = 0;
        $missed = 0;
        $buckets = ['noanswer' => 0, 'busy' => 0, 'cancelled' => 0, 'failed' => 0, 'voicemail' => 0];
        $direction = (string) $direction;

        foreach ($facts as $fact) {
            if ($direction !== '' && $fact['direction'] !== $direction) {
                continue;
            }
            $total++;
            if (!empty($fact['humanAnswered'])) {
                $answered++;
                $talkTotal += (int) $fact['talkTime'];
            }
            // Use comprehensive missed call detection for stats
            $legs = isset($fact['legs']) && is_array($fact['legs']) ? $fact['legs'] : [$fact];
            if ($this->isMissedCall($fact, $legs)) {
                $missed++;
                $reason = isset($fact['missedReason']) && $fact['missedReason'] !== '' ? $fact['missedReason'] : 'noanswer';
                if (isset($buckets[$reason])) {
                    $buckets[$reason]++;
                } else {
                    $buckets['noanswer']++;
                }
            }
        }

        return [
            'total' => $total,
            'answered' => $answered,
            'notAnswered' => $total - $answered,
            'avgTalk' => $answered > 0 ? (int) round($talkTotal / $answered) : 0,
            'missed' => $missed,
            'missedBuckets' => $buckets,
        ];
    }

    public function missedFacts(array $facts, $limit = 20)
    {
        $out = [];
        foreach ($facts as $fact) {
            // Use comprehensive missed call detection
            $legs = isset($fact['legs']) && is_array($fact['legs']) ? $fact['legs'] : [$fact];
            if (!$this->isMissedCall($fact, $legs)) {
                continue;
            }
            $out[] = $fact;
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    /**
     * Normalize a CDR number to ASCII digits: converts Persian/Arabic-Indic
     * digits (۰-۹, ٠-٩) and strips everything that is not 0-9. Bare preg_replace
     * with /\D/ is multibyte-UNSAFE: it strips Persian digits entirely, which
     * turned 109۰۳۳۴۳۹۳۶۳ into "109" and classified it as an internal call.
     */
    public static function digits($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        $value = strtr($value, [
                '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
                '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
                '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
                '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            ]);
        return preg_replace('/\D/', '', $value);
    }

    /**
     * Whether a destination number looks external. A number is external when it
     * has >= internal_max_length+1 digits (default 4). Trunk peers such as
     * 11577/21577 (5 digits) are therefore external, while real extensions
     * (2-4 digits on this site) are not. An explicit "internal" list in
     * config/external.php always wins, and so does the reference DB, which is
     * checked first by classifyDirection().
     */
    private function isExternalNumber($number)
    {
        $number = trim((string) $number);
        if ($number === '') {
            return false;
        }
        if (preg_match('/^[+*#]/', $number)) {
            return true;
        }
        $digits = self::digits($number);
        if ($digits === '') {
            return false;
        }
        static $maxLen = null;
        if ($maxLen === null) {
            $maxLen = (int) \SmartReport\Core\Config::get('external.internal_max_length', 3);
            if ($maxLen < 1) {
                $maxLen = 3;
            }
        }
        if (substr($digits, 0, 1) === '0' && strlen($digits) > 1) {
            return true;
        }
        return strlen($digits) > $maxLen;
    }

    private function classifyEntry(ReferenceRepository $ref, array $entry, $legs = null)
    {
        if ($legs === null) {
            $legs = [$entry];
        }
        $fact = $entry;
        $fact['direction'] = $this->classifyDirection($ref, $entry, $legs);
        $fact['humanAnswered'] = $this->hasHumanLeg($legs);
        $fact['legCount'] = (int) (isset($entry['leg_count']) ? $entry['leg_count'] : count($legs));
        $fact['talkTime'] = 0;
        $fact['recordingUniqueid'] = '';
        $fact['recordingfile'] = '';

        $voicemailAnswered = $this->hasVoicemailLeg($legs);
        $fact['outcome'] = 'NO ANSWER';
        if ($fact['humanAnswered']) {
            $fact['outcome'] = 'ANSWERED';
        } elseif ($this->hasDisposition($legs, ['BUSY'])) {
            $fact['outcome'] = 'BUSY';
        } elseif ($this->hasDisposition($legs, ['FAILED', 'CONGESTION'])) {
            $fact['outcome'] = 'FAILED';
        } elseif ($this->hasDisposition($legs, ['CANCEL'])) {
            $fact['outcome'] = 'CANCEL';
        } elseif ($voicemailAnswered) {
            $fact['outcome'] = 'VOICEMAIL';
        }

        $fact['missedReason'] = '';
        if ($fact['direction'] === 'in' && !$fact['humanAnswered']) {
            if ($voicemailAnswered) {
                $fact['missedReason'] = 'voicemail';
            } elseif ($this->hasDisposition($legs, ['BUSY'])) {
                $fact['missedReason'] = 'busy';
            } elseif ($this->hasDisposition($legs, ['FAILED', 'CONGESTION'])) {
                $fact['missedReason'] = 'failed';
            } elseif ($this->hasDisposition($legs, ['CANCEL'])) {
                $fact['missedReason'] = 'cancelled';
            } else {
                $fact['missedReason'] = 'noanswer';
            }
        }

        return $fact;
    }

    /**
     * Data-driven direction: the origin leg (lowest sequence / uniqueid==linkedid)
     * decides first, using dcontext + trunk channel + destination number patterns.
     * The Asterisk reference data (extensions, DIDs, trunks) enriches the result;
     * everything we learn from the CDR table itself also feeds the reference repo.
     */
    private function classifyDirection(ReferenceRepository $ref, array $entry, $legs = null)
    {
        if ($legs === null) {
            $legs = [$entry];
        }
        $src = isset($entry['src']) ? (string) $entry['src'] : '';
        $dst = isset($entry['dst']) ? (string) $entry['dst'] : '';
        $ctx = isset($entry['dcontext']) ? (string) $entry['dcontext'] : '';
        $channel = isset($entry['channel']) ? (string) $entry['channel'] : '';

        if (in_array($ctx, ['from-did-direct', 'from-pstn', 'ext-did', 'from-did'], true)) {
            return 'in';
        }

        if (preg_match('/^ivr-[0-9]+$/', $ctx) && $ref->isTrunkChannel($channel)) {
            return 'in';
        }

        if ($ctx === 'ext-queues') {
            if ($ref->isTrunkChannel($channel) && preg_match('/^[0-9]+$/', self::digits($dst))) {
                return 'in';
            }
            if (!$ref->isInternal($src) && $ref->isInternal(self::digits($dst))) {
                return 'in';
            }
            return 'out';
        }

        if ($ctx === 'ext-trunk' && $ref->isTrunkChannel($channel)) {
            return 'out';
        }

        if (preg_match('/^from-internal/', $ctx)) {
            $dstDigits = self::digits($dst);
            // Known internal extension via reference data wins.
            if ($dstDigits !== '' && $ref->isInternal($dstDigits)) {
                return 'int';
            }
            // If we know the src is internal but the destination is NOT known
            // internal, fall back to number shape: external-shaped numbers
            // (>= internal_max_length+1 digits, leading 0, + prefix) are
            // outbound. This is what keeps trunk peers like 11577/21577 and
            // full external numbers out of the internal bucket.
            if ($ref->isInternal(self::digits($src)) && $this->isExternalNumber($dstDigits)) {
                return 'out';
            }
            if (!$ref->isInternal(self::digits($src)) && !$ref->isInternal($dstDigits) && $this->isExternalNumber($dstDigits)) {
                return 'out';
            }
            return 'int';
        }

        $srcDigits = self::digits($src);
        $dstDigits = self::digits($dst);
        $srcInternal = $srcDigits !== '' && $ref->isInternal($srcDigits);
        $dstInternal = $dstDigits !== '' && $ref->isInternal($dstDigits);
        $dstDid = $dstDigits !== '' && $ref->isDid($dstDigits);

        // Internal = strictly between two known local extensions. Anything
        // where one side is not a known extension is inbound/outbound, never
        // "internal".
        if ($srcInternal && $dstInternal) {
            return 'int';
        }

        if (!$srcInternal && ($dstInternal || $dstDid)) {
            return 'in';
        }
        if ($srcInternal) {
            return 'out';
        }

        $inCtx = false;
        $outCtx = false;
        $trunk = false;
        foreach ($legs as $leg) {
            $c = isset($leg['dcontext']) ? (string) $leg['dcontext'] : '';
            if ($ref->isInboundContext($c)) {
                $inCtx = true;
            }
            if ($ref->isOutboundContext($c)) {
                $outCtx = true;
            }
            $ch = isset($leg['channel']) ? (string) $leg['channel'] : '';
            $dch = isset($leg['dstchannel']) ? (string) $leg['dstchannel'] : '';
            if ($ref->isTrunkChannel($ch) || $ref->isTrunkChannel($dch)) {
                $trunk = true;
            }
        }
        if ($inCtx) {
            return 'in';
        }
        if ($outCtx) {
            return 'out';
        }
        if ($trunk) {
            return 'out';
        }
        return 'unknown';
    }

    private function pickOrigin($linkedid, array $legs)
    {
        usort($legs, function ($a, $b) {
            $sa = $this->seqOf($a);
            $sb = $this->seqOf($b);
            if ($sa !== $sb) {
                return $sa - $sb;
            }
            return strcmp((string) (isset($a['calldate']) ? $a['calldate'] : ''), (string) (isset($b['calldate']) ? $b['calldate'] : ''));
        });
        foreach ($legs as $leg) {
            $uid = isset($leg['uniqueid']) ? (string) $leg['uniqueid'] : '';
            if ($uid !== '' && $uid === (string) $linkedid) {
                return $leg;
            }
        }
        return $legs[0];
    }

    private function seqOf(array $row)
    {
        $seq = isset($row['sequence']) ? trim((string) $row['sequence']) : '';
        if ($seq !== '' && is_numeric($seq)) {
            return (int) $seq;
        }
        $ts = strtotime(isset($row['calldate']) ? (string) $row['calldate'] : '');
        return $ts !== false ? $ts : 0;
    }

    private function bestTalkLeg(array $legs)
    {
        $best = 0;
        foreach ($legs as $leg) {
            $app = strtoupper((string) (isset($leg['lastapp']) ? $leg['lastapp'] : ''));
            if (strtoupper((string) $leg['disposition']) === 'ANSWERED' && !in_array($app, self::$AUTOMATION_APPS, true) && $app !== 'QUEUE' && (int) $leg['billsec'] > (int) $best) {
                $best = (int) $leg['billsec'];
            }
        }
        if ($best > 0) {
            return $best;
        }
        foreach ($legs as $leg) {
            $app = strtoupper((string) (isset($leg['lastapp']) ? $leg['lastapp'] : ''));
            if (strtoupper((string) $leg['disposition']) === 'ANSWERED' && $app === 'QUEUE' && (int) $leg['billsec'] > (int) $best) {
                $best = (int) $leg['billsec'];
            }
        }
        return $best;
    }

    private function factMatchesParties(array $fact, $src, $dst, $clid)
    {
        $legs = isset($fact['legs']) && is_array($fact['legs']) ? $fact['legs'] : [$fact];
        $queries = [];
        if ($src !== '') {
            $queries['src'] = $src;
        }
        if ($dst !== '') {
            $queries['dst'] = $dst;
        }
        if ($clid !== '') {
            $queries['clid'] = $clid;
        }
        foreach ($queries as $col => $needle) {
            $hit = false;
            foreach ($legs as $leg) {
                $value = isset($leg[$col]) ? (string) $leg[$col] : '';
                if ($value !== '' && strpos($value, $needle) !== false) {
                    $hit = true;
                    break;
                }
            }
            if (!$hit) {
                return false;
            }
        }
        return true;
    }



    private function hasHumanLeg(array $legs)
    {
        foreach ($legs as $leg) {
            if (strtoupper((string) $leg['disposition']) !== 'ANSWERED') {
                continue;
            }
            $app = strtoupper((string) (isset($leg['lastapp']) ? $leg['lastapp'] : ''));
            if (in_array($app, self::$AUTOMATION_APPS, true)) {
                continue;
            }
            return true;
        }
        return false;
    }

    private function hasVoicemailLeg(array $legs)
    {
        foreach ($legs as $leg) {
            if (strtoupper((string) $leg['disposition']) !== 'ANSWERED') {
                continue;
            }
            $app = strtoupper((string) (isset($leg['lastapp']) ? $leg['lastapp'] : ''));
            if ($app === 'VOICEMAIL' || $app === 'VOICEMAILMAIN') {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if a channel represents a human extension (SIP/XXX, Local/XXX@...)
     */
    private function isExtensionChannel($channel)
    {
        $channel = (string) $channel;
        if ($channel === '') {
            return false;
        }
        // Local/XXX@context - check if XXX is numeric (extension)
        if (strpos($channel, 'Local/') === 0) {
            if (preg_match('/^Local\/(\d+)@/', $channel, $m)) {
                $ext = (string) $m[1];
                return $ext !== '' && $ext !== '0';
            }
        }
        // SIP/XXX-, SIP/XXX, PJSIP/XXX- or PJSIP/XXX (with or without call-id suffix)
        if (preg_match('/^(SIP|PJSIP)\/(\d+)(?:-|$)/', $channel, $m)) {
            $ext = (string) $m[2];
            return $ext !== '' && $ext !== '0';
        }
        return false;
    }

    /**
     * Check if an inbound call had the opportunity to be answered by a human.
     * Returns true for:
     * - Queue calls with agent legs (Local/XXX@from-queue or from-internal)
     * - Ring group calls (multiple simultaneous Local/ legs in from-internal)
     * - Direct DID/extension calls (from-did-direct/pstn -> Local/SIP extension)
     * - IVR -> Extension (ivr-* -> extension leg)
     * - Overflow to external (queue leg with external dst)
     */
    private function hasHumanRingingOpportunity(array $legs)
    {
        $localLegs = [];
        $hasQueueAgentLeg = false;
        $hasExtensionLeg = false;
        $hasIvrLeg = false;
        $hasExternalOverflow = false;
        $hasInboundOrigin = false;

        foreach ($legs as $leg) {
            $dcontext = isset($leg['dcontext']) ? (string) $leg['dcontext'] : '';
            $channel = isset($leg['channel']) ? (string) $leg['channel'] : '';
            $dstchannel = isset($leg['dstchannel']) ? (string) $leg['dstchannel'] : '';
            $lastapp = isset($leg['lastapp']) ? strtoupper((string) $leg['lastapp']) : '';
            $dst = isset($leg['dst']) ? (string) $leg['dst'] : '';

            // IVR legs (origin context, independent of automation app)
            if (preg_match('/^ivr-\d+$/', $dcontext)) {
                $hasIvrLeg = true;
            }

            // Inbound origin contexts (direct DID / PSTN)
            if (in_array($dcontext, ['from-did-direct', 'from-pstn', 'from-did', 'ext-did'], true)) {
                $hasInboundOrigin = true;
            }

            // Queue agent legs: Local/XXX@from-queue-...;2 or Local/XXX@from-internal;2
            if (strpos($channel, 'Local/') === 0) {
                $localLegs[] = $leg;
                if (preg_match('/;2$/', $channel)) {
                    if (strpos($channel, '@from-queue') !== false || strpos($channel, '@from-internal') !== false) {
                        $hasQueueAgentLeg = true;
                    }
                }
            }

            // Extension legs: SIP/XXX- or Local/XXX@from-internal
            if ($this->isExtensionChannel($channel) || $this->isExtensionChannel($dstchannel)) {
                $hasExtensionLeg = true;
            }

            // Queue overflow to external number
            if ($dcontext === 'ext-queues' && $this->isExternalNumber($dst)) {
                $hasExternalOverflow = true;
            }

            // Skip automation apps (after flag detection so IVR/extension
            // opportunity is not lost to voicemail/background handlers)
            if (in_array($lastapp, self::$AUTOMATION_APPS, true)) {
                continue;
            }
        }

        // Queue with agent legs
        if ($hasQueueAgentLeg) {
            return true;
        }

        // Ring group: multiple Local/ legs in from-internal simultaneously
        // (detected by multiple Local/;2 legs in from-internal with same call time)
        $ringGroupCount = 0;
        foreach ($localLegs as $leg) {
            $ch = isset($leg['channel']) ? (string) $leg['channel'] : '';
            $ctx = isset($leg['dcontext']) ? (string) $leg['dcontext'] : '';
            if (strpos($ch, 'Local/') === 0 && preg_match('/;2$/', $ch) && $ctx === 'from-internal') {
                $ringGroupCount++;
            }
        }
        if ($ringGroupCount >= 2) {
            return true;
        }

        // Direct DID/extension or IVR -> extension
        if ($hasExtensionLeg && ($hasIvrLeg || $hasInboundOrigin)) {
            return true;
        }

        // Queue overflow to external
        if ($hasExternalOverflow) {
            return true;
        }

        return false;
    }

    /**
     * Comprehensive missed call detection.
     * Missed = inbound call that rang human endpoints but no human answered.
     * Includes: queues, ring groups, direct DID, IVR->ext, overflow to external.
     * Voicemail handled calls are counted as missed with reason 'voicemail'.
     */
    public function isMissedCall(array $entry, array $legs)
    {
        // 1. Must be inbound
        $dir = isset($entry['direction']) ? $entry['direction'] : '';
        if ($dir !== 'in') {
            return false;
        }

        // 2. Must have had human ringing opportunity
        //    (voicemail-answered calls are always counted as missed per spec)
        $vmLeg = $this->hasVoicemailLeg($legs);
        if (!$vmLeg && !$this->hasHumanRingingOpportunity($legs)) {
            return false;
        }

        // 3. No human answered
        if (!empty($entry['humanAnswered'])) {
            return false;
        }

        // 4. Final disposition is unanswered type (including voicemail)
        $outcome = isset($entry['outcome']) ? $entry['outcome'] : (isset($entry['disposition']) ? $entry['disposition'] : '');
        $disp = strtoupper((string) $outcome);
        if (!in_array($disp, ['NO ANSWER', 'BUSY', 'FAILED', 'CONGESTION', 'CANCEL', 'VOICEMAIL'], true)) {
            return false;
        }

        return true;
    }

    private function hasDisposition(array $legs, array $wanted)
    {
        $wanted = array_map('strtoupper', $wanted);
        foreach ($legs as $leg) {
            if (in_array(strtoupper((string) $leg['disposition']), $wanted, true)) {
                return true;
            }
        }
        return false;
    }

    private function legacyFacts(array $filters, $cap)
    {
        $range = $this->rangeParams($filters);
        list($where, $params) = $this->legWhere($filters, $range);
        $rows = $this->db->fetchAll(
            'SELECT ' . $this->selectColumns() . ' FROM cdr WHERE ' . $where . ' ORDER BY calldate DESC LIMIT ' . $cap,
            $params
        );
        $ref = new ReferenceRepository();
        $ref->ensureLoaded();
        $facts = [];
        foreach ($rows as $row) {
            if (self::isClockCode(isset($row['dst']) ? $row['dst'] : '')) {
                continue;
            }
            $row['leg_count'] = 1;
            $row['linkedid'] = isset($row['uniqueid']) ? $row['uniqueid'] : '';
            $facts[] = $this->classifyEntry($ref, $row);
        }
        return $facts;
    }

    /**
     * Filter legacy facts for missed calls, grouping by uniqueid to avoid
     * counting each leg as a separate missed call.
     */
    public function filterLegacyMissed(array $filters)
    {
        $range = $this->rangeParams($filters);
        list($where, $params) = $this->legWhere($filters, $range);
        $rows = $this->db->fetchAll(
            'SELECT ' . $this->selectColumns() . ' FROM cdr WHERE ' . $where . ' ORDER BY calldate ASC',
            $params
        );

        // Group by uniqueid
        $groups = [];
        foreach ($rows as $row) {
            $uid = isset($row['uniqueid']) ? (string) $row['uniqueid'] : '';
            if ($uid === '' || $uid === '0') {
                continue;
            }
            $groups[$uid][] = $row;
        }

        $ref = new ReferenceRepository();
        $ref->ensureLoaded();
        $missed = [];
        foreach ($groups as $uid => $legs) {
            // Pick origin leg (first by calldate)
            usort($legs, function ($a, $b) {
                return strcmp((string) $a['calldate'], (string) $b['calldate']);
            });
            $entry = $legs[0];
            $entry['linkedid'] = $uid;
            $entry['leg_count'] = count($legs);
            $fact = $this->classifyEntry($ref, $entry, $legs);
            $fact['legs'] = $legs;

            if ($this->isMissedCall($fact, $legs)) {
                $missed[] = $fact;
            }
        }

        // Sort by calldate desc
        usort($missed, function ($a, $b) {
            return strcmp((string) $b['calldate'], (string) $a['calldate']);
        });

        return $missed;
    }

    private function rangeParams(array $filters)
    {
        $default = date('Y-m-d', time());
        $dateFrom = (string) (isset($filters['date_from']) && $filters['date_from'] !== '' ? $filters['date_from'] : $default);
        $dateTo = (string) (isset($filters['date_to']) && $filters['date_to'] !== '' ? $filters['date_to'] : $default);
        return [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];
    }

    private function legWhere(array $filters, array $range)
    {
        $where = ['calldate BETWEEN ? AND ?'];
        $params = $range;

        foreach (['src', 'dst', 'clid'] as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $where[] = '`' . $col . '` LIKE ?';
                $params[] = '%' . str_replace(['%', '_'], ['\%', '\_'], (string) $filters[$col]) . '%';
            }
        }

        if (!empty($filters['disposition']) && $filters['disposition'] !== 'ANY' && in_array($filters['disposition'], self::$DISPOSITIONS, true)) {
            $where[] = 'disposition = ?';
            $params[] = $filters['disposition'];
        }

        return [implode(' AND ', $where), $params];
    }

    private function entryWhere(array $filters)
    {
        $where = ['1 = 1'];
        $params = [];

        foreach (['src', 'dst', 'clid'] as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $where[] = 'e.`' . $col . '` LIKE ?';
                $params[] = '%' . str_replace(['%', '_'], ['\%', '\_'], (string) $filters[$col]) . '%';
            }
        }

        if (!empty($filters['disposition']) && $filters['disposition'] !== 'ANY' && in_array($filters['disposition'], self::$DISPOSITIONS, true)) {
            $where[] = 'e.disposition = ?';
            $params[] = $filters['disposition'];
        }

        return [implode(' AND ', $where), $params];
    }

    public static function dispositions()
    {
        return self::$DISPOSITIONS;
    }

    public static function factsCap()
    {
        return self::$FACTS_CAP;
    }
}