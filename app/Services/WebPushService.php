<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\Setting;
use GuzzleHttp\Client;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Browser/phone push notifications (Web Push with VAPID).
 * Keys are generated once and kept in the settings table, never in git.
 * Sends are batched and flushed after the HTTP response so pages stay fast.
 */
class WebPushService
{
    private const SETTING_KEY = 'webpush_vapid';

    /** @var array<int, array{user_ids: list<int>, payload: array<string, mixed>}> */
    private array $pending = [];

    private bool $flushRegistered = false;

    public function publicKey(): ?string
    {
        return $this->keys()['publicKey'] ?? null;
    }

    /**
     * Queue a push to every device of the given users; sent once the response is finished.
     *
     * @param  list<int>  $userIds
     * @param  array{title: string, body: string, url?: string, tag?: string}  $payload
     */
    public function queue(array $userIds, array $payload): void
    {
        $userIds = array_values(array_unique(array_filter($userIds)));
        if ($userIds === []) {
            return;
        }

        $this->pending[] = ['user_ids' => $userIds, 'payload' => $payload];

        if (! $this->flushRegistered) {
            $this->flushRegistered = true;
            app()->terminating(fn () => $this->flush());
        }
    }

    /**
     * Sends everything queued so far. Failures are logged, never thrown.
     *
     * @return int number of pushes the push services accepted
     */
    public function flush(): int
    {
        $batches = $this->pending;
        $this->pending = [];
        $this->flushRegistered = false;

        if ($batches === []) {
            return 0;
        }

        try {
            $webPush = $this->client();
            if (! $webPush) {
                return 0;
            }

            $userIds = collect($batches)->pluck('user_ids')->flatten()->unique()->all();
            $subscriptions = PushSubscription::query()->whereIn('user_id', $userIds)->get()->groupBy('user_id');

            foreach ($batches as $batch) {
                $json = json_encode($batch['payload'], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES);
                foreach ($batch['user_ids'] as $userId) {
                    foreach ($subscriptions->get($userId, []) as $subscription) {
                        $webPush->queueNotification($this->toSubscription($subscription), $json, ['TTL' => 3600, 'urgency' => 'high', 'topic' => $this->topic($batch['payload'])]);
                    }
                }
            }

            $sent = 0;
            foreach ($webPush->flush() as $report) {
                if ($report->isSuccess()) {
                    $sent++;
                } elseif ($report->isSubscriptionExpired()) {
                    // The phone uninstalled the app or revoked permission.
                    PushSubscription::query()->where('endpoint_hash', PushSubscription::hashEndpoint($report->getEndpoint()))->delete();
                } else {
                    report(new \RuntimeException('Push failed: '.$report->getReason()));
                }
            }

            return $sent;
        } catch (Throwable $exception) {
            report($exception);

            return 0;
        }
    }

    private function client(): ?WebPush
    {
        $keys = $this->keys();
        if (! $keys) {
            return null;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => 'mailto:'.(config('mail.from.address') ?: 'noreply@example.com'),
                'publicKey' => $keys['publicKey'],
                'privateKey' => $keys['privateKey'],
            ],
        ], [], new Client(['timeout' => 10, 'connect_timeout' => 5]));
        $webPush->setReuseVAPIDHeaders(true);

        return $webPush;
    }

    /**
     * @return array{publicKey: string, privateKey: string}|null
     */
    private function keys(): ?array
    {
        $keys = Setting::getValue(self::SETTING_KEY);
        if (is_array($keys) && ! empty($keys['publicKey']) && ! empty($keys['privateKey'])) {
            return $keys;
        }

        try {
            $keys = VAPID::createVapidKeys();
            Setting::putValue(self::SETTING_KEY, $keys);

            return $keys;
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    private function toSubscription(PushSubscription $subscription): Subscription
    {
        return Subscription::create([
            'endpoint' => $subscription->endpoint,
            'publicKey' => $subscription->public_key,
            'authToken' => $subscription->auth_token,
            'contentEncoding' => $subscription->content_encoding,
        ]);
    }

    /**
     * Push services collapse queued messages that share a topic (max 32 url-safe chars).
     *
     * @param  array<string, mixed>  $payload
     */
    private function topic(array $payload): ?string
    {
        $tag = (string) ($payload['tag'] ?? '');

        return $tag === '' ? null : substr(preg_replace('/[^A-Za-z0-9_-]/', '-', $tag), 0, 32);
    }
}
