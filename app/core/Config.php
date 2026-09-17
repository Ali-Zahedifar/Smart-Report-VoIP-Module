<?php

declare(strict_types=1);

namespace SmartReport\Core;

use RuntimeException;

final class Config
{
    private static array $options = [];
    private static array $cache = [];

    public static function init(array $options = []): void
    {
        self::$options = $options;
    }

    public static function dir(): string
    {
        return self::$options['config_dir'] ?? SMR_CONFIG;
    }

    public static function fileExists(string $name): bool
    {
        return is_file(self::dir() . '/' . $name . '.php');
    }

    public static function file(string $name): array
    {
        if (isset(self::$cache[$name])) {
            return self::$cache[$name];
        }
        $path = self::dir() . '/' . $name . '.php';
        if (!is_file($path)) {
            throw new RuntimeException('Configuration file not found: ' . $name);
        }
        $data = require $path;
        if (!is_array($data)) {
            throw new RuntimeException('Configuration file must return an array: ' . $name);
        }
        self::$cache[$name] = $data;
        return $data;
    }

    public static function get(string $key, $default = null)
    {
        $parts = explode('.', $key);
        $name = array_shift($parts);
        if (!self::fileExists($name)) {
            return $default;
        }
        $value = self::file($name);
        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    public static function write(string $name, array $data): bool
    {
        $path = self::dir() . '/' . $name . '.php';
        $content = '<?php' . PHP_EOL . PHP_EOL . 'declare(strict_types=1);' . PHP_EOL . PHP_EOL . 'return ' . var_export($data, true) . ';' . PHP_EOL;
        $result = (bool) file_put_contents($path, $content, LOCK_EX);
        if ($result && is_writable($path)) {
            @chmod($path, 0640);
        }
        unset(self::$cache[$name]);
        return $result;
    }

    public static function flush(): void
    {
        self::$cache = [];
    }
}