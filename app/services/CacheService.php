<?php

declare(strict_types=1);

namespace SmartReport\Services;

final class CacheService
{
    public static function get(string $key, $default = null)
    {
        $path = self::path($key);
        if (!is_file($path)) {
            return $default;
        }
        $data = include $path;
        if (!is_array($data) || !isset($data['exp']) || !isset($data['data'])) {
            return $default;
        }
        if ($data['exp'] < time()) {
            @unlink($path);
            return $default;
        }
        return $data['data'];
    }

    public static function set(string $key, $value, int $ttl = 300): void
    {
        if (!is_dir(SMR_CACHE) || !is_writable(SMR_CACHE)) {
            return;
        }
        $content = '<?php' . PHP_EOL . 'return ' . var_export([
            'exp' => time() + $ttl,
            'data' => $value,
        ], true) . ';' . PHP_EOL;
        @file_put_contents(self::path($key), $content, LOCK_EX);
    }

    public static function forget(string $key): void
    {
        $path = self::path($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private static function path(string $key): string
    {
        return SMR_CACHE . '/' . md5($key) . '.php';
    }
}