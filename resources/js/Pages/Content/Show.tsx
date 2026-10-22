import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { Button, Field, Input, PageHeader, PriorityBadge, Select, StatusBadge, Textarea } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatBytes, formatDate } from '@/lib/utils';
import type { FormOptions, SharedProps } from '@/types';

interface Detail {
    id: number;
    reference_number: string;
    title: string;
    description: string | null;
    format_label: string;
    priority: string;
    distribution: string;
    funnel_stage: string | null;
    deadline: string | null;
    target_production_date: string | null;
    is_overdue: boolean;
    brand: { id: number; name: string } | null;
    campaign: { id: number; name: string } | null;
    status: { id: number; name: string; slug: string; color: string } | null;
    owner: { id: number; name: string } | null;
    copywriter: { id: number; name: string } | null;
    video_editor: { id: number; name: string } | null;
    reviewer: { id: number; name: string } | null;
    collaborators: { id: number; name: string }[];
    creator: { id: number; name: string } | null;
    pillar: { name: string } | null;
    objective: { name: string } | null;
    audience_segment: { name: string } | null;
    hypothesis: string | null;
    success_metric: string | null;
    original_idea: string | null;
    hook: string | null;
    main_message: string | null;
    content_angle: string | null;
    creative_concept: string | null;
    storyline: string | null;
    caption_draft: string | null;
    call_to_action: string | null;
    desired_outcome: string | null;
    key_talking_points: string | null;
    visual_direction: string | null;
    required_assets: string | null;
    brand_guidelines_notes: string | null;
    production_instructions: string | null;
    inspiration_source: string | null;
    inspiration_url: string | null;
    internal_notes: string | null;
    complexity: number | null;
    tags: { id: number; name: string }[];
    resource_links: { id: number; name: string; url: string }[];
    versions: Version[];
    assets: Asset[];
    comments: { id: number; body: string; created_at: string; user: { name: string } | null }[];
    activities: { id: number; description: string; created_at: string; user: { name: string } | null }[];
    approvals: { id: number; decision_label: string; notes: string | null; submission_number: number; created_at: string; reviewer: { name: string } | null; asset: { name: string; version: number } | null }[];
    abilities: Record<string, boolean>;
}

interface Version {
    id: number;
    format: string;
    format_label: string;
    headline: string | null;
    caption: string | null;
    hashtags: string | null;
    call_to_action: string | null;
    instructions: string | null;
    scheduled_at: string | null;
    publishing_status: string;
    publishing_status_label: string;
    published_url: string | null;
    published_at: string | null;
    platform: { id: number; name: string } | null;
    metrics: Record<string, number | null> | null;
}

interface Asset {
    id: number;
    name: string;
    original_filename: string;
    size: number;
    kind_label: string;
    version: number;
    previewable: boolean;
    mime_type: string;
    created_at: string | null;
    uploader: { name: string } | null;
}

export default function Show({ contentItem, options }: { contentItem: Detail; options: FormOptions }) {
    const page = usePage<SharedProps>().props;
    const lookups = page.lookups;
    const permissions = page.auth.user?.permissions ?? [];
    const [tab, setTab] = useState('brief');
    const [error, setError] = useState('');
    const item = contentItem;
    const allowed = lookups?.statuses.find((status) => status.slug === item.status?.slug)?.allowed_transitions ?? [];
    const nextStatuses = lookups?.statuses.filter((status) => allowed.includes(status.slug) && (!status.enter_permission || permissions.includes(status.enter_permission))) ?? [];

    return (
        <AppLayout>
            <Head title={item.title} />
            <PageHeader
                title={item.title}
                description={`${item.reference_number} · ${item.brand?.name ?? 'No brand'} · ${item.format_label}`}
                actions={(item.abilities.update || item.abilities.delete) ? (
                    <>
                        {item.abilities.update && <Link href={`/content/${item.id}/edit`} className="inline-flex h-10 items-center rounded-lg border border-zinc-300 bg-white px-3 text-sm">Edit brief</Link>}
                        {item.abilities.delete && <Button type="button" variant="danger" onClick={() => { if (confirm(`Move "${item.title}" to trash?`)) router.delete(`/content/${item.id}`); }}>Delete</Button>}
                    </>
                ) : undefined}
            />
            <div className="mb-4 flex flex-wrap items-center gap-2">
                <StatusBadge status={item.status} />
                <PriorityBadge priority={item.priority} />
                {item.is_overdue && <span className="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-900">Overdue</span>}
                <span className="text-sm text-zinc-500">Assigned to: {item.owner?.name ?? 'Unassigned'}</span>
                <span className="text-sm text-zinc-500">Production deadline: {formatDate(item.deadline)}</span>
            </div>
            {error && <p className="mb-4 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-900">{error}</p>}
            {item.abilities.transition && (
                <form className="mb-4 grid gap-4 md:grid-cols-[14rem_minmax(16rem,28rem)_max-content] md:items-start" onSubmit={(event) => {
                    event.preventDefault();
                    const data = new FormData(event.currentTarget);
                    router.post(`/content/${item.id}/transition`, { status: data.get('status'), notes: data.get('notes') }, { preserveScroll: true, onError: (errors) => setError(errors.status || errors.notes || 'The status could not be changed.'), onSuccess: () => setError('') });
                }}>
                    <Field label="Move to">
                        <Select className="h-10" name="status" defaultValue="">
                            <option value="" disabled>Choose a stage</option>
                            {nextStatuses.map((status) => <option key={status.slug} value={status.slug}>{status.name}</option>)}
                        </Select>
                    </Field>
                    <Field label="Note" hint="Required when requesting revisions."><Input className="h-10" name="notes" /></Field>
                    <Button type="submit" variant="secondary" className="h-10 w-auto justify-self-start md:mt-6">Update status</Button>
                </form>
            )}
            <div className="mb-4 flex gap-2 overflow-x-auto">
                {['brief', 'platforms', 'assets', 'reviews', 'activity'].map((name) => (
                    <button key={name} className={`rounded-full px-3 py-1 text-sm capitalize ${tab === name ? 'bg-zinc-900 text-white' : 'bg-white text-zinc-700'}`} onClick={() => setTab(name)}>{name}</button>
                ))}
            </div>
            {tab === 'brief' && <Brief item={item} options={options} />}
            {tab === 'platforms' && <Platforms item={item} />}
            {tab === 'assets' && <Assets item={item} />}
            {tab === 'reviews' && <Reviews item={item} />}
            {tab === 'activity' && <Activity item={item} />}
        </AppLayout>
    );
}

function Brief({ item, options }: { item: Detail; options: FormOptions }) {
    const assign = useForm({
        owner_id: item.owner?.id ? String(item.owner.id) : '',
        collaborator_ids: item.collaborators.map((person) => person.id),
    });
    const facts: [string, string | null | undefined][] = [
        ['Campaign', item.campaign?.name],
        ['Pillar', item.pillar?.name],
        ['Objective', item.objective?.name],
        ['Funnel', item.funnel_stage],
        ['Distribution', item.distribution],
        ['Audience', item.audience_segment?.name],
        ['Assigned to', item.owner?.name],
        ['Collaborators', item.collaborators.map((person) => person.name).join(', ') || null],
        ['Created by', item.creator?.name],
        ['Target production', formatDate(item.target_production_date)],
        ['Complexity', item.complexity ? String(item.complexity) : null],
    ];
    const brief: [string, string | null][] = [
        ['Original idea', item.original_idea],
        ['Hook', item.hook],
        ['Main message', item.main_message],
        ['Angle', item.content_angle],
        ['Concept', item.creative_concept],
        ['Storyline', item.storyline],
        ['Caption draft', item.caption_draft],
        ['Call to action', item.call_to_action],
        ['Desired outcome', item.desired_outcome],
        ['Talking points', item.key_talking_points],
        ['Visual direction', item.visual_direction],
        ['Required assets', item.required_assets],
        ['Brand guidelines', item.brand_guidelines_notes],
        ['Production instructions', item.production_instructions],
        ['Hypothesis', item.hypothesis],
        ['Success metric', item.success_metric],
        ['Internal notes', item.internal_notes],
        ['Description', item.description],
    ];

    return (
        <div className="space-y-4">
        <div className="grid gap-4 lg:grid-cols-[2fr_1fr]">
            <section className="space-y-4 rounded-xl border border-zinc-200 bg-white p-5">
                {brief.filter(([, value]) => value).map(([label, value]) => (
                    <div key={label}><h3 className="text-xs font-semibold uppercase tracking-wide text-zinc-500">{label}</h3><p className="mt-1 whitespace-pre-wrap text-sm">{value}</p></div>
                ))}
                {brief.every(([, value]) => !value) && <p className="text-sm text-zinc-500">The brief is still empty. Open Edit to add it without starting over.</p>}
                {item.inspiration_url && <a className="text-sm font-medium text-brand-700" href={item.inspiration_url} target="_blank" rel="noreferrer">Inspiration: {item.inspiration_source || item.inspiration_url}</a>}
                {item.resource_links.length > 0 && (
                    <div>
                        <h3 className="text-xs font-semibold uppercase tracking-wide text-zinc-500">Creative assets</h3>
                        <div className="mt-2 space-y-1">
                            {item.resource_links.map((link) => (
                                <a key={link.id} className="block text-sm font-medium text-brand-700" href={link.url} target="_blank" rel="noreferrer">{link.name}</a>
                            ))}
                        </div>
                    </div>
                )}
                <div className="flex flex-wrap gap-2">{item.tags.map((tag) => <span key={tag.id} className="rounded-full bg-zinc-100 px-2 py-1 text-xs">{tag.name}</span>)}</div>
            </section>
            <aside className="space-y-4">
                <section className="rounded-xl border border-zinc-200 bg-white p-4 text-sm">
                    {facts.map(([label, value]) => <p key={label} className="flex justify-between gap-3 border-b border-zinc-100 py-2"><span className="text-zinc-500">{label}</span><span className="text-right">{value || '—'}</span></p>)}
                </section>
                {item.abilities.assign && (
                    <form className="space-y-3 rounded-xl border border-zinc-200 bg-white p-4" onSubmit={(event) => { event.preventDefault(); assign.put(`/content/${item.id}/assignment`); }}>
                        <h3 className="font-semibold">Assignment</h3>
                        <p className="text-xs text-zinc-500">The person you assign sees this task on their dashboard and production board. They can edit the details only when they are also checked as a collaborator.</p>
                        <Field label="Assign task to" error={assign.errors.owner_id}>
                            <Select value={assign.data.owner_id} onChange={(event) => assign.setData('owner_id', event.target.value)}>
                                <option value="">Unassigned</option>
                                {options.people.map((person) => <option key={person.id} value={person.id}>{person.name}</option>)}
                            </Select>
                        </Field>
                        <Field label="Collaborators" hint="Collaborators can edit the task details." error={assign.errors.collaborator_ids}>
                            <div className="max-h-36 space-y-1 overflow-auto">
                                {options.people.map((person) => (
                                    <label key={person.id} className="flex items-center gap-2 text-sm">
                                        <input type="checkbox" checked={assign.data.collaborator_ids.includes(person.id)} onChange={() => assign.setData('collaborator_ids', assign.data.collaborator_ids.includes(person.id) ? assign.data.collaborator_ids.filter((id) => id !== person.id) : [...assign.data.collaborator_ids, person.id])} />
                                        {person.name}
                                    </label>
                                ))}
                            </div>
                        </Field>
                        <Button type="submit" disabled={assign.processing}>Save assignments</Button>
                    </form>
                )}
            </aside>
        </div>
        <Notes item={item} />
        </div>
    );
}

function Notes({ item }: { item: Detail }) {
    const comment = useForm({ body: '', mention_ids: [] as number[] });

    return (
        <section className="rounded-xl border border-zinc-200 bg-white p-5">
            <h3 className="font-semibold">Notes</h3>
            <p className="mt-1 text-sm text-zinc-500">Add a link or a note when the task is finished. The person this task is assigned to can write here.</p>
            <div className="mt-4 space-y-3">
                {item.comments.length === 0 && <p className="text-sm text-zinc-500">No notes yet.</p>}
                {item.comments.map((entry) => (
                    <article key={entry.id} className="rounded-lg border border-zinc-100 bg-zinc-50 p-3">
                        <p className="text-sm font-medium">{entry.user?.name}</p>
                        <CommentBody body={entry.body} />
                        <p className="text-xs text-zinc-500">{formatDate(entry.created_at)}</p>
                    </article>
                ))}
            </div>
            {item.abilities.comment && (
                <form className="mt-4 space-y-2" onSubmit={(event) => { event.preventDefault(); comment.post(`/content/${item.id}/comments`, { preserveScroll: true, onSuccess: () => comment.reset('body') }); }}>
                    <Textarea value={comment.data.body} onChange={(event) => comment.setData('body', event.target.value)} placeholder="Paste a finished video link, or leave a note for the team" rows={4} />
                    {comment.errors.body && <p className="text-sm text-rose-700">{comment.errors.body}</p>}
                    <Button type="submit" disabled={comment.processing || comment.data.body.trim() === ''}>Add note</Button>
                </form>
            )}
        </section>
    );
}

function CommentBody({ body }: { body: string }) {
    const parts = body.split(/(https?:\/\/[^\s]+)/g);

    return (
        <p className="mt-1 whitespace-pre-wrap text-sm">
            {parts.map((part, index) => (
                /^https?:\/\//.test(part)
                    ? <a key={index} className="font-medium text-brand-700 underline" href={part} target="_blank" rel="noreferrer">{part}</a>
                    : <span key={index}>{part}</span>
            ))}
        </p>
    );
}

function Platforms({ item }: { item: Detail }) {
    const lookups = usePage<SharedProps>().props.lookups;
    const form = useForm({ platform_id: lookups?.platforms[0]?.id ? String(lookups.platforms[0].id) : '', format: item.versions[0]?.format ?? 'reel', headline: '', caption: '', hashtags: '', call_to_action: '', instructions: '', scheduled_at: '', publishing_status: 'draft' });
    const publish = useForm({ published_url: '', published_at: '', notes: '', mark_content_published: true, version_id: 0 });

    return (
        <div className="space-y-4">
            <p className="text-sm text-zinc-600">One idea can ship as several platform versions. Changing a publishing date does not change the production deadline.</p>
            {item.versions.length === 0 && <p className="rounded-xl border border-dashed border-zinc-300 bg-white p-6 text-sm text-zinc-500">No platform versions yet.</p>}
            {item.versions.map((version) => (
                <article key={version.id} className="rounded-xl border border-zinc-200 bg-white p-4">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <h3 className="font-semibold">{version.platform?.name} · {version.format_label}</h3>
                        <span className="text-sm text-zinc-500">{version.publishing_status_label}</span>
                    </div>
                    {version.caption && <p className="mt-2 whitespace-pre-wrap text-sm">{version.caption}</p>}
                    <p className="mt-2 text-sm text-zinc-500">Publishes {version.scheduled_at ? formatDate(version.scheduled_at) : 'unscheduled'} · Production deadline {formatDate(item.deadline)}</p>
                    {version.published_url && <a className="mt-1 block text-sm text-brand-700" href={version.published_url} target="_blank" rel="noreferrer">{version.published_url}</a>}
                    {version.metrics && <p className="mt-2 text-sm">Engagement rate {version.metrics.engagement_rate ?? '—'}% · CTR {version.metrics.click_through_rate ?? '—'}%</p>}
                    {item.abilities.publish && version.publishing_status !== 'published' && (
                        <form className="mt-3 grid gap-2 md:grid-cols-[1fr_auto]" onSubmit={(event) => { event.preventDefault(); router.post(`/versions/${version.id}/publish`, { published_url: publish.data.published_url, published_at: publish.data.published_at, notes: publish.data.notes, mark_content_published: true }, { preserveScroll: true }); }}>
                            <Input placeholder="https:// published URL" value={publish.data.published_url} onChange={(event) => publish.setData('published_url', event.target.value)} />
                            <Button type="submit">Record as published</Button>
                        </form>
                    )}
                </article>
            ))}
            {item.abilities.update && (
                <form className="grid gap-3 rounded-xl border border-zinc-200 bg-white p-4 md:grid-cols-2" onSubmit={(event: FormEvent) => { event.preventDefault(); form.post(`/content/${item.id}/versions`); }}>
                    <h3 className="md:col-span-2 font-semibold">Add a platform version</h3>
                    <Field label="Platform"><Select value={form.data.platform_id} onChange={(event) => form.setData('platform_id', event.target.value)}>{lookups?.platforms.map((platform) => <option key={platform.id} value={platform.id}>{platform.name}</option>)}</Select></Field>
                    <Field label="Format"><Select value={form.data.format} onChange={(event) => form.setData('format', event.target.value)}>{lookups?.formats.map((format) => <option key={format.value} value={format.value}>{format.label}</option>)}</Select></Field>
                    <Field label="Headline"><Input value={form.data.headline} onChange={(event) => form.setData('headline', event.target.value)} /></Field>
                    <Field label="Hashtags"><Input value={form.data.hashtags} onChange={(event) => form.setData('hashtags', event.target.value)} /></Field>
                    <div className="md:col-span-2"><Field label="Caption"><Textarea value={form.data.caption} onChange={(event) => form.setData('caption', event.target.value)} /></Field></div>
                    <Field label="Call to action"><Input value={form.data.call_to_action} onChange={(event) => form.setData('call_to_action', event.target.value)} /></Field>
                    <Field label="Publishing date" hint="Does not change the production deadline."><Input type="datetime-local" value={form.data.scheduled_at} onChange={(event) => form.setData('scheduled_at', event.target.value)} /></Field>
                    <div className="md:col-span-2"><Field label="Platform instructions"><Textarea value={form.data.instructions} onChange={(event) => form.setData('instructions', event.target.value)} /></Field></div>
                    {form.errors.platform_id && <p className="text-sm text-rose-700">{form.errors.platform_id}</p>}
                    <Button type="submit" disabled={form.processing}>Add version</Button>
                </form>
            )}
        </div>
    );
}

function Assets({ item }: { item: Detail }) {
    const lookups = usePage<SharedProps>().props.lookups;
    const form = useForm<{ file: File | null; name: string; description: string; kind: string; is_final: boolean }>({ file: null, name: '', description: '', kind: 'image', is_final: false });

    return (
        <div className="space-y-4">
            {item.assets.length === 0 && <p className="rounded-xl border border-dashed border-zinc-300 bg-white p-6 text-sm text-zinc-500">No files yet. Drafts stay in private storage.</p>}
            <div className="grid gap-3 md:grid-cols-2">
                {item.assets.map((asset) => (
                    <article key={asset.id} className="rounded-xl border border-zinc-200 bg-white p-4">
                        {asset.previewable && asset.mime_type.startsWith('image/') && <img src={`/assets/${asset.id}/preview`} alt="" className="mb-3 max-h-48 w-full rounded-lg object-cover" />}
                        {asset.previewable && asset.mime_type.startsWith('video/') && <video src={`/assets/${asset.id}/preview`} controls className="mb-3 max-h-48 w-full rounded-lg" />}
                        <h3 className="font-medium">{asset.name}</h3>
                        <p className="text-sm text-zinc-500">{asset.kind_label} · v{asset.version} · {formatBytes(asset.size)} · {asset.uploader?.name}</p>
                        <a className="mt-2 inline-block text-sm font-medium text-brand-700" href={`/assets/${asset.id}/download`}>Download</a>
                    </article>
                ))}
            </div>
            {item.abilities.upload && (
                <form className="space-y-3 rounded-xl border border-zinc-200 bg-white p-4" onSubmit={(event) => { event.preventDefault(); form.post(`/content/${item.id}/assets`, { forceFormData: true }); }}>
                    <h3 className="font-semibold">Upload a draft</h3>
                    <Input type="file" onChange={(event) => form.setData('file', event.target.files?.[0] ?? null)} />
                    {form.errors.file && <p className="text-sm text-rose-700">{form.errors.file}</p>}
                    {form.progress && <p className="text-sm text-zinc-500">Uploading {form.progress.percentage}%</p>}
                    <Field label="Kind"><Select value={form.data.kind} onChange={(event) => form.setData('kind', event.target.value)}>{lookups?.asset_kinds.map((kind) => <option key={kind.value} value={kind.value}>{kind.label}</option>)}</Select></Field>
                    <Field label="Description"><Input value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} /></Field>
                    <Button type="submit" disabled={form.processing}>Upload</Button>
                </form>
            )}
        </div>
    );
}

function Reviews({ item }: { item: Detail }) {
    const review = useForm({ decision: 'revision_requested', notes: '', asset_id: '' });

    return (
        <div className="space-y-4">
            <p className="text-sm text-zinc-600">Approval is internal. It does not publish the content.</p>
            {item.approvals.length === 0 && <p className="rounded-xl border border-dashed border-zinc-300 bg-white p-6 text-sm text-zinc-500">No review decisions yet.</p>}
            {item.approvals.map((approval) => (
                <article key={approval.id} className="rounded-xl border border-zinc-200 bg-white p-4">
                    <p className="font-medium">{approval.decision_label} · submission {approval.submission_number}</p>
                    <p className="text-sm text-zinc-500">{approval.reviewer?.name} · {formatDate(approval.created_at)}{approval.asset ? ` · reviewed ${approval.asset.name} v${approval.asset.version}` : ''}</p>
                    {approval.notes && <p className="mt-2 whitespace-pre-wrap text-sm">{approval.notes}</p>}
                </article>
            ))}
            {item.abilities.approve && (
                <form className="space-y-3 rounded-xl border border-zinc-200 bg-white p-4" onSubmit={(event) => { event.preventDefault(); review.post(`/content/${item.id}/reviews`); }}>
                    <Field label="Decision" error={review.errors.decision}>
                        <Select value={review.data.decision} onChange={(event) => review.setData('decision', event.target.value)}>
                            <option value="approved">Approve</option>
                            <option value="revision_requested">Request revisions</option>
                            <option value="rejected">Reject</option>
                        </Select>
                    </Field>
                    <Field label="Notes" error={review.errors.notes} hint="Required unless you are approving.">
                        <Textarea value={review.data.notes} onChange={(event) => review.setData('notes', event.target.value)} />
                    </Field>
                    <Field label="Asset version reviewed">
                        <Select value={review.data.asset_id} onChange={(event) => review.setData('asset_id', event.target.value)}>
                            <option value="">No specific file</option>
                            {item.assets.map((asset) => <option key={asset.id} value={asset.id}>{asset.name} v{asset.version}</option>)}
                        </Select>
                    </Field>
                    <Button type="submit" disabled={review.processing}>Submit review</Button>
                </form>
            )}
        </div>
    );
}

function Activity({ item }: { item: Detail }) {
    const comment = useForm({ body: '', mention_ids: [] as number[] });

    return (
        <div className="grid gap-4 lg:grid-cols-2">
            <section className="rounded-xl border border-zinc-200 bg-white p-4">
                <h3 className="font-semibold">Comments</h3>
                <div className="mt-3 space-y-3">
                    {item.comments.length === 0 && <p className="text-sm text-zinc-500">No comments yet.</p>}
                    {item.comments.map((entry) => (
                        <article key={entry.id}><p className="text-sm font-medium">{entry.user?.name}</p><CommentBody body={entry.body} /><p className="text-xs text-zinc-500">{formatDate(entry.created_at)}</p></article>
                    ))}
                </div>
                {item.abilities.comment && (
                    <form className="mt-4 space-y-2" onSubmit={(event) => { event.preventDefault(); comment.post(`/content/${item.id}/comments`, { onSuccess: () => comment.reset('body') }); }}>
                        <Textarea value={comment.data.body} onChange={(event) => comment.setData('body', event.target.value)} placeholder="Add a production note" />
                        {comment.errors.body && <p className="text-sm text-rose-700">{comment.errors.body}</p>}
                        <Button type="submit" disabled={comment.processing}>Comment</Button>
                    </form>
                )}
            </section>
            <section className="rounded-xl border border-zinc-200 bg-white p-4">
                <h3 className="font-semibold">History</h3>
                <ol className="mt-3 space-y-3">
                    {item.activities.map((entry) => (
                        <li key={entry.id} className="text-sm"><span className="font-medium">{entry.user?.name ?? 'System'}</span> {entry.description}<span className="mt-1 block text-xs text-zinc-500">{formatDate(entry.created_at)}</span></li>
                    ))}
                </ol>
            </section>
        </div>
    );
}
