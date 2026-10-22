<?php

namespace App\Http\Controllers;

use App\Services\DashboardMetrics;
use App\Support\FormOptions;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardMetrics $metrics): Response
    {
        $filters = $request->only(['from', 'to', 'brand_id', 'campaign_id', 'platform_id', 'user_id']);

        return Inertia::render('Dashboard', [
            'metrics' => $metrics->build($request->user(), $filters),
            'filters' => $filters,
            'options' => FormOptions::for($request->user()),
        ]);
    }
}
