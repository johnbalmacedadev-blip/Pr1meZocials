<?php

namespace App\Http\Controllers;

use App\Models\RolletSlip;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The exchange gift draw. People say who they are first, then the server draws for them,
 * leaving out their own name. The public page shows every name, always, and nothing in
 * the browser reveals which names are taken. A drawn name is held for this browser while
 * the person confirms it, so reloading cannot draw a different one and nobody else can
 * draw the same one.
 */
class RolletController extends Controller
{
    private const HOLD_MINUTES = 10;

    private const SESSION_TOKEN = 'rollet_draw';

    public function index(Request $request): Response
    {
        $labels = RolletSlip::labels();
        $draw = $this->currentDraw($request);
        $busy = RolletSlip::busyPickerIds();

        return Inertia::render('Rollet/Index', [
            'names' => RolletSlip::query()->orderBy('id')->pluck('name'),
            // Who still has to pick. This says nothing about which names are taken.
            'people' => collect($labels)
                ->reject(fn (string $label, int $id) => in_array($id, $busy, true))
                ->map(fn (string $label, int $id) => ['id' => $id, 'label' => $label])
                ->values(),
            'draw' => $draw ? ['name' => $labels[$draw->id], 'picker' => $labels[$draw->picker_slip_id] ?? null] : null,
            'claimed' => $request->session()->get('rollet_claimed'),
            'allTaken' => ! RolletSlip::query()->whereNull('picked_at')->exists(),
        ]);
    }

    public function draw(Request $request): RedirectResponse
    {
        if ($this->currentDraw($request)) {
            return redirect()->route('rollet.index');
        }

        $data = $request->validate(['picker_id' => ['required', 'integer']], ['picker_id.required' => 'Choose your name first.']);
        $picker = RolletSlip::query()->find($data['picker_id']);
        if (! $picker) {
            throw ValidationException::withMessages(['rollet' => 'Choose your name from the list.']);
        }

        $label = RolletSlip::labels()[$picker->id];
        if (RolletSlip::query()->where('picker_slip_id', $picker->id)->whereNotNull('picked_at')->exists()) {
            throw ValidationException::withMessages(['rollet' => $label.' has already picked a name.']);
        }
        if (in_array($picker->id, RolletSlip::busyPickerIds(), true)) {
            throw ValidationException::withMessages(['rollet' => $label.' is picking on another device right now. Please try again in a few minutes.']);
        }

        $token = Str::random(64);
        foreach ($this->candidates($picker)->shuffle() as $id) {
            // Two people can reach this at once: only the update that still finds it free wins.
            $won = RolletSlip::query()->whereKey($id)->free()->update([
                'draw_token' => $token,
                'held_until' => now()->addMinutes(self::HOLD_MINUTES),
                'picker_slip_id' => $picker->id,
            ]);

            if ($won === 1) {
                $request->session()->put(self::SESSION_TOKEN, $token);

                return redirect()->route('rollet.index');
            }
        }

        throw ValidationException::withMessages(['rollet' => RolletSlip::query()->whereNull('picked_at')->exists()
            ? 'Someone is confirming the last names right now. Please try again in a few minutes.'
            : 'All names have been picked.']);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $slip = $this->currentDraw($request);
        if (! $slip) {
            throw ValidationException::withMessages(['rollet' => 'Your pick has expired. Please press the button again.']);
        }

        $labels = RolletSlip::labels();
        $picker = $labels[$slip->picker_slip_id] ?? 'Someone';

        $won = RolletSlip::query()->whereKey($slip->id)->where('draw_token', $slip->draw_token)->whereNull('picked_at')->update([
            'picked_by' => $picker,
            'picked_at' => now(),
            'draw_token' => null,
            'held_until' => null,
        ]);
        $request->session()->forget(self::SESSION_TOKEN);

        if ($won !== 1) {
            throw ValidationException::withMessages(['rollet' => 'Your pick has expired. Please press the button again.']);
        }

        return redirect()->route('rollet.index')->with('rollet_claimed', ['name' => $labels[$slip->id], 'picker' => $picker]);
    }

    public function results(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('rollet.view'), 403);

        $slips = RolletSlip::query()->orderBy('id')->get();
        $labels = RolletSlip::labels();
        $donePickers = $slips->whereNotNull('picked_at')->pluck('picker_slip_id')->all();

        return Inertia::render('Rollet/Results', [
            'results' => $slips->whereNotNull('picked_at')->sortBy('picked_at')->values()->map(fn (RolletSlip $slip) => [
                'id' => $slip->id,
                'name' => $labels[$slip->id],
                'picked_by' => $slip->picked_by,
                'picked_at' => $slip->picked_at?->toIso8601String(),
            ]),
            'remaining' => $slips->whereNull('picked_at')->map(fn (RolletSlip $slip) => $labels[$slip->id])->values(),
            'waiting' => $slips->reject(fn (RolletSlip $slip) => in_array($slip->id, $donePickers, true))->map(fn (RolletSlip $slip) => $labels[$slip->id])->values(),
            'total' => $slips->count(),
            'canRemove' => $request->user()->hasPermission('rollet.delete'),
        ]);
    }

    /** Puts a name back in the draw and lets its picker pick again, for a mistake. */
    public function destroy(Request $request, RolletSlip $slip): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('rollet.delete'), 403);

        $slip->update(['picked_by' => null, 'picked_at' => null, 'draw_token' => null, 'held_until' => null, 'picker_slip_id' => null]);

        return back()->with('success', RolletSlip::labels()[$slip->id].' is back in the draw.');
    }

    /**
     * Names this person may draw: never their own. When two names are left, the other person
     * must not be left with only their own name, so this person draws that name.
     *
     * @return Collection<int, int>
     */
    private function candidates(RolletSlip $picker): Collection
    {
        $free = RolletSlip::query()->free()->pluck('id');
        $othersWaiting = RolletSlip::query()
            ->whereKeyNot($picker->id)
            ->whereNotIn('id', RolletSlip::busyPickerIds())
            ->pluck('id');

        if ($free->count() === 2 && $othersWaiting->count() === 1 && $free->contains($othersWaiting->first())) {
            return collect([$othersWaiting->first()]);
        }

        return $free->reject(fn (int $id) => $id === $picker->id)->values();
    }

    private function currentDraw(Request $request): ?RolletSlip
    {
        $token = $request->session()->get(self::SESSION_TOKEN);

        return is_string($token) && $token !== ''
            ? RolletSlip::query()->where('draw_token', $token)->whereNull('picked_at')->first()
            : null;
    }
}
