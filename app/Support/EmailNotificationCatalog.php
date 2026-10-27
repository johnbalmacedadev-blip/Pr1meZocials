<?php

namespace App\Support;

/**
 * Email notification categories a user can opt into. Each category groups
 * one or more in-app notification types sent through NotificationService.
 */
class EmailNotificationCatalog
{
    /**
     * @return array<string, array{label: string, description: string, types: list<string>}>
     */
    public static function categories(): array
    {
        return [
            'task_assigned' => [
                'label' => 'New task assigned',
                'description' => 'You are made the owner of a content task.',
                'types' => ['assigned'],
            ],
            'task_status' => [
                'label' => 'Task status changed',
                'description' => 'A task you own or collaborate on moves to a new status.',
                'types' => ['status_changed'],
            ],
            'reviews' => [
                'label' => 'Reviews and approvals',
                'description' => 'Work is submitted for your review, approved, or sent back for revisions.',
                'types' => ['submitted_for_review', 'approved', 'revision_requested'],
            ],
            'mentions' => [
                'label' => 'Mentions and task notes',
                'description' => 'Someone mentions you in a comment or sends you a task note.',
                'types' => ['mention', 'task_note'],
            ],
            'deadlines' => [
                'label' => 'Deadlines and schedule changes',
                'description' => 'Your content is due soon, overdue, or its publishing date changes.',
                'types' => ['deadline_approaching', 'overdue', 'schedule_changed'],
            ],
            'event_created' => [
                'label' => 'New events',
                'description' => 'A new event is added to the calendar for one of your brands.',
                'types' => ['event_created'],
            ],
            'event_upcoming' => [
                'label' => 'Upcoming event reminders',
                'description' => 'A reminder the day before an event on one of your brands.',
                'types' => ['event_upcoming'],
            ],
            'content_posted' => [
                'label' => 'New content posted',
                'description' => 'A post is published or scheduled on a brand page you can access.',
                'types' => ['content_posted', 'facebook_post_published', 'facebook_post_scheduled'],
            ],
            'team' => [
                'label' => 'New team members',
                'description' => 'Someone joins the team through an invite link (administrators).',
                'types' => ['user_registered'],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::categories());
    }

    public static function categoryFor(string $type): ?string
    {
        foreach (self::categories() as $key => $category) {
            if (in_array($type, $category['types'], true)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @return list<array{key: string, label: string, description: string}>
     */
    public static function forDisplay(): array
    {
        return collect(self::categories())
            ->map(fn (array $category, string $key) => [
                'key' => $key,
                'label' => $category['label'],
                'description' => $category['description'],
            ])
            ->values()
            ->all();
    }
}
