<?php

namespace App\Http\Controllers;

use App\Models\RolletSlip;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The exchange gift draw. The public page shows every name, always, and the server alone
 * decides what can be drawn, so nothing in the browser reveals which names are taken.
 * A drawn name is held for this browser while the person confirms it, so reloading
 * cannot draw a different name and nobody else can draw the same one.
 */
class RolletController extends Controller
{
    private const HOLD_MINUTES = 10;

    private const SESSION_TOKEN = 'rollet_draw';

    private const SESSION_EXCLUDE = 'rollet_exclude';

    public function index(Request $request): Response
    {
        return Inertia::render('Rollet/Index', [
            'names' => RolletSlip::query()->orderBy('id')->pluck('name'),
            'draw' => $this->currentDraw($request)?->name,
            'claimed' => $request->session()->get('rollet_claimed'),
            'allTaken' => ! RolletSlip::query()->whereNull('picked_at')->exists(),
        ]);
    }

    public function draw(Request $request): RedirectResponse
    {
        if ($this->currentDraw($request)) {
            return redirect()->route('rollet.index');
        }

        $free = RolletSlip::query()->free()->pluck('id')->shuffle();
        $exclude = $request->session()->pull(self::SESSION_EXCLUDE);
        if ($free->count() > 1) {
            $free = $free->reject(fn (int $id) => $id === $exclude)->values();
        }

        $token = Str::random(64);
        foreach ($free as $id) {
            // Two people can reach this at once: only the update that still finds it free wins.
            $won = RolletSlip::query()->whereKey($id)->free()->update([
                'draw_token' => $token,
                'held_until' => now()->addMinutes(self::HOLD_MINUTES),
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
        $data = $request->validate(['picker_name' => ['required', 'string', 'max:80']], [
            'picker_name.required' => 'Write your name.',
        ]);
        $picker = trim(preg_replace('/\s+/', ' ', $data['picker_name']));
        $spoken = mb_strtolower($picker);

        if ($picker === '') {
            throw ValidationException::withMessages(['picker_name' => 'Write your name.']);
        }

        $slip = $this->currentDraw($request);
        if (! $slip) {
            throw ValidationException::withMessages(['rollet' => 'Your pick has expired. Please press the button again.']);
        }

        $othersFree = RolletSlip::query()->free()->whereKeyNot($slip->id)->exists();
        if ($spoken === mb_strtolower($slip->name) && $othersFree) {
            // Nobody buys their own gift: put it back and draw another name.
            $slip->update(['draw_token' => null, 'held_until' => null]);
            $request->session()->forget(self::SESSION_TOKEN);
            $request->session()->put(self::SESSION_EXCLUDE, $slip->id);

            throw ValidationException::withMessages(['rollet' => 'You picked your own name! Press the button to pick again.']);
        }

        if (RolletSlip::query()->whereNotNull('picked_at')->whereRaw('LOWER(picked_by) = ?', [$spoken])->exists()) {
            throw ValidationException::withMessages(['picker_name' => $picker.' already has a name. If you are a different '.$picker.', add your last name.']);
        }

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

        return redirect()->route('rollet.index')->with('rollet_claimed', ['name' => $slip->name, 'picker' => $picker]);
    }

    public function results(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('rollet.view'), 403);

        $slips = RolletSlip::query()->orderBy('id')->get();

        return Inertia::render('Rollet/Results', [
            'results' => $slips->whereNotNull('picked_at')->sortBy('picked_at')->values()->map(fn (RolletSlip $slip) => [
                'id' => $slip->id,
                'name' => $slip->name,
                'picked_by' => $slip->picked_by,
                'picked_at' => $slip->picked_at?->toIso8601String(),
            ]),
            'remaining' => $slips->whereNull('picked_at')->pluck('name')->values(),
            'total' => $slips->count(),
            'canRemove' => $request->user()->hasPermission('rollet.delete'),
        ]);
    }

    /** Puts a name back in the draw, for a mistake or someone who picked twice. */
    public function destroy(Request $request, RolletSlip $slip): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('rollet.delete'), 403);

        $slip->update(['picked_by' => null, 'picked_at' => null, 'draw_token' => null, 'held_until' => null]);

        return back()->with('success', $slip->name.' is back in the draw.');
    }

    private function currentDraw(Request $request): ?RolletSlip
    {
        $token = $request->session()->get(self::SESSION_TOKEN);

        return is_string($token) && $token !== ''
            ? RolletSlip::query()->where('draw_token', $token)->whereNull('picked_at')->first()
            : null;
    }
}
