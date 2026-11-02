<?php

namespace App\Policies;

use App\Models\Funnel;
use App\Models\User;

class FunnelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('funnels.view');
    }

    public function view(User $user, Funnel $funnel): bool
    {
        return $user->hasPermission('funnels.view') && $user->canAccessBrand($funnel->brand_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('funnels.create');
    }

    public function update(User $user, Funnel $funnel): bool
    {
        return $user->hasPermission('funnels.update') && $user->canAccessBrand($funnel->brand_id);
    }

    public function delete(User $user, Funnel $funnel): bool
    {
        return $user->hasPermission('funnels.delete') && $user->canAccessBrand($funnel->brand_id);
    }
}
