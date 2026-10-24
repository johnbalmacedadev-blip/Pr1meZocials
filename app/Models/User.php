<?php

namespace App\Models;

use App\Support\SafeEncrypted;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'job_title',
        'is_active',
        'facebook_user_token',
        'facebook_token_expires_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'facebook_user_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
            'facebook_token_expires_at' => 'datetime',
        ];
    }

    protected function facebookUserToken(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => SafeEncrypted::get($value),
            set: fn (?string $value) => SafeEncrypted::set($value),
        );
    }

    public function hasFacebookConnection(): bool
    {
        return filled($this->facebook_user_token);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class);
    }

    public function socialAccounts(): BelongsToMany
    {
        return $this->belongsToMany(SocialAccount::class);
    }

    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class);
    }

    public function userNotifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    public function savedViews(): HasMany
    {
        return $this->hasMany(SavedView::class);
    }

    public function isAdmin(): bool
    {
        $this->loadMissing('roles');

        return $this->roles->contains('slug', 'administrator');
    }

    public function hasRole(string $slug): bool
    {
        $this->loadMissing('roles');

        return $this->roles->contains('slug', $slug);
    }

    public function hasPermission(string $slug): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $owned = array_fill_keys($this->permissionSlugs(), true);

        foreach (\App\Support\PageAccess::acceptedAs($slug) as $candidate) {
            if (isset($owned[$candidate])) {
                return true;
            }
        }

        return isset($owned[$slug]);
    }

    /**
     * @return list<string>
     */
    public function permissionSlugs(): array
    {
        if ($this->isAdmin()) {
            return Permission::query()->orderBy('slug')->pluck('slug')->all();
        }

        $this->loadMissing('roles.permissions');

        return $this->roles
            ->flatMap(fn (Role $role) => $role->permissions)
            ->pluck('slug')
            ->unique()
            ->values()
            ->all();
    }

    public function canAccessBrand(int $brandId): bool
    {
        if ($this->isAdmin()) {
            return Brand::query()->whereKey($brandId)->exists();
        }

        return $this->brands()->whereKey($brandId)->exists();
    }

    /**
     * @return list<int>
     */
    public function accessibleBrandIds(): array
    {
        if ($this->isAdmin()) {
            return Brand::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $this->loadMissing('brands');

        return $this->brands->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function accessibleBrands(): Collection
    {
        if ($this->isAdmin()) {
            return Brand::query()->orderBy('name')->get();
        }

        $this->loadMissing('brands');

        return $this->brands->sortBy('name')->values();
    }

    public function canAccessSocialAccount(int $socialAccountId): bool
    {
        if ($this->isAdmin()) {
            return SocialAccount::query()->whereKey($socialAccountId)->exists();
        }

        return $this->socialAccounts()->whereKey($socialAccountId)->exists();
    }

    /**
     * Pages the user may publish to (explicitly linked pages).
     *
     * @return list<int>
     */
    public function accessibleSocialAccountIds(): array
    {
        if ($this->isAdmin()) {
            return SocialAccount::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $this->loadMissing('socialAccounts');

        return $this->socialAccounts->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Pages the user may see on calendars / posting schedule (brand-wide when they can view any content).
     *
     * @return list<int>
     */
    public function visibleSocialAccountIds(): array
    {
        if ($this->isAdmin()) {
            return SocialAccount::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        if ($this->hasPermission('content.view.any')) {
            return SocialAccount::query()
                ->whereIn('brand_id', $this->accessibleBrandIds() ?: [0])
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return $this->accessibleSocialAccountIds();
    }
}
