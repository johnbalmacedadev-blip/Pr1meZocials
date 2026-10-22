<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class CampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('campaigns.view');
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $user->hasPermission('campaigns.view') && $user->canAccessBrand($campaign->brand_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('campaigns.manage');
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->hasPermission('campaigns.manage') && $user->canAccessBrand($campaign->brand_id);
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $this->update($user, $campaign);
    }
}
