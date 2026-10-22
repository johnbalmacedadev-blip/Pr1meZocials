<?php

namespace App\Http\Requests;

use App\Enums\ContentFormat;
use App\Enums\Distribution;
use App\Enums\FunnelStage;
use App\Enums\Priority;
use App\Models\ContentItem;
use App\Rules\HttpUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ContentItem::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'campaign_id' => ['nullable', 'integer', Rule::exists('campaigns', 'id')->where('brand_id', $this->integer('brand_id'))],
            'format' => ['required', Rule::enum(ContentFormat::class)],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['nullable', Rule::enum(Priority::class)],
            'distribution' => ['nullable', Rule::enum(Distribution::class)],
            'funnel_stage' => ['nullable', Rule::enum(FunnelStage::class)],
            'pillar_id' => ['nullable', 'integer', 'exists:content_pillars,id'],
            'objective_id' => ['nullable', 'integer', 'exists:content_objectives,id'],
            'audience_segment_id' => ['nullable', 'integer', 'exists:audience_segments,id'],
            'audience_notes' => ['nullable', 'string', 'max:500'],
            'deadline' => ['nullable', 'date'],
            'target_production_date' => ['nullable', 'date'],
            'original_idea' => ['nullable', 'string', 'max:10000'],
            'inspiration_source' => ['nullable', 'string', 'max:255'],
            'inspiration_url' => ['nullable', 'string', 'max:2048', new HttpUrl],
            'hypothesis' => ['nullable', 'string', 'max:5000'],
            'success_metric' => ['nullable', 'string', 'max:5000'],
            'hook' => ['nullable', 'string', 'max:500'],
            'main_message' => ['nullable', 'string', 'max:5000'],
            'content_angle' => ['nullable', 'string', 'max:5000'],
            'creative_concept' => ['nullable', 'string', 'max:10000'],
            'storyline' => ['nullable', 'string', 'max:20000'],
            'caption_draft' => ['nullable', 'string', 'max:10000'],
            'call_to_action' => ['nullable', 'string', 'max:255'],
            'desired_outcome' => ['nullable', 'string', 'max:5000'],
            'key_talking_points' => ['nullable', 'string', 'max:10000'],
            'visual_direction' => ['nullable', 'string', 'max:10000'],
            'required_assets' => ['nullable', 'string', 'max:5000'],
            'brand_guidelines_notes' => ['nullable', 'string', 'max:5000'],
            'production_instructions' => ['nullable', 'string', 'max:10000'],
            'internal_notes' => ['nullable', 'string', 'max:10000'],
            'complexity' => ['nullable', 'integer', 'min:1', 'max:5'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'copywriter_id' => ['nullable', 'integer', 'exists:users,id'],
            'video_editor_id' => ['nullable', 'integer', 'exists:users,id'],
            'reviewer_id' => ['nullable', 'integer', 'exists:users,id'],
            'collaborator_ids' => ['nullable', 'array'],
            'collaborator_ids.*' => ['integer', 'exists:users,id'],
            'resource_link_ids' => ['nullable', 'array'],
            'resource_link_ids.*' => ['integer', 'exists:resource_links,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merged = [];

        foreach ([
            'campaign_id', 'pillar_id', 'objective_id', 'audience_segment_id', 'owner_id',
            'copywriter_id', 'video_editor_id', 'reviewer_id', 'deadline', 'target_production_date',
            'inspiration_url', 'complexity', 'priority', 'distribution', 'funnel_stage',
        ] as $field) {
            if ($this->input($field) === '') {
                $merged[$field] = null;
            }
        }

        if ($merged !== []) {
            $this->merge($merged);
        }
    }
}
