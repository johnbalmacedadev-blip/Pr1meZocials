import { useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { Button, Field, Input, Select, Textarea } from '@/Components/ui';
import type { FormOptions, SharedProps } from '@/types';
import { usePage } from '@inertiajs/react';

export interface ContentFormData {
    title: string;
    brand_id: string;
    campaign_id: string;
    format: string;
    description: string;
    priority: string;
    distribution: string;
    funnel_stage: string;
    pillar_id: string;
    objective_id: string;
    audience_segment_id: string;
    audience_notes: string;
    deadline: string;
    target_production_date: string;
    original_idea: string;
    inspiration_source: string;
    inspiration_url: string;
    hypothesis: string;
    success_metric: string;
    hook: string;
    main_message: string;
    content_angle: string;
    creative_concept: string;
    storyline: string;
    caption_draft: string;
    call_to_action: string;
    desired_outcome: string;
    key_talking_points: string;
    visual_direction: string;
    required_assets: string;
    brand_guidelines_notes: string;
    production_instructions: string;
    internal_notes: string;
    complexity: string;
    tag_ids: number[];
    owner_id: string;
    copywriter_id: string;
    video_editor_id: string;
    reviewer_id: string;
}

export const emptyContentForm: ContentFormData = {
    title: '', brand_id: '', campaign_id: '', format: 'video', description: '', priority: 'medium', distribution: 'organic',
    funnel_stage: '', pillar_id: '', objective_id: '', audience_segment_id: '', audience_notes: '', deadline: '',
    target_production_date: '', original_idea: '', inspiration_source: '', inspiration_url: '', hypothesis: '',
    success_metric: '', hook: '', main_message: '', content_angle: '', creative_concept: '', storyline: '', caption_draft: '',
    call_to_action: '', desired_outcome: '', key_talking_points: '', visual_direction: '', required_assets: '',
    brand_guidelines_notes: '', production_instructions: '', internal_notes: '', complexity: '', tag_ids: [],
    owner_id: '', copywriter_id: '', video_editor_id: '', reviewer_id: '',
};

export default function ContentForm({
    initial,
    options,
    action,
    method,
    expandedByDefault = false,
    canAssign = false,
}: {
    initial: ContentFormData;
    options: FormOptions;
    action: string;
    method: 'post' | 'put';
    expandedByDefault?: boolean;
    canAssign?: boolean;
}) {
    const lookups = usePage<SharedProps>().props.lookups;
    const form = useForm(initial);
    const [expanded, setExpanded] = useState(expandedByDefault);
    const campaigns = options.campaigns.filter((campaign) => String(campaign.brand_id) === form.data.brand_id);
    const segments = options.segments.filter((segment) => !segment.brand_id || String(segment.brand_id) === form.data.brand_id);
    const tags = options.tags.filter((tag) => !tag.brand_id || String(tag.brand_id) === form.data.brand_id);

    function submit(event: FormEvent) {
        event.preventDefault();
        form[method](action);
    }

    return (
        <form onSubmit={submit} className="space-y-6">
            <section className="grid gap-4 rounded-xl border border-zinc-200 bg-white p-5 md:grid-cols-2">
                <div className="md:col-span-2">
                    <Field label="Title" error={form.errors.title}>
                        <Input value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} required />
                    </Field>
                </div>
                <Field label="Brand" error={form.errors.brand_id}>
                    <Select value={form.data.brand_id} onChange={(event) => form.setData('brand_id', event.target.value)} required>
                        <option value="">Select a brand</option>
                        {options.brands.map((brand) => <option key={brand.id} value={brand.id}>{brand.name}</option>)}
                    </Select>
                </Field>
                <Field label="Format" error={form.errors.format}>
                    <Select value={form.data.format} onChange={(event) => form.setData('format', event.target.value)}>
                        {lookups?.formats.map((format) => <option key={format.value} value={format.value}>{format.label}</option>)}
                    </Select>
                </Field>
                <Field label="Campaign" error={form.errors.campaign_id}>
                    <Select value={form.data.campaign_id} onChange={(event) => form.setData('campaign_id', event.target.value)}>
                        <option value="">No campaign yet</option>
                        {campaigns.map((campaign) => <option key={campaign.id} value={campaign.id}>{campaign.name}</option>)}
                    </Select>
                </Field>
                <Field label="Priority" error={form.errors.priority}>
                    <Select value={form.data.priority} onChange={(event) => form.setData('priority', event.target.value)}>
                        {lookups?.priorities.map((priority) => <option key={priority.value} value={priority.value}>{priority.label}</option>)}
                    </Select>
                </Field>
                <div className="md:col-span-2">
                    <Field label="Short description" error={form.errors.description}>
                        <Textarea value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} />
                    </Field>
                </div>
                <Field label="Inspiration URL" error={form.errors.inspiration_url} hint="Optional. Use http or https.">
                    <Input value={form.data.inspiration_url} onChange={(event) => form.setData('inspiration_url', event.target.value)} placeholder="https://" />
                </Field>
                <Field label="Inspiration source" error={form.errors.inspiration_source}>
                    <Input value={form.data.inspiration_source} onChange={(event) => form.setData('inspiration_source', event.target.value)} />
                </Field>
            </section>

            <button type="button" className="text-sm font-medium text-teal-800 hover:underline" onClick={() => setExpanded((value) => !value)}>
                {expanded ? 'Hide the full brief' : 'Expand into a full creative brief'}
            </button>

            {expanded && (
                <>
                    <section className="grid gap-4 rounded-xl border border-zinc-200 bg-white p-5 md:grid-cols-2">
                        <h2 className="md:col-span-2 text-sm font-semibold uppercase tracking-wide text-zinc-500">Strategy</h2>
                        <Field label="Content pillar"><Select value={form.data.pillar_id} onChange={(event) => form.setData('pillar_id', event.target.value)}><option value="">Unassigned</option>{options.pillars.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</Select></Field>
                        <Field label="Objective"><Select value={form.data.objective_id} onChange={(event) => form.setData('objective_id', event.target.value)}><option value="">Unassigned</option>{options.objectives.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</Select></Field>
                        <Field label="Funnel stage"><Select value={form.data.funnel_stage} onChange={(event) => form.setData('funnel_stage', event.target.value)}><option value="">Unassigned</option>{lookups?.funnel_stages.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}</Select></Field>
                        <Field label="Distribution"><Select value={form.data.distribution} onChange={(event) => form.setData('distribution', event.target.value)}>{lookups?.distributions.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}</Select></Field>
                        <Field label="Audience segment"><Select value={form.data.audience_segment_id} onChange={(event) => form.setData('audience_segment_id', event.target.value)}><option value="">Unassigned</option>{segments.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</Select></Field>
                        <Field label="Audience notes"><Input value={form.data.audience_notes} onChange={(event) => form.setData('audience_notes', event.target.value)} /></Field>
                        <Field label="Production deadline" hint="This is the production deadline, not the publishing date."><Input type="date" value={form.data.deadline} onChange={(event) => form.setData('deadline', event.target.value)} /></Field>
                        <Field label="Target production date"><Input type="date" value={form.data.target_production_date} onChange={(event) => form.setData('target_production_date', event.target.value)} /></Field>
                        <Field label="Complexity" hint="1 is simple, 5 is a heavy production."><Input type="number" min={1} max={5} value={form.data.complexity} onChange={(event) => form.setData('complexity', event.target.value)} /></Field>
                        <div className="md:col-span-2"><Field label="Hypothesis" error={form.errors.hypothesis}><Textarea value={form.data.hypothesis} onChange={(event) => form.setData('hypothesis', event.target.value)} /></Field></div>
                        <div className="md:col-span-2"><Field label="Success metric" error={form.errors.success_metric}><Textarea value={form.data.success_metric} onChange={(event) => form.setData('success_metric', event.target.value)} /></Field></div>
                        <div className="md:col-span-2">
                            <Field label="Tags">
                                <div className="flex flex-wrap gap-2">
                                    {tags.map((tag) => {
                                        const checked = form.data.tag_ids.includes(tag.id);
                                        return (
                                            <label key={tag.id} className="inline-flex items-center gap-2 rounded-full border border-zinc-200 px-3 py-1 text-sm">
                                                <input type="checkbox" checked={checked} onChange={() => form.setData('tag_ids', checked ? form.data.tag_ids.filter((id) => id !== tag.id) : [...form.data.tag_ids, tag.id])} />
                                                {tag.name}
                                            </label>
                                        );
                                    })}
                                    {tags.length === 0 && <span className="text-sm text-zinc-500">No tags for this brand yet.</span>}
                                </div>
                            </Field>
                        </div>
                    </section>
                    <section className="grid gap-4 rounded-xl border border-zinc-200 bg-white p-5">
                        <h2 className="text-sm font-semibold uppercase tracking-wide text-zinc-500">Creative brief</h2>
                        {([
                            ['original_idea', 'Original idea'],
                            ['hook', 'Hook or opening line'],
                            ['main_message', 'Main message'],
                            ['content_angle', 'Content angle'],
                            ['creative_concept', 'Creative concept'],
                            ['storyline', 'Storyline or script outline'],
                            ['caption_draft', 'Caption or copy draft'],
                            ['call_to_action', 'Call to action'],
                            ['desired_outcome', 'Desired outcome'],
                            ['key_talking_points', 'Key talking points'],
                            ['visual_direction', 'Visual direction'],
                            ['required_assets', 'Required assets'],
                            ['brand_guidelines_notes', 'Brand guidelines'],
                            ['production_instructions', 'Production instructions'],
                            ['internal_notes', 'Internal notes'],
                        ] as const).map(([key, label]) => (
                            <Field key={key} label={label} error={form.errors[key]}>
                                {key === 'hook' || key === 'call_to_action' ? (
                                    <Input value={form.data[key]} onChange={(event) => form.setData(key, event.target.value)} />
                                ) : (
                                    <Textarea value={form.data[key]} onChange={(event) => form.setData(key, event.target.value)} />
                                )}
                            </Field>
                        ))}
                    </section>
                    {canAssign && (
                        <section className="grid gap-4 rounded-xl border border-zinc-200 bg-white p-5 md:grid-cols-2">
                            <h2 className="md:col-span-2 text-sm font-semibold uppercase tracking-wide text-zinc-500">People</h2>
                            {([
                                ['owner_id', 'Primary owner'],
                                ['copywriter_id', 'Copywriter'],
                                ['video_editor_id', 'Video editor'],
                                ['reviewer_id', 'Reviewer'],
                            ] as const).map(([key, label]) => (
                                <Field key={key} label={label}>
                                    <Select value={form.data[key]} onChange={(event) => form.setData(key, event.target.value)}>
                                        <option value="">Unassigned</option>
                                        {options.people.map((person) => <option key={person.id} value={person.id}>{person.name}</option>)}
                                    </Select>
                                </Field>
                            ))}
                        </section>
                    )}
                </>
            )}

            <div className="flex items-center gap-3">
                <Button type="submit" disabled={form.processing}>{form.processing ? 'Saving…' : 'Save idea'}</Button>
                {form.recentlySuccessful && <span className="text-sm text-emerald-700">Saved</span>}
            </div>
        </form>
    );
}
