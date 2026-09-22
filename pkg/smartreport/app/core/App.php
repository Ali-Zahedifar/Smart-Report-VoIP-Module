<?php

namespace SmartReport\Core;

use Exception;

final class App
{
    private static $ready = null;
    private static $reason = null;
    private static $settings = null;

    public static function ready()
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
        } catch (Exception $e) {
            self::$reason = $e->getMessage();
            self::$ready = false;
            return false;
        }
    }

    public static function reason()
    {
        self::ready();
        return self::$reason;
    }

    /**
     * App version. SMR_VERSION (app/core/bootstrap.php) is the single source
     * of truth; a stale config/app.php (e.g. left over from an earlier deploy
     * that was not fully replaced) must never make the panel misreport it.
     */
    public static function version()
    {
        return defined('SMR_VERSION') ? SMR_VERSION : 'unknown';
    }

    public static function name()
    {
        return (string) self::setting('branding.app_name', Config::get('app.name', 'Smart-Report'));
    }

    public static function setting($key, $default = null)
    {
        if (self::$settings === null) {
            self::$settings = [];
            try {
                $rows = Database::main()->fetchAll('SELECT k, v FROM smr_settings');
                foreach ($rows as $row) {
                    self::$settings[$row['k']] = $row['v'];
                }
            } catch (Exception $e) {
                return $default;
            }
        }
        return array_key_exists($key, self::$settings) ? self::$settings[$key] : $default;
    }

    public static function setSetting($key, $value)
    {
        $db = Database::main();
        $sql = 'INSERT INTO smr_settings (k, v, note, updated_at) VALUES (?, ?, NULL, NOW())
                ON DUPLICATE KEY UPDATE v = VALUES(v), updated_at = NOW()';
        $db->query($sql, [$key, is_scalar($value) ? (string) $value : json_encode($value)]);
        self::$settings = null;
    }
}