<?php

declare(strict_types=1);

namespace SmartReport\Core;

final class App
{
    private static ?bool $ready = null;
    private static ?string $reason = null;
    private static ?array $settings = null;

    public static function ready(): bool
    {
        if (self::$ready !== null) {
            return self::$ready;
        }
        try {
            $db = Database::main();
            $db->fetchValue('SELECT 1');
            if (!$db->tableExists('smr_feature_registry')) {
                self::$reason = 'Missing table smr_feature_registry';
                self::$ready = false;
                return false;
            }
            if (!$db->tableExists('smr_users')) {
                self::$reason = 'Missing table smr_users';
                self::$ready = false;
                return false;
            }
            self::$ready = true;
            return true;
        } catch (\Throwable $e) {
            self::$reason = $e->getMessage();
            self::$ready = false;
            return false;
        }
    }

    public static function reason(): ?string
    {
        self::ready();
        return self::$reason;
    }

    public static function version(): string
    {
        return Config::get('app.version', SMR_VERSION);
    }

    public static function name(): string
    {
        return (string) self::setting('branding.app_name', Config::get('app.name', 'Smart-Report'));
    }

    public static function setting(string $key, $default = null)
    {
        if (self::$settings === null) {
            self::$settings = [];
            try {
                $rows = Database::main()->fetchAll('SELECT k, v FROM smr_settings');
                foreach ($rows as $row) {
                    self::$settings[$row['k']] = $row['v'];
                }
            } catch (\Throwable $e) {
                return $default;
            }
        }
        return array_key_exists($key, self::$settings) ? self::$settings[$key] : $default;
    }

    public static function setSetting(string $key, $value): void
    {
        $db = Database::main();
        $sql = 'INSERT INTO smr_settings (k, v, note, updated_at) VALUES (?, ?, NULL, NOW())
                ON DUPLICATE KEY UPDATE v = VALUES(v), updated_at = NOW()';
        $db->query($sql, [$key, is_scalar($value) ? (string) $value : json_encode($value)]);
        self::$settings = null;
    }
}