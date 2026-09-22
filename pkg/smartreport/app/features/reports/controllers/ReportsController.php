<?php

namespace SmartReport\Features\Reports\Controllers;

use SmartReport\Core\Auth;
use SmartReport\Core\App;
use SmartReport\Core\Controller;
use SmartReport\Features\Calls\Models\CdrModel;

class ReportsController extends Controller
{
    /** @var string[] All chart canvas ids in display order. */
    private $allChartIds = ['dirChart', 'hourChart', 'missedChart', 'talkChart', 'queueChart', 'agentChart'];

    public function index()
    {
        $enabled = $this->loadEnabledCharts();

        $this->view(':features/reports/views/index', [
            'title' => t('reports.title'),
            'enabled_charts' => $enabled,
            'is_root' => (Auth::role() === 'root'),
        ]);
    }

    public function saveCharts()
    {
        $this->requireRole(['root']);

        $requested = $this->post('charts', []);
        $requested = is_array($requested) ? $requested : [];

        $enabled = [];
        foreach ($requested as $id) {
            $id = is_string($id) ? trim($id) : '';
            if ($id !== '' && in_array($id, $this->allChartIds, true) && !in_array($id, $enabled, true)) {
                $enabled[] = $id;
            }
        }

        App::setSetting('reports.enabled_charts', json_encode($enabled));

        $this->json(['ok' => true, 'enabled' => $enabled]);
    }

    /** @return string[] */
    private function loadEnabledCharts()
    {
        $saved = App::setting('reports.enabled_charts', null);
        if (is_string($saved) && $saved !== '') {
            $decoded = json_decode($saved, true);
            if (is_array($decoded)) {
                $enabled = array_values(array_intersect($this->allChartIds, array_map('strval', $decoded)));
                if ($enabled !== []) {
                    return $enabled;
                }
            }
        }
        return $this->allChartIds;
    }

    public function data()
    {
        $dateFrom = $this->normalizeDate($this->query('date_from', date('Y-m-d')));
        $dateTo = $this->normalizeDate($this->query('date_to', date('Y-m-d')));
        $queue = $this->query('queue', '');

        try {
            $model = new CdrModel();
            $external = true;

            $filters = [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'src' => '',
                'dst' => '',
                'clid' => '',
                'disposition' => '',
                'direction' => '',
            ];

            $facts = $model->factsInRange($filters);
            $linkedids = [];
            foreach ($facts as $fact) {
                $linkedids[] = isset($fact['linkedid']) ? $fact['linkedid'] : '';
            }
            $legs = $model->legsForFacts($linkedids);
            $model->applyLegs($facts, $legs);

            // Apply queue filter if specified
            if ($queue !== '') {
                $facts = $this->filterFactsByQueue($facts, $legs, $queue);
            }

            $data = [
                'calls_by_direction' => $this->getCallsByDirection($facts),
                'calls_by_hour' => $this->getCallsByHour($facts),
                'missed_trend' => $this->getMissedTrend($model, $dateFrom, $dateTo, $queue),
                'talk_time_dist' => $this->getTalkTimeDistribution($facts),
                'queue_performance' => $this->getQueuePerformance($facts, $legs),
                'agent_performance' => $this->getAgentPerformance($facts, $legs),
            ];

            $this->json($data);
        } catch (\Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    private function getCallsByDirection(array $facts)
    {
        $counts = ['in' => 0, 'out' => 0, 'int' => 0, 'unknown' => 0];
        foreach ($facts as $fact) {
            $dir = isset($fact['direction']) ? $fact['direction'] : 'unknown';
            if (isset($counts[$dir])) {
                $counts[$dir]++;
            } else {
                $counts['unknown']++;
            }
        }
        // Only render buckets that actually have calls - an all-zero
        // "unknown" slice on an otherwise clean doughnut is noise.
        $labels = [];
        $data = [];
        foreach ($counts as $k => $v) {
            if ($v > 0) {
                $labels[] = t('dir.' . $k);
                $data[] = $v;
            }
        }
        return [
            'labels' => $labels,
            'data' => $data,
        ];
    }

    private function getCallsByHour(array $facts)
    {
        $hours = array_fill(0, 24, 0);
        foreach ($facts as $fact) {
            $dt = isset($fact['calldate']) ? $fact['calldate'] : '';
            if ($dt !== '') {
                $ts = strtotime($dt);
                if ($ts !== false) {
                    $h = (int) date('H', $ts);
                    $hours[$h]++;
                }
            }
        }
        return [
            'labels' => array_map(function ($h) { return sprintf('%02d:00', $h); }, range(0, 23)),
            'data' => $hours,
        ];
    }

    /**
     * Missed-call trend across the requested range. One factsInRange call for
     * the whole span (grouped per day in PHP) instead of one query set per
     * day, and it honours the queue filter like every other chart.
     *
     * @return array{labels:string[],data:int[]}
     */
    private function getMissedTrend(CdrModel $model, $dateFrom, $dateTo, $queue = '')
    {
        $labels = [];
        $data = [];
        $buckets = [];

        try {
            $start = new \DateTime($dateFrom);
            $end = new \DateTime($dateTo);
            $end->modify('+1 day');
            $period = new \DatePeriod($start, new \DateInterval('P1D'), $end);
            foreach ($period as $day) {
                $key = $day->format('Y-m-d');
                $buckets[$key] = 0;
                $labels[] = $day->format('M d');
            }
        } catch (\Exception $e) {
            return ['labels' => [], 'data' => []];
        }

        $filters = [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'src' => '',
            'dst' => '',
            'clid' => '',
            'disposition' => '',
            'direction' => 'in',
        ];

        $facts = $model->factsInRange($filters);
        $linkedids = [];
        foreach ($facts as $fact) {
            $linkedids[] = isset($fact['linkedid']) ? $fact['linkedid'] : '';
        }
        $legs = $model->legsForFacts($linkedids);
        $model->applyLegs($facts, $legs);

        if ($queue !== '') {
            $facts = $this->filterFactsByQueue($facts, $legs, $queue);
        }

        foreach ($facts as $fact) {
            $legsForFact = isset($fact['legs']) && is_array($fact['legs']) ? $fact['legs'] : [$fact];
            if (!$model->isMissedCall($fact, $legsForFact)) {
                continue;
            }
            $ts = strtotime((string) (isset($fact['calldate']) ? $fact['calldate'] : ''));
            if ($ts === false) {
                continue;
            }
            $key = date('Y-m-d', $ts);
            if (isset($buckets[$key])) {
                $buckets[$key]++;
            }
        }

        return ['labels' => $labels, 'data' => array_values($buckets)];
    }

    /** Keep only facts whose call hit the given queue (ext-queues leg). */
    private function filterFactsByQueue(array $facts, array $legs, $queue)
    {
        $out = [];
        foreach ($facts as $fact) {
            $l = isset($legs[$fact['linkedid']]) ? $legs[$fact['linkedid']] : [];
            foreach ($l as $leg) {
                $dst = isset($leg['dst']) ? (string) $leg['dst'] : '';
                $ctx = isset($leg['dcontext']) ? (string) $leg['dcontext'] : '';
                if ($ctx === 'ext-queues' && $dst === $queue) {
                    $out[] = $fact;
                    break;
                }
            }
        }
        return $out;
    }

    private function getTalkTimeDistribution(array $facts)
    {
        $bins = [
            '0-30s' => 0,
            '30s-1m' => 0,
            '1-3m' => 0,
            '3-5m' => 0,
            '5-10m' => 0,
            '10m+' => 0,
        ];

        foreach ($facts as $fact) {
            if (!empty($fact['humanAnswered'])) {
                $talk = (int) (isset($fact['talkTime']) ? $fact['talkTime'] : 0);
                if ($talk <= 30) $bins['0-30s']++;
                elseif ($talk <= 60) $bins['30s-1m']++;
                elseif ($talk <= 180) $bins['1-3m']++;
                elseif ($talk <= 300) $bins['3-5m']++;
                elseif ($talk <= 600) $bins['5-10m']++;
                else $bins['10m+']++;
            }
        }

        return [
            'labels' => array_keys($bins),
            'data' => array_values($bins),
        ];
    }

    private function getQueuePerformance(array $facts, array $legs)
    {
        $queues = [];
        foreach ($facts as $fact) {
            $l = isset($legs[$fact['linkedid']]) ? $legs[$fact['linkedid']] : [];
            foreach ($l as $leg) {
                $ctx = isset($leg['dcontext']) ? (string) $leg['dcontext'] : '';
                $dst = isset($leg['dst']) ? (string) $leg['dst'] : '';
                if ($ctx === 'ext-queues' && preg_match('/^\d+$/', $dst)) {
                    $queue = $dst;
                    if (!isset($queues[$queue])) {
                        $queues[$queue] = [
                            'offered' => 0,
                            'answered' => 0,
                            'missed' => 0,
                            'abandoned' => 0,
                            'wait_total' => 0,
                            'talk_total' => 0,
                            'wait_count' => 0,
                            'talk_count' => 0,
                        ];
                    }
                    $queues[$queue]['offered']++;
                    if (!empty($fact['humanAnswered'])) {
                        $queues[$queue]['answered']++;
                    } else {
                        $queues[$queue]['missed']++;
                        $disp = strtoupper((string) (isset($fact['outcome']) ? $fact['outcome'] : (isset($fact['disposition']) ? $fact['disposition'] : '')));
                        if ($disp === 'NO ANSWER') {
                            $queues[$queue]['abandoned']++;
                        }
                    }
                }
            }
        }

        // Calculate wait times and talk times from legs
        foreach ($facts as $fact) {
            $l = isset($legs[$fact['linkedid']]) ? $legs[$fact['linkedid']] : [];
            $queue = null;
            foreach ($l as $leg) {
                $ctx = isset($leg['dcontext']) ? (string) $leg['dcontext'] : '';
                $dst = isset($leg['dst']) ? (string) $leg['dst'] : '';
                if ($ctx === 'ext-queues' && preg_match('/^\d+$/', $dst)) {
                    $queue = $dst;
                    break;
                }
            }
            if ($queue && isset($queues[$queue])) {
                foreach ($l as $leg) {
                    $lastapp = strtoupper((string) (isset($leg['lastapp']) ? $leg['lastapp'] : ''));
                    $disp = strtoupper((string) (isset($leg['disposition']) ? $leg['disposition'] : ''));
                    $billsec = (int) (isset($leg['billsec']) ? $leg['billsec'] : 0);
                    $ch = isset($leg['channel']) ? (string) $leg['channel'] : '';

                    // Queue wait time: Queue app leg billsec
                    if ($lastapp === 'QUEUE' && $disp === 'ANSWERED') {
                        $queues[$queue]['wait_total'] += $billsec;
                        $queues[$queue]['wait_count']++;
                    }
                    // Agent talk time: Dial leg ANSWERED
                    if ($lastapp === 'DIAL' && $disp === 'ANSWERED' && strpos($ch, 'Local/') === 0) {
                        $queues[$queue]['talk_total'] += $billsec;
                        $queues[$queue]['talk_count']++;
                    }
                }
            }
        }

        $result = [];
        foreach ($queues as $queue => $q) {
            $result[] = [
                'queue' => $queue,
                'offered' => $q['offered'],
                'answered' => $q['answered'],
                'missed' => $q['missed'],
                'abandoned' => $q['abandoned'],
                'abandonment_rate' => $q['offered'] > 0 ? round(100 * $q['abandoned'] / $q['offered'], 1) : 0,
                'avg_wait' => $q['wait_count'] > 0 ? round($q['wait_total'] / $q['wait_count']) : 0,
                'avg_talk' => $q['talk_count'] > 0 ? round($q['talk_total'] / $q['talk_count']) : 0,
            ];
        }

        return $result;
    }

    private function getAgentPerformance(array $facts, array $legs)
    {
        $agents = [];
        foreach ($facts as $fact) {
            $l = isset($legs[$fact['linkedid']]) ? $legs[$fact['linkedid']] : [];
            foreach ($l as $leg) {
                $ch = isset($leg['channel']) ? (string) $leg['channel'] : '';
                $lastapp = strtoupper((string) (isset($leg['lastapp']) ? $leg['lastapp'] : ''));
                $disp = strtoupper((string) (isset($leg['disposition']) ? $leg['disposition'] : ''));
                $billsec = (int) (isset($leg['billsec']) ? $leg['billsec'] : 0);

                // Agent leg: Local/XXX@from-queue or Local/XXX@from-internal
                if (strpos($ch, 'Local/') === 0 && preg_match('/^Local\/(\d+)@/', $ch, $m)) {
                    $ext = $m[1];
                    if (!isset($agents[$ext])) {
                        $agents[$ext] = [
                            'extension' => $ext,
                            'answered' => 0,
                            'missed' => 0,
                            'talk_total' => 0,
                            'talk_count' => 0,
                        ];
                    }
                    if ($lastapp === 'DIAL' && $disp === 'ANSWERED') {
                        $agents[$ext]['answered']++;
                        $agents[$ext]['talk_total'] += $billsec;
                        $agents[$ext]['talk_count']++;
                    } elseif ($lastapp === 'DIAL' && $disp !== 'ANSWERED') {
                        $agents[$ext]['missed']++;
                    }
                }
            }
        }

        $result = [];
        foreach ($agents as $ext => $a) {
            if ($a['answered'] > 0 || $a['missed'] > 0) {
                $result[] = [
                    'extension' => $ext,
                    'answered' => $a['answered'],
                    'missed' => $a['missed'],
                    'avg_talk' => $a['talk_count'] > 0 ? round($a['talk_total'] / $a['talk_count']) : 0,
                ];
            }
        }

        // Sort by answered desc
        usort($result, function ($a, $b) {
            return $b['answered'] - $a['answered'];
        });

        return array_slice($result, 0, 20); // Top 20 agents
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
}