<?php

declare(strict_types=1);

namespace SmartReport\Features\Dashboard\Controllers;

use SmartReport\Core\Controller;
use SmartReport\Features\Calls\Models\CdrModel;

class DashboardController extends Controller
{
    public function index(): void
    {
        $stats = [
            'total' => 0,
            'answered' => 0,
            'notAnswered' => 0,
            'other' => 0,
            'avgTalk' => 0,
            'external' => false,
        ];
        $recent = [];
        $error = null;

        try {
            $model = new CdrModel();
            $stats = array_merge($stats, $model->todayStats());
            $stats['external'] = true;
            $recent = $model->recent(10);
        } catch (\Throwable $e) {
            $stats['external'] = false;
            $error = $e->getMessage();
        }

        $this->view(':features/dashboard/views/index', [
            'title' => t('dashboard.title'),
            'stats' => $stats,
            'recent' => $recent,
            'error' => $error,
        ]);
    }
}