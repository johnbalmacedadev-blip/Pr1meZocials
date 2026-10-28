<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\WebPushService;
use Illuminate\Database\Eloquent\Collection;
use App\Support\ChatAttachments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Team chat over short polling (shared hosting has no websocket server).
 * Everyone is in one team group chat; direct messages are one-to-one threads.
 * Presence comes from users.last_seen_at, refreshed by each sync.
 */
class ChatController extends Controller
{
    private const ONLINE_SECONDS = 120;

    private const PAGE_SIZE = 40;

    public function index(Request $request): Response
    {
        return Inertia::render('Messenger/Index', [
            'initialConversationId' => $request->integer('conversation') ?: null,
        ]);
    }

    public function sync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'conversation_id' => ['nullable', 'integer'],
            'after_id' => ['nullable', 'integer', 'min:0'],
            'mark_read' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $this->touchPresence($user);

        $team = ChatConversation::team();
        $team->ensureTeamMember($user);

        $thread = null;
        if (! empty($data['conversation_id'])) {
            $conversation = $this->conversationFor($user, (int) $data['conversation_id']);
            $thread = $this->threadPayload($conversation, $user, (int) ($data['after_id'] ?? 0), (bool) ($data['mark_read'] ?? false));
        }

        $conversations = $this->conversationList($user);

        return response()->json([
            'me_id' => $user->id,
            'team_id' => $team->id,
            'users' => $this->people($user),
            'conversations' => $conversations,
            'unread_total' => collect($conversations)->sum('unread'),
            'attachment_types' => ChatAttachments::types($user),
            'thread' => $thread,
        ]);
    }

    public function direct(Request $request, User $user): JsonResponse
    {
        $me = $request->user();
        abort_if($user->id === $me->id || ! $user->is_active, 422, 'You cannot start a chat with this person.');

        $conversation = DB::transaction(function () use ($me, $user) {
            $conversation = ChatConversation::query()->firstOrCreate(
                ['key' => ChatConversation::directKey($me->id, $user->id)],
                ['kind' => 'direct'],
            );
            $conversation->participants()->syncWithoutDetaching([$me->id, $user->id]);

            return $conversation;
        });

        return response()->json(['conversation_id' => $conversation->id]);
    }

    public function store(Request $request, ChatConversation $conversation, WebPushService $push, NotificationService $notifications): JsonResponse
    {
        $user = $request->user();
        $conversation->ensureTeamMember($user);
        abort_unless($conversation->hasParticipant($user), 403);

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'attachment_type' => ['nullable', 'string', Rule::in(['content', 'event', 'inspiration', 'resource', 'campaign'])],
            'attachment_id' => ['nullable', 'integer', 'required_with:attachment_type'],
        ]);

        $body = trim((string) ($data['body'] ?? ''));
        $attachment = null;

        if (! empty($data['attachment_type'])) {
            $attachment = ChatAttachments::resolve($user, $data['attachment_type'], (int) $data['attachment_id']);
            if (! $attachment) {
                throw ValidationException::withMessages(['attachment_id' => 'That item is not available to share.']);
            }
        }

        if ($body === '' && ! $attachment) {
            throw ValidationException::withMessages(['body' => 'Type a message or attach an item.']);
        }

        $message = DB::transaction(function () use ($conversation, $user, $body, $attachment) {
            $message = $conversation->messages()->create([
                'user_id' => $user->id,
                'body' => $body === '' ? null : $body,
                'attachment' => $attachment,
            ]);
            $conversation->forceFill(['last_message_at' => $message->created_at])->save();
            // Your own message counts as read.
            $conversation->participants()->updateExistingPivot($user->id, ['last_read_message_id' => $message->id]);

            return $message;
        });

        $this->touchPresence($user);

        $url = $conversation->isTeam()
            ? route('messenger')
            : route('messenger', ['tab' => 'direct', 'conversation' => $conversation->id]);

        // @everyone / @Name: a notification in the bell (plus push and, if opted in, email).
        $mentioned = $this->mentioned($conversation, $user, $body);
        foreach ($mentioned as $person) {
            $notifications->send(
                $person,
                'mention',
                $user->name.' mentioned you',
                Str::limit(($conversation->isTeam() ? 'Team chat: ' : '').$body, 160),
                'chat-mention:'.$message->id.':'.$person->id,
                ['url' => $url],
            );
        }

        // Phone/browser push for everyone else in the conversation, sent after the response.
        $recipients = $conversation->participants()
            ->where('users.is_active', true)
            ->whereKeyNot($user->id)
            ->whereKeyNot($mentioned->modelKeys())
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $push->queue($recipients, [
            'title' => $conversation->isTeam() ? $user->name.' · Team chat' : $user->name,
            'body' => Str::limit($body !== '' ? $body : 'Shared: '.($attachment['title'] ?? 'an item'), 160),
            'url' => $url,
            'tag' => 'chat-'.$conversation->id,
        ]);

        return response()->json(['message' => $message->load('user:id,name')->toPayload()]);
    }

    public function older(Request $request, ChatConversation $conversation): JsonResponse
    {
        abort_unless($conversation->hasParticipant($request->user()), 403);
        $before = $request->integer('before_id');

        $messages = $conversation->messages()
            ->with('user:id,name')
            ->when($before > 0, fn ($query) => $query->where('id', '<', $before))
            ->orderByDesc('id')
            ->limit(self::PAGE_SIZE)
            ->get()
            ->reverse()
            ->values();

        return response()->json([
            'messages' => $messages->map->toPayload()->values(),
            'has_more' => $messages->count() === self::PAGE_SIZE,
        ]);
    }

    public function attachables(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        return response()->json([
            'items' => ChatAttachments::search($request->user(), $data['type'], trim((string) ($data['q'] ?? ''))),
        ]);
    }

    /**
     * People tagged in a message: "@everyone" tags everyone who can read the
     * conversation; "@Full Name" tags that teammate. The sender is never included.
     *
     * @return Collection<int, User>
     */
    private function mentioned(ChatConversation $conversation, User $sender, string $body): Collection
    {
        if (! str_contains($body, '@')) {
            return new Collection;
        }

        $audience = $conversation->isTeam()
            ? User::query()->where('is_active', true)->whereKeyNot($sender->id)->get()
            : $conversation->participants()->where('users.is_active', true)->whereKeyNot($sender->id)->get();

        if (preg_match('/(^|\s)@(everyone|all)(?![\p{L}\p{N}_])/iu', $body)) {
            return $audience;
        }

        return $audience->filter(fn (User $person) => $person->name !== ''
            && preg_match('/(^|\s)@'.preg_quote($person->name, '/').'(?![\p{L}\p{N}_])/iu', $body))->values();
    }

    private function touchPresence(User $user): void
    {
        if ($user->last_seen_at && $user->last_seen_at->gt(now()->subSeconds(30))) {
            return;
        }

        // Base query so updated_at is left alone.
        User::query()->whereKey($user->id)->toBase()->update(['last_seen_at' => now()]);
    }

    private function conversationFor(User $user, int $id): ChatConversation
    {
        $conversation = ChatConversation::query()->findOrFail($id);
        $conversation->ensureTeamMember($user);
        abort_unless($conversation->hasParticipant($user), 403);

        return $conversation;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function people(User $me): array
    {
        $onlineSince = now()->subSeconds(self::ONLINE_SECONDS);

        return User::query()
            ->where('is_active', true)
            ->whereKeyNot($me->id)
            ->orderBy('name')
            ->get(['id', 'name', 'job_title', 'last_seen_at'])
            ->map(fn (User $person) => [
                'id' => $person->id,
                'name' => $person->name,
                'job_title' => $person->job_title,
                'online' => $person->last_seen_at !== null && $person->last_seen_at->gte($onlineSince),
                'last_seen_at' => $person->last_seen_at?->toIso8601String(),
            ])
            ->sortByDesc('online')
            ->values()
            ->all();
    }

    /**
     * Team chat first, then direct messages by latest activity.
     *
     * @return list<array<string, mixed>>
     */
    private function conversationList(User $me): array
    {
        $conversations = ChatConversation::query()
            ->where(fn ($query) => $query->where('kind', 'team')->orWhereNotNull('last_message_at'))
            ->whereHas('participants', fn ($query) => $query->whereKey($me->id))
            ->with(['participants:users.id,users.name'])
            ->orderByRaw("CASE WHEN kind = 'team' THEN 0 ELSE 1 END")
            ->orderByDesc('last_message_at')
            ->limit(60)
            ->get();

        if ($conversations->isEmpty()) {
            return [];
        }

        $ids = $conversations->pluck('id');

        $unread = ChatMessage::query()
            ->join('chat_participants as p', function ($join) use ($me) {
                $join->on('p.conversation_id', '=', 'chat_messages.conversation_id')
                    ->where('p.user_id', '=', $me->id);
            })
            ->whereIn('chat_messages.conversation_id', $ids)
            ->where('chat_messages.user_id', '!=', $me->id)
            ->whereRaw('chat_messages.id > COALESCE(p.last_read_message_id, 0)')
            ->groupBy('chat_messages.conversation_id')
            ->selectRaw('chat_messages.conversation_id, COUNT(*) as total')
            ->pluck('total', 'chat_messages.conversation_id');

        $latest = ChatMessage::query()
            ->with('user:id,name')
            ->whereIn('id', ChatMessage::query()->whereIn('conversation_id', $ids)->groupBy('conversation_id')->selectRaw('MAX(id)'))
            ->get()
            ->keyBy('conversation_id');

        return $conversations->map(function (ChatConversation $conversation) use ($me, $unread, $latest) {
            $other = $conversation->isTeam() ? null : $conversation->participants->firstWhere('id', '!=', $me->id);
            $last = $latest->get($conversation->id);

            return [
                'id' => $conversation->id,
                'kind' => $conversation->kind,
                'name' => $conversation->isTeam() ? 'Team chat' : ($other?->name ?? 'Former teammate'),
                'user' => $other ? ['id' => $other->id, 'name' => $other->name] : null,
                'last_message' => $last ? [
                    'user_id' => $last->user_id,
                    'user_name' => $last->user?->name,
                    'preview' => $last->body ?? ('Shared: '.($last->attachment['title'] ?? 'an item')),
                    'created_at' => $last->created_at?->toIso8601String(),
                ] : null,
                'unread' => (int) ($unread[$conversation->id] ?? 0),
            ];
        })->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function threadPayload(ChatConversation $conversation, User $me, int $afterId, bool $markRead): array
    {
        $query = $conversation->messages()->with('user:id,name');

        if ($afterId > 0) {
            $messages = $query->where('id', '>', $afterId)->orderBy('id')->limit(200)->get();
            $hasMore = null;
        } else {
            $messages = $query->orderByDesc('id')->limit(self::PAGE_SIZE)->get()->reverse()->values();
            $hasMore = $messages->count() === self::PAGE_SIZE;
        }

        $participants = $conversation->participants()->get(['users.id', 'users.name']);
        $mine = $participants->firstWhere('id', $me->id);
        $others = $participants->where('id', '!=', $me->id)->values();

        if ($markRead) {
            $newest = $conversation->messages()->max('id');
            if ($newest && (int) ($mine?->pivot->last_read_message_id ?? 0) < $newest) {
                $conversation->participants()->updateExistingPivot($me->id, ['last_read_message_id' => $newest]);
            }
        }

        $other = $conversation->isTeam() ? null : $others->first();

        return [
            'conversation_id' => $conversation->id,
            'kind' => $conversation->kind,
            'name' => $conversation->isTeam() ? 'Team chat' : ($other?->name ?? 'Former teammate'),
            'user' => $other ? ['id' => $other->id, 'name' => $other->name] : null,
            'messages' => $messages->map->toPayload()->values(),
            'has_more' => $hasMore,
            // Who has read up to which message, for "Seen" / "Seen by …".
            'readers' => $others->map(fn (User $person) => [
                'id' => $person->id,
                'name' => $person->name,
                'last_read_message_id' => $person->pivot->last_read_message_id ? (int) $person->pivot->last_read_message_id : null,
            ])->values(),
        ];
    }
}
