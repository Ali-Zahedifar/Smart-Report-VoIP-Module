<?php

namespace SmartReport\Core;

final class Log
{
    public static function write($level, $message)
    {
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, '[' . strtoupper($level) . '] ' . $message . PHP_EOL);
        }
        $line = '[' . date('Y-m-d H:i:s') . '] [' . strtoupper($level) . '] ' . $message . PHP_EOL;
        if (is_dir(SMR_LOGS) && is_writable(SMR_LOGS)) {
            @file_put_contents(SMR_LOGS . '/app.log', $line, FILE_APPEND | LOCK_EX);
        }
    }

    public static function info($message)
    {
        self::write('info', $message);
    }

    public static function error($message)
    {
        self::write('error', $message);
    }
}