<?php

namespace SmartReport\Services;

use SmartReport\Core\Config;

/**
 * Minimal Asterisk Manager Interface (AMI) client.
 *
 * Opens a short-lived TCP connection per request (login -> actions -> close),
 * which is the right trade-off for a reporting panel: no daemon to supervise,
 * and Issabel's default 127.0.0.1 manager handles the polling load easily.
 * PHP 5.4-compatible (fsockopen, no fiber/async magic).
 *
 * Actions used:
 *  - Status        -> active channels (caller, state, linkedid, duration)
 *  - QueueStatus   -> queue params + members + waiting callers
 *  - ChanSpy       -> listen/whisper/barge into a live channel (async)
 */
class AmiService
{
    /** @var array */
    private $cfg;

    private $socket = null;
    private $lastError = '';

    public function __construct()
    {
        $cfg = Config::get('external.ami', []);
        $cfg = is_array($cfg) ? $cfg : [];
        $this->cfg = [
            'enabled'  => !empty($cfg['enabled']),
            'host'     => isset($cfg['host']) ? (string) $cfg['host'] : '127.0.0.1',
            'port'     => isset($cfg['port']) ? (int) $cfg['port'] : 5038,
            'username' => isset($cfg['username']) ? (string) $cfg['username'] : '',
            'password' => isset($cfg['password']) ? (string) $cfg['password'] : '',
        ];
    }

    public function enabled()
    {
        return $this->cfg['enabled'] && $this->cfg['username'] !== '';
    }

    public function configMasked()
    {
        return [
            'enabled'  => $this->cfg['enabled'],
            'host'     => $this->cfg['host'],
            'port'     => $this->cfg['port'],
            'username' => $this->cfg['username'],
            'password' => $this->cfg['password'] !== '' ? 'set' : '',
        ];
    }

    public function lastError()
    {
        return $this->lastError;
    }

    /**
     * Active calls from `Action: Status`. Returns a list of channel summaries
     * sorted by start time desc. Empty list when AMI is off/unreachable.
     */
    public function channels()
    {
        if (!$this->enabled()) {
            $this->lastError = 'ami_disabled';
            return [];
        }
        $events = $this->action('Status', 'Status complete', 4.0);
        $out = [];
        foreach ($events as $event) {
            $name = isset($event['Event']) ? $event['Event'] : '';
            if ($name !== 'Status' && $name !== 'StatusBegin') {
                continue;
            }
            $channel = isset($event['Channel']) ? $event['Channel'] : '';
            if ($channel === '') {
                continue;
            }
            $state = isset($event['ChannelStateDesc']) ? $event['ChannelStateDesc'] : (isset($event['State']) ? $event['State'] : '');
            $out[] = [
                'channel'     => $channel,
                'uniqueid'    => isset($event['Uniqueid']) ? $event['Uniqueid'] : '',
                'linkedid'    => isset($event['Linkedid']) ? $event['Linkedid'] : '',
                'caller_num'  => isset($event['CallerIDNum']) ? $event['CallerIDNum'] : '',
                'caller_name' => isset($event['CallerIDName']) ? $event['CallerIDName'] : '',
                'context'     => isset($event['Context']) ? $event['Context'] : '',
                'extension'   => isset($event['Extension']) ? $event['Extension'] : '',
                'state'       => $state,
                'state_code'  => isset($event['ChannelState']) ? $event['ChannelState'] : '',
                'duration'    => isset($event['Seconds']) ? (int) $event['Seconds'] : 0,
                'bridged'     => isset($event['BridgedChannel']) ? $event['BridgedChannel'] : '',
                'account'     => isset($event['AccountCode']) ? $event['AccountCode'] : '',
                'app'         => isset($event['Application']) ? $event['Application'] : '',
                'app_data'    => isset($event['ApplicationData']) ? $event['ApplicationData'] : '',
            ];
        }
        usort($out, function ($a, $b) {
            return $b['duration'] - $a['duration'];
        });
        return $out;
    }

    /**
     * Queue snapshot from `Action: QueueStatus`. Returns a list of queues,
     * each with params, waiting entries and member states.
     */
    public function queues()
    {
        if (!$this->enabled()) {
            $this->lastError = 'ami_disabled';
            return [];
        }
        $events = $this->action('QueueStatus', 'QueueStatusComplete', 4.0);
        $queues = [];
        $current = null;
        foreach ($events as $event) {
            $name = isset($event['Event']) ? $event['Event'] : '';
            if ($name === 'QueueParams') {
                if ($current !== null) {
                    $queues[] = $current;
                }
                $current = [
                    'queue'       => isset($event['Queue']) ? $event['Queue'] : '',
                    'max'         => isset($event['Max']) ? (int) $event['Max'] : 0,
                    'calls'       => isset($event['Calls']) ? (int) $event['Calls'] : 0,
                    'holdtime'    => isset($event['Holdtime']) ? (int) $event['Holdtime'] : 0,
                    'talktime'    => isset($event['TalkTime']) ? (int) $event['TalkTime'] : 0,
                    'completed'   => isset($event['Completed']) ? (int) $event['Completed'] : 0,
                    'abandoned'   => isset($event['Abandoned']) ? (int) $event['Abandoned'] : 0,
                    'sl'          => isset($event['ServiceLevel']) ? (int) $event['ServiceLevel'] : 0,
                    'sl_perf'     => isset($event['ServicelevelPerf']) ? (float) $event['ServicelevelPerf'] : 0.0,
                    'waiting'     => [],
                    'members'     => [],
                ];
                continue;
            }
            if ($current === null) {
                continue;
            }
            if ($name === 'QueueEntry') {
                $current['waiting'][] = [
                    'channel'   => isset($event['Channel']) ? $event['Channel'] : '',
                    'caller'    => isset($event['CallerIDNum']) ? $event['CallerIDNum'] : '',
                    'name'      => isset($event['CallerIDName']) ? $event['CallerIDName'] : '',
                    'wait'      => isset($event['Wait']) ? (int) $event['Wait'] : 0,
                    'position'  => isset($event['Position']) ? (int) $event['Position'] : 0,
                ];
                continue;
            }
            if ($name === 'QueueMember') {
                $current['members'][] = [
                    'name'      => isset($event['Name']) ? $event['Name'] : '',
                    'location'  => isset($event['Location']) ? $event['Location'] : '',
                    'status'    => isset($event['Status']) ? (int) $event['Status'] : 0,
                    'paused'    => isset($event['Paused']) ? (int) $event['Paused'] : 0,
                    'in_call'   => isset($event['InCall']) ? (int) $event['InCall'] : 0,
                    'calls'     => isset($event['CallsTaken']) ? (int) $event['CallsTaken'] : 0,
                    'last_call' => isset($event['LastCall']) ? (int) $event['LastCall'] : 0,
                ];
            }
        }
        if ($current !== null) {
            $queues[] = $current;
        }
        return $queues;
    }

    /**
     * Start spying on a channel. $mode: 'listen' (silent), 'whisper'
     * (talk to the spied party only), 'barge' (talk to both sides).
     * The spyer is the channel AMI executes from - no tone, stops on hangup.
     *
     * @return array{ok:bool,error:string}
     */
    public function spy($channel, $mode = 'listen')
    {
        if (!$this->enabled()) {
            return ['ok' => false, 'error' => 'ami_disabled'];
        }
        $channel = trim((string) $channel);
        if ($channel === '' || !preg_match('/^[A-Za-z0-9_@\/\.\-\:;]+$/', $channel)) {
            return ['ok' => false, 'error' => 'bad_channel'];
        }
        $options = 'qS'; // q = no listen tone, S = stop when the spied call ends
        if ($mode === 'whisper') {
            $options = 'wqS';
        } elseif ($mode === 'barge') {
            $options = 'WqS';
        }
        $params = [
            'ChanSpy' => $channel,
            'Options' => $options,
        ];
        $resp = $this->rawAction('ChanSpy', $params, 3.0);
        if ($resp === null) {
            return ['ok' => false, 'error' => $this->lastError !== '' ? $this->lastError : 'no_response'];
        }
        $response = isset($resp['Response']) ? strtolower(trim($resp['Response'])) : '';
        $message = isset($resp['Message']) ? $resp['Message'] : '';
        if ($response === 'success' || $response === 'goodbye') {
            return ['ok' => true, 'error' => ''];
        }
        return ['ok' => false, 'error' => $message !== '' ? $message : $response];
    }

    // ------------------------------------------------------------------

    /**
     * Run one action and collect event packets until $terminal is seen.
     */
    private function action($actionName, $terminal, $timeout)
    {
        $packets = $this->rawActionMulti($actionName, [], $terminal, $timeout);
        return $packets;
    }

    private function connect()
    {
        if ($this->socket !== null) {
            return true;
        }
        $host = $this->cfg['host'] === '' ? '127.0.0.1' : $this->cfg['host'];
        $errno = 0;
        $errstr = '';
        $sock = @fsockopen($host, (int) $this->cfg['port'], $errno, $errstr, 3.0);
        if ($sock === false) {
            $this->lastError = 'connect_failed: ' . $errstr;
            return false;
        }
        stream_set_timeout($sock, 3);
        $this->socket = $sock;

        // Consume the Asterisk banner.
        $this->readPacket(2.0);

        $resp = $this->rawAction('Login', [
            'Username' => $this->cfg['username'],
            'Secret'   => $this->cfg['password'],
            'Events'   => 'off',
        ], 2.0);
        $ok = $resp !== null
            && isset($resp['Response'])
            && strtolower(trim($resp['Response'])) === 'success';
        if (!$ok) {
            $msg = $resp !== null && isset($resp['Message']) ? $resp['Message'] : 'auth_failed';
            $this->lastError = 'login_failed: ' . $msg;
            $this->close();
            return false;
        }
        return true;
    }

    private function rawAction($action, array $params, $timeout)
    {
        $packets = $this->rawActionMulti($action, $params, null, $timeout);
        foreach ($packets as $packet) {
            if (isset($packet['Response'])) {
                return $packet;
            }
        }
        return isset($packets[0]) ? $packets[0] : null;
    }

    private function rawActionMulti($action, array $params, $terminal, $timeout)
    {
        if (!$this->connect()) {
            return [];
        }
        $actionId = 'smr' . dechex(mt_rand()) . dechex(mt_rand());
        $out = "Action: $action\r\nActionID: $actionId\r\n";
        foreach ($params as $key => $value) {
            $out .= "$key: $value\r\n";
        }
        $out .= "\r\n";
        if (fwrite($this->socket, $out) === false) {
            $this->lastError = 'write_failed';
            $this->close();
            return [];
        }

        $deadline = microtime(true) + (float) $timeout;
        $packets = [];
        $current = [];
        while (microtime(true) < $deadline) {
            $packet = $this->readPacket(0.6);
            if ($packet === false) {
                if (feof($this->socket)) {
                    break;
                }
                continue;
            }
            if ($packet === []) {
                continue; // banner noise / empty lines
            }
            $ev = isset($packet['Event']) ? $packet['Event'] : '';
            if ($ev === 'Response' || isset($packet['Response'])) {
                $packets[] = $packet;
                if (isset($packet['ActionID']) && $packet['ActionID'] === $actionId && $terminal === null) {
                    break;
                }
                continue;
            }
            if ($ev !== '') {
                $packets[] = $packet;
                if ($terminal !== null && $ev === $terminal) {
                    break;
                }
                if ($terminal !== null && isset($packet['EventList']) && strtolower($packet['EventList']) === 'complete') {
                    break;
                }
            }
        }
        return $packets;
    }

    /**
     * Read one packet (up to a blank line). Returns assoc key=>value array,
     * [] on protocol noise (e.g. "Asterisk Call Manager/1.1"), false on timeout/EOF.
     */
    private function readPacket($waitSeconds)
    {
        if ($this->socket === null) {
            return false;
        }
        $packet = [];
        $lines = 0;
        $end = microtime(true) + (float) $waitSeconds;
        while (microtime(true) < $end) {
            $line = fgets($this->socket, 1024);
            if ($line === false) {
                if ($lines === 0 && feof($this->socket)) {
                    return false;
                }
                if ($lines > 0) {
                    break;
                }
                continue;
            }
            $line = rtrim($line, "\r\n");
            if ($line === '') {
                if ($lines > 0) {
                    break;
                }
                continue;
            }
            $lines++;
            $pos = strpos($line, ':');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            if ($key === '') {
                continue;
            }
            $packet[$key] = $value;
        }
        if ($lines === 0) {
            return microtime(true) >= $end ? false : [];
        }
        return $packet;
    }

    private function close()
    {
        if ($this->socket !== null) {
            @fwrite($this->socket, "Action: Logoff\r\n\r\n");
            @fclose($this->socket);
            $this->socket = null;
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
