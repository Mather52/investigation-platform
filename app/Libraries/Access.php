<?php

namespace App\Libraries;

/** صلاحيات المستخدم الحالي من الجلسة */
class Access
{
    public static function roles(): array
    {
        return (array) session()->get('roles');
    }

    public static function isAdmin(): bool
    {
        return in_array('admin', self::roles(), true);
    }

    public static function has(string $role): bool
    {
        return self::isAdmin() || in_array($role, self::roles(), true);
    }

    /** هل يملك المستخدم أياً من الأدوار؟ (مدير النظام يملك الكل) */
    public static function hasAny(array $roles): bool
    {
        if ($roles === [] || self::isAdmin()) {
            return true;
        }

        return array_intersect($roles, self::roles()) !== [];
    }
}
