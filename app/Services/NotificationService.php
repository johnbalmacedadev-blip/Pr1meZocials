<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserNotification;

class NotificationService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function send(User $recipient, string $type, string $title, string $body, string $dedupeKey, array $data = []): ?UserNotification
    {
        if ($recipient->is_active === false) {
            return null;
        }

        $existing = UserNotification::query()
            ->where('user_id', $recipient->id)
            ->where('dedupe_key', $dedupeKey)
            ->first();

        if ($existing) {
            return $existing;
        }

        return UserNotification::query()->create([
            'user_id' => $recipient->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'dedupe_key' => $dedupeKey,
        ]);
    }
}
