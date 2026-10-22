<?php

namespace App\Models;

use App\Enums\ContentFormat;
use App\Services\ReferenceNumberGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContentItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'brand_id',
        'campaign_id',
        'title',
        'description',
        'pillar_id',
        'objective_id',
        'audience_segment_id',
        'audience_notes',
        'funnel_stage',
        'priority',
        'distribution',
        'format',
        'complexity',
        'target_production_date',
        'deadline',
        'scheduled_publish_at',
        'published_at',
        'hypothesis',
        'success_metric',
        'original_idea',
        'hook',
        'main_message',
        'content_angle',
        'creative_concept',
        'storyline',
        'caption_draft',
        'call_to_action',
        'desired_outcome',
        'key_talking_points',
        'visual_direction',
        'required_assets',
        'brand_guidelines_notes',
        'production_instructions',
        'inspiration_source',
        'inspiration_url',
        'internal_notes',
    ];

    protected function casts(): array
    {
        return [
            'target_production_date' => 'date',
            'deadline' => 'date',
            'scheduled_publish_at' => 'datetime',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
            'complexity' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ContentItem $item) {
            if (blank($item->reference_number)) {
                $item->reference_number = app(ReferenceNumberGenerator::class)->next();
            }

            if (blank($item->status_id)) {
                $item->status_id = WorkflowStatus::query()->where('slug', 'inbox')->value('id');
            }
        });
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(WorkflowStatus::class, 'status_id');
    }

    public function pillar(): BelongsTo
    {
        return $this->belongsTo(ContentPillar::class, 'pillar_id');
    }

    public function objective(): BelongsTo
    {
        return $this->belongsTo(ContentObjective::class, 'objective_id');
    }

    public function audienceSegment(): BelongsTo
    {
        return $this->belongsTo(AudienceSegment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function copywriter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'copywriter_id');
    }

    public function videoEditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'video_editor_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function collaborators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'content_collaborators')
            ->withPivot('assigned_by')
            ->withTimestamps();
    }

    public function platformVersions(): HasMany
    {
        return $this->hasMany(ContentPlatformVersion::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(CreativeAsset::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ContentComment::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ContentActivity::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(ContentApproval::class);
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        $brandIds = $user->accessibleBrandIds();
        $query->whereIn('brand_id', $brandIds === [] ? [0] : $brandIds);

        if ($user->hasPermission('content.view.any')) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($user) {
            $inner->where('created_by', $user->id)
                ->orWhere('owner_id', $user->id)
                ->orWhere('copywriter_id', $user->id)
                ->orWhere('video_editor_id', $user->id)
                ->orWhere('reviewer_id', $user->id)
                ->orWhereHas('collaborators', fn (Builder $collaborators) => $collaborators->where('users.id', $user->id))
                ->orWhereHas('campaign.members', fn (Builder $members) => $members->where('users.id', $user->id));

            if ($user->hasPermission('content.approve')) {
                $inner->orWhereHas('status', fn (Builder $status) => $status->whereIn('slug', [
                    'internal_review',
                    'revision_required',
                    'approved',
                ]));
            }
        });
    }

    public function isAssignedTo(User $user): bool
    {
        if (in_array($user->id, [
            $this->created_by,
            $this->owner_id,
            $this->copywriter_id,
            $this->video_editor_id,
            $this->reviewer_id,
        ], true)) {
            return true;
        }

        if ($this->relationLoaded('collaborators')) {
            return $this->collaborators->contains('id', $user->id);
        }

        return $this->collaborators()->where('users.id', $user->id)->exists();
    }

    public function isOverdue(): bool
    {
        if ($this->deadline === null) {
            return false;
        }

        $slug = $this->status?->slug;

        if (in_array($slug, config('ops.closed_statuses'), true)) {
            return false;
        }

        return $this->deadline->copy()->endOfDay()->isPast();
    }

    public function formatLabel(): string
    {
        return ContentFormat::tryFrom((string) $this->format)?->label() ?? ucfirst((string) $this->format);
    }
}
