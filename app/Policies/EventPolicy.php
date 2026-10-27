<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('events.view');
    }

    public function view(User $user, Event $event): bool
    {
        return $user->hasPermission('events.view') && $user->canAccessBrand($event->brand_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('events.create');
    }

    public function update(User $user, Event $event): bool
    {
        return $user->hasPermission('events.update') && $user->canAccessBrand($event->brand_id);
    }

    public function delete(User $user, Event $event): bool
    {
        return $user->hasPermission('events.delete') && $user->canAccessBrand($event->brand_id);
    }
}
