<?php

namespace App\Http\Controllers;

use App\Models\TaskNote;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TaskNoteController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeSender($request);

        $data = $request->validate([
            'recipient_id' => ['required', 'integer', 'exists:users,id'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $body = trim($data['body']);
        if ($body === '') {
            throw ValidationException::withMessages(['body' => 'Write a note before sending.']);
        }

        $recipient = User::query()->whereKey($data['recipient_id'])->where('is_active', true)->firstOrFail();
        abort_unless($this->canReach($request->user(), $recipient), 403);

        $note = TaskNote::query()->create([
            'recipient_id' => $recipient->id,
            'sender_id' => $request->user()->id,
            'body' => $body,
        ]);

        $this->notifications->send(
            $recipient,
            'task_note',
            'New task note',
            $request->user()->name.' sent you a note.',
            'task-note-'.$note->id,
            ['note_id' => $note->id],
        );

        return back()->with('success', 'Note sent to '.$recipient->name.'.');
    }

    public function complete(Request $request, TaskNote $taskNote): RedirectResponse
    {
        abort_unless($request->user()->is($taskNote->recipient) || $request->user()->is($taskNote->sender), 403);

        $taskNote->forceFill(['completed_at' => now()])->save();

        return back()->with('success', 'Note marked done.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function pendingFor(User $viewer, User $recipient): array
    {
        return TaskNote::query()
            ->pending()
            ->where('recipient_id', $recipient->id)
            ->with('sender:id,name')
            ->latest()
            ->get()
            ->map(fn (TaskNote $note) => [
                'id' => $note->id,
                'body' => $note->body,
                'created_at' => $note->created_at?->toIso8601String(),
                'sender' => $note->sender ? ['id' => $note->sender->id, 'name' => $note->sender->name] : null,
                'can_complete' => $viewer->is($note->recipient) || $viewer->is($note->sender),
            ])
            ->all();
    }

    private function authorizeSender(Request $request): void
    {
        $allowed = $request->user()->hasPermission('content.view')
            || $request->user()->hasPermission('content.view.any')
            || $request->user()->hasPermission('team.view');

        abort_unless($allowed, 403);
    }

    private function canReach(User $sender, User $recipient): bool
    {
        if ($sender->isAdmin() || $sender->is($recipient)) {
            return true;
        }

        $ids = $sender->accessibleBrandIds() ?: [0];

        return $recipient->brands()->whereIn('brands.id', $ids)->exists();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public static function recipientsFor(User $sender): array
    {
        return User::query()
            ->where('is_active', true)
            ->when(! $sender->isAdmin(), fn (Builder $query) => $query->whereHas('brands', fn (Builder $brands) => $brands->whereIn('brands.id', $sender->accessibleBrandIds() ?: [0])))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $person) => ['id' => $person->id, 'name' => $person->name])
            ->all();
    }
}
