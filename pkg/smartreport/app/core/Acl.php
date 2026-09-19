<?php

namespace SmartReport\Core;

final class Acl
{
    public static function userHasRole(array $roles)
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