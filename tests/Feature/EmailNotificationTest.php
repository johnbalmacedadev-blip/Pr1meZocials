<?php

namespace Tests\Feature;

use App\Mail\NotificationMail;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsOperations;
use Tests\TestCase;

class EmailNotificationTest extends TestCase
{
    use BuildsOperations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOperations();
    }

    public function test_user_can_choose_email_notification_categories(): void
    {
        $brand = $this->makeBrand();
        $user = $this->makeUser('marketing_manager', $brand);

        $this->actingAs($user)->put('/notification-preferences', [
            'categories' => ['task_assigned', 'event_created'],
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(['task_assigned', 'event_created'], $user->fresh()->emailNotificationCategories());

        $this->actingAs($user)->put('/notification-preferences', [
            'categories' => ['not_a_category'],
        ])->assertSessionHasErrors('categories.0');

        $this->actingAs($user)->get('/profile')->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Edit')
            ->where('emailNotifications.enabled', ['task_assigned', 'event_created'])
        );
    }

    public function test_notifications_are_emailed_only_for_enabled_categories(): void
    {
        Mail::fake();
        $brand = $this->makeBrand();
        $user = $this->makeUser('creative', $brand, ['email_notifications' => ['task_assigned']]);
        $notifications = app(NotificationService::class);

        $notifications->send($user, 'assigned', 'Task assigned', 'You were assigned.', 'assigned-test');
        $notifications->send($user, 'status_changed', 'Task status changed', 'Moved.', 'status-test');

        Mail::assertSent(NotificationMail::class, 1);
        Mail::assertSent(NotificationMail::class, fn (NotificationMail $mail) => $mail->hasTo($user->email) && $mail->heading === 'Task assigned');
        $this->assertDatabaseCount('user_notifications', 2);
    }

    public function test_new_event_emails_brand_members_who_opted_in(): void
    {
        Mail::fake();
        $brand = $this->makeBrand();
        $manager = $this->makeUser('marketing_manager', $brand);
        $teammate = $this->makeUser('marketing_manager', $brand, ['email_notifications' => ['event_created']]);
        $outsider = $this->makeUser('marketing_manager', $this->makeBrand('Other'), ['email_notifications' => ['event_created']]);

        $this->actingAs($manager)->post('/events', [
            'brand_id' => $brand->id,
            'title' => 'Launch party',
            'starts_on' => now()->addWeek()->toDateString(),
            'start_time' => '19:00',
            'recurrence' => 'none',
        ])->assertRedirect()->assertSessionHasNoErrors();

        Mail::assertSent(NotificationMail::class, fn (NotificationMail $mail) => $mail->hasTo($teammate->email));
        Mail::assertNotSent(NotificationMail::class, fn (NotificationMail $mail) => $mail->hasTo($outsider->email) || $mail->hasTo($manager->email));
    }

    public function test_test_button_sends_email_to_current_user(): void
    {
        Mail::fake();
        config(['mail.default' => 'smtp']);
        $user = $this->makeUser('creative', $this->makeBrand());

        $this->actingAs($user)->post('/notification-preferences/test')
            ->assertRedirect()
            ->assertSessionHas('success');

        Mail::assertSent(NotificationMail::class, fn (NotificationMail $mail) => $mail->hasTo($user->email) && $mail->heading === 'Test email notification');
    }
}
