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
                if ((string) (isset($fact['direction']) ? $fact['direction'] : '') !== 'in' || !empty($fact['humanAnswered'])) {
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
            if ($fact['direction'] === 'in' && empty($fact['humanAnswered'])) {
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
            if ($fact['direction'] !== 'in' || !empty($fact['humanAnswered'])) {
                continue;
            }
            $out[] = $fact;
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    private function classifyEntry(ReferenceRepository $ref, array $entry, array $legs = null)
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
    private function classifyDirection(ReferenceRepository $ref, array $entry, array $legs = null)
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

        if (preg_match('/^ivr-\d+$/', $ctx) && $ref->isTrunkChannel($channel)) {
            return 'in';
        }

        if ($ctx === 'ext-queues') {
            if ($ref->isTrunkChannel($channel) && preg_match('/^\d+$/', $dst)) {
                return 'in';
            }
            if (!$ref->isInternal($src) && $ref->isInternal(preg_replace('/\D/', '', $dst))) {
                return 'in';
            }
            return 'out';
        }

        if ($ctx === 'ext-trunk' && $ref->isTrunkChannel($channel)) {
            return 'out';
        }

        if (preg_match('/^from-internal/', $ctx)) {
            if (!$ref->isInternal(preg_replace('/\D/', '', $dst)) && $this->isExternalNumber($dst)) {
                return 'out';
            }
            return 'int';
        }

        $srcInternal = $ref->isInternal($src);
        $dstInternal = $ref->isInternal($dst);
        $dstDid = $ref->isDid($dst);

        if (!$srcInternal && ($dstInternal || $dstDid)) {
            return 'in';
        }
        if ($srcInternal && $dstInternal) {
            return 'int';
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

    private function isExternalNumber($number)
    {
        $number = trim((string) $number);
        if ($number === '') {
            return false;
        }
        if (preg_match('/^[+*#]/', $number)) {
            return true;
        }
        if (!preg_match('/^\d+$/', $number)) {
            return false;
        }
        if (substr($number, 0, 1) === '0') {
            return true;
        }
        return strlen($number) >= 6;
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
            $row['leg_count'] = 1;
            $row['linkedid'] = isset($row['uniqueid']) ? $row['uniqueid'] : '';
            $facts[] = $this->classifyEntry($ref, $row);
        }
        return $facts;
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