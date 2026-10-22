<?php

namespace App\Models;

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
        'email',
        'password',
        'job_title',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class);
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

        $this->loadMissing('roles.permissions');

        return $this->roles
            ->flatMap(fn (Role $role) => $role->permissions)
            ->contains('slug', $slug);
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

        $this->loadMissing('brands');

        return $this->brands->contains('id', $brandId);
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
}
