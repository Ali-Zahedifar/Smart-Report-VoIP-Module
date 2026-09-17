<?php

declare(strict_types=1);

namespace SmartReport\Features\Calls\Services;

use SmartReport\Core\Config;
use SmartReport\Core\Response;

class RecordingService
{
    private string $monitorDir;

    private const MIME = [
        'wav' => 'audio/wav',
        'mp3' => 'audio/mpeg',
        'ogg' => 'audio/ogg',
        'oga' => 'audio/ogg',
        'opus' => 'audio/ogg',
        'flac' => 'audio/flac',
        'gsm' => 'audio/x-gsm',
        'sln' => 'audio/x-wav',
        'ulaw' => 'audio/basic',
        'alaw' => 'audio/basic',
    ];

    public function __construct()
    {
        $dir = Config::get('external.monitor_dir', '/var/spool/asterisk/monitor');
        $this->monitorDir = rtrim((string) $dir, '/');
    }

    public function monitorDir(): string
    {
        return $this->monitorDir;
    }

    public function resolve(?array $cdrRow): ?string
    {
        if ($cdrRow === null) {
            return null;
        }
        $seen = [];
        foreach ($this->candidates($cdrRow) as $candidate) {
            if ($candidate === '' || isset($seen[$candidate])) {
                continue;
            }
            $seen[$candidate] = true;
            $real = realpath($candidate);
            if ($real !== false && is_file($real) && is_readable($real)) {
                return $real;
            }
        }

        $uniqueid = isset($cdrRow['uniqueid']) ? (string) $cdrRow['uniqueid'] : '';
        $calldate = isset($cdrRow['calldate']) ? (string) $cdrRow['calldate'] : '';
        if ($uniqueid !== '' && $calldate !== '') {
            $ts = strtotime($calldate);
            if ($ts !== false) {
                $dateDir = $this->monitorDir . '/' . date('Y/m/d', $ts);
                $found = $this->globRecording($dateDir, $uniqueid);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    public function serve(string $path, string $mode = 'inline'): void
    {
        if (!is_file($path) || !is_readable($path)) {
            Response::json(['error' => 'Recording file not readable'], 404);
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = self::MIME[$ext] ?? 'application/octet-stream';
        $size = (int) filesize($path);
        $basename = basename($path);

        header('Content-Type: ' . $mime . '; charset=binary');
        header('Content-Length: ' . $size);
        header('Accept-Ranges: none');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-transform');

        if ($mode === 'download') {
            $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $basename);
            header('Content-Disposition: attachment; filename="' . ($safeName !== null ? $safeName : $basename) . '"; filename*=UTF-8\'\'' . rawurlencode($basename));
        }

        readfile($path);
        exit;
    }

    private function candidates(array $cdrRow): array
    {
        $out = [];

        $recordingfile = isset($cdrRow['recordingfile']) ? trim((string) $cdrRow['recordingfile']) : '';
        if ($recordingfile !== '') {
            $out[] = $this->monitorDir . '/' . ltrim($recordingfile, '/');
        }

        $uniqueid = isset($cdrRow['uniqueid']) ? (string) $cdrRow['uniqueid'] : '';
        $calldate = isset($cdrRow['calldate']) ? (string) $cdrRow['calldate'] : '';
        if ($calldate !== '') {
            $ts = strtotime($calldate);
            if ($ts !== false) {
                $dateDir = $this->monitorDir . '/' . date('Y/m/d', $ts);
                foreach (['wav', 'mp3', 'ogg', 'gsm', 'sln', 'WAV', 'MP3'] as $ext) {
                    $out[] = $dateDir . '/' . $uniqueid . '.' . $ext;
                }
            }
        }
        if ($uniqueid !== '') {
            foreach (['wav', 'mp3', 'ogg', 'gsm'] as $ext) {
                $out[] = $this->monitorDir . '/' . $uniqueid . '.' . $ext;
                $out[] = $this->monitorDir . '/' . strtoupper($uniqueid) . '.' . $ext;
            }
        }

        return $out;
    }

    private function globRecording(string $dir, string $uniqueid): ?string
    {
        if (!is_dir($dir)) {
            return null;
        }
        foreach (glob($dir . '/*' . $uniqueid . '*') ?: [] as $file) {
            if (is_file($file) && is_readable($file)) {
                return $file;
            }
        }
        return null;
    }
}