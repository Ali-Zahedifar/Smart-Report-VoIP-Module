<?php

namespace SmartReport\Features\Calls\Services;

use SmartReport\Core\Config;
use SmartReport\Core\Response;

class RecordingService
{
    /**
     * Files smaller than this are header-only artifacts (e.g. a 44-byte empty
     * WAV that Asterisk creates and never fills) — treat them as "no recording".
     */
    const MIN_RECORDING_BYTES = 1024;

    private static $MIME = [
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

    private $monitorDir;

    public function __construct()
    {
        $dir = Config::get('external.monitor_dir', '/var/spool/asterisk/monitor');
        $this->monitorDir = rtrim((string) $dir, '/');
    }

    public function monitorDir()
    {
        return $this->monitorDir;
    }

    public function resolve($cdrRow = null)
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
            if ($real !== false && $this->isRealRecording($real)) {
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

    public function serve($path, $mode = 'inline')
    {
        if (!is_file($path) || !is_readable($path)) {
            Response::json(['error' => 'Recording file not readable'], 404);
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = array_key_exists($ext, self::$MIME) ? self::$MIME[$ext] : 'application/octet-stream';
        $size = (int) filesize($path);
        $basename = basename($path);

        header('Content-Type: ' . $mime . '; charset=binary');
        header('Accept-Ranges: bytes');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-transform');

        if ($mode === 'download') {
            $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $basename);
            header('Content-Disposition: attachment; filename="' . ($safeName !== null ? $safeName : $basename) . '"; filename*=UTF-8\'\'' . rawurlencode($basename));
            header('Content-Length: ' . $size);
            readfile($path);
            exit;
        }

        $start = 0;
        $end = $size - 1;
        $range = isset($_SERVER['HTTP_RANGE']) ? (string) $_SERVER['HTTP_RANGE'] : '';

        if ($range !== '' && preg_match('/^bytes=(\d*)-(\d*)$/', $range, $m)) {
            $first = $m[1] !== '' ? (int) $m[1] : -1;
            $last = $m[2] !== '' ? (int) $m[2] : -1;

            if ($first === -1 && $last !== -1) {
                $start = max(0, $size - $last);
                $end = $size - 1;
            } elseif ($first !== -1 && $last >= $first) {
                $start = $first;
                $end = min($last, $size - 1);
            } elseif ($first !== -1 && $first < $size) {
                $start = $first;
                $end = $size - 1;
            } else {
                header('Content-Range: bytes */' . $size);
                header('Content-Length: 0');
                http_response_code(416);
                exit;
            }

            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
            header('Content-Length: ' . ($end - $start + 1));
            http_response_code(206);
        } else {
            header('Content-Length: ' . $size);
        }

        $fp = fopen($path, 'rb');
        if ($fp === false) {
            Response::json(['error' => 'Recording file not readable'], 404);
        }
        if ($start > 0) {
            fseek($fp, $start);
        }
        $remaining = $end - $start + 1;
        $chunk = 8192;
        while ($remaining > 0 && !feof($fp)) {
            $out = fread($fp, min($chunk, $remaining));
            if ($out === false || $out === '') {
                break;
            }
            echo $out;
            $remaining -= strlen($out);
        }
        fclose($fp);
        exit;
    }

    private function candidates(array $cdrRow)
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

    private function globRecording($dir, $uniqueid)
    {
        if (!is_dir($dir)) {
            return null;
        }
        $found = glob($dir . '/*' . $uniqueid . '*');
        if (!is_array($found)) {
            $found = [];
        }
        foreach ($found as $file) {
            if ($this->isRealRecording($file)) {
                return $file;
            }
        }
        return null;
    }

    /**
     * A recording counts only when the file exists, is readable and is bigger
     * than the header-only threshold — 0s calls legitimately have 44-byte WAV
     * stubs on disk that hold no audio.
     */
    private function isRealRecording($path)
    {
        return is_file($path)
            && is_readable($path)
            && filesize($path) >= self::MIN_RECORDING_BYTES;
    }
}