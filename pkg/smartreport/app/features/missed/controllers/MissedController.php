<?php

namespace SmartReport\Features\Missed\Controllers;

use SmartReport\Core\Controller;
use SmartReport\Features\Calls\Models\CdrModel;
use SmartReport\Features\Calls\Services\ReferenceRepository;

class MissedController extends Controller
{
    public function index()
    {
        $filters = $this->filtersFromRequest();
        $perPage = (int) $this->query('limit', 50);
        $page = max(1, (int) $this->query('page', 1));
        $offset = ($page - 1) * $perPage;

        $legs = [];
        $rows = [];
        $total = 0;
        $external = false;
        $error = null;

        try {
            $model = new CdrModel();
            $external = true;
            $hasRecording = $model->hasColumn('recordingfile');

            $facts = $model->factsInRange($filters);
            // Filter only missed calls
            $facts = array_filter($facts, function ($fact) {
                return (isset($fact['direction']) ? $fact['direction'] : '') === 'in' && empty($fact['humanAnswered']);
            });
            $facts = array_values($facts);

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
        } catch (\Exception $e) {
            $external = false;
            $error = $e->getMessage();
        }

        $this->view(':features/missed/views/index', [
            'title' => t('missed.title'),
            'filters' => $filters,
            'result' => ['rows' => $rows, 'total' => $total],
            'legs' => $legs,
            'perPage' => $perPage,
            'page' => $page,
            'limit' => $perPage,
            'hasRecording' => $hasRecording,
            'external' => $external,
            'error' => $error,
            'mode' => 'linkedid',
            'legacy' => false,
            'queryString' => $this->queryStringForPagination($filters, $perPage),
        ]);
    }

    private function filtersFromRequest()
    {
        $today = date('Y-m-d');
        $defaultFrom = date('Y-m-d', strtotime('-6 days'));
        $dateFrom = $this->normalizeDate($this->query('date_from', $defaultFrom));
        $dateTo = $this->normalizeDate($this->query('date_to', $today));

        if ($dateFrom > $dateTo) {
            set_flash('error', t('calls.invalid_dates'));
            $dateFrom = $defaultFrom;
            $dateTo = $today;
        }

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'src' => trim((string) $this->query('src', '')),
            'dst' => trim((string) $this->query('dst', '')),
            'clid' => trim((string) $this->query('clid', '')),
            'disposition' => (string) $this->query('disposition', ''),
            'direction' => 'in',
            'missed' => '1',
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
        $qs['limit'] = $perPage;
        unset($qs['page']);
        unset($qs['route']);
        return http_build_query($qs);
    }
}