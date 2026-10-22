<?php

namespace App\Http\Controllers;

use App\Services\ContentQuery;
use App\Support\ContentPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function index(Request $request, ContentQuery $query, ContentPresenter $presenter): Response
    {
        $term = trim($request->string('q')->toString());
        $items = collect();

        if ($term !== '') {
            $items = $query->make($request->user(), ['q' => $term])
                ->with(['brand:id,name', 'campaign:id,name', 'status:id,name,slug,color', 'owner:id,name', 'platformVersions.platform:id,name,slug,color'])
                ->limit(30)
                ->get();
        }

        return Inertia::render('Search/Index', [
            'q' => $term,
            'results' => $items->map(fn ($item) => $presenter->summary($item))->values(),
        ]);
    }
}
