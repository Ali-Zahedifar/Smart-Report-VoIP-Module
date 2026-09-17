<?php

declare(strict_types=1);

namespace SmartReport\Core;

final class Csrf
{
    public static function token(): string
    {
        if (SMR_CLI) {
            return '';
        }
        if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['_csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    public static function validate(): bool
    {
        if (SMR_CLI) {
            return true;
        }
        $token = $_POST['_csrf'] ?? null;
        if (!is_string($token) || $token === '') {
            return false;
        }
        $stored = self::token();
        if ($stored === '') {
            return false;
        }
        return hash_equals($stored, $token);
    }
}