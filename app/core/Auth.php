<?php

declare(strict_types=1);

namespace SmartReport\Core;

use SmartReport\Services\Audit;

final class Auth
{
    private static ?array $user = null;
    private static bool $resolved = false;

    public static function attempt(string $username, string $password): bool
    {
        if (self::lockedSeconds() > 0) {
            return false;
        }
        $db = Database::main();
        $user = $db->fetchRow('SELECT * FROM smr_users WHERE username = ? AND active = 1 LIMIT 1', [$username]);
        if ($user !== null && password_verify($password, $user['password_hash'])) {
            self::resetAttempts();
            self::login($user);
            return true;
        }
        self::recordFailure();
        Audit::log('login_failed', 'username=' . $username . ' from=' . SMR_REMOTE_ADDR);
        return false;
    }

    public static function login(array $user): void
    {
        if (!SMR_CLI) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
        }
        try {
            Database::main()->update('smr_users', ['last_login_at' => date('Y-m-d H:i:s')], 'id = ?', [(int) $user['id']]);
        } catch (\Throwable $e) {
            Log::error('Auth::login last_login update: ' . $e->getMessage());
        }
        self::$user = $user;
        self::$resolved = true;
        Audit::log('login_success', 'from=' . SMR_REMOTE_ADDR);
    }

    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$user;
        }
        self::$resolved = true;
        self::$user = null;
        if (SMR_CLI || empty($_SESSION['user_id'])) {
            return null;
        }
        try {
            $user = Database::main()->fetchRow('SELECT * FROM smr_users WHERE id = ? LIMIT 1', [(int) $_SESSION['user_id']]);
            if ($user !== null && (int) $user['active'] !== 1) {
                $user = null;
            }
            self::$user = $user;
        } catch (\Throwable $e) {
            Log::error('Auth::user: ' . $e->getMessage());
        }
        return self::$user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function role(): ?string
    {
        $user = self::user();
        return $user !== null ? (string) $user['role'] : null;
    }

    public static function logout(): void
    {
        Audit::log('logout');
        if (!SMR_CLI) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
            }
            session_destroy();
        }
        self::$user = null;
        self::$resolved = true;
    }

    public static function recordFailure(): void
    {
        if (SMR_CLI) {
            return;
        }
        $fails = (int) ($_SESSION['_login_fails'] ?? 0);
        $fails++;
        $_SESSION['_login_fails'] = $fails;
        $max = (int) Config::get('app.login_max_attempts', 5);
        if ($fails >= $max) {
            $_SESSION['_login_locked_until'] = time() + (int) Config::get('app.login_lockout_seconds', 60);
        }
    }

    public static function resetAttempts(): void
    {
        if (SMR_CLI) {
            return;
        }
        unset($_SESSION['_login_fails'], $_SESSION['_login_locked_until']);
    }

    public static function lockedSeconds(): int
    {
        if (SMR_CLI) {
            return 0;
        }
        $until = (int) ($_SESSION['_login_locked_until'] ?? 0);
        if ($until <= 0) {
            return 0;
        }
        $remaining = $until - time();
        if ($remaining <= 0) {
            unset($_SESSION['_login_locked_until'], $_SESSION['_login_fails']);
            return 0;
        }
        return $remaining;
    }
}