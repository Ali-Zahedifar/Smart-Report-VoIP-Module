<?php

namespace SmartReport\Features\Calls\Services;

use SmartReport\Core\Config;
use SmartReport\Core\Database;
use SmartReport\Services\CacheService;

/**
 * Site-specific call routing reference data (extensions, queues, ring groups,
 * inbound DIDs, trunks). Primary source is the Asterisk configuration database;
 * when it is unreachable the service falls back to the "routing" block of
 * config/external.php. No hard-coded SIP/PJSIP patterns are used for
 * classification - the reference data is the source of truth.
 */
class ReferenceRepository
{
    private $loaded = false;
    private $mode = 'patterns';
    private $internalExtensions = [];
    private $didExtensions = [];
    private $trunkIdents = [];
    private $contextsIn = ['from-pstn', 'from-did', 'from-trunk', 'from-did-direct', 'ext-did', 'ivr-*', 'ext-queues'];
    private $contextsOut = ['outbound-allroutes', 'out-', 'ext-trunk'];
    private $trunkChannelPatterns = ['PJSIP/', 'DAHDI/', 'IAX2/'];

    private $tableCanon = [
        'users' => ['extension'],
        'devices' => ['id'],
        'ringgroups' => ['grpnum'],
        'queues_config' => ['extension'],
        'incoming' => ['extension'],
        'trunks' => ['name', 'channelid'],
    ];

    public function __construct()
    {
        $this->loadConfigFallback();
    }

    public function ensureLoaded()
    {
        if ($this->loaded) {
            return;
        }
        $this->loaded = true;
        $cached = CacheService::get('refdata_v2', null);
        if (is_array($cached)) {
            $this->applyCache($cached);
            return;
        }
        $this->loadFromAsteriskDb();
        $this->learnFromCdr();
        if ($this->mode === 'db' || !empty($this->internalExtensions) || !empty($this->didExtensions) || !empty($this->trunkIdents)) {
            CacheService::set('refdata_v2', [
                'mode' => $this->mode,
                'exts' => $this->internalExtensions,
                'dids' => $this->didExtensions,
                'trunks' => $this->trunkIdents,
            ], 3600);
        }
    }

    public function mode()
    {
        $this->ensureLoaded();
        return $this->mode;
    }

    public function internalExtensions()
    {
        $this->ensureLoaded();
        return $this->internalExtensions;
    }

    public function isInternal($number)
    {
        $this->ensureLoaded();
        $key = preg_replace('/\D/', '', (string) $number);
        return $key !== '' && isset($this->internalExtensions[$key]);
    }

    public function isDid($number)
    {
        $this->ensureLoaded();
        $key = preg_replace('/\D/', '', (string) $number);
        return $key !== '' && isset($this->didExtensions[$key]);
    }

    public function isTrunkChannel($channel)
    {
        $this->ensureLoaded();
        $channel = (string) $channel;
        if ($channel === '') {
            return false;
        }
        foreach ($this->trunkIdents as $ident) {
            if ($ident !== '' && strpos($channel, $ident) === 0) {
                return true;
            }
        }
        foreach ($this->trunkChannelPatterns as $pattern) {
            if ($pattern !== '' && strpos($channel, $pattern) === 0) {
                return true;
            }
        }
        return false;
    }

    public function anyLegUsesTrunk(array $legs)
    {
        foreach ($legs as $leg) {
            $channel = isset($leg['channel']) ? (string) $leg['channel'] : '';
            $dstchannel = isset($leg['dstchannel']) ? (string) $leg['dstchannel'] : '';
            $lastapp = isset($leg['lastapp']) ? (string) $leg['lastapp'] : '';
            $isLocal = strpos($channel, 'Local/') === 0;
            $isLocalDst = strpos($dstchannel, 'Local/') === 0;
            if ($this->isTrunkChannel($channel) || $this->isTrunkChannel($dstchannel)) {
                return true;
            }
            if ($isLocal || $isLocalDst) {
                continue;
            }
            if ($this->inContexts($lastapp === 'Dial' ? (isset($leg['dcontext']) ? (string) $leg['dcontext'] : '') : (string) (isset($leg['dcontext']) ? $leg['dcontext'] : ''), $this->contextsOut)) {
                return true;
            }
        }
        return false;
    }

    public function isInboundContext($context)
    {
        return $this->inContexts((string) $context, $this->contextsIn);
    }

    public function isOutboundContext($context)
    {
        return $this->inContexts((string) $context, $this->contextsOut);
    }

    private function inContexts($context, array $patterns)
    {
        $context = (string) $context;
        if ($context === '') {
            return false;
        }
        foreach ($patterns as $pattern) {
            if ($pattern === '') {
                continue;
            }
            if (substr($pattern, -1) === '*') {
                if (strpos($context, rtrim($pattern, '*')) === 0) {
                    return true;
                }
            } elseif ($context === $pattern) {
                return true;
            }
        }
        return false;
    }

    private function loadFromAsteriskDb()
    {
        try {
            $db = Database::external('asterisk');
        } catch (\Exception $e) {
            $this->mode = 'patterns';
            return;
        }

        $tables = [];
        try {
            foreach ($db->fetchAll('SHOW TABLES') as $row) {
                $values = array_values($row);
                if (isset($values[0])) {
                    $tables[strtolower((string) $values[0])] = true;
                }
            }
        } catch (\Exception $e) {
            $this->mode = 'patterns';
            return;
        }

        $empty = true;
        foreach ($this->tableCanon as $table => $columns) {
            if (!isset($tables[$table]) || empty($columns)) {
                continue;
            }
            $rowNums = $this->readNumericColumn($db, $table, $columns[0]);
            if ($table === 'trunks') {
                foreach ($rowNums as $value) {
                    $this->trunkIdents[] = $value;
                }
                $extra = $this->readColumn($db, $table, isset($columns[1]) ? $columns[1] : 'channelid');
                foreach ($extra as $value) {
                    if (trim($value) === '') {
                        continue;
                    }
                    $this->trunkIdents[] = $this->normalizeTrunkIdent($value);
                }
            } elseif ($table === 'incoming') {
                foreach ($rowNums as $key => $value) {
                    $this->didExtensions[$key] = true;
                }
            } else {
                foreach ($rowNums as $key => $value) {
                    $this->internalExtensions[$key] = true;
                }
            }
        }

        $this->internalExtensions = array_filter($this->internalExtensions);
        $this->didExtensions = array_filter($this->didExtensions);
        $this->trunkIdents = array_values(array_unique(array_filter($this->trunkIdents)));

        if (!empty($this->internalExtensions) || !empty($this->didExtensions) || !empty($this->trunkIdents)) {
            $this->mode = 'db';
        }

        if ($empty && !empty($tables)) {
            $this->mode = 'patterns';
        }
    }

    private function readNumericColumn($db, $table, $column)
    {
        $out = [];
        try {
            $rows = $db->fetchAll('SELECT `' . $column . '` FROM `' . $table . '`');
            foreach ($rows as $row) {
                $value = isset($row[$column]) ? $row[$column] : (isset($row[0]) ? $row[0] : '');
                $key = preg_replace('/\D/', '', (string) $value);
                if ($key !== '' && $key !== '0') {
                    $out[$key] = true;
                }
            }
        } catch (\Exception $e) {
            // fall through
        }
        return $out;
    }

    private function readColumn($db, $table, $column)
    {
        $out = [];
        try {
            $rows = $db->fetchAll('SELECT `' . $column . '` FROM `' . $table . '`');
            foreach ($rows as $row) {
                $value = isset($row[$column]) ? $row[$column] : (isset($row[0]) ? $row[0] : '');
                $out[] = trim((string) $value);
            }
        } catch (\Exception $e) {
            // fall through
        }
        return $out;
    }

    private function normalizeTrunkIdent($value)
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (strpos($value, '/') !== false) {
            return $value;
        }
        return 'PJSIP/' . $value;
    }

    /**
     * Self-learning fallback: when the Asterisk configuration database carries no
     * usable reference data, learn extensions and trunk idents from the CDR table
     * itself. Extensions are numeric SIP peers that originate in the internal
     * dial plan; trunks are the channel peers seen on inbound/outbound contexts.
     */
    private function learnFromCdr()
    {
        try {
            $cdr = Database::external('cdr');
        } catch (\Exception $e) {
            return;
        }

        $this->trunkIdents = array_merge($this->trunkIdents, $this->learnTrunksFromCdr($cdr));

        try {
            $rows = $cdr->fetchAll(
                "SELECT channel, dcontext FROM cdr WHERE channel LIKE ? LIMIT 8000",
                ['SIP/%']
            );
            foreach ($rows as $row) {
                $channel = isset($row['channel']) ? (string) $row['channel'] : '';
                $ctx = isset($row['dcontext']) ? (string) $row['dcontext'] : '';
                if (!preg_match('/^SIP\/(\d+)-/', $channel, $m)) {
                    continue;
                }
                if (in_array($ctx, ['ext-trunk', 'from-did-direct', 'from-did', 'ext-queues', 'from-pstn'], true)) {
                    continue;
                }
                $key = (string) $m[1];
                if ($key === '' || $key === '0') {
                    continue;
                }
                if (in_array('SIP/' . $key, $this->trunkIdents, true)) {
                    continue;
                }
                $this->internalExtensions[$key] = true;
            }
        } catch (\Exception $e) {
            // fall through
        }

        $this->trunkIdents = array_values(array_unique(array_filter($this->trunkIdents)));
        $exts = [];
        foreach ($this->internalExtensions as $key => $value) {
            if ((string) $key !== '') {
                $exts[$key] = true;
            }
        }
        $this->internalExtensions = $exts;
    }

    private function learnTrunksFromCdr($cdr)
    {
        try {
            $rows = $cdr->fetchAll(
                "SELECT DISTINCT channel FROM cdr WHERE dcontext IN ('ext-trunk', 'from-did-direct', 'from-did', 'ext-queues', 'from-pstn') AND (channel LIKE 'SIP/%' OR channel LIKE 'PJSIP/%') LIMIT 8000"
            );
            foreach ($rows as $row) {
                $channel = isset($row['channel']) ? (string) $row['channel'] : '';
                if (preg_match('~^([A-Za-z0-9_]+)/([^/\s]+)-~', $channel, $m)) {
                    $peer = trim($m[2]);
                    if ($peer === '') {
                        continue;
                    }
                    $this->trunkIdents[] = $m[1] . '/' . $peer;
                }
            }
        } catch (\Exception $e) {
            // fall through
        }
        return array_values(array_unique(array_filter($this->trunkIdents)));
    }

    private function loadConfigFallback()
    {
        $routing = (array) Config::get('external.routing', []);
        if (empty($routing)) {
            return;
        }
        foreach ($routing as $key => $value) {
            if (!is_array($value)) {
                continue;
            }
            $items = [];
            foreach ($value as $item) {
                if (is_array($item)) {
                    continue;
                }
                $items[preg_replace('/\D/', '', (string) $item)] = true;
            }
            $items = array_filter($items);
            if (in_array($key, ['extensions', 'queues', 'ringgroups'], true)) {
                $this->internalExtensions = array_merge($this->internalExtensions, $items);
            } elseif ($key === 'dids') {
                $this->didExtensions = array_merge($this->didExtensions, $items);
            } elseif ($key === 'trunks') {
                foreach (array_keys($items) as $ident) {
                    if ($ident !== '') {
                        $this->trunkIdents[] = $this->normalizeTrunkIdent($ident);
                    }
                }
            }
        }
        if (isset($routing['contexts']) && is_array($routing['contexts'])) {
            if (isset($routing['contexts']['in']) && is_array($routing['contexts']['in'])) {
                $this->contextsIn = array_map('strval', $routing['contexts']['in']);
            }
            if (isset($routing['contexts']['out']) && is_array($routing['contexts']['out'])) {
                $this->contextsOut = array_map('strval', $routing['contexts']['out']);
            }
        }
        if (isset($routing['channels']) && is_array($routing['channels']) && isset($routing['channels']['trunk']) && is_array($routing['channels']['trunk'])) {
            $this->trunkChannelPatterns = array_map('strval', $routing['channels']['trunk']);
        }
    }

    private function applyCache(array $data)
    {
        $this->mode = isset($data['mode']) ? (string) $data['mode'] : 'patterns';
        if (isset($data['exts']) && is_array($data['exts'])) {
            $this->internalExtensions = $data['exts'];
        }
        if (isset($data['dids']) && is_array($data['dids'])) {
            $this->didExtensions = $data['dids'];
        }
        if (isset($data['trunks']) && is_array($data['trunks'])) {
            $this->trunkIdents = $data['trunks'];
        }
    }
}