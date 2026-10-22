<?php

namespace App\Http\Controllers;

use App\Http\Controllers\TaskNoteController;
use App\Models\ContentItem;
use App\Models\User;
use App\Services\AssignmentService;
use App\Services\ContentQuery;
use App\Services\ContentWorkflow;
use App\Services\NotificationService;
use App\Support\ContentPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ContentWorkflowController extends Controller
{
    public function __construct(
        private ContentPresenter $presenter,
        private NotificationService $notifications,
    ) {}

    public function board(Request $request, ContentQuery $query): Response
    {
        $this->authorize('viewAny', ContentItem::class);

        $filters = $request->only([
            'q', 'brand_id', 'campaign_id', 'format', 'priority', 'owner', 'owner_id', 'platform_id', 'overdue',
        ]);

        $items = $query->make($request->user(), $filters)
            ->with(['brand:id,name', 'campaign:id,name', 'status:id,name,slug,color', 'owner:id,name', 'platformVersions.platform:id,name,slug,color'])
            ->limit(200)
            ->get();

        $selected = null;

        if ($request->filled('open')) {
            $open = ContentItem::query()->find($request->integer('open'));

            if ($open && $request->user()->can('view', $open)) {
                $selected = $this->presenter->detail($open, $request->user());
            }
        }

        return Inertia::render('Board/Index', [
            'cards' => $items->map(fn (ContentItem $item) => $this->presenter->summary($item))->values(),
            'filters' => $filters,
            'selected' => $selected,
            'truncated' => $items->count() === 200,
            'notes' => TaskNoteController::pendingFor($request->user(), $request->user()),
            'recipients' => TaskNoteController::recipientsFor($request->user()),
        ]);
    }

    public function transition(Request $request, ContentItem $content, ContentWorkflow $workflow): RedirectResponse
    {
        $this->authorize('transition', $content);

        $data = $request->validate([
            'status' => ['required', 'string', 'exists:workflow_statuses,slug'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $workflow->transition($content, $data['status'], $request->user(), $data['notes'] ?? null);

        return back()->with('success', 'Status updated.');
    }

    public function assign(Request $request, ContentItem $content, AssignmentService $assignments): RedirectResponse
    {
        $this->authorize('assign', $content);

        $data = $request->validate([
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'collaborator_ids' => ['nullable', 'array'],
            'collaborator_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $assignments->sync($content, $data, $request->user());

        return back()->with('success', 'Assignment saved.');
    }

    public function review(Request $request, ContentItem $content, ContentWorkflow $workflow): RedirectResponse
    {
        $this->authorize('approve', $content);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'revision_requested', 'rejected'])],
            'notes' => ['nullable', 'string', 'max:5000', 'required_if:decision,revision_requested', 'required_if:decision,rejected'],
            'asset_id' => ['nullable', 'integer', Rule::exists('creative_assets', 'id')->where('content_item_id', $content->id)],
        ]);

        DB::transaction(function () use ($request, $content, $workflow, $data) {
            $workflow->recordDecision(
                $content,
                $request->user(),
                $data['decision'],
                $data['notes'] ?? null,
                $data['asset_id'] ?? null,
            );

            $workflow->transition(
                $content->fresh('status'),
                $data['decision'] === 'approved' ? 'approved' : 'in_progress',
                $request->user(),
                $data['notes'] ?? null,
                false,
            );
        });

        $message = match ($data['decision']) {
            'approved' => 'Approved. This does not publish the content.',
            'rejected' => 'Rejected and sent back for revision.',
            default => 'Revision request sent to the owner.',
        };

        return back()->with('success', $message);
    }

    public function comment(Request $request, ContentItem $content): RedirectResponse
    {
        $this->authorize('view', $content);
        abort_unless(
            $request->user()->hasPermission('comments.create') || $content->isAssignedTo($request->user()),
            403,
        );

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'mention_ids' => ['nullable', 'array'],
            'mention_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $comment = $content->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $data['body'],
            'mention_ids' => $data['mention_ids'] ?? [],
        ]);

        app(\App\Services\ActivityLogger::class)->log($content, $request->user(), 'commented', 'Added a comment.');

        foreach (User::query()->whereIn('id', $data['mention_ids'] ?? [])->get() as $mentioned) {
            if ($mentioned->id === $request->user()->id || ! $mentioned->can('view', $content)) {
                continue;
            }

            $this->notifications->send(
                $mentioned,
                'mention',
                'You were mentioned',
                $request->user()->name.' mentioned you on '.$content->reference_number.'.',
                'mention:'.$comment->id.':'.$mentioned->id,
                ['content_id' => $content->id],
            );
        }

        return back()->with('success', 'Comment added.');
    }
}
