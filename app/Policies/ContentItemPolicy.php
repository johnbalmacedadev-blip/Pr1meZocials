<?php

namespace App\Policies;

use App\Models\ContentItem;
use App\Models\User;

class ContentItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('content.view') || $user->hasPermission('content.view.any');
    }

    public function view(User $user, ContentItem $item): bool
    {
        if (! $user->canAccessBrand($item->brand_id)) {
            return false;
        }

        if ($user->hasPermission('content.view.any')) {
            return true;
        }

        if (! $user->hasPermission('content.view')) {
            return false;
        }

        if ($item->isAssignedTo($user)) {
            return true;
        }

        if ($item->campaign_id && $item->campaign()->whereHas('members', fn ($query) => $query->where('users.id', $user->id))->exists()) {
            return true;
        }

        $item->loadMissing('status');

        return $user->hasPermission('content.approve')
            && in_array($item->status?->slug, ['internal_review', 'revision_required', 'approved'], true);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('content.create');
    }

    public function update(User $user, ContentItem $item): bool
    {
        if (! $this->view($user, $item)) {
            return false;
        }

        if ($user->hasPermission('content.update.any')) {
            return true;
        }

        return $user->hasPermission('content.update') && $item->isAssignedTo($user);
    }

    public function delete(User $user, ContentItem $item): bool
    {
        return $user->canAccessBrand($item->brand_id) && $user->hasPermission('content.delete');
    }

    public function restore(User $user, ContentItem $item): bool
    {
        return $this->delete($user, $item);
    }

    public function assign(User $user, ContentItem $item): bool
    {
        return $this->view($user, $item) && $user->hasPermission('content.assign');
    }

    public function transition(User $user, ContentItem $item): bool
    {
        if (! $this->view($user, $item) || ! $user->hasPermission('content.transition')) {
            return false;
        }

        if ($user->hasPermission('content.update.any') || $user->hasPermission('content.approve') || $user->hasPermission('content.publish')) {
            return true;
        }

        return $item->isAssignedTo($user);
    }

    public function approve(User $user, ContentItem $item): bool
    {
        if (! $this->view($user, $item) || ! $user->hasPermission('content.approve')) {
            return false;
        }

        if ($user->hasPermission('content.update.any')) {
            return true;
        }

        return $item->reviewer_id === null || $item->reviewer_id === $user->id;
    }

    public function publish(User $user, ContentItem $item): bool
    {
        return $this->view($user, $item) && $user->hasPermission('content.publish');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('content.export');
    }
}
