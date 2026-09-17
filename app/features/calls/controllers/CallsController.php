<?php

declare(strict_types=1);

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
    private const DISPOSITIONS = ['ANSWERED', 'NO ANSWER', 'BUSY', 'FAILED', 'CONGESTION', 'CANCEL'];

    public function index(): void
    {
        $filters = $this->filtersFromRequest();
        $choices = (array) Config::get('app.pagination_choices', [25, 50, 100, 250, 500]);
        $perPage = in_array((string) $this->query('limit', ''), array_map('strval', $choices), true)
            ? (int) $this->query('limit')
            : (int) Config::get('app.pagination_default', 50);
        $page = max(1, (int) $this->query('page', 1));
        $offset = ($page - 1) * $perPage;

        $result = ['rows' => [], 'total' => 0];
        $external = false;
        $error = null;
        $hasRecording = false;

        try {
            $model = new CdrModel();
            $external = true;
            $hasRecording = $model->hasColumn('recordingfile');
            $result = $model->listings($filters, $perPage, $offset);
        } catch (\Throwable $e) {
            $external = false;
            $error = $e->getMessage();
        }

        $this->view(':features/calls/views/list', [
            'title' => t('calls.title'),
            'filters' => $filters,
            'result' => $result,
            'perPage' => $perPage,
            'page' => $page,
            'limit' => $perPage,
            'hasRecording' => $hasRecording,
            'external' => $external,
            'error' => $error,
            'dispositions' => self::DISPOSITIONS,
            'limitChoices' => $choices,
            'queryString' => $this->queryStringForPagination($filters, $perPage),
        ]);
    }

    public function export(): void
    {
        $filters = $this->filtersFromRequest();
        try {
            $model = new CdrModel();
            $rows = $model->findAll($filters);
        } catch (\Throwable $e) {
            $this->redirect('/calls', t('calls.need_external'), 'error');
        }

        $headers = ['calldate', 'clid', 'src', 'dst', 'dcontext', 'duration', 'billsec', 'disposition', 'accountcode', 'uniqueid'];
        if ($model->hasColumn('recordingfile')) {
            $headers[] = 'recordingfile';
        }

        $data = [];
        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $col) {
                $line[$col] = $row[$col] ?? '';
            }
            $data[] = $line;
        }

        Audit::log('call_export', 'rows=' . count($data));
        ExportService::csv('smartreport_calls_' . date('Ymd_His') . '.csv', $headers, $data);
    }

    public function audio(): void
    {
        $uniqueid = trim((string) $this->query('uniqueid', ''));
        $mode = $this->query('mode', 'inline') === 'download' ? 'download' : 'inline';
        if ($uniqueid === '') {
            Response::json(['error' => t('calls.recording_not_found')], 400);
        }

        try {
            $model = new CdrModel();
            $row = $model->findByUniqueId($uniqueid);
        } catch (\Throwable $e) {
            Response::json(['error' => t('calls.recording_not_found')], 404);
        }

        $service = new RecordingService();
        $path = $service->resolve($row);
        if ($path === null) {
            Response::json(['error' => t('calls.recording_not_found')], 404);
        }
        $service->serve($path, $mode);
    }

    private function filtersFromRequest(): array
    {
        $today = date('Y-m-d');
        $dateFrom = $this->normalizeDate($this->query('date_from', $today));
        $dateTo = $this->normalizeDate($this->query('date_to', $today));

        if ($dateFrom > $dateTo) {
            set_flash('error', t('calls.invalid_dates'));
            $dateFrom = $today;
            $dateTo = $today;
        }

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'src' => trim((string) $this->query('src', '')),
            'dst' => trim((string) $this->query('dst', '')),
            'clid' => trim((string) $this->query('clid', '')),
            'disposition' => (string) $this->query('disposition', ''),
        ];
    }

    private function normalizeDate($value): string
    {
        $value = trim((string) $value);
        $ts = strtotime($value);
        if ($ts === false) {
            return date('Y-m-d');
        }
        return date('Y-m-d', $ts);
    }

    private function queryStringForPagination(array $filters, int $perPage): string
    {
        parse_str((string) ($_SERVER['QUERY_STRING'] ?? ''), $qs);
        $qs['date_from'] = $filters['date_from'];
        $qs['date_to'] = $filters['date_to'];
        $qs['src'] = $filters['src'];
        $qs['dst'] = $filters['dst'];
        $qs['clid'] = $filters['clid'];
        $qs['disposition'] = $filters['disposition'];
        $qs['limit'] = $perPage;
        unset($qs['page']);
        return http_build_query($qs);
    }
}