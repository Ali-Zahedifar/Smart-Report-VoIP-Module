<?php

namespace SmartReport\Features\Livereport\Controllers;

use SmartReport\Core\App;
use SmartReport\Core\Auth;
use SmartReport\Core\Controller;
use SmartReport\Core\Csrf;
use SmartReport\Services\AmiService;
use SmartReport\Services\Audit;

class LiveController extends Controller
{
    public function index()
    {
        $ami = new AmiService();
        $canSpy = $this->spyEnabled();
        $this->view(':features/livereport/views/index', [
            'title' => t('live.title'),
            'ami' => $ami->configMasked(),
            'amiEnabled' => $ami->enabled(),
            'amiError' => '',
            'canSpy' => $canSpy,
            'isRoot' => Auth::role() === 'root',
        ]);
    }

    /**
     * Polling endpoint (called every ~5 s by live.js).
     */
    public function data()
    {
        $ami = new AmiService();
        $out = [
            'ok' => false,
            'error' => '',
            'channels' => [],
            'queues' => [],
            'summary' => ['active' => 0, 'inuse' => 0, 'ringing' => 0, 'waiting' => 0, 'onhold' => 0],
            'can_spy' => $this->spyEnabled(),
            'spy_mode' => (string) App::setting('live.spy_mode', 'listen'),
            'ts' => date('c'),
        ];
        if (!$ami->enabled()) {
            $out['error'] = 'ami_disabled';
            $this->json($out);
        }
        try {
            $channels = $ami->channels();
            $queues = $ami->queues();
            $out['channels'] = $channels;
            $out['queues'] = $queues;
            $summary = ['active' => count($channels), 'inuse' => 0, 'ringing' => 0, 'waiting' => 0, 'onhold' => 0];
            foreach ($channels as $ch) {
                $state = strtolower((string) $ch['state']);
                if ($state === 'up') {
                    $summary['inuse']++;
                } elseif ($state === 'ringing' || $state === 'ring') {
                    $summary['ringing']++;
                }
            }
            foreach ($queues as $q) {
                $summary['waiting'] += count($q['waiting']);
            }
            $out['summary'] = $summary;
            $out['ok'] = count($channels) > 0 || count($queues) > 0;
            if (!$out['ok']) {
                $out['error'] = $ami->lastError() !== '' ? $ami->lastError() : 'no_data';
            }
        } catch (\Exception $e) {
            $out['error'] = $e->getMessage();
        }
        $this->json($out);
    }

    /**
     * Start spying on a channel. Body: channel=..., mode=listen|whisper|barge
     */
    public function spy()
    {
        if (!$this->spyEnabled()) {
            $this->json(['ok' => false, 'error' => 'spy_disabled'], 403);
        }
        if (!Csrf::validate()) {
            $this->json(['ok' => false, 'error' => 'csrf'], 400);
        }
        $allowed = (string) App::setting('live.spy_mode', 'listen');
        if ($allowed === 'listen') {
            $allowedList = ['listen'];
        } elseif ($allowed === 'whisper') {
            $allowedList = ['listen', 'whisper'];
        } else {
            $allowedList = ['listen', 'whisper', 'barge'];
        }
        $mode = (string) $this->post('mode', 'listen');
        if (!in_array($mode, $allowedList, true)) {
            $this->json(['ok' => false, 'error' => 'mode_not_allowed'], 403);
        }
        $channel = (string) $this->post('channel', '');
        $ami = new AmiService();
        $result = $ami->spy($channel, $mode);
        if (!empty($result['ok'])) {
            Audit::log('live_spy', 'channel=' . $channel . ' mode=' . $mode);
        }
        $this->json($result, empty($result['ok']) ? 502 : 200);
    }

    /** Root: AMI connection settings + spy gating. */
    public function saveSettings()
    {
        if (!Csrf::validate()) {
            $this->json(['ok' => false, 'error' => 'csrf'], 400);
        }
        $enabled = $this->post('ami_enabled', '0') === '1' ? 1 : 0;
        App::setSetting('live.spy_enabled', $this->post('spy_enabled', '0') === '1' ? '1' : '0');
        $mode = (string) $this->post('spy_mode', 'listen');
        if (in_array($mode, ['listen', 'whisper', 'barge'], true)) {
            App::setSetting('live.spy_mode', $mode);
        }
        Audit::log('live_settings_saved', 'spy_enabled=' . App::setting('live.spy_enabled') . ' spy_mode=' . App::setting('live.spy_mode'));
        $this->json(['ok' => true, 'spy_enabled' => App::setting('live.spy_enabled'), 'spy_mode' => App::setting('live.spy_mode')]);
    }

    private function spyEnabled()
    {
        if (Auth::role() !== 'root' && Auth::role() !== 'admin') {
            return false;
        }
        return (int) App::setting('live.spy_enabled', 0) === 1;
    }
}
