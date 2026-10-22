<?php

namespace App\Models;

use App\Enums\ApprovalDecision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentApproval extends Model
{
    protected $fillable = [
        'content_item_id',
        'creative_asset_id',
        'reviewer_id',
        'decision',
        'notes',
        'submission_number',
    ];

    public function contentItem(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(CreativeAsset::class, 'creative_asset_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function decisionLabel(): string
    {
        return ApprovalDecision::tryFrom((string) $this->decision)?->label() ?? ucfirst((string) $this->decision);
    }
}
