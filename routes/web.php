<?php

use App\Http\Controllers\AssetController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ContentItemController;
use App\Http\Controllers\ContentWorkflowController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\InspirationController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationPreferenceController;
use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\PlatformVersionController;
use App\Http\Controllers\PostingScheduleController;
use App\Http\Controllers\SocialPostingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ResourceLinkController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\SavedViewController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TeamInviteController;
use App\Http\Controllers\TaskNoteController;
use Illuminate\Support\Facades\Route;

Route::get('/privacy', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('/terms', [LegalController::class, 'terms'])->name('legal.terms');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
    Route::get('/join/{token}', [TeamInviteController::class, 'show'])->name('join.show');
    Route::post('/join/{token}', [TeamInviteController::class, 'register'])->middleware('throttle:10,1')->name('join.register');
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
    Route::post('/content/bulk-destroy', [ContentItemController::class, 'bulkDestroy'])->name('content.bulk-destroy');
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

    // /creative-assets (not /resources) — Apache would otherwise serve the Laravel resources/ folder.
    Route::get('/creative-assets', [ResourceLinkController::class, 'index'])->name('resources.index');
    Route::get('/creative-assets/{resourceLink}/files', [ResourceLinkController::class, 'files'])->name('resources.files');
    Route::get('/creative-assets/{resourceLink}/files/{file}/preview', [ResourceLinkController::class, 'preview'])->where('file', '[A-Za-z0-9_-]+')->name('resources.files.preview');
    Route::get('/creative-assets/{resourceLink}/files/{file}/download', [ResourceLinkController::class, 'download'])->where('file', '[A-Za-z0-9_-]+')->name('resources.files.download');
    Route::post('/creative-assets', [ResourceLinkController::class, 'store'])->name('resources.store');
    Route::put('/creative-assets/{resourceLink}', [ResourceLinkController::class, 'update'])->name('resources.update');
    Route::delete('/creative-assets/{resourceLink}', [ResourceLinkController::class, 'destroy'])->name('resources.destroy');

    Route::get('/inspiration', [InspirationController::class, 'index'])->name('inspiration.index');
    Route::post('/inspiration', [InspirationController::class, 'store'])->name('inspiration.store');
    Route::put('/inspiration/{inspiration}', [InspirationController::class, 'update'])->name('inspiration.update');
    Route::post('/inspiration/{inspiration}/archive', [InspirationController::class, 'archive'])->name('inspiration.archive');
    Route::delete('/inspiration/{inspiration}', [InspirationController::class, 'destroy'])->name('inspiration.destroy');

    Route::get('/board', [ContentWorkflowController::class, 'board'])->name('board');
    Route::post('/task-notes', [TaskNoteController::class, 'store'])->name('task-notes.store');
    Route::post('/task-notes/{taskNote}/complete', [TaskNoteController::class, 'complete'])->name('task-notes.complete');
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar');
    Route::get('/events', [EventController::class, 'index'])->name('events.index');
    Route::get('/events/create', [EventController::class, 'create'])->name('events.create');
    Route::post('/events', [EventController::class, 'store'])->middleware('throttle:uploads')->name('events.store');
    Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
    Route::get('/events/{event}/edit', [EventController::class, 'edit'])->name('events.edit');
    Route::post('/events/{event}', [EventController::class, 'update'])->middleware('throttle:uploads')->name('events.update');
    Route::delete('/events/{event}', [EventController::class, 'destroy'])->name('events.destroy');
    Route::post('/event-occurrences/{occurrence}', [EventController::class, 'updateOccurrence'])->middleware('throttle:uploads')->name('events.occurrences.update');
    Route::get('/event-occurrences/{occurrence}/media/{type}', [EventController::class, 'media'])->where('type', 'poster|video')->name('events.occurrences.media');
    Route::get('/posting-schedule', [PostingScheduleController::class, 'index'])->name('posting-schedule');
    Route::post('/posting-schedule', [PostingScheduleController::class, 'store'])->name('posting-schedule.store');
    Route::delete('/posting-schedule/{entry}', [PostingScheduleController::class, 'destroy'])->name('posting-schedule.destroy');

    Route::get('/campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
    Route::get('/campaigns/create', [CampaignController::class, 'create'])->name('campaigns.create');
    Route::post('/campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
    Route::get('/campaigns/{campaign}', [CampaignController::class, 'show'])->name('campaigns.show');
    Route::get('/campaigns/{campaign}/edit', [CampaignController::class, 'edit'])->name('campaigns.edit');
    Route::put('/campaigns/{campaign}', [CampaignController::class, 'update'])->name('campaigns.update');
    Route::delete('/campaigns/{campaign}', [CampaignController::class, 'destroy'])->name('campaigns.destroy');

    Route::get('/team', [TeamController::class, 'index'])->name('team.index');
    Route::post('/team', [TeamController::class, 'store'])->name('team.store');
    Route::put('/team/roles/{role}/permissions', [RolePermissionController::class, 'update'])->name('team.roles.permissions');
    Route::post('/team/permission-presets', [RolePermissionController::class, 'storePreset'])->name('team.permission-presets.store');
    Route::post('/team/roles/{role}/presets/{preset}', [RolePermissionController::class, 'applyPreset'])->name('team.roles.presets.apply');
    Route::delete('/team/permission-presets/{preset}', [RolePermissionController::class, 'destroyPreset'])->name('team.permission-presets.destroy');
    Route::put('/team/{user}', [TeamController::class, 'update'])->name('team.update');
    Route::delete('/team/{user}', [TeamController::class, 'destroy'])->name('team.destroy');
    Route::get('/team/{user}', [TeamController::class, 'show'])->name('team.show');

    Route::get('/performance', [PerformanceController::class, 'index'])->name('performance.index');
    Route::post('/performance', [PerformanceController::class, 'store'])->name('performance.store');
    Route::delete('/performance/{report}', [PerformanceController::class, 'destroy'])->name('performance.destroy');

    Route::get('/posting', [SocialPostingController::class, 'index'])->name('posting.index');
    Route::post('/posting', [SocialPostingController::class, 'store'])->middleware('throttle:uploads')->name('posting.store');
    Route::post('/posting/facebook/credentials', [SocialPostingController::class, 'saveMetaCredentials'])->name('posting.facebook.credentials');
    Route::get('/posting/facebook/redirect', [SocialPostingController::class, 'redirectToFacebook'])->name('posting.facebook.redirect');
    Route::get('/posting/facebook/callback', [SocialPostingController::class, 'handleFacebookCallback'])->name('posting.facebook.callback');
    Route::post('/posting/facebook/refresh', [SocialPostingController::class, 'refreshPages'])->name('posting.facebook.refresh');
    Route::delete('/posting/facebook', [SocialPostingController::class, 'destroyMeta'])->name('posting.facebook.destroy');
    Route::post('/posting/tiktok/credentials', [SocialPostingController::class, 'saveTikTokCredentials'])->name('posting.tiktok.credentials');
    Route::get('/posting/tiktok/redirect', [SocialPostingController::class, 'redirectToTikTok'])->name('posting.tiktok.redirect');
    Route::get('/posting/tiktok/callback', [SocialPostingController::class, 'handleTikTokCallback'])->name('posting.tiktok.callback');
    Route::delete('/posting/tiktok/{account}', [SocialPostingController::class, 'destroyTikTokAccount'])->name('posting.tiktok.destroy');
    Route::patch('/posting/pages/{account}', [SocialPostingController::class, 'updatePage'])->name('posting.pages.update');
    Route::post('/posting/{post}/sync', [SocialPostingController::class, 'sync'])->name('posting.sync');
    Route::put('/posting/{post}', [SocialPostingController::class, 'update'])->name('posting.update');
    Route::delete('/posting/{post}', [SocialPostingController::class, 'destroy'])->name('posting.destroy');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');

    Route::post('/saved-views', [SavedViewController::class, 'store'])->name('saved-views.store');
    Route::delete('/saved-views/{savedView}', [SavedViewController::class, 'destroy'])->name('saved-views.destroy');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::post('/settings/users', [SettingsController::class, 'storeUser'])->name('settings.users.store');
    Route::put('/settings/users/{user}', [SettingsController::class, 'updateUser'])->name('settings.users.update');
    Route::post('/settings/invites', [TeamInviteController::class, 'store'])->name('settings.invites.store');
    Route::delete('/settings/invites/{invite}', [TeamInviteController::class, 'destroy'])->name('settings.invites.destroy');
    Route::post('/settings/brands', [SettingsController::class, 'storeBrand'])->name('settings.brands.store');
    Route::put('/settings/brands/{brand}', [SettingsController::class, 'updateBrand'])->name('settings.brands.update');
    Route::delete('/settings/brands/{brand}', [SettingsController::class, 'destroyBrand'])->name('settings.brands.destroy');
    Route::post('/settings/platforms', [SettingsController::class, 'storePlatform'])->name('settings.platforms.store');
    Route::put('/settings/platforms/{platform}', [SettingsController::class, 'updatePlatform'])->name('settings.platforms.update');
    Route::delete('/settings/platforms/{platform}', [SettingsController::class, 'destroyPlatform'])->name('settings.platforms.destroy');
    Route::put('/settings/workflow/{status}', [SettingsController::class, 'updateWorkflow'])->name('settings.workflow.update');
    Route::post('/settings/pillars', [SettingsController::class, 'storePillar'])->name('settings.pillars.store');
    Route::put('/settings/pillars/{pillar}', [SettingsController::class, 'updatePillar'])->name('settings.pillars.update');
    Route::delete('/settings/pillars/{pillar}', [SettingsController::class, 'destroyPillar'])->name('settings.pillars.destroy');
    Route::post('/settings/objectives', [SettingsController::class, 'storeObjective'])->name('settings.objectives.store');
    Route::put('/settings/objectives/{objective}', [SettingsController::class, 'updateObjective'])->name('settings.objectives.update');
    Route::delete('/settings/objectives/{objective}', [SettingsController::class, 'destroyObjective'])->name('settings.objectives.destroy');
    Route::post('/settings/segments', [SettingsController::class, 'storeSegment'])->name('settings.segments.store');
    Route::put('/settings/segments/{segment}', [SettingsController::class, 'updateSegment'])->name('settings.segments.update');
    Route::delete('/settings/segments/{segment}', [SettingsController::class, 'destroySegment'])->name('settings.segments.destroy');
    Route::post('/settings/tags', [SettingsController::class, 'storeTag'])->name('settings.tags.store');
    Route::put('/settings/tags/{tag}', [SettingsController::class, 'updateTag'])->name('settings.tags.update');
    Route::delete('/settings/tags/{tag}', [SettingsController::class, 'destroyTag'])->name('settings.tags.destroy');
    Route::put('/settings/general', [SettingsController::class, 'updateSettings'])->name('settings.general.update');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])->name('profile.password');
    Route::put('/notification-preferences', [NotificationPreferenceController::class, 'update'])->name('notification-preferences.update');
    Route::post('/notification-preferences/test', [NotificationPreferenceController::class, 'test'])->middleware('throttle:6,1')->name('notification-preferences.test');

    Route::get('/messenger', [ChatController::class, 'index'])->name('messenger');
    Route::post('/chat/sync', [ChatController::class, 'sync'])->middleware('throttle:120,1')->name('chat.sync');
    Route::post('/chat/direct/{user}', [ChatController::class, 'direct'])->name('chat.direct');
    Route::post('/chat/conversations/{conversation}/messages', [ChatController::class, 'store'])->middleware('throttle:60,1')->name('chat.messages.store');
    Route::get('/chat/conversations/{conversation}/messages', [ChatController::class, 'older'])->name('chat.messages.older');
    Route::get('/chat/attachables', [ChatController::class, 'attachables'])->name('chat.attachables');
});
