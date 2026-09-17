<?php

declare(strict_types=1);

namespace SmartReport\Services;

use SmartReport\Core\Auth;
use SmartReport\Core\Database;
use SmartReport\Core\Lang;
use SmartReport\Core\Log;

final class Audit
{
    public static function log(string $action, string $details = ''): void
    {
        try {
            $db = Database::main();
            $user = Auth::user();
            $db->insert('smr_audit_log', [
                'user_id' => $user !== null ? (int) $user['id'] : null,
                'username' => $user !== null ? (string) $user['username'] : '',
                'action' => $action,
                'details' => $details,
                'ip' => SMR_REMOTE_ADDR,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Log::error('Audit: ' . $e->getMessage());
        }
    }

    public static function label(string $action): string
    {
        return Lang::has('audit.' . $action) ? Lang::t('audit.' . $action) : $action;
    }
}