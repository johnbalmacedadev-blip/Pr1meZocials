<?php

namespace App\Policies;

use App\Models\CreativeAsset;
use App\Models\User;

class CreativeAssetPolicy
{
    public function view(User $user, CreativeAsset $asset): bool
    {
        if (! $user->hasPermission('assets.view')) {
            return false;
        }

        if ($asset->content_item_id) {
            $asset->loadMissing('contentItem');

            return $asset->contentItem ? $user->can('view', $asset->contentItem) : false;
        }

        return $asset->brand_id ? $user->canAccessBrand($asset->brand_id) : false;
    }

    public function delete(User $user, CreativeAsset $asset): bool
    {
        return $this->view($user, $asset) && $user->hasPermission('assets.delete');
    }
}
