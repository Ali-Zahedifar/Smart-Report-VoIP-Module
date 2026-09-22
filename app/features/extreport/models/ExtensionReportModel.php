<?php

namespace SmartReport\Features\Extreport\Models;

use SmartReport\Features\Calls\Models\CdrModel;
use SmartReport\Features\Calls\Services\RecordingService;

/**
 * Per-extension productivity metrics from call facts.
 *
 * A call is attributed to an extension when the extension appears on the
 * call: it placed it (src), received it (dst), or one of its legs carries
 * the extension as a channel peer (SIP/105-, Local/105@, PJSIP/105-).
 * In/out/int buckets follow the CdrModel classification; totals count every
 * call the extension touched, so manager + member attribution overlap by
 * design (each role still sees the full call).
 */
class ExtensionReportModel
{
    public static function perExtension(array $facts)
    {
        $rows = [];
        foreach ($facts as $fact) {
            $legs = isset($fact['legs']) && is_array($fact['legs']) ? $fact['legs'] : [$fact];
            $exts = self::extensionsOf($fact, $legs);
            $talk = (int) (isset($fact['talkTime']) ? $fact['talkTime'] : 0);
            $missed = false;
            if ((isset($fact['direction']) ? $fact['direction'] : '') === 'in' && empty($fact['humanAnswered'])) {
                $legsF = $legs;
                $missed = true; // controller pre-filters inbound; final call check via CdrModel
            }
            foreach ($exts as $ext) {
                if (!isset($rows[$ext])) {
                    $rows[$ext] = [
                        'extension' => $ext,
                        'calls' => 0,
                        'in' => 0,
                        'out' => 0,
                        'int' => 0,
                        'talk' => 0,
                        'talk_calls' => 0,
                        'missed' => 0,
                        'first' => '',
                        'last' => '',
                        'busy' => 0,
                        'hold' => 0,
                    ];
                }
                $row = &$rows[$ext];
                $row['calls']++;
                $dir = isset($fact['direction']) ? $fact['direction'] : 'unknown';
                if (isset($row[$dir])) {
                    $row[$dir]++;
                }
                if ($talk > 0) {
                    $row['talk'] += $talk;
                    $row['talk_calls']++;
                }
                if ($missed) {
                    $row['missed']++;
                }
                $ts = strtotime((string) (isset($fact['calldate']) ? $fact['calldate'] : ''));
                if ($ts !== false) {
                    $d = date('Y-m-d H:i:s', $ts);
                    if ($row['first'] === '' || $d < $row['first']) {
                        $row['first'] = $d;
                    }
                    if ($d > $row['last']) {
                        $row['last'] = $d;
                    }
                    $h = (int) date('G', $ts);
                    if ($h >= 8 && $h < 18) {
                        $row['busy'] += $talk;
                    }
                }
            }
            unset($row);
        }
        foreach ($rows as &$row) {
            $row['avg_talk'] = $row['talk_calls'] > 0 ? (int) round($row['talk'] / $row['talk_calls']) : 0;
        }
        unset($row);
        usort($rows, function ($a, $b) {
            return $b['calls'] - $a['calls'];
        });
        return array_values($rows);
    }

    /** Extensions appearing on the call entry or any leg. */
    public static function extensionsOf(array $fact, array $legs)
    {
        $exts = [];
        $src = CdrModel::digits(isset($fact['src']) ? $fact['src'] : '');
        $dst = CdrModel::digits(isset($fact['dst']) ? $fact['dst'] : '');
        if ($src !== '' && strlen($src) <= 6) {
            $exts[$src] = true;
        }
        if ($dst !== '' && strlen($dst) <= 6) {
            $exts[$dst] = true;
        }
        foreach ($legs as $leg) {
            foreach (['channel', 'dstchannel'] as $col) {
                $ch = isset($leg[$col]) ? (string) $leg[$col] : '';
                if ($ch === '') {
                    continue;
                }
                if (preg_match('~^(?:SIP|PJSIP|IAX2|DAHDI)/([0-9]{1,6})(?:-|$)~', $ch, $m)) {
                    $exts[$m[1]] = true;
                } elseif (preg_match('~^Local/([0-9]{1,6})@~', $ch, $m)) {
                    $exts[$m[1]] = true;
                }
            }
        }
        unset($exts['']);
        // PHP casts numeric-string array keys to ints; callers compare with
        // strict in_array against CdrModel::digits() strings, so cast back.
        return array_map('strval', array_keys($exts));
    }

    /** One extension's calls (fact list) for the detail view. */
    public static function callsOfExtension(array $facts, $extension)
    {
        $ext = CdrModel::digits($extension);
        $out = [];
        foreach ($facts as $fact) {
            $legs = isset($fact['legs']) && is_array($fact['legs']) ? $fact['legs'] : [$fact];
            if (in_array($ext, self::extensionsOf($fact, $legs), true)) {
                $out[] = $fact;
            }
        }
        return $out;
    }

    /**
     * Full compute pipeline shared by the standalone extension report and the
     * "Extensions" tab of the employee report: facts -> recording flags ->
     * per-extension rows -> hourly talk distribution -> totals.
     *
     * @return array{rows:array[],hourly:array{labels:string[],data:int[]},totals:array{calls:int,talk:int,missed:int},external:bool,error:string|null}
     */
    public static function compute(array $filters, $singleExt = '')
    {
        $out = [
            'rows' => [],
            'hourly' => ['labels' => [], 'data' => []],
            'totals' => ['calls' => 0, 'talk' => 0, 'missed' => 0],
            'external' => false,
            'error' => null,
        ];
        try {
            $model = new CdrModel();
            $out['external'] = true;
            $facts = $model->factsInRange($filters);
            $linkedids = [];
            foreach ($facts as $fact) {
                $linkedids[] = isset($fact['linkedid']) ? $fact['linkedid'] : '';
            }
            $legs = $model->legsForFacts($linkedids);
            $model->applyLegs($facts, $legs);

            if ($singleExt !== '') {
                $facts = self::callsOfExtension($facts, $singleExt);
            }

            $recorder = new RecordingService();
            foreach ($facts as &$fact) {
                $fact['hasRecording'] = !empty($fact['recordingUniqueid']) && $recorder->resolve($fact) !== null;
            }
            unset($fact);

            $rows = self::perExtension($facts);

            $hours = array_fill(0, 24, 0);
            foreach ($facts as $fact) {
                $ts = strtotime((string) (isset($fact['calldate']) ? $fact['calldate'] : ''));
                if ($ts === false) {
                    continue;
                }
                $hours[(int) date('G', $ts)] += (int) (isset($fact['talkTime']) ? $fact['talkTime'] : 0);
            }
            $out['hourly'] = [
                'labels' => array_map(function ($h) { return sprintf('%02d:00', $h); }, range(0, 23)),
                'data' => $hours,
            ];

            $totals = ['calls' => 0, 'talk' => 0, 'missed' => 0];
            foreach ($rows as $row) {
                $totals['calls'] += $row['calls'];
                $totals['talk'] += $row['talk'];
                $totals['missed'] += $row['missed'];
            }
            $out['totals'] = $totals;
            $out['rows'] = $rows;
        } catch (\Exception $e) {
            $out['external'] = false;
            $out['error'] = $e->getMessage();
        }
        return $out;
    }
}
