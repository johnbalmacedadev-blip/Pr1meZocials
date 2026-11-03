<?php

namespace App\Http\Controllers;

use App\Models\Funnel;
use App\Models\FunnelOrder;
use App\Services\NotificationService;
use App\Support\FunnelPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Throwable;

/**
 * The customer-facing funnel page and its order form. No sign-in required.
 */
class PublicFunnelController extends Controller
{
    private const SOURCE_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'fbclid', 'gclid', 'ttclid', 'referrer'];

    public function show(Request $request, Funnel $funnel): Response
    {
        // Draft and paused pages stay private, but the team can still preview them.
        abort_unless($funnel->isLive() || $request->user()?->can('view', $funnel), 404);

        return response(FunnelPage::render($funnel));
    }

    public function order(Request $request, Funnel $funnel, NotificationService $notifications): JsonResponse
    {
        abort_unless($funnel->isLive(), 404);

        // Only bots fill this hidden field: tell them it worked and keep nothing.
        if ($request->filled('website')) {
            return response()->json(['ok' => true]);
        }

        $request->merge(['phone_number' => preg_replace('/[\s-]+/', '', (string) $request->input('phone_number'))]);

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'phone_number' => ['required', 'string', 'regex:/^(09|\+639|639)\d{9}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['required', 'string', 'max:255'],
            'province' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'barangay' => ['nullable', 'string', 'max:120'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'source' => ['nullable', 'array'],
            'source.*' => ['nullable', 'string', 'max:500'],
        ], [
            'phone_number.regex' => 'Please enter a valid Philippine mobile number, for example 09171234567.',
            'email.email' => 'Please enter a valid email address, or leave it blank.',
        ]);

        $phone = '09'.substr($data['phone_number'], -9);
        $quantity = (int) $data['quantity'];

        // A retry after a slow connection or a double tap must not ship twice.
        $alreadyPlaced = $funnel->orders()
            ->where('phone', $phone)
            ->where('address', $data['address'])
            ->where('quantity', $quantity)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->exists();

        if ($alreadyPlaced) {
            return response()->json(['ok' => true]);
        }

        // The price always comes from the funnel, never from the browser.
        $order = $funnel->orders()->create([
            'full_name' => $data['full_name'],
            'phone' => $phone,
            'email' => $data['email'] ?? null,
            'address' => $data['address'],
            'province' => $data['province'],
            'city' => $data['city'],
            'barangay' => $data['barangay'] ?? null,
            'quantity' => $quantity,
            'unit_price' => $funnel->unit_price,
            'total' => round((float) $funnel->unit_price * $quantity, 2),
            'source' => Arr::only($data['source'] ?? [], self::SOURCE_KEYS) ?: null,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 490, ''),
        ]);

        // Runs after the customer has their answer, and a mail problem can never lose the order.
        dispatch(function () use ($funnel, $order, $notifications) {
            try {
                $this->notifyTeam($funnel, $order, $notifications);
            } catch (Throwable $exception) {
                report($exception);
            }
        })->afterResponse();

        return response()->json(['ok' => true], 201);
    }

    private function notifyTeam(Funnel $funnel, FunnelOrder $order, NotificationService $notifications): void
    {
        foreach ($notifications->brandAudience($funnel->brand_id, 'funnels.view') as $person) {
            $notifications->send(
                $person,
                'funnel_order',
                'New order: '.$funnel->product_name,
                $order->full_name.' ordered '.$order->quantity.' ('.FunnelPage::money($order->total).'). '.$order->phone.', '.$order->shippingAddress().'.',
                'funnel-order-'.$order->id,
                ['funnel_id' => $funnel->id, 'funnel_order_id' => $order->id],
            );
        }
    }
}
