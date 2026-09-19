<?php

namespace SmartReport\Core;

use RuntimeException;

final class Config
{
    private static $options = [];
    private static $cache = [];

    public static function init(array $options = [])
    {
        self::$options = $options;
    }

    public static function dir()
    {
        return isset(self::$options['config_dir']) ? self::$options['config_dir'] : SMR_CONFIG;
    }

    public static function fileExists($name)
    {
        return is_file(self::dir() . '/' . $name . '.php');
    }

    public static function file($name)
    {
        if (isset(self::$cache[$name])) {
            return self::$cache[$name];
        }
        $path = self::dir() . '/' . $name . '.php';
        if (!is_file($path)) {
            throw new RuntimeException('Configuration file not found: ' . $name);
        }
        if (!is_readable($path)) {
            throw new RuntimeException(
                'Configuration file is not readable by the current user: ' . $path
                . ' (blank HTTP 500? fix with: chmod 644 ' . $path . ' then chown -R <web-user> ' . SMR_ROOT . ')'
            );
        }
        $data = require $path;
        if (!is_array($data)) {
            throw new RuntimeException('Configuration file must return an array: ' . $name);
        }
        self::$cache[$name] = $data;
        return $data;
    }

    public static function get($key, $default = null)
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

    public static function write($name, array $data)
    {
        $path = self::dir() . '/' . $name . '.php';
        $content = '<?php' . PHP_EOL . PHP_EOL . 'return ' . var_export($data, true) . ';' . PHP_EOL;
        $result = (bool) file_put_contents($path, $content, LOCK_EX);
        if ($result && is_writable($path)) {
            @chmod($path, 0644);
        }
        unset(self::$cache[$name]);
        return $result;
    }

    public static function flush()
    {
        self::$cache = [];
    }
}