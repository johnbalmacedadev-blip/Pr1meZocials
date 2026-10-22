import { useForm } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { FormEvent, ReactNode, useState } from 'react';
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
    collaborator_ids: number[];
    resource_link_ids: number[];
}

export const emptyContentForm: ContentFormData = {
    title: '', brand_id: '', campaign_id: '', format: 'video', description: '', priority: 'medium', distribution: 'organic',
    funnel_stage: '', pillar_id: '', objective_id: '', audience_segment_id: '', audience_notes: '', deadline: '',
    target_production_date: '', original_idea: '', inspiration_source: '', inspiration_url: '', hypothesis: '',
    success_metric: '', hook: '', main_message: '', content_angle: '', creative_concept: '', storyline: '', caption_draft: '',
    call_to_action: '', desired_outcome: '', key_talking_points: '', visual_direction: '', required_assets: '',
    brand_guidelines_notes: '', production_instructions: '', internal_notes: '', complexity: '', tag_ids: [],
    owner_id: '', collaborator_ids: [], resource_link_ids: [],
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
    const [openSections, setOpenSections] = useState({ assets: expandedByDefault, strategy: expandedByDefault, brief: expandedByDefault, people: expandedByDefault });
    const [inspirationQuery, setInspirationQuery] = useState('');
    const [inspirationOpen, setInspirationOpen] = useState(false);
    const inspirationNeedle = inspirationQuery.trim().toLowerCase();
    const inspirationMatches = (options.inspirations ?? []).filter((item) => {
        if (inspirationNeedle === '') {
            return true;
        }

        return item.url.toLowerCase().includes(inspirationNeedle) || (item.name ?? '').toLowerCase().includes(inspirationNeedle) || (item.platform?.name ?? '').toLowerCase().includes(inspirationNeedle);
    }).slice(0, 8);
    const campaigns = options.campaigns.filter((campaign) => String(campaign.brand_id) === form.data.brand_id);
    const segments = options.segments.filter((segment) => !segment.brand_id || String(segment.brand_id) === form.data.brand_id);
    const tags = options.tags.filter((tag) => !tag.brand_id || String(tag.brand_id) === form.data.brand_id);

    function submit(event: FormEvent) {
        event.preventDefault();
        form[method](action);
    }

    function toggleSection(key: keyof typeof openSections) {
        setOpenSections((current) => ({ ...current, [key]: !current[key] }));
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
                    <Select value={form.data.brand_id} onChange={(event) => {
                        const brandId = event.target.value;
                        const allowed = new Set((options.resource_links ?? []).filter((item) => String(item.brand_id) === brandId).map((item) => item.id));
                        form.setData({
                            ...form.data,
                            brand_id: brandId,
                            resource_link_ids: form.data.resource_link_ids.filter((id) => allowed.has(id)),
                        });
                    }} required>
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
                <Field label="Inspiration URL" error={form.errors.inspiration_url} hint="Optional. Paste a link, or choose a saved video.">
                    <Input value={form.data.inspiration_url} onChange={(event) => form.setData('inspiration_url', event.target.value)} placeholder="https://" />
                </Field>
                <div className="relative">
                    <Field label="Saved inspiration" hint="Search videos saved on the inspiration board.">
                        <Input
                            value={inspirationQuery}
                            onChange={(event) => { setInspirationQuery(event.target.value); setInspirationOpen(true); }}
                            onFocus={() => setInspirationOpen(true)}
                            onBlur={() => setInspirationOpen(false)}
                            placeholder="Search name, platform, or URL"
                            aria-label="Search saved inspiration"
                        />
                    </Field>
                    {inspirationOpen && (
                        <ul className="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-lg border border-zinc-200 bg-white py-1 shadow-lg">
                            {inspirationMatches.length === 0 ? (
                                <li className="px-3 py-2 text-sm text-zinc-500">{(options.inspirations ?? []).length === 0 ? 'No saved inspiration yet.' : 'No matching videos.'}</li>
                            ) : inspirationMatches.map((item) => (
                                <li key={item.id}>
                                    <button
                                        type="button"
                                        className="flex w-full flex-col items-start gap-0.5 px-3 py-2 text-left hover:bg-zinc-50"
                                        onMouseDown={(event) => event.preventDefault()}
                                        onClick={() => {
                                            form.setData({
                                                ...form.data,
                                                inspiration_url: item.url,
                                                inspiration_source: item.name || item.platform?.name || '',
                                            });
                                            setInspirationQuery('');
                                            setInspirationOpen(false);
                                        }}
                                    >
                                        <span className="text-sm font-medium text-zinc-900">{item.name || item.platform?.name || 'Saved video'}</span>
                                        <span className="line-clamp-2 break-all text-xs text-zinc-500">{item.url}</span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </section>

            <Disclosure title="Creative assets" description="Choose the folders this content will use." open={openSections.assets} onToggle={() => toggleSection('assets')}>
                    <Field label="Folders" error={form.errors.resource_link_ids}>
                        <div className="max-h-48 space-y-2 overflow-auto">
                            {(options.resource_links ?? []).filter((item) => String(item.brand_id) === form.data.brand_id).map((item) => (
                                <label key={item.id} className="flex items-start gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        className="mt-1"
                                        checked={form.data.resource_link_ids.includes(item.id)}
                                        onChange={() => form.setData('resource_link_ids', form.data.resource_link_ids.includes(item.id) ? form.data.resource_link_ids.filter((id) => id !== item.id) : [...form.data.resource_link_ids, item.id])}
                                    />
                                    <span>
                                        <span className="font-medium text-zinc-900">{item.name}</span>
                                        <span className="mt-0.5 block truncate text-xs text-zinc-500">{item.url}</span>
                                    </span>
                                </label>
                            ))}
                            {(options.resource_links ?? []).filter((item) => String(item.brand_id) === form.data.brand_id).length === 0 && (
                                <span className="text-sm text-zinc-500">No creative asset folders for this brand yet.</span>
                            )}
                        </div>
                    </Field>
                </Disclosure>
            <Disclosure title="Strategy" open={openSections.strategy} onToggle={() => toggleSection('strategy')}>
                <div className="grid gap-4 md:grid-cols-2">
                        <Field label="Content pillar"><Select value={form.data.pillar_id} onChange={(event) => form.setData('pillar_id', event.target.value)}><option value="">Unassigned</option>{options.pillars.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</Select></Field>
                        <Field label="Objective"><Select value={form.data.objective_id} onChange={(event) => form.setData('objective_id', event.target.value)}><option value="">Unassigned</option>{options.objectives.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</Select></Field>
                        <Field label="Funnel stage"><Select value={form.data.funnel_stage} onChange={(event) => form.setData('funnel_stage', event.target.value)}><option value="">Unassigned</option>{lookups?.funnel_stages.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}</Select></Field>
                        <Field label="Distribution"><Select value={form.data.distribution} onChange={(event) => form.setData('distribution', event.target.value)}>{lookups?.distributions.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}</Select></Field>
                        <Field label="Audience segment"><Select value={form.data.audience_segment_id} onChange={(event) => form.setData('audience_segment_id', event.target.value)}><option value="">Unassigned</option>{segments.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</Select></Field>
                        <Field label="Audience notes"><Input value={form.data.audience_notes} onChange={(event) => form.setData('audience_notes', event.target.value)} /></Field>
                        <Field label="Production deadline" hint="This is the production deadline, not the publishing date."><Input type="date" value={form.data.deadline} onChange={(event) => form.setData('deadline', event.target.value)} /></Field>
                        <Field label="Target production date"><Input type="date" value={form.data.target_production_date} onChange={(event) => form.setData('target_production_date', event.target.value)} /></Field>
                        {method === 'put' && (
                            <>
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
                            </>
                        )}
                    </div>
                </Disclosure>
                <Disclosure title="Creative brief" open={openSections.brief} onToggle={() => toggleSection('brief')}>
                    <div className="grid gap-4">
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
                    </div>
                </Disclosure>
                    {canAssign && (
                        <Disclosure title="People" open={openSections.people} onToggle={() => toggleSection('people')}>
                            <div className="grid gap-4 md:grid-cols-2">
                            <div className="md:col-span-2">
                                <Field label="Assign task to" error={form.errors.owner_id}>
                                    <Select value={form.data.owner_id} onChange={(event) => form.setData('owner_id', event.target.value)}>
                                        <option value="">Unassigned</option>
                                        {options.people.map((person) => <option key={person.id} value={person.id}>{person.name}</option>)}
                                    </Select>
                                </Field>
                            </div>
                            <div className="md:col-span-2">
                                <Field label="Collaborators" hint="Collaborators can edit the task details." error={form.errors.collaborator_ids}>
                                    <div className="max-h-36 space-y-1 overflow-auto">
                                        {options.people.map((person) => (
                                            <label key={person.id} className="flex items-center gap-2 text-sm">
                                                <input
                                                    type="checkbox"
                                                    checked={form.data.collaborator_ids.includes(person.id)}
                                                    onChange={() => form.setData('collaborator_ids', form.data.collaborator_ids.includes(person.id) ? form.data.collaborator_ids.filter((id) => id !== person.id) : [...form.data.collaborator_ids, person.id])}
                                                />
                                                {person.name}
                                            </label>
                                        ))}
                                    </div>
                                </Field>
                            </div>
                            </div>
                        </Disclosure>
                    )}

            <div className="flex items-center gap-3">
                <Button type="submit" disabled={form.processing}>{form.processing ? 'Saving…' : 'Save idea'}</Button>
                {form.recentlySuccessful && <span className="text-sm text-emerald-700">Saved</span>}
            </div>
        </form>
    );
}

function Disclosure({ title, description, open, onToggle, children }: { title: string; description?: string; open: boolean; onToggle: () => void; children: ReactNode }) {
    return (
        <section className="rounded-xl border border-zinc-200 bg-white">
            <button type="button" className="flex w-full items-center justify-between gap-3 px-5 py-4 text-left" aria-expanded={open} onClick={onToggle}>
                <span>
                    <span className="block text-sm font-semibold uppercase tracking-wide text-zinc-500">{title}</span>
                    {description && <span className="mt-1 block text-sm font-normal normal-case tracking-normal text-zinc-500">{description}</span>}
                </span>
                <ChevronDown className={`h-4 w-4 shrink-0 text-zinc-500 transition ${open ? 'rotate-180' : ''}`} />
            </button>
            {open && <div className="border-t border-zinc-100 px-5 py-5">{children}</div>}
        </section>
    );
}
