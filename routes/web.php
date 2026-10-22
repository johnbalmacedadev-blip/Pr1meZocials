<?php

use App\Http\Controllers\AssetController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ContentItemController;
use App\Http\Controllers\ContentWorkflowController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\PlatformVersionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SavedViewController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/', fn () => redirect()->route('dashboard'))->name('home');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/search', [SearchController::class, 'index'])->name('search');

    Route::get('/content', [ContentItemController::class, 'index'])->name('content.index');
    Route::get('/content/create', [ContentItemController::class, 'create'])->name('content.create');
    Route::post('/content', [ContentItemController::class, 'store'])->name('content.store');
    Route::get('/content/export', [ContentItemController::class, 'export'])->middleware('throttle:exports')->name('content.export');
    Route::get('/content/{content}', [ContentItemController::class, 'show'])->name('content.show');
    Route::get('/content/{content}/edit', [ContentItemController::class, 'edit'])->name('content.edit');
    Route::put('/content/{content}', [ContentItemController::class, 'update'])->name('content.update');
    Route::post('/content/{content}/archive', [ContentItemController::class, 'archive'])->name('content.archive');
    Route::delete('/content/{content}', [ContentItemController::class, 'destroy'])->name('content.destroy');
    Route::post('/content/{content}/restore', [ContentItemController::class, 'restore'])->name('content.restore');

    Route::post('/content/{content}/transition', [ContentWorkflowController::class, 'transition'])->name('content.transition');
    Route::put('/content/{content}/assignment', [ContentWorkflowController::class, 'assign'])->name('content.assign');
    Route::post('/content/{content}/reviews', [ContentWorkflowController::class, 'review'])->name('content.reviews.store');
    Route::post('/content/{content}/comments', [ContentWorkflowController::class, 'comment'])->name('content.comments.store');

    Route::post('/content/{content}/assets', [AssetController::class, 'store'])->middleware('throttle:uploads')->name('content.assets.store');
    Route::post('/content/{content}/versions', [PlatformVersionController::class, 'store'])->name('content.versions.store');

    Route::put('/versions/{version}', [PlatformVersionController::class, 'update'])->name('versions.update');
    Route::delete('/versions/{version}', [PlatformVersionController::class, 'destroy'])->name('versions.destroy');
    Route::post('/versions/{version}/reschedule', [PlatformVersionController::class, 'reschedule'])->name('versions.reschedule');
    Route::post('/versions/{version}/publish', [PlatformVersionController::class, 'publish'])->name('versions.publish');

    Route::get('/assets', [AssetController::class, 'index'])->name('assets.index');
    Route::get('/assets/{asset}/download', [AssetController::class, 'download'])->name('assets.download');
    Route::get('/assets/{asset}/preview', [AssetController::class, 'preview'])->name('assets.preview');
    Route::delete('/assets/{asset}', [AssetController::class, 'destroy'])->name('assets.destroy');

    Route::get('/board', [ContentWorkflowController::class, 'board'])->name('board');
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar');

    Route::get('/campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
    Route::get('/campaigns/create', [CampaignController::class, 'create'])->name('campaigns.create');
    Route::post('/campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
    Route::get('/campaigns/{campaign}', [CampaignController::class, 'show'])->name('campaigns.show');
    Route::get('/campaigns/{campaign}/edit', [CampaignController::class, 'edit'])->name('campaigns.edit');
    Route::put('/campaigns/{campaign}', [CampaignController::class, 'update'])->name('campaigns.update');
    Route::delete('/campaigns/{campaign}', [CampaignController::class, 'destroy'])->name('campaigns.destroy');

    Route::get('/team', [TeamController::class, 'index'])->name('team.index');
    Route::get('/team/{user}', [TeamController::class, 'show'])->name('team.show');

    Route::get('/performance', [PerformanceController::class, 'index'])->name('performance.index');
    Route::post('/performance', [PerformanceController::class, 'store'])->name('performance.store');
    Route::delete('/performance/{report}', [PerformanceController::class, 'destroy'])->name('performance.destroy');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');

    Route::post('/saved-views', [SavedViewController::class, 'store'])->name('saved-views.store');
    Route::delete('/saved-views/{savedView}', [SavedViewController::class, 'destroy'])->name('saved-views.destroy');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::post('/settings/users', [SettingsController::class, 'storeUser'])->name('settings.users.store');
    Route::put('/settings/users/{user}', [SettingsController::class, 'updateUser'])->name('settings.users.update');
    Route::post('/settings/brands', [SettingsController::class, 'storeBrand'])->name('settings.brands.store');
    Route::put('/settings/brands/{brand}', [SettingsController::class, 'updateBrand'])->name('settings.brands.update');
    Route::post('/settings/platforms', [SettingsController::class, 'storePlatform'])->name('settings.platforms.store');
    Route::put('/settings/platforms/{platform}', [SettingsController::class, 'updatePlatform'])->name('settings.platforms.update');
    Route::put('/settings/workflow/{status}', [SettingsController::class, 'updateWorkflow'])->name('settings.workflow.update');
    Route::post('/settings/pillars', [SettingsController::class, 'storePillar'])->name('settings.pillars.store');
    Route::post('/settings/objectives', [SettingsController::class, 'storeObjective'])->name('settings.objectives.store');
    Route::post('/settings/segments', [SettingsController::class, 'storeSegment'])->name('settings.segments.store');
    Route::post('/settings/tags', [SettingsController::class, 'storeTag'])->name('settings.tags.store');
    Route::put('/settings/general', [SettingsController::class, 'updateSettings'])->name('settings.general.update');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])->name('profile.password');
});
