<?php

declare(strict_types=1);

namespace SmartReport\Core;

final class Acl
{
    public static function userHasRole(array $roles): bool
    {
        if (empty($roles)) {
            return true;
        }
        if (!Auth::check()) {
            return false;
        }
        return in_array(Auth::role(), $roles, true);
    }
}