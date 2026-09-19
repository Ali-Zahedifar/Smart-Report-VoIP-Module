<?php

namespace SmartReport\Features\Dashboard\Controllers;

use SmartReport\Core\Controller;
use SmartReport\Features\Calls\Models\CdrModel;
use SmartReport\Features\Calls\Services\ReferenceRepository;

class DashboardController extends Controller
{
    public function index()
    {
        $direction = (string) $this->query('dir', '');
        if (!in_array($direction, ['in', 'out', 'int', 'missed'], true)) {
            $direction = '';
        }
        $focusMissed = $direction === 'missed';
        if ($focusMissed) {
            $direction = '';
        }

        $stats = [
            'total' => 0,
            'answered' => 0,
            'notAnswered' => 0,
            'other' => 0,
            'avgTalk' => 0,
            'missed' => 0,
            'missedBuckets' => ['noanswer' => 0, 'busy' => 0, 'cancelled' => 0, 'failed' => 0, 'voicemail' => 0],
            'external' => false,
        ];
        $recent = [];
        $missed = [];
        $legacy = true;
        $error = null;

        try {
            $model = new CdrModel();
            $legacy = $model->isLegacy();
            $filters = [
                'date_from' => date('Y-m-d'),
                'date_to' => date('Y-m-d'),
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

            $stats = array_merge($stats, $model->todayStats($facts, $direction));
            $stats['external'] = true;

$factsForRecent = $facts;
        if ($direction !== '') {
            $filtered = [];
            foreach ($factsForRecent as $fact) {
                if (isset($fact['direction']) && $fact['direction'] === $direction) {
                    $filtered[] = $fact;
                }
            }
            $factsForRecent = $filtered;
        }
        $missed = $model->missedFacts($facts, 20);
        if ($focusMissed) {
            $factsForRecent = $missed;
        }
        $recent = array_slice($factsForRecent, 0, 10);

            $ref = new ReferenceRepository();
            $refMode = $ref->mode();
        } catch (\Exception $e) {
            $stats['external'] = false;
            $refMode = 'patterns';
            $error = $e->getMessage();
        }

        $this->view(':features/dashboard/views/index', [
            'title' => t('dashboard.title'),
            'stats' => $stats,
            'recent' => $recent,
            'missed' => $missed,
            'direction' => $direction !== '' ? $direction : ($focusMissed ? 'missed' : ''),
            'focusMissed' => $focusMissed,
            'legacy' => $legacy,
            'refMode' => isset($refMode) ? $refMode : 'patterns',
            'error' => $error,
        ]);
    }
}