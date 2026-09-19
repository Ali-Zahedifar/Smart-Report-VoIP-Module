<?php

namespace SmartReport\Core;

final class Csrf
{
    public static function token()
    {
        if (SMR_CLI) {
            return '';
        }
        if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(smr_random_bytes(16));
        }
        return $_SESSION['_csrf'];
    }

    public static function field()
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    public static function validate()
    {
        if (SMR_CLI) {
            return true;
        }
        $token = isset($_POST['_csrf']) ? $_POST['_csrf'] : null;
        if (!is_string($token) || $token === '') {
            return false;
        }
        $stored = self::token();
        if ($stored === '') {
            return false;
        }
        return smr_hash_equals($stored, $token);
    }
}