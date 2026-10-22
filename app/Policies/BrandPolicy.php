<?php

namespace App\Policies;

use App\Models\Brand;
use App\Models\User;

class BrandPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('brands.view');
    }

    public function view(User $user, Brand $brand): bool
    {
        return $user->hasPermission('brands.view') && $user->canAccessBrand($brand->id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('brands.manage');
    }

    public function update(User $user, Brand $brand): bool
    {
        return $user->hasPermission('brands.manage') && ($user->isAdmin() || $user->canAccessBrand($brand->id));
    }
}
