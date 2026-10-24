<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class LegalController extends Controller
{
    public function privacy(): Response
    {
        return Inertia::render('Legal/Privacy', [
            'appName' => config('app.name', 'Prime Z'),
            'appUrl' => rtrim((string) config('app.url'), '/'),
            'updatedAt' => '2026-10-24',
        ]);
    }

    public function terms(): Response
    {
        return Inertia::render('Legal/Terms', [
            'appName' => config('app.name', 'Prime Z'),
            'appUrl' => rtrim((string) config('app.url'), '/'),
            'updatedAt' => '2026-10-24',
        ]);
    }
}
