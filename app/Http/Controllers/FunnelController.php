<?php

namespace App\Http\Controllers;

use App\Models\Funnel;
use App\Models\FunnelOrder;
use App\Support\FormOptions;
use App\Support\FunnelPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FunnelController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Funnel::class);

        $funnels = Funnel::query()
            ->whereIn('brand_id', $request->user()->accessibleBrandIds() ?: [0])
            ->with('brand:id,name')
            ->withCount([
                'orders',
                'orders as new_orders_count' => fn ($query) => $query->where('status', 'new'),
            ])
            ->withSum(['orders as sales_total' => fn ($query) => $query->where('status', '!=', 'cancelled')], 'total')
            ->latest()
            ->paginate(25)
            ->through(fn (Funnel $funnel) => [
                'id' => $funnel->id,
                'name' => $funnel->name,
                'product_name' => $funnel->product_name,
                'brand' => $funnel->brand?->name,
                'status' => $funnel->status,
                'public_url' => $funnel->publicUrl(),
                'short_link' => $funnel->short_link,
                'price' => FunnelPage::money($funnel->unit_price),
                'orders_count' => $funnel->orders_count,
                'new_orders_count' => $funnel->new_orders_count,
                'sales_total' => FunnelPage::money($funnel->sales_total ?? 0),
                'created_at' => $funnel->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Funnels/Index', [
            'funnels' => $funnels,
            'canCreate' => $request->user()->can('create', Funnel::class),
            'canUpdate' => $request->user()->hasPermission('funnels.update'),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Funnel::class);

        return Inertia::render('Funnels/Form', [
            'funnel' => null,
            'options' => $this->options($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Funnel::class);

        $funnel = Funnel::query()->create([
            ...$this->validated($request),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('funnels.show', $funnel)->with('success', 'Funnel created. Its link is ready to share.');
    }

    public function show(Request $request, Funnel $funnel): Response
    {
        $this->authorize('view', $funnel);

        $status = $request->string('status')->toString();
        $search = trim($request->string('q')->toString());

        $orders = $funnel->orders()
            ->when(in_array($status, FunnelOrder::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('full_name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")))
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (FunnelOrder $order) => [
                'id' => $order->id,
                'reference' => $order->reference(),
                'full_name' => $order->full_name,
                'phone' => $order->phone,
                'address' => $order->shippingAddress(),
                'quantity' => $order->quantity,
                'total' => FunnelPage::money($order->total),
                'status' => $order->status,
                'source' => $this->sourceLabel($order),
                'created_at' => $order->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Funnels/Show', [
            'funnel' => [
                'id' => $funnel->id,
                'name' => $funnel->name,
                'product_name' => $funnel->product_name,
                'status' => $funnel->status,
                'public_url' => $funnel->publicUrl(),
                'short_link' => $funnel->short_link,
                'price' => FunnelPage::money($funnel->unit_price),
                'compare_price' => FunnelPage::money($funnel->compare_at_price),
            ],
            'orders' => $orders,
            'filters' => ['status' => $status, 'q' => $search],
            'statuses' => FunnelOrder::STATUSES,
            'canManage' => $request->user()->can('update', $funnel),
            'canDelete' => $request->user()->can('delete', $funnel),
        ]);
    }

    public function edit(Request $request, Funnel $funnel): Response
    {
        $this->authorize('update', $funnel);

        return Inertia::render('Funnels/Form', [
            'funnel' => $funnel->only(['id', 'brand_id', 'name', 'product_name', 'slug', 'short_link', 'template', 'status', 'unit_price', 'compare_at_price']),
            'options' => $this->options($request),
        ]);
    }

    public function update(Request $request, Funnel $funnel): RedirectResponse
    {
        $this->authorize('update', $funnel);

        $funnel->update($this->validated($request, $funnel));

        return redirect()->route('funnels.show', $funnel)->with('success', 'Funnel updated.');
    }

    public function destroy(Funnel $funnel): RedirectResponse
    {
        $this->authorize('delete', $funnel);

        $funnel->delete();

        return redirect()->route('funnels.index')->with('success', 'Funnel removed. Its link no longer works.');
    }

    public function updateOrder(Request $request, Funnel $funnel, FunnelOrder $order): RedirectResponse
    {
        $this->authorize('update', $funnel);
        abort_unless($order->funnel_id === $funnel->id, 404);

        $data = $request->validate(['status' => ['required', Rule::in(FunnelOrder::STATUSES)]]);
        $order->update($data);

        return back()->with('success', $order->reference().' marked as '.$data['status'].'.');
    }

    public function export(Funnel $funnel): StreamedResponse
    {
        $this->authorize('view', $funnel);

        return response()->streamDownload(function () use ($funnel) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Order', 'Date', 'Status', 'Full name', 'Mobile number', 'Address', 'Barangay', 'City', 'Province',
                'Quantity', 'Unit price', 'Total', 'Source',
            ]);

            $funnel->orders()->latest()->chunk(500, function ($orders) use ($handle) {
                foreach ($orders as $order) {
                    fputcsv($handle, array_map($this->safeCell(...), [
                        $order->reference(),
                        $order->created_at?->toDateTimeString(),
                        $order->status,
                        $order->full_name,
                        $order->phone,
                        $order->address,
                        $order->barangay,
                        $order->city,
                        $order->province,
                        $order->quantity,
                        $order->unit_price,
                        $order->total,
                        $this->sourceLabel($order),
                    ]));
                }
            });

            fclose($handle);
        }, $funnel->slug.'-orders.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Customers type these values, so stop a spreadsheet running them as formulas.
     */
    private function safeCell(mixed $value): mixed
    {
        return is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    private function sourceLabel(FunnelOrder $order): ?string
    {
        $source = $order->source ?? [];

        if (! empty($source['utm_source'])) {
            return collect([$source['utm_source'], $source['utm_campaign'] ?? null])->filter()->implode(' / ');
        }

        return match (true) {
            ! empty($source['fbclid']) => 'Facebook',
            ! empty($source['ttclid']) => 'TikTok',
            ! empty($source['gclid']) => 'Google',
            ! empty($source['referrer']) => parse_url($source['referrer'], PHP_URL_HOST) ?: null,
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Funnel $funnel = null): array
    {
        // People often paste a short link without https://, as in bit.ly/abc123.
        $shortLink = trim((string) $request->input('short_link'));
        if ($shortLink !== '' && ! preg_match('#^[a-z][a-z0-9+.-]*:#i', $shortLink)) {
            $shortLink = 'https://'.$shortLink;
        }
        $request->merge(['short_link' => $shortLink === '' ? null : $shortLink]);

        $data = $request->validate([
            'brand_id' => ['required', 'integer', Rule::in($request->user()->accessibleBrandIds())],
            'name' => ['required', 'string', 'max:150'],
            'product_name' => ['required', 'string', 'max:150'],
            // Deleted funnels keep their slug so an old ad link can never open a different product.
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('funnels', 'slug')->ignore($funnel?->id)],
            'short_link' => ['nullable', 'string', 'max:255', 'url:http,https'],
            'template' => ['required', Rule::in(array_keys(FunnelPage::templates()))],
            'status' => ['required', Rule::in(Funnel::STATUSES)],
            'unit_price' => ['required', 'numeric', 'min:1', 'max:9999999'],
            'compare_at_price' => ['required', 'numeric', 'gt:unit_price', 'max:9999999'],
        ], [
            'slug.regex' => 'Use lowercase letters, numbers and dashes only.',
            'short_link.url' => 'Enter a full link, for example https://bit.ly/abc123.',
            'compare_at_price.gt' => 'The regular price must be higher than the promo price.',
        ]);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function options(Request $request): array
    {
        return [
            'brands' => $request->user()->accessibleBrands()->map->only(['id', 'name'])->values(),
            'templates' => collect(FunnelPage::templates())->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])->values(),
            'statuses' => Funnel::STATUSES,
        ];
    }
}
