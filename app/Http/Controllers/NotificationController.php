<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $notifications = $request->user()->userNotifications()->latest()->paginate(20)->withQueryString();

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
        ]);
    }

    public function read(Request $request, UserNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $notification->forceFill(['read_at' => now()])->save();

        $contentId = $notification->data['content_id'] ?? null;

        if ($contentId) {
            return redirect()->route('content.show', $contentId);
        }

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->userNotifications()->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('success', 'Notifications marked as read.');
    }
}
