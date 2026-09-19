<?php

namespace SmartReport\Core;

final class Lang
{
    private static $messages = [];
    private static $languages = ['en' => 'English'];
    private static $current = 'en';
    private static $rtl = false;
    private static $init = false;

    public static function init(array $appCfg = [], $override = null)
    {
        self::$init = true;
        self::$languages = isset($appCfg['languages']) ? $appCfg['languages'] : ['en' => 'English'];
        $default = (string) (isset($appCfg['default_language']) ? $appCfg['default_language'] : 'en');
        $lang = $default;

        if (!SMR_CLI) {
            if ($override !== null && $override !== '') {
                $lang = $override;
            } else {
                $req = isset($_GET['lang']) ? $_GET['lang'] : null;
                if (is_string($req) && isset(self::$languages[$req])) {
                    $lang = $req;
                    $_SESSION['lang'] = $req;
                } elseif (isset($_SESSION['lang']) && isset(self::$languages[$_SESSION['lang']])) {
                    $lang = $_SESSION['lang'];
                } elseif (isset($_COOKIE['smr_lang']) && isset(self::$languages[$_COOKIE['smr_lang']])) {
                    $lang = $_COOKIE['smr_lang'];
                }
            }
        }

        if (!isset(self::$languages[$lang])) {
            $lang = $default;
        }

        self::$current = $lang;
        self::$rtl = in_array($lang, ['fa', 'ar', 'he', 'ur'], true);
        self::load($lang);
    }

    public static function ensureInit()
    {
        if (!self::$init) {
            self::init();
        }
    }

    private static function load($lang)
    {
        $path = SMR_CONFIG . '/languages/' . $lang . '.php';
        if (is_file($path)) {
            self::$messages = require $path;
        }
    }

    public static function set($lang)
    {
        if (!isset(self::$languages[$lang])) {
            return;
        }
        self::$current = $lang;
        self::$rtl = in_array($lang, ['fa', 'ar', 'he', 'ur'], true);
        self::load($lang);
        if (!SMR_CLI) {
            $_SESSION['lang'] = $lang;
            setcookie('smr_lang', $lang, time() + 31536000, SMR_BASE_URL === '' ? '/' : SMR_BASE_URL . '/', '', !empty($_SERVER['HTTPS']), true);
        }
    }

    public static function current()
    {
        return self::$current;
    }

    public static function dir()
    {
        return self::$rtl ? 'rtl' : 'ltr';
    }

    public static function isRtl()
    {
        return self::$rtl;
    }

    public static function languages()
    {
        return self::$languages;
    }

    public static function has($key)
    {
        return isset(self::$messages[$key]);
    }

    public static function t($key, array $params = [])
    {
        $text = isset(self::$messages[$key]) ? self::$messages[$key] : $key;
        if (!empty($params)) {
            $replace = [];
            foreach ($params as $k => $v) {
                $replace['%' . $k . '%'] = (string) $v;
            }
            $text = strtr($text, $replace);
        }
        return $text;
    }
}