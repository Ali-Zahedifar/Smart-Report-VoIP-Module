<?php

namespace SmartReport\Services;

use SmartReport\Core\Auth;
use SmartReport\Core\Database;
use SmartReport\Core\Lang;
use SmartReport\Core\Log;

final class Audit
{
    public static function log($action, $details = '')
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
        } catch (\Exception $e) {
            Log::error('Audit: ' . $e->getMessage());
        }
    }

    public static function label($action)
    {
        return Lang::has('audit.' . $action) ? Lang::t('audit.' . $action) : $action;
    }
}