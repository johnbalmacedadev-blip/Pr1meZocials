<?php

namespace App\Http\Controllers;

use App\Enums\ContentFormat;
use App\Enums\Priority;
use App\Models\ContentItem;
use App\Models\CreativeAsset;
use App\Models\User;
use App\Models\WorkflowStatus;
use App\Services\ActivityLogger;
use App\Services\AssetService;
use App\Services\AssignmentService;
use App\Services\ContentQuery;
use App\Services\ContentWriter;
use App\Services\NotificationService;
use App\Support\FormOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Tickets: a quick way to hand a task to teammates. Each ticket is a content item
 * that lands in "Ready for Production" on the production board, with its images
 * stored as reference assets and everyone on it notified.
 */
class TicketController extends Controller
{
    public const MAX_IMAGES = 6;

    private const DRIVE_HOSTS = ['drive.google.com', 'docs.google.com'];

    public function __construct(private NotificationService $notifications) {}

    public function index(Request $request, ContentQuery $query): Response
    {
        $this->authorize('create', ContentItem::class);

        $tickets = $query->make($request->user(), [], false)
            ->where('is_ticket', true)
            ->with(['brand:id,name', 'status:id,name,slug,color', 'owner:id,name', 'collaborators:users.id,users.name'])
            ->withCount('assets')
            ->latest('id')
            ->limit(20)
            ->get();

        return Inertia::render('Tickets/Index', [
            ...$this->formProps($request->user()),
            'tickets' => $tickets->map(fn (ContentItem $item) => [
                'id' => $item->id,
                'reference_number' => $item->reference_number,
                'title' => $item->title,
                'priority' => $item->priority,
                'deadline' => $item->deadline?->toDateString(),
                'created_at' => $item->created_at?->toIso8601String(),
                'brand' => $item->brand?->name,
                'status' => $item->status?->only(['name', 'slug', 'color']),
                'team' => $this->team($item),
                'images' => $item->assets_count,
                'drive_url' => $this->driveUrl($item),
            ])->values(),
        ]);
    }

    public function show(Request $request, ContentItem $ticket): Response|RedirectResponse
    {
        $this->authorize('view', $ticket);

        if (! $ticket->is_ticket) {
            return redirect()->route('content.show', $ticket);
        }

        $ticket->load(['brand:id,name', 'status:id,name,slug,color', 'owner:id,name', 'creator:id,name', 'collaborators:users.id,users.name']);

        return Inertia::render('Tickets/Show', [
            ...$this->formProps($request->user()),
            'ticket' => [
                'id' => $ticket->id,
                'reference_number' => $ticket->reference_number,
                'title' => $ticket->title,
                'details' => $ticket->description,
                'brand' => $ticket->brand?->name,
                'format' => $ticket->format,
                'format_label' => $ticket->formatLabel(),
                'priority' => $ticket->priority,
                'deadline' => $ticket->deadline?->toDateString(),
                'drive_url' => $this->driveUrl($ticket),
                'status' => $ticket->status?->only(['name', 'slug', 'color']),
                'creator' => $ticket->creator?->name,
                'created_at' => $ticket->created_at?->toIso8601String(),
                'team' => $this->team($ticket),
                'images' => $ticket->assets()
                    ->where('mime_type', 'like', 'image/%')
                    ->orderBy('id')
                    ->get(['id', 'original_filename'])
                    ->map(fn (CreativeAsset $asset) => ['id' => $asset->id, 'name' => $asset->original_filename])
                    ->values(),
            ],
            'shareUrl' => URL::signedRoute('tickets.shared', $ticket, absolute: false),
            'canEdit' => $request->user()->can('update', $ticket),
            'startEditing' => $request->boolean('edit'),
        ]);
    }

    /**
     * Shared task link: anyone with the signed link sees a read-only summary.
     * Images, the Drive link and the team stay behind sign-in.
     */
    public function shared(Request $request, ContentItem $ticket): Response|RedirectResponse
    {
        abort_unless($ticket->is_ticket, 404);

        if ($request->user()?->can('view', $ticket)) {
            return redirect()->route('tickets.show', $ticket);
        }

        $ticket->load('status:id,name,slug,color');

        return Inertia::render('Tickets/Shared', [
            'ticket' => [
                'id' => $ticket->id,
                'reference_number' => $ticket->reference_number,
                'title' => $ticket->title,
                'details' => $ticket->description,
                'format_label' => $ticket->formatLabel(),
                'priority' => $ticket->priority,
                'deadline' => $ticket->deadline?->toDateString(),
                'status' => $ticket->status?->only(['name', 'slug', 'color']),
                'images' => $ticket->assets()->where('mime_type', 'like', 'image/%')->count(),
                'has_drive' => $this->driveUrl($ticket) !== null,
            ],
            'signedIn' => $request->user() !== null,
        ]);
    }

    /** Ticket images are visible to anyone who can see the ticket. */
    public function image(ContentItem $ticket, int $image): StreamedResponse
    {
        abort_unless($ticket->is_ticket, 404);
        $this->authorize('view', $ticket);

        $asset = $ticket->assets()->where('mime_type', 'like', 'image/%')->findOrFail($image);
        abort_unless($asset->existsOnDisk(), 404);

        return Storage::disk($asset->disk)->response($asset->path, null, [
            'Content-Type' => $asset->mime_type,
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    public function store(Request $request, ContentWriter $writer, AssetService $assets, AssignmentService $assignments): RedirectResponse
    {
        $this->authorize('create', ContentItem::class);
        $user = $request->user();
        $data = $this->validated($request, true);
        $members = $data['members'];

        $item = DB::transaction(function () use ($data, $user, $writer, $assets, $assignments, $request, $members) {
            $item = $writer->create([
                'title' => $data['title'],
                'description' => $data['details'] ?? null,
                'brand_id' => (int) $data['brand_id'],
                'format' => $data['format'],
                'priority' => $data['priority'] ?? 'medium',
                'deadline' => $data['deadline'] ?? null,
                ...$this->driveFields($data['drive_url'] ?? null),
            ], $user);

            $item->forceFill([
                'is_ticket' => true,
                'status_id' => WorkflowStatus::query()->where('slug', 'ready_for_production')->value('id') ?? $item->status_id,
            ])->save();

            foreach ($request->file('images', []) as $image) {
                $assets->store($item, $user, $image, ['kind' => 'reference']);
            }

            // The first teammate leads the task; AssignmentService notifies them.
            $assignments->sync($item, ['owner_id' => $members[0], 'collaborator_ids' => array_slice($members, 1)], $user);

            return $item;
        });

        $this->notifyCollaborators($item, array_slice($members, 1), $user);

        return redirect()->route('tickets.index')->with('success', 'Ticket '.$item->reference_number.' created and added to the production board.');
    }

    public function update(Request $request, ContentItem $ticket, AssetService $assets, AssignmentService $assignments, ActivityLogger $activity): RedirectResponse
    {
        abort_unless($ticket->is_ticket, 404);
        $this->authorize('update', $ticket);
        $user = $request->user();
        $data = $this->validated($request, false);
        $members = $data['members'];

        $remove = $ticket->assets()->whereIn('id', $data['remove_image_ids'] ?? [])->get();
        $kept = $ticket->assets()->where('mime_type', 'like', 'image/%')->count() - $remove->count();
        if ($kept + count($request->file('images', [])) > self::MAX_IMAGES) {
            throw ValidationException::withMessages(['images' => 'A ticket can have up to '.self::MAX_IMAGES.' images.']);
        }

        $previousCollaborators = $ticket->collaborators()->pluck('users.id')->all();

        DB::transaction(function () use ($ticket, $data, $user, $assets, $assignments, $activity, $request, $members, $remove) {
            $ticket->fill([
                'title' => $data['title'],
                'description' => $data['details'] ?? null,
                'format' => $data['format'],
                'priority' => $data['priority'] ?? 'medium',
                'deadline' => $data['deadline'] ?? null,
                ...$this->driveFields($data['drive_url'] ?? null),
            ])->save();

            $remove->each->delete();
            foreach ($request->file('images', []) as $image) {
                $assets->store($ticket, $user, $image, ['kind' => 'reference']);
            }

            $assignments->sync($ticket, ['owner_id' => $members[0], 'collaborator_ids' => array_slice($members, 1)], $user);
            $activity->log($ticket, $user, 'updated', 'Ticket updated.');
        });

        $this->notifyCollaborators($ticket, array_diff(array_slice($members, 1), $previousCollaborators, [$ticket->owner_id]), $user);

        return redirect()->route('tickets.show', $ticket)->with('success', 'Ticket '.$ticket->reference_number.' saved.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $creating): array
    {
        $options = FormOptions::for($request->user());

        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'details' => ['nullable', 'string', 'max:10000'],
            'brand_id' => $creating ? ['required', 'integer', Rule::in($options['brands']->pluck('id')->all())] : ['prohibited'],
            'format' => ['required', Rule::enum(ContentFormat::class)],
            'priority' => ['nullable', Rule::enum(Priority::class)],
            'deadline' => ['nullable', 'date'],
            'drive_url' => ['nullable', 'string', 'max:2048', 'url:https'],
            'images' => ['nullable', 'array', 'max:'.self::MAX_IMAGES],
            'images.*' => ['file', 'mimes:jpg,jpeg,png,gif,webp', 'max:20480'],
            'remove_image_ids' => $creating ? ['prohibited'] : ['nullable', 'array'],
            'remove_image_ids.*' => ['integer'],
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['integer', 'distinct', Rule::in($options['people']->pluck('id')->all())],
        ], [
            'brand_id.in' => 'Choose a brand you work on.',
            'member_ids.required' => 'Choose at least one teammate for this task.',
            'member_ids.*.in' => 'Choose teammates from the list.',
            'images.max' => 'Attach up to '.self::MAX_IMAGES.' images.',
            'images.*.mimes' => 'Images must be JPG, PNG, GIF, or WebP.',
            'images.*.max' => 'Each image can be up to 20 MB.',
        ]);

        if (filled($data['drive_url'] ?? null) && ! in_array(strtolower((string) parse_url($data['drive_url'], PHP_URL_HOST)), self::DRIVE_HOSTS, true)) {
            throw ValidationException::withMessages(['drive_url' => 'Paste a Google Drive link (drive.google.com or docs.google.com).']);
        }

        $data['members'] = array_values(array_unique(array_map('intval', $data['member_ids'])));

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(User $user): array
    {
        $options = FormOptions::for($user);

        return [
            'brands' => $options['brands'],
            'people' => $options['people'],
            'formats' => ContentFormat::options(),
            'priorities' => Priority::options(),
            'maxImages' => self::MAX_IMAGES,
        ];
    }

    /**
     * @return array{inspiration_source: string|null, inspiration_url: string|null}
     */
    private function driveFields(?string $url): array
    {
        return [
            'inspiration_source' => filled($url) ? 'Google Drive' : null,
            'inspiration_url' => filled($url) ? $url : null,
        ];
    }

    private function driveUrl(ContentItem $item): ?string
    {
        return $item->inspiration_source === 'Google Drive' ? $item->inspiration_url : null;
    }

    /**
     * Lead first, then the rest of the team.
     *
     * @return list<array{id: int, name: string}>
     */
    private function team(ContentItem $item): array
    {
        return collect([$item->owner])->filter()->merge($item->collaborators)
            ->unique('id')->map(fn (User $person) => ['id' => $person->id, 'name' => $person->name])->values()->all();
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function notifyCollaborators(ContentItem $item, array $ids, User $actor): void
    {
        foreach (User::query()->whereIn('id', $ids)->get() as $person) {
            if ($person->is($actor)) {
                continue;
            }

            $this->notifications->send(
                $person,
                'assigned',
                'Task assigned',
                $actor->name.' added you to '.$item->reference_number.' · '.$item->title.($item->deadline ? ' Deadline '.$item->deadline->toFormattedDateString().'.' : '.'),
                'assigned:'.$item->id.':'.$person->id.':collaborator:'.now()->toDateString(),
                ['content_id' => $item->id, 'role' => 'collaborator'],
            );
        }
    }
}
