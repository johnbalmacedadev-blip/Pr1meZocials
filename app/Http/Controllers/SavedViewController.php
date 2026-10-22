<?php

namespace App\Http\Controllers;

use App\Models\SavedView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SavedViewController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'module' => ['required', 'string', 'max:64'],
            'filters' => ['required', 'array'],
        ]);

        $request->user()->savedViews()->create($data);

        return back()->with('success', 'View saved.');
    }

    public function destroy(Request $request, SavedView $savedView): RedirectResponse
    {
        abort_unless($savedView->user_id === $request->user()->id, 403);
        $savedView->delete();

        return back()->with('success', 'Saved view removed.');
    }
}
