<?php

namespace SmartReport\Features\Extreport\Controllers;

use SmartReport\Core\Controller;
use SmartReport\Features\Calls\Models\CdrModel;
use SmartReport\Features\Extreport\Models\ExtensionReportModel;
use SmartReport\Services\Audit;
use SmartReport\Services\ExportService;

class ExtReportController extends Controller
{
    public function index()
    {
        $filters = $this->filtersFromRequest();
        $data = ExtensionReportModel::compute($filters);
        $this->view(':features/extreport/views/index', array_merge($filters, $data, [
            'title' => t('extreport.title'),
        ]));
    }

    public function detail()
    {
        $filters = $this->filtersFromRequest();
        $ext = CdrModel::digits($this->query('ext', ''));
        if ($ext === '') {
            $this->redirect('/ext-report', t('extreport.empty'), 'error');
        }
        $data = ExtensionReportModel::compute($filters, $ext);
        $this->view(':features/extreport/views/detail', array_merge($filters, $data, [
            'title' => t('extreport.detail_title'),
            'ext' => $ext,
        ]));
    }

    public function data()
    {
        $filters = $this->filtersFromRequest();
        $this->json(ExtensionReportModel::compute($filters, CdrModel::digits($this->query('ext', ''))));
    }

    public function export()
    {
        $filters = $this->filtersFromRequest();
        $data = ExtensionReportModel::compute($filters);
        $headers = ['extension', 'calls', 'incoming', 'outgoing', 'internal', 'talk_seconds', 'avg_talk_seconds', 'missed_inbound', 'first_call', 'last_call'];
        $lines = [];
        foreach ($data['rows'] as $row) {
            $lines[] = [
                'extension' => $row['extension'],
                'calls' => $row['calls'],
                'incoming' => $row['in'],
                'outgoing' => $row['out'],
                'internal' => $row['int'],
                'talk_seconds' => $row['talk'],
                'avg_talk_seconds' => $row['avg_talk'],
                'missed_inbound' => $row['missed'],
                'first_call' => $row['first'],
                'last_call' => $row['last'],
            ];
        }
        Audit::log('extreport_export', 'rows=' . count($lines));
        ExportService::csv('smartreport_extension_report_' . date('Ymd_His') . '.csv', $headers, $lines);
    }

    // -----------------------------------------------------------------

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
        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'department' => trim((string) $this->query('department', '')),
        ];
    }

    private function normalizeDate($value)
    {
        $value = trim((string) $value);
        $ts = strtotime($value);
        return $ts === false ? date('Y-m-d') : date('Y-m-d', $ts);
    }
}
