<?php

namespace App\Services;

use App\Mail\NotificationMail;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotificationService
{
    public function __construct(private WebPushService $push) {}

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

        $notification = UserNotification::query()->create([
            'user_id' => $recipient->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'dedupe_key' => $dedupeKey,
        ]);

        if ($recipient->email && $recipient->wantsEmailFor($type)) {
            // Send once the surrounding transaction commits so a rollback never emails.
            DB::afterCommit(fn () => $this->email($recipient, $title, $body, $data));
        }

        DB::afterCommit(fn () => $this->push->queue([$recipient->id], [
            'title' => $title,
            'body' => $body,
            'url' => $this->linkFor($data)[0],
            'tag' => 'notification-'.$notification->id,
        ]));

        return $notification;
    }

    /**
     * Active users who can see the given brand (brand members plus administrators).
     *
     * @return Collection<int, User>
     */
    public function brandAudience(int $brandId, ?string $permission = null, ?User $except = null): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->when($except, fn ($query) => $query->whereKeyNot($except->id))
            ->where(function ($query) use ($brandId) {
                $query->whereHas('brands', fn ($brands) => $brands->where('brands.id', $brandId))
                    ->orWhereHas('roles', fn ($roles) => $roles->where('slug', 'administrator'));
            })
            ->with('roles')
            ->get()
            ->filter(fn (User $user) => $permission === null || $user->hasPermission($permission))
            ->values();
    }

    /**
     * Sends right away and lets mail errors bubble up, so the test button can show them.
     */
    public function sendTestEmail(User $recipient): void
    {
        Mail::to($recipient->email)->send($this->mailable(
            $recipient,
            'Test email notification',
            'This is a test from your email notification settings. If you can read this, email delivery is working.',
            [],
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function email(User $recipient, string $title, string $body, array $data): void
    {
        try {
            Mail::to($recipient->email)->send($this->mailable($recipient, $title, $body, $data));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Where a notification points: [url, button label].
     *
     * @param  array<string, mixed>  $data
     * @return array{0: string, 1: string}
     */
    private function linkFor(array $data): array
    {
        return match (true) {
            isset($data['content_id']) => [route('content.show', $data['content_id']), 'Open task'],
            isset($data['event_id']) => [route('events.show', $data['event_id']), 'Open event'],
            ! empty($data['permalink_url']) => [(string) $data['permalink_url'], 'View post'],
            ! empty($data['url']) => [(string) $data['url'], 'Open chat'],
            default => [route('notifications.index'), 'Open notifications'],
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function mailable(User $recipient, string $title, string $body, array $data): NotificationMail
    {
        [$url, $label] = $this->linkFor($data);

        return new NotificationMail(
            $title,
            $body,
            $url,
            $label,
            (string) Setting::getValue('organization_name', config('app.name', 'Prime Z')),
            $recipient->name,
        );
    }
}
