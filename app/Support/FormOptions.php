<?php

namespace App\Support;

use App\Models\AudienceSegment;
use App\Models\Brand;
use App\Models\Campaign;
use App\Models\ContentObjective;
use App\Models\ContentPillar;
use App\Models\Tag;
use App\Models\User;

class FormOptions
{
    /**
     * @return array<string, mixed>
     */
    public static function for(User $user): array
    {
        $brandIds = $user->accessibleBrandIds();
        $brandScope = $brandIds === [] ? [0] : $brandIds;

        return [
            'brands' => Brand::query()->whereIn('id', $brandScope)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'campaigns' => Campaign::query()->whereIn('brand_id', $brandScope)->orderBy('name')->get(['id', 'name', 'brand_id', 'status']),
            'pillars' => ContentPillar::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'objectives' => ContentObjective::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'segments' => AudienceSegment::query()
                ->where('is_active', true)
                ->where(fn ($query) => $query->whereNull('brand_id')->orWhereIn('brand_id', $brandScope))
                ->orderBy('name')
                ->get(['id', 'name', 'brand_id']),
            'tags' => Tag::query()
                ->where(fn ($query) => $query->whereNull('brand_id')->orWhereIn('brand_id', $brandScope))
                ->orderBy('name')
                ->get(['id', 'name', 'brand_id']),
            'people' => User::query()
                ->where('is_active', true)
                ->where(function ($query) use ($brandScope) {
                    $query->whereHas('brands', fn ($brands) => $brands->whereIn('brands.id', $brandScope))
                        ->orWhereHas('roles', fn ($roles) => $roles->where('slug', 'administrator'));
                })
                ->orderBy('name')
                ->get(['id', 'name', 'job_title']),
        ];
    }
}
