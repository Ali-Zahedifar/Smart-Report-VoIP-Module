<?php

namespace SmartReport\Features\Calls\Controllers;

use SmartReport\Core\Config;
use SmartReport\Core\Controller;
use SmartReport\Core\Response;
use SmartReport\Features\Calls\Models\CdrModel;
use SmartReport\Features\Calls\Services\RecordingService;
use SmartReport\Services\Audit;
use SmartReport\Services\ExportService;

class CallsController extends Controller
{
    private static $DISPOSITIONS = ['ANSWERED', 'NO ANSWER', 'BUSY', 'FAILED', 'CONGESTION', 'CANCEL'];

    // 'int' is intentionally not offered in the All Calls filter: internal
    // traffic lives in its own Internal Calls section. filterFacts() still
    // accepts it for backward-compatible URLs.
    private static $DIRECTIONS = ['in', 'out'];

    public function index()
    {
        $filters = $this->filtersFromRequest();
        $choices = (array) Config::get('app.pagination_choices', [25, 50, 100, 250, 500]);
        $perPage = in_array((string) $this->query('limit', ''), array_map('strval', $choices), true)
            ? (int) $this->query('limit')
            : (int) Config::get('app.pagination_default', 50);
        $page = max(1, (int) $this->query('page', 1));
        $offset = ($page - 1) * $perPage;

        $legs = [];
        $mode = 'legacy';
        $rows = [];
        $total = 0;
        $hitCap = false;

        try {
            $model = new CdrModel();
            $external = true;
            $hasRecording = $model->hasColumn('recordingfile');
            $mode = $model->isLegacy() ? 'legacy' : 'linkedid';

            if ($mode === 'legacy') {
                $result = $model->listings($filters, $perPage, $offset);
                $rows = $result['rows'];
                $total = (int) $result['total'];

                $recorder = new RecordingService();
                foreach ($rows as &$row) {
                    $row['hasRecording'] = !empty($row['recordingfile']) && $recorder->resolve($row) !== null;
                }
                unset($row);
            } else {
                $facts = $model->factsInRange($filters);
                $hitCap = count($facts) >= (int) CdrModel::factsCap();
                $linkedids = [];
                foreach ($facts as $fact) {
                    $linkedids[] = isset($fact['linkedid']) ? $fact['linkedid'] : '';
                }
                $legs = $model->legsForFacts($linkedids);
                $model->applyLegs($facts, $legs);
                $facts = $model->filterFacts($facts, $filters);
                $total = count($facts);
                $paged = array_slice($facts, $offset, $perPage);
                $rows = $paged;

                // Read-only existence check per row: show play/download only
                // when the recording file is really on disk.
                $recorder = new RecordingService();
                foreach ($rows as &$row) {
                    $row['hasRecording'] = !empty($row['recordingUniqueid']) && $recorder->resolve($row) !== null;
                }
                unset($row);
            }
        } catch (\Exception $e) {
            $external = false;
            $error = $e->getMessage();
        }

        $this->view(':features/calls/views/list', [
            'title' => t('calls.title'),
            'filters' => $filters,
            'result' => ['rows' => $rows, 'total' => $total],
            'legs' => $legs,
            'perPage' => $perPage,
            'page' => $page,
            'limit' => $perPage,
            'hasRecording' => $hasRecording,
            'external' => $external,
            'error' => $error,
            'mode' => $mode,
            'hitCap' => $hitCap,
            'dispositions' => self::$DISPOSITIONS,
            'directions' => self::$DIRECTIONS,
            'showLegs' => (int) \SmartReport\Core\App::setting('ui.show_legs', 0) === 1,
            'limitChoices' => $choices,
            'queryString' => $this->queryStringForPagination($filters, $perPage),
        ]);
    }

    public function export()
    {
        $filters = $this->filtersFromRequest();
        $kind = $this->query('export', 'summary') === 'full' ? 'full' : 'summary';
        $direction = $filters['direction'];

        try {
            $model = new CdrModel();
            if ($model->isLegacy()) {
                $missedOnly = !empty($filters['missed']);
                if ($missedOnly) {
                    // Legacy mode missed export - group by uniqueid
                    $missedFacts = $model->filterLegacyMissed($filters);
                    $headers = ['calldate', 'direction', 'clid', 'src', 'dst', 'talk_time_seconds', 'outcome', 'missed_reason', 'recording'];
                    $data = [];
                    foreach ($missedFacts as $fact) {
                        $data[] = [
                            'calldate' => isset($fact['calldate']) ? $fact['calldate'] : '',
                            'direction' => isset($fact['direction']) ? $fact['direction'] : '',
                            'clid' => isset($fact['clid']) ? $fact['clid'] : '',
                            'src' => isset($fact['src']) ? $fact['src'] : '',
                            'dst' => isset($fact['dst']) ? $fact['dst'] : '',
                            'talk_time_seconds' => isset($fact['talkTime']) ? (int) $fact['talkTime'] : 0,
                            'outcome' => isset($fact['outcome']) ? $fact['outcome'] : '',
                            'missed_reason' => isset($fact['missedReason']) ? $fact['missedReason'] : '',
                            'recording' => isset($fact['recordingfile']) ? $fact['recordingfile'] : '',
                        ];
                    }
                    Audit::log('call_export', 'mode=legacy missed rows=' . count($data));
                    ExportService::csv('smartreport_calls_missed_' . date('Ymd_His') . '.csv', $headers, $data);
                    return;
                }

                $rows = $model->findAll($filters);
                $headers = ['calldate', 'clid', 'src', 'dst', 'dcontext', 'duration', 'billsec', 'disposition', 'accountcode', 'uniqueid'];
                if ($model->hasColumn('recordingfile')) {
                    $headers[] = 'recordingfile';
                }
                $data = [];
                foreach ($rows as $row) {
                    $line = [];
                    foreach ($headers as $col) {
                        $line[$col] = isset($row[$col]) ? $row[$col] : '';
                    }
                    $data[] = $line;
                }
                Audit::log('call_export', 'mode=legacy rows=' . count($data));
                ExportService::csv('smartreport_calls_' . date('Ymd_His') . '.csv', $headers, $data);
            }

            $facts = $model->factsInRange($filters);
            $linkedids = [];
            foreach ($facts as $fact) {
                $linkedids[] = isset($fact['linkedid']) ? $fact['linkedid'] : '';
            }
            $legs = $model->legsForFacts($linkedids);
            $model->applyLegs($facts, $legs);

            $facts = $model->filterFacts($facts, $filters);

            if ($kind === 'summary') {
                $headers = ['calldate', 'direction', 'clid', 'src', 'dst', 'talk_time_seconds', 'outcome', 'missed_reason', 'recording'];
                $data = [];
                foreach ($facts as $fact) {
                    $data[] = [
                        'calldate' => isset($fact['calldate']) ? $fact['calldate'] : '',
                        'direction' => isset($fact['direction']) ? $fact['direction'] : '',
                        'clid' => isset($fact['clid']) ? $fact['clid'] : '',
                        'src' => isset($fact['src']) ? $fact['src'] : '',
                        'dst' => isset($fact['dst']) ? $fact['dst'] : '',
                        'talk_time_seconds' => isset($fact['talkTime']) ? (int) $fact['talkTime'] : 0,
                        'outcome' => isset($fact['outcome']) ? $fact['outcome'] : '',
                        'missed_reason' => isset($fact['missedReason']) ? $fact['missedReason'] : '',
                        'recording' => isset($fact['recordingfile']) ? $fact['recordingfile'] : '',
                    ];
                }
                Audit::log('call_export', 'mode=summary rows=' . count($data));
                ExportService::csv('smartreport_calls_summary_' . date('Ymd_His') . '.csv', $headers, $data);
            }

            $headers = ['calldate', 'linkedid', 'direction', 'channel', 'dstchannel', 'clid', 'src', 'dst', 'dcontext', 'lastapp', 'lastdata', 'duration', 'billsec', 'disposition', 'accountcode', 'uniqueid'];
            if ($model->hasColumn('recordingfile')) {
                $headers[] = 'recordingfile';
            }
            $data = [];
            foreach ($facts as $fact) {
                $rows = isset($legs[$fact['linkedid']]) ? $legs[$fact['linkedid']] : [];
                if (empty($rows)) {
                    $rows = [$fact];
                }
                foreach ($rows as $row) {
                    $line = [];
                    foreach ($headers as $col) {
                        if ($col === 'direction') {
                            $line[$col] = isset($fact['direction']) ? $fact['direction'] : '';
                        } else {
                            $line[$col] = isset($row[$col]) ? $row[$col] : '';
                        }
                    }
                    $data[] = $line;
                }
            }
            Audit::log('call_export', 'mode=full rows=' . count($data));
            ExportService::csv('smartreport_calls_full_' . date('Ymd_His') . '.csv', $headers, $data);
        } catch (\Exception $e) {
            $this->redirect('/calls', t('calls.need_external'), 'error');
        }
    }

    public function audio()
    {
        $uniqueid = trim((string) $this->query('uniqueid', ''));
        $mode = $this->query('mode', 'inline') === 'download' ? 'download' : 'inline';
        if ($uniqueid === '') {
            Response::json(['error' => t('calls.recording_not_found')], 400);
        }

        try {
            $model = new CdrModel();
            $row = $model->findByUniqueId($uniqueid);
        } catch (\Exception $e) {
            Response::json(['error' => t('calls.recording_not_found')], 404);
        }

        $service = new RecordingService();
        $path = $service->resolve($row);
        if ($path === null) {
            Response::json(['error' => t('calls.recording_not_found')], 404);
        }
        $service->serve($path, $mode);
    }

    private function filtersFromRequest()
    {
        $today = date('Y-m-d');
        $dateFrom = $this->normalizeDate($this->query('date_from', $today));
        $dateTo = $this->normalizeDate($this->query('date_to', $today));

        if ($dateFrom > $dateTo) {
            set_flash('error', t('calls.invalid_dates'));
            $dateFrom = $today;
            $dateTo = $today;
        }

        $direction = (string) $this->query('dir', '');
        if (!in_array($direction, self::$DIRECTIONS, true)) {
            $direction = '';
        }

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'src' => trim((string) $this->query('src', '')),
            'dst' => trim((string) $this->query('dst', '')),
            'clid' => trim((string) $this->query('clid', '')),
            'disposition' => (string) $this->query('disposition', ''),
            'direction' => $direction,
            'missed' => (string) $this->query('missed', '') === '1' ? '1' : '',
        ];
    }

    private function normalizeDate($value)
    {
        $value = trim((string) $value);
        $ts = strtotime($value);
        if ($ts === false) {
            return date('Y-m-d');
        }
        return date('Y-m-d', $ts);
    }

    private function queryStringForPagination(array $filters, $perPage)
    {
        $queryString = isset($_SERVER['QUERY_STRING']) ? (string) $_SERVER['QUERY_STRING'] : '';
        $qs = [];
        parse_str($queryString, $qs);
        if (!is_array($qs)) {
            $qs = [];
        }
        $qs['date_from'] = $filters['date_from'];
        $qs['date_to'] = $filters['date_to'];
        $qs['src'] = $filters['src'];
        $qs['dst'] = $filters['dst'];
        $qs['clid'] = $filters['clid'];
        $qs['disposition'] = $filters['disposition'];
        $qs['dir'] = $filters['direction'];
        $qs['missed'] = $filters['missed'];
        $qs['limit'] = $perPage;
        unset($qs['page']);
        unset($qs['route']);
        unset($qs['export']);
        return http_build_query($qs);
    }
}