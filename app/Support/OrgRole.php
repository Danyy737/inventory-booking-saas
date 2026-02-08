<?php

namespace App\Support;

use App\Models\User;

class OrgRole
{
    public static function currentRole(User $user): ?string
    {
        $orgId = $user->current_organisation_id;
        if (!$orgId) return null;

        $org = $user->organisations()
            ->where('organisations.id', $orgId)
            ->first();

        return $org?->pivot?->role;
    }

    public static function isAdminLike(?string $role): bool
    {
        return in_array($role, ['owner', 'admin'], true);
    }
}
