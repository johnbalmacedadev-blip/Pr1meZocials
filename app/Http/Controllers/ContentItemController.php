<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContentRequest;
use App\Http\Requests\UpdateContentRequest;
use App\Models\ContentItem;
use App\Services\AssignmentService;
use App\Services\ContentQuery;
use App\Services\ContentWorkflow;
use App\Services\ContentWriter;
use App\Support\ContentPresenter;
use App\Support\FormOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContentItemController extends Controller
{
    public function __construct(private ContentPresenter $presenter) {}

    public function index(Request $request, ContentQuery $query): Response
    {
        $this->authorize('viewAny', ContentItem::class);

        $filters = $this->filters($request);
        $items = $query->make($request->user(), $filters)
            ->with(['brand:id,name', 'campaign:id,name', 'status:id,name,slug,color', 'owner:id,name', 'reviewer:id,name', 'platformVersions.platform:id,name,slug,color'])
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Content/Index', [
            'content' => $this->presenter->paginate($items),
            'filters' => $filters,
            'options' => FormOptions::for($request->user()),
            'savedViews' => $request->user()->savedViews()->where('module', 'content')->latest()->get(['id', 'name', 'filters']),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', ContentItem::class);

        return Inertia::render('Content/Create', [
            'options' => FormOptions::for($request->user()),
        ]);
    }

    public function store(StoreContentRequest $request, ContentWriter $writer, AssignmentService $assignments): RedirectResponse
    {
        abort_unless($request->user()->canAccessBrand((int) $request->integer('brand_id')), 403);

        $item = $writer->create($request->validated(), $request->user());

        if ($request->user()->can('assign', $item)) {
            $assignments->sync($item, $request->safe()->only([
                'owner_id', 'collaborator_ids',
            ]), $request->user());
        }

        return redirect()->route('content.show', $item)->with('success', 'Idea '.$item->reference_number.' captured.');
    }

    public function show(Request $request, ContentItem $content): Response
    {
        $this->authorize('view', $content);

        return Inertia::render('Content/Show', [
            'contentItem' => $this->presenter->detail($content, $request->user()),
            'options' => FormOptions::for($request->user()),
        ]);
    }

    public function edit(Request $request, ContentItem $content): Response
    {
        $this->authorize('update', $content);

        return Inertia::render('Content/Edit', [
            'contentItem' => $this->presenter->detail($content, $request->user()),
            'options' => FormOptions::for($request->user()),
        ]);
    }

    public function update(UpdateContentRequest $request, ContentItem $content, ContentWriter $writer, AssignmentService $assignments): RedirectResponse
    {
        if ($request->filled('brand_id')) {
            abort_unless($request->user()->canAccessBrand((int) $request->integer('brand_id')), 403);
        }

        $writer->update($content, $request->validated(), $request->user());

        if ($request->user()->can('assign', $content)) {
            $assignments->sync($content, $request->safe()->only([
                'owner_id', 'collaborator_ids',
            ]), $request->user());
        }

        return redirect()->route('content.show', $content)->with('success', 'Content brief saved.');
    }

    public function archive(Request $request, ContentItem $content, ContentWorkflow $workflow): RedirectResponse
    {
        $this->authorize('transition', $content);
        abort_unless($request->user()->hasPermission('content.archive'), 403);

        $workflow->transition($content, 'archived', $request->user(), $request->string('note')->toString() ?: null);

        return back()->with('success', 'Content archived.');
    }

    public function destroy(ContentItem $content): RedirectResponse
    {
        $this->authorize('delete', $content);
        $content->delete();

        return redirect()->route('content.index')->with('success', 'Content moved to trash.');
    }

    public function restore(int $content): RedirectResponse
    {
        $item = ContentItem::withTrashed()->findOrFail($content);
        $this->authorize('restore', $item);
        $item->restore();

        return redirect()->route('content.show', $item)->with('success', 'Content restored.');
    }

    public function export(Request $request, ContentQuery $query): StreamedResponse
    {
        $this->authorize('export', ContentItem::class);

        $items = $query->make($request->user(), $this->filters($request))
            ->with(['brand:id,name', 'campaign:id,name', 'status:id,name', 'owner:id,name', 'pillar:id,name', 'objective:id,name'])
            ->limit(5000)
            ->get();

        return response()->streamDownload(function () use ($items) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Reference', 'Title', 'Brand', 'Campaign', 'Status', 'Format', 'Priority', 'Owner',
                'Deadline', 'Pillar', 'Objective', 'Funnel', 'Distribution', 'Published at',
            ]);

            foreach ($items as $item) {
                fputcsv($handle, [
                    $item->reference_number,
                    $item->title,
                    $item->brand?->name,
                    $item->campaign?->name,
                    $item->status?->name,
                    $item->formatLabel(),
                    $item->priority,
                    $item->owner?->name,
                    $item->deadline?->toDateString(),
                    $item->pillar?->name,
                    $item->objective?->name,
                    $item->funnel_stage,
                    $item->distribution,
                    $item->published_at?->toDateTimeString(),
                ]);
            }

            fclose($handle);
        }, 'prime-z-content-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return $request->only([
            'q', 'brand_id', 'campaign_id', 'status', 'format', 'priority', 'distribution',
            'funnel_stage', 'pillar_id', 'objective_id', 'owner', 'owner_id', 'collaborator_id',
            'reviewer_id', 'platform_id', 'overdue', 'deadline_from', 'deadline_to',
            'publish_from', 'publish_to', 'sort', 'direction', 'trashed',
        ]);
    }
}
