<?php

namespace SmartReport\Features\Empreport\Models;

use SmartReport\Core\Database;
use SmartReport\Features\Calls\Models\CdrModel;
use SmartReport\Features\Calls\Services\RecordingService;

/**
 * Employee productivity, in two independent parts:
 *
 *  1. WORK SESSIONS - derived from the dial-in clock codes. An employee
 *     calls 88XX (8810-8899 range) from any phone; the dialplan dials the
 *     clock feature and hangs up. We read those CDR rows (src=extension,
 *     dst=code) and toggle sessions: code without an open session = clock-in,
 *     code with an open session = clock-out. No DB writes from the dialplan
 *     are needed - the CDR is the source of truth and it survives reloads.
 *
 *  2. CALL ACTIVITY - the same per-extension metrics as the extension
 *     report, keyed by the employee's assigned extension(s).
 */
class EmployeeModel
{
    const CODE_MIN = 8810;
    const CODE_MAX = 8899;

    private static $extCache = null;

    // ------------------------------------------------------------- roster

    public static function all($onlyActive = false)
    {
        $db = Database::main();
        $sql = 'SELECT * FROM smr_employees';
        if ($onlyActive) {
            $sql .= ' WHERE active = 1';
        }
        $sql .= ' ORDER BY name ASC';
        $rows = $db->fetchAll($sql);
        $sessions = self::openSessionMap();
        foreach ($rows as &$row) {
            $row['clocked_in'] = isset($sessions[$row['id']]);
            $row['clock_in_at'] = isset($sessions[$row['id']]) ? $sessions[$row['id']] : '';
        }
        unset($row);
        return $rows;
    }

    public static function find($id)
    {
        return Database::main()->fetchRow('SELECT * FROM smr_employees WHERE id = ?', [(int) $id]);
    }

    public static function codeExists($code, $exceptId = 0)
    {
        return (int) Database::main()->fetchValue(
            'SELECT COUNT(*) FROM smr_employees WHERE code = ? AND id <> ?',
            [(string) $code, (int) $exceptId]
        ) > 0;
    }

    public static function nextFreeCode()
    {
        $used = [];
        foreach (Database::main()->fetchAll('SELECT code FROM smr_employees') as $row) {
            $used[(string) $row['code']] = true;
        }
        for ($i = self::CODE_MIN; $i <= self::CODE_MAX; $i++) {
            if (!isset($used[(string) $i])) {
                return (string) $i;
            }
        }
        return '';
    }

    public static function create($data)
    {
        $now = date('Y-m-d H:i:s');
        return Database::main()->insert('smr_employees', [
            'code' => (string) $data['code'],
            'name' => (string) $data['name'],
            'extension' => isset($data['extension']) ? (string) $data['extension'] : '',
            'department' => isset($data['department']) ? (string) $data['department'] : '',
            'active' => !empty($data['active']) ? 1 : 0,
            'created_at' => $now,
        ]);
    }

    public static function update($id, array $data)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return Database::main()->update('smr_employees', $data, 'id = ?', [(int) $id]);
    }

    public static function delete($id)
    {
        $db = Database::main();
        $db->delete('smr_employee_sessions', 'employee_id = ?', [(int) $id]);
        return $db->delete('smr_employees', 'id = ?', [(int) $id]);
    }

    // ----------------------------------------------------------- sessions

    private static function openSessionMap()
    {
        $map = [];
        foreach (Database::main()->fetchAll('SELECT employee_id, MIN(clock_in) AS clock_in FROM smr_employee_sessions WHERE clock_out IS NULL GROUP BY employee_id') as $row) {
            $map[(int) $row['employee_id']] = (string) $row['clock_in'];
        }
        return $map;
    }

    /**
     * Derive sessions from CDR rows that dialed a clock code.
     *
     * Toggle rules: a code call from extension X for employee E opens a
     * session when none is open, and closes the open one when it exists.
     * Calls from unknown extensions are still tracked but not attributed
     * to a specific phone - src_ext keeps the number for auditing.
     */
    public static function deriveSessions(array $filters)
    {
        $rows = self::codeCalls($filters);
        $employees = [];
        $byCode = [];
        foreach (Database::main()->fetchAll('SELECT id, code, name, active FROM smr_employees') as $emp) {
            $employees[(int) $emp['id']] = $emp;
            $byCode[(string) $emp['code']] = (int) $emp['id'];
        }
        $open = self::openSessionMap();
        $openSessionId = [];
        foreach (Database::main()->fetchAll('SELECT id, employee_id FROM smr_employee_sessions WHERE clock_out IS NULL') as $s) {
            $openSessionId[(int) $s['employee_id']] = (int) $s['id'];
        }

        $events = [];
        foreach ($rows as $row) {
            $code = CdrModel::digits(isset($row['dst']) ? $row['dst'] : '');
            if (!isset($byCode[$code])) {
                continue;
            }
            $empId = $byCode[$code];
            $emp = $employees[$empId];
            if (empty($emp['active'])) {
                continue;
            }
            $events[] = [
                'employee_id' => $empId,
                'ts' => strtotime((string) $row['calldate']),
                'src' => CdrModel::digits(isset($row['src']) ? $row['src'] : ''),
                'uniqueid' => (string) (isset($row['uniqueid']) ? $row['uniqueid'] : ''),
            ];
        }
        usort($events, function ($a, $b) {
            return $a['ts'] - $b['ts'];
        });

        $db = Database::main();
        $inserts = 0;
        foreach ($events as $event) {
            $empId = $event['employee_id'];
            if (isset($open[$empId])) {
                // clock-out: close the open session
                $db->update(
                    'smr_employee_sessions',
                    ['clock_out' => date('Y-m-d H:i:s', $event['ts']), 'src_ext' => $event['src'] !== '' ? $event['src'] : null],
                    'id = ? AND clock_out IS NULL',
                    [$openSessionId[$empId]]
                );
                unset($open[$empId], $openSessionId[$empId]);
            } else {
                // clock-in: open a session
                $db->insert('smr_employee_sessions', [
                    'employee_id' => $empId,
                    'clock_in' => date('Y-m-d H:i:s', $event['ts']),
                    'clock_out' => null,
                    'src_ext' => $event['src'] !== '' ? $event['src'] : null,
                    'source' => 'code',
                ]);
                $openSessionId[$empId] = (int) $db->lastInsertId();
                $open[$empId] = date('Y-m-d H:i:s', $event['ts']);
                $inserts++;
            }
        }
        return ['events' => count($events), 'opened' => $inserts];
    }

    /** Raw CDR rows whose dst is one of the clock codes. */
    private static function codeCalls(array $filters)
    {
        $codes = [];
        foreach (Database::main()->fetchAll('SELECT code FROM smr_employees') as $row) {
            $codes[] = (string) $row['code'];
        }
        if (empty($codes)) {
            return [];
        }
        $db = Database::external('cdr');
        $where = 'calldate BETWEEN ? AND ? AND dst IN (' . implode(', ', array_fill(0, count($codes), '?')) . ')';
        $params = array_merge(
            [$filters['date_from'] . ' 00:00:00', $filters['date_to'] . ' 23:59:59'],
            $codes
        );
        return $db->fetchAll(
            'SELECT calldate, src, dst, uniqueid, disposition FROM cdr WHERE ' . $where . ' ORDER BY calldate ASC',
            $params
        );
    }

    /** Session list per employee (for the detail view). */
    public static function sessionsOf($employeeId, array $filters)
    {
        return Database::main()->fetchAll(
            'SELECT * FROM smr_employee_sessions WHERE employee_id = ? AND clock_in BETWEEN ? AND ? ORDER BY clock_in ASC',
            [(int) $employeeId, $filters['date_from'] . ' 00:00:00', $filters['date_to'] . ' 23:59:59']
        );
    }

    /**
     * Per-employee work + call metrics over the filter range.
     * Computed live from the sessions table (sync first via deriveSessions)
     * and the call facts attributed to each employee's extension.
     */
    public static function report(array $filters)
    {
        $employees = self::all();
        $extsByEmp = [];
        foreach ($employees as $emp) {
            $ext = CdrModel::digits(isset($emp['extension']) ? $emp['extension'] : '');
            if ($ext !== '') {
                $extsByEmp[$ext][] = (int) $emp['id'];
            }
        }

        // Call metrics per extension (reuse extension pipeline).
        $extMetrics = [];
        try {
            $model = new CdrModel();
            $facts = $model->factsInRange($filters);
            $linkedids = [];
            foreach ($facts as $fact) {
                $linkedids[] = isset($fact['linkedid']) ? $fact['linkedid'] : '';
            }
            $legs = $model->legsForFacts($linkedids);
            $model->applyLegs($facts, $legs);

            $recorder = new RecordingService();
            foreach ($facts as &$fact) {
                $fact['hasRecording'] = !empty($fact['recordingUniqueid']) && $recorder->resolve($fact) !== null;
            }
            unset($fact);

            foreach ($facts as $fact) {
                $legsF = isset($fact['legs']) && is_array($fact['legs']) ? $fact['legs'] : [$fact];
                foreach (\SmartReport\Features\Extreport\Models\ExtensionReportModel::extensionsOf($fact, $legsF) as $ext) {
                    if (!isset($extsByEmp[$ext])) {
                        continue;
                    }
                    foreach ($extsByEmp[$ext] as $empId) {
                        if (!isset($extMetrics[$empId])) {
                            $extMetrics[$empId] = ['calls' => 0, 'talk' => 0, 'missed' => 0, 'in' => 0, 'out' => 0, 'int' => 0];
                        }
                        $m = &$extMetrics[$empId];
                        $m['calls']++;
                        $dir = isset($fact['direction']) ? $fact['direction'] : '';
                        if (isset($m[$dir])) {
                            $m[$dir]++;
                        }
                        $m['talk'] += (int) (isset($fact['talkTime']) ? $fact['talkTime'] : 0);
                        if ($dir === 'in' && empty($fact['humanAnswered'])) {
                            $m['missed']++;
                        }
                        unset($m);
                    }
                }
            }
        } catch (\Exception $e) {
            $extMetrics = [];
        }

        $from = $filters['date_from'] . ' 00:00:00';
        $to = $filters['date_to'] . ' 23:59:59';

        $out = [];
        foreach ($employees as $emp) {
            $id = (int) $emp['id'];
            $sessions = Database::main()->fetchAll(
                'SELECT clock_in, clock_out FROM smr_employee_sessions WHERE employee_id = ? AND clock_in BETWEEN ? AND ? ORDER BY clock_in ASC',
                [$id, $from, $to]
            );
            $seconds = 0;
            foreach ($sessions as $s) {
                $endTs = $s['clock_out'] !== null ? strtotime((string) $s['clock_out']) : time();
                $startTs = strtotime((string) $s['clock_in']);
                if ($endTs !== false && $startTs !== false && $endTs > $startTs) {
                    $seconds += ($endTs - $startTs);
                }
            }
            $m = isset($extMetrics[$id]) ? $extMetrics[$id] : ['calls' => 0, 'talk' => 0, 'missed' => 0, 'in' => 0, 'out' => 0, 'int' => 0];

            $out[] = [
                'id' => $id,
                'code' => (string) $emp['code'],
                'name' => (string) $emp['name'],
                'extension' => (string) (isset($emp['extension']) ? $emp['extension'] : ''),
                'department' => (string) (isset($emp['department']) ? $emp['department'] : ''),
                'active' => (int) $emp['active'],
                'clocked_in' => !empty($emp['clocked_in']),
                'clock_in_at' => (string) (isset($emp['clock_in_at']) ? $emp['clock_in_at'] : ''),
                'sessions' => count($sessions),
                'work_seconds' => $seconds,
                'calls' => $m['calls'],
                'talk' => $m['talk'],
                'missed' => $m['missed'],
                'in' => $m['in'],
                'out' => $m['out'],
                'int' => $m['int'],
                'talk_per_hour' => $seconds > 0 ? round(3600 * $m['talk'] / $seconds, 1) : 0.0,
            ];
        }
        return $out;
    }
}
