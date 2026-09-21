<?php

namespace SmartReport\Features\QueueReport\Controllers;

use SmartReport\Core\Controller;
use SmartReport\Features\Calls\Models\CdrModel;
use SmartReport\Features\Calls\Services\ReferenceRepository;
use SmartReport\Services\ExportService;

class QueueReportController extends Controller
{
    public function index()
    {
        $filters = $this->filtersFromRequest();
        $perPage = (int) $this->query('limit', 50);
        $page = max(1, (int) $this->query('page', 1));
        $offset = ($page - 1) * $perPage;

        $queues = [];
        $total = 0;
        $external = false;
        $error = null;

        try {
            $model = new CdrModel();
            $external = true;

            $facts = $model->factsInRange($filters);
            $linkedids = [];
            foreach ($facts as $fact) {
                $linkedids[] = isset($fact['linkedid']) ? $fact['linkedid'] : '';
            }
            $legs = $model->legsForFacts($linkedids);
            $model->applyLegs($facts, $legs);

            $queues = $this->aggregateQueueData($facts, $legs);
            
            // Apply queue filter if specified
            $queueFilter = isset($filters['queue']) ? $filters['queue'] : '';
            if ($queueFilter !== '') {
                $queues = array_filter($queues, function ($q) use ($queueFilter) {
                    return $q['queue'] === $queueFilter;
                });
                $queues = array_values($queues);
            }

            $total = count($queues);
            $paged = array_slice($queues, $offset, $perPage);
            $queues = $paged;
        } catch (\Exception $e) {
            $external = false;
            $error = $e->getMessage();
        }

        $this->view(':features/queuereport/views/index', [
            'title' => t('queue_report.title'),
            'filters' => $filters,
            'queues' => $queues,
            'total' => $total,
            'perPage' => $perPage,
            'page' => $page,
            'limit' => $perPage,
            'external' => $external,
            'error' => $error,
            'queryString' => $this->queryStringForPagination($filters, $perPage),
        ]);
    }

    public function data()
    {
        $filters = $this->filtersFromRequest();
        
        try {
            $model = new CdrModel();
            $facts = $model->factsInRange($filters);
            $linkedids = [];
            foreach ($facts as $fact) {
                $linkedids[] = isset($fact['linkedid']) ? $fact['linkedid'] : '';
            }
            $legs = $model->legsForFacts($linkedids);
            $model->applyLegs($facts, $legs);
            
            $queues = $this->aggregateQueueData($facts, $legs);
            
            $this->json($queues);
        } catch (\Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    public function detail()
    {
        $queue = trim((string) $this->query('queue', ''));
        if ($queue === '') {
            // Safety net: normal navigation always carries an explicit queue=.
            $this->redirect('/queue-report', t('queue_report.queue_not_specified'), 'error');
        }
        
        $filters = $this->filtersFromRequest();
        $filters['queue'] = $queue;
        
        try {
            $model = new CdrModel();
            $facts = $model->factsInRange($filters);
            $linkedids = [];
            foreach ($facts as $fact) {
                $linkedids[] = isset($fact['linkedid']) ? $fact['linkedid'] : '';
            }
            $legs = $model->legsForFacts($linkedids);
            $model->applyLegs($facts, $legs);
            
            // Filter only this queue's calls
            $queueFacts = array_filter($facts, function ($fact) use ($queue, $legs) {
                $l = isset($legs[$fact['linkedid']]) ? $legs[$fact['linkedid']] : [];
                foreach ($l as $leg) {
                    $dst = isset($leg['dst']) ? (string) $leg['dst'] : '';
                    $ctx = isset($leg['dcontext']) ? (string) $leg['dcontext'] : '';
                    if ($ctx === 'ext-queues' && $dst === $queue) {
                        return true;
                    }
                }
                return false;
            });
            
            $queueStats = $this->getQueueDetailStats($queueFacts, $legs);
            $agentStats = $this->getAgentStats($queueFacts, $legs);
            $hourlyStats = $this->getHourlyStats($queueFacts, $legs);
            
            $this->view(':features/queuereport/views/detail', [
                'title' => t('queue_report.detail_title', ['queue' => $queue]),
                'queue' => $queue,
                'filters' => $filters,
                'stats' => $queueStats,
                'agents' => $agentStats,
                'hourly' => $hourlyStats,
            ]);
        } catch (\Exception $e) {
            set_flash('error', $e->getMessage());
            $this->redirect('/queue-report');
        }
    }

    public function export()
    {
        $filters = $this->filtersFromRequest();
        $queue = $this->query('queue', '');
        
        try {
            $model = new CdrModel();
            $facts = $model->factsInRange($filters);
            $linkedids = [];
            foreach ($facts as $fact) {
                $linkedids[] = isset($fact['linkedid']) ? $fact['linkedid'] : '';
            }
            $legs = $model->legsForFacts($linkedids);
            $model->applyLegs($facts, $legs);
            
            $queues = $this->aggregateQueueData($facts, $legs);
            
            if ($queue !== '') {
                $queues = array_filter($queues, function ($q) use ($queue) {
                    return $q['queue'] === $queue;
                });
                $queues = array_values($queues);
            }
            
            $headers = [
                'queue' => t('queue_report.queue'),
                'offered' => t('queue_report.offered'),
                'answered' => t('queue_report.answered'),
                'missed' => t('queue_report.missed'),
                'abandoned' => t('queue_report.abandoned'),
                'abandonment_rate' => t('queue_report.abandonment_rate'),
                'avg_wait' => t('queue_report.avg_wait'),
                'avg_talk' => t('queue_report.avg_talk'),
                'service_level_20' => t('queue_report.service_level_20'),
                'service_level_30' => t('queue_report.service_level_30'),
                'service_level_60' => t('queue_report.service_level_60'),
            ];
            
            $data = [];
            foreach ($queues as $q) {
                $data[] = [
                    'queue' => $q['queue'],
                    'offered' => $q['offered'],
                    'answered' => $q['answered'],
                    'missed' => $q['missed'],
                    'abandoned' => $q['abandoned'],
                    'abandonment_rate' => $q['abandonment_rate'] . '%',
                    'avg_wait' => format_duration($q['avg_wait']),
                    'avg_talk' => format_duration($q['avg_talk']),
                    'service_level_20' => $q['service_level_20'] . '%',
                    'service_level_30' => $q['service_level_30'] . '%',
                    'service_level_60' => $q['service_level_60'] . '%',
                ];
            }
            
            ExportService::csv('queue_report_' . date('Ymd_His') . '.csv', $headers, $data);
        } catch (\Exception $e) {
            $this->redirect('/queue-report', t('calls.need_external'), 'error');
        }
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
            'direction' => '',
            'queue' => trim((string) $this->query('queue', '')),
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
        $qs['queue'] = $filters['queue'];
        $qs['limit'] = $perPage;
        unset($qs['page']);
        unset($qs['route']);
        unset($qs['export']);
        return http_build_query($qs);
    }

    private function aggregateQueueData(array $facts, array $legs)
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
                            'queue' => $queue,
                            'offered' => 0,
                            'answered' => 0,
                            'missed' => 0,
                            'abandoned' => 0,
                            'wait_total' => 0,
                            'talk_total' => 0,
                            'wait_count' => 0,
                            'talk_count' => 0,
                            'service_level_20' => 0,
                            'service_level_30' => 0,
                            'service_level_60' => 0,
                            'answered_20' => 0,
                            'answered_30' => 0,
                            'answered_60' => 0,
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
        
        // Calculate wait times, talk times, and service levels from legs
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
                        
                        // Service level: answered within 20/30/60 seconds
                        if ($billsec <= 20) $queues[$queue]['answered_20']++;
                        if ($billsec <= 30) $queues[$queue]['answered_30']++;
                        if ($billsec <= 60) $queues[$queue]['answered_60']++;
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
            $abandonmentRate = $q['offered'] > 0 ? round(100 * $q['abandoned'] / $q['offered'], 1) : 0;
            $sl20 = $q['offered'] > 0 ? round(100 * $q['answered_20'] / $q['offered'], 1) : 0;
            $sl30 = $q['offered'] > 0 ? round(100 * $q['answered_30'] / $q['offered'], 1) : 0;
            $sl60 = $q['offered'] > 0 ? round(100 * $q['answered_60'] / $q['offered'], 1) : 0;
            
            $result[] = [
                'queue' => $queue,
                'offered' => $q['offered'],
                'answered' => $q['answered'],
                'missed' => $q['missed'],
                'abandoned' => $q['abandoned'],
                'abandonment_rate' => $abandonmentRate,
                'avg_wait' => $q['wait_count'] > 0 ? round($q['wait_total'] / $q['wait_count']) : 0,
                'avg_talk' => $q['talk_count'] > 0 ? round($q['talk_total'] / $q['talk_count']) : 0,
                'service_level_20' => $sl20,
                'service_level_30' => $sl30,
                'service_level_60' => $sl60,
            ];
        }
        
        return $result;
    }

    private function getQueueDetailStats(array $facts, array $legs)
    {
        $offered = count($facts);
        $answered = 0;
        $missed = 0;
        $abandoned = 0;
        $waitTotal = 0;
        $waitCount = 0;
        $talkTotal = 0;
        $talkCount = 0;
        $answered20 = 0;
        $answered30 = 0;
        $answered60 = 0;
        
        foreach ($facts as $fact) {
            if (!empty($fact['humanAnswered'])) {
                $answered++;
            } else {
                $missed++;
                $disp = strtoupper((string) (isset($fact['outcome']) ? $fact['outcome'] : (isset($fact['disposition']) ? $fact['disposition'] : '')));
                if ($disp === 'NO ANSWER') {
                    $abandoned++;
                }
            }
        }
        
        foreach ($facts as $fact) {
            $l = isset($legs[$fact['linkedid']]) ? $legs[$fact['linkedid']] : [];
            foreach ($l as $leg) {
                $lastapp = strtoupper((string) (isset($leg['lastapp']) ? $leg['lastapp'] : ''));
                $disp = strtoupper((string) (isset($leg['disposition']) ? $leg['disposition'] : ''));
                $billsec = (int) (isset($leg['billsec']) ? $leg['billsec'] : 0);
                $ch = isset($leg['channel']) ? (string) $leg['channel'] : '';

                if ($lastapp === 'QUEUE' && $disp === 'ANSWERED') {
                    $waitTotal += $billsec;
                    $waitCount++;
                    if ($billsec <= 20) $answered20++;
                    if ($billsec <= 30) $answered30++;
                    if ($billsec <= 60) $answered60++;
                }
                if ($lastapp === 'DIAL' && $disp === 'ANSWERED' && strpos($ch, 'Local/') === 0) {
                    $talkTotal += $billsec;
                    $talkCount++;
                }
            }
        }
        
        return [
            'offered' => $offered,
            'answered' => $answered,
            'missed' => $missed,
            'abandoned' => $abandoned,
            'abandonment_rate' => $offered > 0 ? round(100 * $abandoned / $offered, 1) : 0,
            'avg_wait' => $waitCount > 0 ? round($waitTotal / $waitCount) : 0,
            'avg_talk' => $talkCount > 0 ? round($talkTotal / $talkCount) : 0,
            'service_level_20' => $offered > 0 ? round(100 * $answered20 / $offered, 1) : 0,
            'service_level_30' => $offered > 0 ? round(100 * $answered30 / $offered, 1) : 0,
            'service_level_60' => $offered > 0 ? round(100 * $answered60 / $offered, 1) : 0,
        ];
    }

    private function getAgentStats(array $facts, array $legs)
    {
        $agents = [];
        
        foreach ($facts as $fact) {
            $l = isset($legs[$fact['linkedid']]) ? $legs[$fact['linkedid']] : [];
            foreach ($l as $leg) {
                $ch = isset($leg['channel']) ? (string) $leg['channel'] : '';
                $lastapp = strtoupper((string) (isset($leg['lastapp']) ? $leg['lastapp'] : ''));
                $disp = strtoupper((string) (isset($leg['disposition']) ? $leg['disposition'] : ''));
                $billsec = (int) (isset($leg['billsec']) ? $leg['billsec'] : 0);
                $dstchannel = isset($leg['dstchannel']) ? (string) $leg['dstchannel'] : '';

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
                            'ring_time' => 0,
                            'ring_count' => 0,
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
                // Also check dstchannel for agent identification
                if (strpos($dstchannel, 'Local/') === 0 && preg_match('/^Local\/(\d+)@/', $dstchannel, $m)) {
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
                    'total_calls' => $a['answered'] + $a['missed'],
                ];
            }
        }
        
        usort($result, function ($a, $b) {
            return $b['answered'] - $a['answered'];
        });
        
        return $result;
    }

    private function getHourlyStats(array $facts, array $legs)
    {
        $hours = array_fill(0, 24, [
            'offered' => 0,
            'answered' => 0,
            'missed' => 0,
        ]);
        
        foreach ($facts as $fact) {
            $dt = isset($fact['calldate']) ? $fact['calldate'] : '';
            if ($dt !== '') {
                $ts = strtotime($dt);
                if ($ts !== false) {
                    $h = (int) date('H', $ts);
                    $hours[$h]['offered']++;
                    if (!empty($fact['humanAnswered'])) {
                        $hours[$h]['answered']++;
                    } else {
                        $hours[$h]['missed']++;
                    }
                }
            }
        }
        
        return $hours;
    }
}