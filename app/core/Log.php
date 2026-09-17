<?php

declare(strict_types=1);

namespace SmartReport\Core;

final class Log
{
    public static function write(string $level, string $message): void
    {
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, '[' . strtoupper($level) . '] ' . $message . PHP_EOL);
        }
        $line = '[' . date('Y-m-d H:i:s') . '] [' . strtoupper($level) . '] ' . $message . PHP_EOL;
        if (is_dir(SMR_LOGS) && is_writable(SMR_LOGS)) {
            @file_put_contents(SMR_LOGS . '/app.log', $line, FILE_APPEND | LOCK_EX);
        }
    }

    public static function info(string $message): void
    {
        self::write('info', $message);
    }

    public static function error(string $message): void
    {
        self::write('error', $message);
    }
}