<?php

namespace App\Http\Controllers;

use App\Models\ContentItem;
use App\Models\User;
use App\Models\WorkflowStatus;
use App\Support\ContentPresenter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('team.view'), 403);

        $from = Carbon::parse($request->input('from', now()->subDays(29)->toDateString()))->startOfDay();
        $to = Carbon::parse($request->input('to', now()->toDateString()))->endOfDay();
        $brandIds = $request->user()->accessibleBrandIds();

        $people = User::query()
            ->with('roles:id,name,slug')
            ->where('is_active', true)
            ->when(! $request->user()->isAdmin(), fn (Builder $query) => $query->whereHas('brands', fn (Builder $brands) => $brands->whereIn('brands.id', $brandIds ?: [0])))
            ->orderBy('name')
            ->get();

        $statusIds = WorkflowStatus::query()->pluck('slug', 'id');
        $rows = ContentItem::query()
            ->visibleTo($request->user())
            ->whereIn('owner_id', $people->pluck('id')->all() ?: [0])
            ->get(['id', 'owner_id', 'status_id', 'deadline', 'published_at', 'complexity', 'title', 'reference_number']);

        $members = $people->map(function (User $person) use ($rows, $statusIds, $from, $to) {
            $owned = $rows->where('owner_id', $person->id);
            $open = $owned->filter(fn (ContentItem $item) => ! in_array($statusIds[$item->status_id] ?? '', config('ops.closed_statuses'), true));
            $complexity = $open->whereNotNull('complexity');

            return [
                'id' => $person->id,
                'name' => $person->name,
                'job_title' => $person->job_title,
                'roles' => $person->roles->map->only(['name', 'slug'])->values(),
                'active' => $open->count(),
                'in_progress' => $open->filter(fn (ContentItem $item) => ($statusIds[$item->status_id] ?? '') === 'in_progress')->count(),
                'awaiting_review' => $open->filter(fn (ContentItem $item) => ($statusIds[$item->status_id] ?? '') === 'internal_review')->count(),
                'overdue' => $open->filter(fn (ContentItem $item) => $item->deadline && $item->deadline->copy()->endOfDay()->isPast())->count(),
                'completed' => $owned->filter(fn (ContentItem $item) => $item->published_at && $item->published_at->between($from, $to))->count(),
                'upcoming' => $open->filter(fn (ContentItem $item) => $item->deadline && $item->deadline->between(now()->startOfDay(), now()->addDays(14)))->count(),
                'average_complexity' => $complexity->isEmpty() ? null : round($complexity->avg('complexity'), 1),
            ];
        })->values();

        return Inertia::render('Team/Index', [
            'members' => $members,
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        ]);
    }

    public function show(Request $request, User $user, ContentPresenter $presenter): Response
    {
        abort_unless($request->user()->hasPermission('team.view'), 403);
        abort_unless($request->user()->isAdmin() || $user->brands()->whereIn('brands.id', $request->user()->accessibleBrandIds() ?: [0])->exists(), 403);

        $items = ContentItem::query()
            ->visibleTo($request->user())
            ->where(function (Builder $query) use ($user) {
                $query->where('owner_id', $user->id)
                    ->orWhere('copywriter_id', $user->id)
                    ->orWhere('video_editor_id', $user->id)
                    ->orWhere('reviewer_id', $user->id)
                    ->orWhereHas('collaborators', fn (Builder $collaborators) => $collaborators->where('users.id', $user->id));
            })
            ->with(['brand:id,name', 'campaign:id,name', 'status:id,name,slug,color', 'owner:id,name', 'platformVersions.platform:id,name,slug,color'])
            ->latest('updated_at')
            ->limit(50)
            ->get();

        return Inertia::render('Team/Show', [
            'member' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'job_title' => $user->job_title,
                'roles' => $user->roles()->get(['name', 'slug']),
            ],
            'content' => $items->map(fn (ContentItem $item) => $presenter->summary($item))->values(),
        ]);
    }
}
