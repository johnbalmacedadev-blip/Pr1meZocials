<?php

namespace App\Http\Controllers;

use App\Enums\ContentFormat;
use App\Enums\Priority;
use App\Models\ContentItem;
use App\Models\User;
use App\Models\WorkflowStatus;
use App\Services\AssetService;
use App\Services\AssignmentService;
use App\Services\ContentQuery;
use App\Services\ContentWriter;
use App\Services\NotificationService;
use App\Support\FormOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

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

        $options = FormOptions::for($request->user());
        $tickets = $query->make($request->user(), [], false)
            ->where('is_ticket', true)
            ->with(['brand:id,name', 'status:id,name,slug,color', 'owner:id,name', 'collaborators:users.id,users.name'])
            ->withCount('assets')
            ->latest('id')
            ->limit(20)
            ->get();

        return Inertia::render('Tickets/Index', [
            'brands' => $options['brands'],
            'people' => $options['people'],
            'formats' => ContentFormat::options(),
            'priorities' => Priority::options(),
            'maxImages' => self::MAX_IMAGES,
            'tickets' => $tickets->map(fn (ContentItem $item) => [
                'id' => $item->id,
                'reference_number' => $item->reference_number,
                'title' => $item->title,
                'priority' => $item->priority,
                'deadline' => $item->deadline?->toDateString(),
                'created_at' => $item->created_at?->toIso8601String(),
                'brand' => $item->brand?->name,
                'status' => $item->status?->only(['name', 'slug', 'color']),
                'team' => collect([$item->owner])->filter()->merge($item->collaborators)
                    ->unique('id')->map(fn (User $person) => ['id' => $person->id, 'name' => $person->name])->values(),
                'images' => $item->assets_count,
                'drive_url' => $item->inspiration_source === 'Google Drive' ? $item->inspiration_url : null,
            ])->values(),
        ]);
    }

    public function store(Request $request, ContentWriter $writer, AssetService $assets, AssignmentService $assignments): RedirectResponse
    {
        $this->authorize('create', ContentItem::class);
        $user = $request->user();
        $options = FormOptions::for($user);
        $brandIds = $options['brands']->pluck('id')->all();
        $peopleIds = $options['people']->pluck('id')->all();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'details' => ['nullable', 'string', 'max:10000'],
            'brand_id' => ['required', 'integer', Rule::in($brandIds)],
            'format' => ['required', Rule::enum(ContentFormat::class)],
            'priority' => ['nullable', Rule::enum(Priority::class)],
            'deadline' => ['nullable', 'date'],
            'drive_url' => ['nullable', 'string', 'max:2048', 'url:https'],
            'images' => ['nullable', 'array', 'max:'.self::MAX_IMAGES],
            'images.*' => ['file', 'mimes:jpg,jpeg,png,gif,webp', 'max:20480'],
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['integer', 'distinct', Rule::in($peopleIds)],
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

        $members = array_values(array_unique(array_map('intval', $data['member_ids'])));

        $item = DB::transaction(function () use ($data, $user, $writer, $assets, $assignments, $request, $members) {
            $item = $writer->create([
                'title' => $data['title'],
                'description' => $data['details'] ?? null,
                'brand_id' => (int) $data['brand_id'],
                'format' => $data['format'],
                'priority' => $data['priority'] ?? null,
                'deadline' => $data['deadline'] ?? null,
                'inspiration_source' => filled($data['drive_url'] ?? null) ? 'Google Drive' : null,
                'inspiration_url' => $data['drive_url'] ?? null,
            ], $user);

            $item->forceFill([
                'is_ticket' => true,
                'status_id' => WorkflowStatus::query()->where('slug', 'ready_for_production')->value('id') ?? $item->status_id,
            ])->save();

            foreach ($request->file('images', []) as $image) {
                $assets->store($item, $user, $image, ['kind' => 'reference']);
            }

            // The first teammate leads the task; AssignmentService notifies them.
            $assignments->sync($item, [
                'owner_id' => $members[0],
                'collaborator_ids' => array_slice($members, 1),
            ], $user);

            return $item;
        });

        foreach (User::query()->whereIn('id', array_slice($members, 1))->get() as $person) {
            if ($person->is($user)) {
                continue;
            }

            $this->notifications->send(
                $person,
                'assigned',
                'Task assigned',
                $user->name.' added you to '.$item->reference_number.' · '.$item->title.($item->deadline ? ' Deadline '.$item->deadline->toFormattedDateString().'.' : '.'),
                'assigned:'.$item->id.':'.$person->id.':collaborator:'.now()->toDateString(),
                ['content_id' => $item->id, 'role' => 'collaborator'],
            );
        }

        return redirect()->route('tickets.index')->with('success', 'Ticket '.$item->reference_number.' created and added to the production board.');
    }
}
