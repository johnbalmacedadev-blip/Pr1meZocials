<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\ContentItem;
use App\Support\ContentPresenter;
use App\Support\FormOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Campaign::class);

        $campaigns = Campaign::query()
            ->whereIn('brand_id', $request->user()->accessibleBrandIds() ?: [0])
            ->with(['brand:id,name', 'owner:id,name'])
            ->withCount('contentItems')
            ->when($request->filled('brand_id'), fn ($query) => $query->where('brand_id', $request->integer('brand_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Campaigns/Index', [
            'campaigns' => $campaigns,
            'filters' => $request->only(['brand_id', 'status']),
            'options' => FormOptions::for($request->user()),
            'canManage' => $request->user()->hasPermission('campaigns.manage'),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Campaign::class);

        return Inertia::render('Campaigns/Form', [
            'campaign' => null,
            'options' => FormOptions::for($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Campaign::class);
        $data = $this->validated($request);
        abort_unless($request->user()->canAccessBrand((int) $data['brand_id']), 403);

        $campaign = Campaign::query()->create([
            ...$data,
            'slug' => $this->slug($data['name'], (int) $data['brand_id']),
        ]);
        $campaign->members()->sync($data['member_ids'] ?? []);

        return redirect()->route('campaigns.show', $campaign)->with('success', 'Campaign created.');
    }

    public function show(Request $request, Campaign $campaign, ContentPresenter $presenter): Response
    {
        $this->authorize('view', $campaign);

        $items = ContentItem::query()
            ->visibleTo($request->user())
            ->where('campaign_id', $campaign->id)
            ->with(['brand:id,name', 'campaign:id,name', 'status:id,name,slug,color', 'owner:id,name', 'platformVersions.platform:id,name,slug,color'])
            ->latest('updated_at')
            ->limit(50)
            ->get();

        return Inertia::render('Campaigns/Show', [
            'campaign' => $this->payload($campaign),
            'content' => $items->map(fn (ContentItem $item) => $presenter->summary($item))->values(),
            'canManage' => $request->user()->can('update', $campaign),
        ]);
    }

    public function edit(Request $request, Campaign $campaign): Response
    {
        $this->authorize('update', $campaign);

        return Inertia::render('Campaigns/Form', [
            'campaign' => $this->payload($campaign),
            'options' => FormOptions::for($request->user()),
        ]);
    }

    public function update(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->authorize('update', $campaign);
        $data = $this->validated($request, $campaign);
        abort_unless($request->user()->canAccessBrand((int) $data['brand_id']), 403);

        $campaign->fill(collect($data)->except('member_ids')->all());

        if ($campaign->isDirty('name') || $campaign->isDirty('brand_id')) {
            $campaign->slug = $this->slug($campaign->name, (int) $campaign->brand_id, $campaign->id);
        }

        $campaign->save();
        $campaign->members()->sync($data['member_ids'] ?? []);

        return redirect()->route('campaigns.show', $campaign)->with('success', 'Campaign updated.');
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        $this->authorize('delete', $campaign);
        $campaign->delete();

        return redirect()->route('campaigns.index')->with('success', 'Campaign archived.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Campaign $campaign = null): array
    {
        $data = $request->validate([
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'objective' => ['nullable', 'string', 'max:5000'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(['planning', 'active', 'paused', 'completed'])],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'exists:users,id'],
        ]);

        foreach (['start_date', 'end_date', 'budget', 'owner_id', 'description', 'objective'] as $field) {
            if (($data[$field] ?? null) === '') {
                $data[$field] = null;
            }
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Campaign $campaign): array
    {
        $campaign->load(['brand:id,name', 'owner:id,name', 'members:id,name']);

        return [
            'id' => $campaign->id,
            'brand_id' => $campaign->brand_id,
            'name' => $campaign->name,
            'description' => $campaign->description,
            'objective' => $campaign->objective,
            'start_date' => $campaign->start_date?->toDateString(),
            'end_date' => $campaign->end_date?->toDateString(),
            'status' => $campaign->status,
            'budget' => $campaign->budget,
            'owner_id' => $campaign->owner_id,
            'brand' => $campaign->brand?->only(['id', 'name']),
            'owner' => $campaign->owner?->only(['id', 'name']),
            'member_ids' => $campaign->members->pluck('id')->all(),
            'members' => $campaign->members->map->only(['id', 'name'])->values(),
        ];
    }

    private function slug(string $name, int $brandId, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'campaign';
        $slug = $base;
        $suffix = 2;

        while (Campaign::withTrashed()
            ->where('brand_id', $brandId)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
