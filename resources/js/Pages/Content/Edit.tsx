import { Head, usePage } from '@inertiajs/react';
import ContentForm, { ContentFormData } from '@/Components/ContentForm';
import { PageHeader } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import type { FormOptions, SharedProps } from '@/types';

export default function Edit({ contentItem, options }: { contentItem: ContentFormData & { id: number; reference_number: string; abilities: { update: boolean }; tag_ids?: number[] }; options: FormOptions }) {
    const canAssign = usePage<SharedProps>().props.auth.user?.permissions.includes('content.assign') ?? false;
    const initial: ContentFormData = {
        title: contentItem.title ?? '',
        brand_id: String((contentItem as { brand?: { id: number } }).brand?.id ?? contentItem.brand_id ?? ''),
        campaign_id: String((contentItem as { campaign?: { id: number } }).campaign?.id ?? ''),
        format: contentItem.format ?? 'other',
        description: contentItem.description ?? '',
        priority: contentItem.priority ?? 'medium',
        distribution: contentItem.distribution ?? 'organic',
        funnel_stage: contentItem.funnel_stage ?? '',
        pillar_id: String((contentItem as { pillar?: { id: number } }).pillar?.id ?? ''),
        objective_id: String((contentItem as { objective?: { id: number } }).objective?.id ?? ''),
        audience_segment_id: String((contentItem as { audience_segment?: { id: number } }).audience_segment?.id ?? ''),
        audience_notes: contentItem.audience_notes ?? '',
        deadline: contentItem.deadline ?? '',
        target_production_date: contentItem.target_production_date ?? '',
        original_idea: contentItem.original_idea ?? '',
        inspiration_source: contentItem.inspiration_source ?? '',
        inspiration_url: contentItem.inspiration_url ?? '',
        hypothesis: contentItem.hypothesis ?? '',
        success_metric: contentItem.success_metric ?? '',
        hook: contentItem.hook ?? '',
        main_message: contentItem.main_message ?? '',
        content_angle: contentItem.content_angle ?? '',
        creative_concept: contentItem.creative_concept ?? '',
        storyline: contentItem.storyline ?? '',
        caption_draft: contentItem.caption_draft ?? '',
        call_to_action: contentItem.call_to_action ?? '',
        desired_outcome: contentItem.desired_outcome ?? '',
        key_talking_points: contentItem.key_talking_points ?? '',
        visual_direction: contentItem.visual_direction ?? '',
        required_assets: contentItem.required_assets ?? '',
        brand_guidelines_notes: contentItem.brand_guidelines_notes ?? '',
        production_instructions: contentItem.production_instructions ?? '',
        internal_notes: contentItem.internal_notes ?? '',
        complexity: contentItem.complexity ? String(contentItem.complexity) : '',
        tag_ids: (contentItem as { tags?: { id: number }[] }).tags?.map((tag) => tag.id) ?? [],
        owner_id: String((contentItem as { owner?: { id: number } }).owner?.id ?? ''),
        copywriter_id: String((contentItem as { copywriter?: { id: number } }).copywriter?.id ?? ''),
        video_editor_id: String((contentItem as { video_editor?: { id: number } }).video_editor?.id ?? ''),
        reviewer_id: String((contentItem as { reviewer?: { id: number } }).reviewer?.id ?? ''),
    };

    return (
        <AppLayout>
            <Head title={`Edit ${contentItem.reference_number}`} />
            <PageHeader title={contentItem.title} description={contentItem.reference_number} />
            <ContentForm initial={initial} options={options} action={`/content/${contentItem.id}`} method="put" expandedByDefault canAssign={canAssign} />
        </AppLayout>
    );
}
