<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use App\Support\EmailNotificationCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class NotificationPreferenceController extends Controller
{
    /**
     * Props for the email notification panel shown in Settings and on the profile page.
     *
     * @return array<string, mixed>
     */
    public static function props(Request $request): array
    {
        $mailer = (string) config('mail.default');

        return [
            'categories' => EmailNotificationCatalog::forDisplay(),
            'enabled' => $request->user()->emailNotificationCategories(),
            'email' => $request->user()->email,
            'delivers' => ! in_array($mailer, ['log', 'array'], true),
            'mailer' => $mailer,
        ];
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'categories' => ['present', 'array'],
            'categories.*' => ['string', Rule::in(EmailNotificationCatalog::keys())],
        ]);

        $request->user()->update([
            'email_notifications' => array_values(array_unique($data['categories'])),
        ]);

        return back()->with('success', 'Email notification settings saved.');
    }

    public function test(Request $request, NotificationService $notifications): RedirectResponse
    {
        $user = $request->user();

        if (! $user->email) {
            return back()->with('error', 'Add an email address to your account before sending a test.');
        }

        try {
            $notifications->sendTestEmail($user);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'The test email could not be sent: '.$exception->getMessage());
        }

        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            return back()->with('error', 'Test email was written to the server log, not delivered. Set MAIL_MAILER to smtp or sendmail in .env to send real emails.');
        }

        return back()->with('success', 'Test email sent to '.$user->email.'. Check your inbox (and spam folder).');
    }
}
