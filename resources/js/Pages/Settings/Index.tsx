import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import QRCode from 'qrcode';
import { FormEvent, useEffect, useMemo, useState } from 'react';
import PlatformIcon from '@/Components/PlatformIcon';
import { Button, Field, Input, PageHeader, Select, Textarea } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { confirmAction } from '@/lib/confirm';
import { tone } from '@/types';

const colorOptions = Object.keys(tone);

interface Role { id: number; name: string; slug: string; description: string | null }
interface Brand { id: number; name: string; description: string | null; website: string | null; industry: string | null; is_active: boolean }
interface Person {
    id: number;
    name: string;
    email: string;
    job_title: string | null;
    is_active: boolean;
    roles: { id: number; name: string }[];
    brands: { id: number; name: string }[];
    social_account_ids: number[];
    social_accounts: { id: number; name: string }[];
}
interface FacebookPageOption {
    id: number;
    name: string;
    brand_id: number;
    brand_name: string | null;
    is_active: boolean;
}
interface StatusRow { id: number; name: string; slug: string; color: string; sort_order: number; allowed_transitions: string[] | null }
interface Named { id: number; name: string; description?: string | null; brand_id?: number | null; brand?: { id?: number; name: string } | null }
interface PlatformRow { id: number; name: string; slug: string; color: string; is_active: boolean; sort_order: number; is_custom: boolean }
interface Invite {
    id: number;
    url: string;
    role: { id: number; name: string; slug: string } | null;
    brand_ids: number[];
    expires_at: string | null;
    max_uses: number | null;
    uses_count: number;
    revoked_at: string | null;
    is_open: boolean;
    created_by: string | null;
    created_at: string | null;
}

const sections = ['general', 'users', 'brands', 'platforms', 'workflow', 'taxonomy'] as const;

export default function Settings({ section, users, roles, brands, facebookPages = [], platforms, statuses, pillars, objectives, segments, tags, settings, invites, abilities }: {
    section: string;
    users: Person[];
    roles: Role[];
    brands: Brand[];
    facebookPages?: FacebookPageOption[];
    platforms: PlatformRow[];
    statuses: StatusRow[];
    pillars: Named[];
    objectives: Named[];
    segments: Named[];
    tags: Named[];
    settings: { deadline_warning_days: number; organization_name: string };
    invites: Invite[];
    abilities: Record<string, boolean>;
}) {
    const visible = sections.filter((item) => {
        if (item === 'users') return users.length > 0 || abilities.users;
        if (item === 'platforms') return abilities.platforms;
        if (item === 'workflow') return abilities.workflow;
        if (item === 'taxonomy') return abilities.taxonomy || abilities.tags;
        if (item === 'general') return abilities.settings;
        return abilities.brands || brands.length > 0;
    });

    return (
        <AppLayout>
            <Head title="Settings" />
            <PageHeader title="Settings" description="Users, brands, workflow, and vocabulary used by the content records." />
            <div className="mb-4 flex flex-wrap gap-2">
                {visible.map((item) => (
                    <button key={item} className={`rounded-full px-3 py-1 text-sm capitalize ${section === item ? 'bg-zinc-900 text-white' : 'bg-white'}`} onClick={() => router.get('/settings', { section: item }, { preserveState: false })}>{item}</button>
                ))}
            </div>
            {section === 'general' && abilities.settings && <GeneralForm settings={settings} />}
            {section === 'users' && <UsersSection users={users} roles={roles} brands={brands} facebookPages={facebookPages} invites={invites} canManage={abilities.users} />}
            {section === 'brands' && <BrandsSection brands={brands} canManage={abilities.brands} />}
            {section === 'platforms' && abilities.platforms && <PlatformsSection platforms={platforms} />}
            {section === 'workflow' && abilities.workflow && <WorkflowSection statuses={statuses} />}
            {section === 'taxonomy' && <TaxonomySection pillars={pillars} objectives={objectives} segments={segments} tags={tags} brands={brands} abilities={abilities} />}
        </AppLayout>
    );
}

function GeneralForm({ settings }: { settings: { deadline_warning_days: number; organization_name: string } }) {
    const form = useForm({ organization_name: settings.organization_name, deadline_warning_days: String(settings.deadline_warning_days) });
    return (
        <form className="max-w-xl space-y-3 rounded-xl border border-zinc-200 bg-white p-4" onSubmit={(event) => { event.preventDefault(); form.put('/settings/general'); }}>
            <Field label="Organization name" error={form.errors.organization_name}><Input value={form.data.organization_name} onChange={(event) => form.setData('organization_name', event.target.value)} /></Field>
            <Field label="Deadline warning (days)" error={form.errors.deadline_warning_days}><Input type="number" min={1} max={30} value={form.data.deadline_warning_days} onChange={(event) => form.setData('deadline_warning_days', event.target.value)} /></Field>
            <Button type="submit" disabled={form.processing}>Save settings</Button>
        </form>
    );
}

function UsersSection({ users, roles, brands, facebookPages, invites, canManage }: { users: Person[]; roles: Role[]; brands: Brand[]; facebookPages: FacebookPageOption[]; invites: Invite[]; canManage: boolean }) {
    const form = useForm({
        name: '',
        email: '',
        password: '',
        job_title: '',
        is_active: true,
        role_ids: [] as number[],
        brand_ids: [] as number[],
        social_account_ids: [] as number[],
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/settings/users', { onSuccess: () => form.reset() });
    }

    function setBrandIds(ids: number[]) {
        const nextPages = form.data.social_account_ids.filter((pageId) => {
            const page = facebookPages.find((item) => item.id === pageId);
            return page ? ids.includes(page.brand_id) : false;
        });
        form.setData({
            ...form.data,
            brand_ids: ids,
            social_account_ids: nextPages,
        });
    }

    const createPageOptions = facebookPages
        .filter((page) => form.data.brand_ids.includes(page.brand_id))
        .map((page) => ({
            id: page.id,
            name: page.brand_name ? `${page.name} (${page.brand_name})` : page.name,
        }));

    return (
        <div className="space-y-4">
            {canManage && <InvitePanel roles={roles} brands={brands} invites={invites} />}
            <div className="grid gap-4 lg:grid-cols-[1fr_22rem]">
                <div className="divide-y divide-zinc-100 rounded-xl border border-zinc-200 bg-white">
                    {users.map((person) => (
                        <div key={person.id} className="px-4 py-3 text-sm">
                            <p className="font-medium">{person.name} {!person.is_active && <span className="text-rose-700">(inactive)</span>}</p>
                            <p className="text-zinc-500">{person.email} · {person.job_title || 'No title'}</p>
                            <p className="text-zinc-600">{person.roles.map((role) => role.name).join(', ')} · {person.brands.map((brand) => brand.name).join(', ') || 'No brands'}</p>
                            <p className="text-zinc-600">
                                Pages: {(person.social_accounts ?? []).length > 0
                                    ? person.social_accounts.map((page) => page.name).join(', ')
                                    : 'None (cannot post)'}
                            </p>
                            {canManage && <UserEditor person={person} roles={roles} brands={brands} facebookPages={facebookPages} />}
                        </div>
                    ))}
                </div>
                {canManage && (
                    <form onSubmit={submit} className="space-y-3 rounded-xl border border-zinc-200 bg-white p-4">
                        <h2 className="font-semibold">New user</h2>
                        <Field label="Name" error={form.errors.name}><Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required /></Field>
                        <Field label="Email" error={form.errors.email}><Input type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} required /></Field>
                        <Field label="Temporary password" error={form.errors.password}><Input type="password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} required /></Field>
                        <Field label="Job title"><Input value={form.data.job_title} onChange={(event) => form.setData('job_title', event.target.value)} /></Field>
                        <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.data.is_active} onChange={(event) => form.setData('is_active', event.target.checked)} /> Active</label>
                        <ChoiceGroup label="Roles" error={form.errors.role_ids} options={roles} selected={form.data.role_ids} onChange={(ids) => form.setData('role_ids', ids)} />
                        <ChoiceGroup label="Brand access" options={brands} selected={form.data.brand_ids} onChange={setBrandIds} />
                        <ChoiceGroup
                            label="Facebook page posting access"
                            error={form.errors.social_account_ids}
                            options={createPageOptions}
                            selected={form.data.social_account_ids}
                            onChange={(ids) => form.setData('social_account_ids', ids)}
                            emptyHint={form.data.brand_ids.length === 0 ? 'Select brands first to choose pages.' : 'No Facebook pages for the selected brands.'}
                        />
                        <Button type="submit" disabled={form.processing}>Create user</Button>
                    </form>
                )}
            </div>
        </div>
    );
}

function InvitePanel({ roles, brands, invites }: { roles: Role[]; brands: Brand[]; invites: Invite[] }) {
    const inviteRoles = useMemo(() => roles.filter((role) => role.slug !== 'administrator'), [roles]);
    const defaultRole = inviteRoles.find((role) => role.slug === 'creative')?.id ?? inviteRoles[0]?.id ?? '';
    const form = useForm({
        role_id: String(defaultRole),
        brand_ids: [] as number[],
        expires_in_days: '14',
        max_uses: '25',
    });
    const openInvite = invites.find((invite) => invite.is_open) ?? invites[0] ?? null;
    const [qrSrc, setQrSrc] = useState('');
    const [copied, setCopied] = useState(false);

    useEffect(() => {
        if (!openInvite?.url) {
            setQrSrc('');
            return;
        }
        let cancelled = false;
        QRCode.toDataURL(openInvite.url, { width: 220, margin: 1 })
            .then((url) => { if (!cancelled) setQrSrc(url); })
            .catch(() => { if (!cancelled) setQrSrc(''); });
        return () => { cancelled = true; };
    }, [openInvite?.url]);

    async function copyLink() {
        if (!openInvite?.url) return;
        await navigator.clipboard.writeText(openInvite.url);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 2000);
    }

    return (
        <section className="rounded-xl border border-zinc-200 bg-white p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="font-semibold">Invite teammates</h2>
                    <p className="mt-1 text-sm text-zinc-600">Share a link or QR code so people can register themselves.</p>
                </div>
            </div>
            <div className="mt-4 grid gap-4 lg:grid-cols-[1fr_auto]">
                <form
                    className="space-y-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post('/settings/invites', { preserveScroll: true });
                    }}
                >
                    <Field label="Role for new members" error={form.errors.role_id}>
                        <Select value={form.data.role_id} onChange={(event) => form.setData('role_id', event.target.value)} required>
                            {inviteRoles.map((role) => <option key={role.id} value={role.id}>{role.name}</option>)}
                        </Select>
                    </Field>
                    <ChoiceGroup label="Brand access" options={brands} selected={form.data.brand_ids} onChange={(ids) => form.setData('brand_ids', ids)} />
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Field label="Expires in (days)" error={form.errors.expires_in_days}>
                            <Input type="number" min={1} max={90} value={form.data.expires_in_days} onChange={(event) => form.setData('expires_in_days', event.target.value)} />
                        </Field>
                        <Field label="Max uses" error={form.errors.max_uses}>
                            <Input type="number" min={1} max={500} value={form.data.max_uses} onChange={(event) => form.setData('max_uses', event.target.value)} />
                        </Field>
                    </div>
                    <Button type="submit" disabled={form.processing}>{form.processing ? 'Creating…' : 'Create invite link'}</Button>
                </form>
                {openInvite && (
                    <div className="flex flex-col items-center gap-3 rounded-lg border border-zinc-200 bg-zinc-50 p-4">
                        {qrSrc ? <img src={qrSrc} alt="Invite QR code" className="h-[220px] w-[220px] rounded-md bg-white" /> : <div className="grid h-[220px] w-[220px] place-items-center text-sm text-zinc-500">Generating QR…</div>}
                        <p className={`text-xs font-medium ${openInvite.is_open ? 'text-emerald-700' : 'text-rose-700'}`}>
                            {openInvite.is_open ? 'Active invite' : 'Closed invite'} · {openInvite.role?.name}
                        </p>
                        <p className="max-w-xs break-all text-center text-xs text-zinc-600">{openInvite.url}</p>
                        <div className="flex flex-wrap justify-center gap-2">
                            <Button type="button" size="sm" variant="secondary" onClick={copyLink}>{copied ? 'Copied' : 'Copy link'}</Button>
                            {openInvite.is_open && (
                                <Button
                                type="button"
                                size="sm"
                                variant="secondary"
                                onClick={async () => {
                                    const ok = await confirmAction({
                                        title: 'Revoke invite?',
                                        message: 'People with this link will no longer be able to join.',
                                        confirmLabel: 'Revoke',
                                        tone: 'warning',
                                    });
                                    if (ok) router.delete(`/settings/invites/${openInvite.id}`, { preserveScroll: true });
                                }}
                            >
                                Revoke
                            </Button>
                            )}
                        </div>
                        <p className="text-xs text-zinc-500">
                            Used {openInvite.uses_count}{openInvite.max_uses ? ` / ${openInvite.max_uses}` : ''}
                            {openInvite.expires_at ? ` · expires ${new Date(openInvite.expires_at).toLocaleDateString()}` : ''}
                        </p>
                    </div>
                )}
            </div>
            {invites.length > 1 && (
                <ul className="mt-4 space-y-2 border-t border-zinc-100 pt-3 text-sm">
                    {invites.slice(0, 8).map((invite) => (
                        <li key={invite.id} className="flex flex-wrap items-center justify-between gap-2 text-zinc-600">
                            <span>{invite.role?.name} · {invite.is_open ? 'Open' : 'Closed'} · {invite.uses_count}{invite.max_uses ? `/${invite.max_uses}` : ''} uses</span>
                            {invite.is_open && (
                                <button
                                    type="button"
                                    className="text-brand-700 hover:underline"
                                    onClick={async () => {
                                        const ok = await confirmAction({
                                            title: 'Revoke invite?',
                                            message: 'People with this link will no longer be able to join.',
                                            confirmLabel: 'Revoke',
                                            tone: 'warning',
                                        });
                                        if (ok) router.delete(`/settings/invites/${invite.id}`, { preserveScroll: true });
                                    }}
                                >
                                    Revoke
                                </button>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

function UserEditor({ person, roles, brands, facebookPages }: { person: Person; roles: Role[]; brands: Brand[]; facebookPages: FacebookPageOption[] }) {
    const form = useForm({
        name: person.name,
        email: person.email,
        password: '',
        job_title: person.job_title ?? '',
        is_active: person.is_active,
        role_ids: person.roles.map((role) => role.id),
        brand_ids: person.brands.map((brand) => brand.id),
        social_account_ids: person.social_account_ids ?? [],
    });

    function setBrandIds(ids: number[]) {
        const nextPages = form.data.social_account_ids.filter((pageId) => {
            const page = facebookPages.find((item) => item.id === pageId);
            return page ? ids.includes(page.brand_id) : false;
        });
        form.setData({
            ...form.data,
            brand_ids: ids,
            social_account_ids: nextPages,
        });
    }

    const pageOptions = facebookPages
        .filter((page) => form.data.brand_ids.includes(page.brand_id))
        .map((page) => ({
            id: page.id,
            name: page.brand_name ? `${page.name} (${page.brand_name})` : page.name,
        }));

    return (
        <details className="mt-2">
            <summary className="cursor-pointer text-brand-700">Edit access</summary>
            <form className="mt-2 grid gap-2" onSubmit={(event) => { event.preventDefault(); form.put(`/settings/users/${person.id}`); }}>
                <Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />
                <Input type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} />
                <Input type="password" placeholder="Leave blank to keep the password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} />
                <label className="flex items-center gap-2"><input type="checkbox" checked={form.data.is_active} onChange={(event) => form.setData('is_active', event.target.checked)} /> Active</label>
                <ChoiceGroup label="Roles" error={form.errors.role_ids} options={roles} selected={form.data.role_ids} onChange={(ids) => form.setData('role_ids', ids)} />
                <ChoiceGroup label="Brands" options={brands} selected={form.data.brand_ids} onChange={setBrandIds} />
                <ChoiceGroup
                    label="Facebook page posting access"
                    error={form.errors.social_account_ids}
                    options={pageOptions}
                    selected={form.data.social_account_ids}
                    onChange={(ids) => form.setData('social_account_ids', ids)}
                    emptyHint={form.data.brand_ids.length === 0 ? 'Select brands first to choose pages.' : 'No Facebook pages for the selected brands.'}
                />
                {form.errors.email && <p className="text-sm text-rose-700">{form.errors.email}</p>}
                <Button type="submit" size="sm" disabled={form.processing}>Save user</Button>
            </form>
        </details>
    );
}

function ChoiceGroup({ label, options, selected, onChange, error, emptyHint }: { label: string; options: { id: number; name: string }[]; selected: number[]; onChange: (ids: number[]) => void; error?: string; emptyHint?: string }) {
    return (
        <fieldset>
            <legend className="mb-1 text-sm font-medium">{label}</legend>
            <div className="grid gap-1">
                {options.length === 0 && emptyHint ? (
                    <p className="text-xs text-zinc-500">{emptyHint}</p>
                ) : (
                    options.map((option) => (
                        <label key={option.id} className="flex items-center gap-2 text-sm">
                            <input type="checkbox" checked={selected.includes(option.id)} onChange={() => onChange(selected.includes(option.id) ? selected.filter((id) => id !== option.id) : [...selected, option.id])} />
                            {option.name}
                        </label>
                    ))
                )}
            </div>
            {error && <p className="text-sm text-rose-700">{error}</p>}
        </fieldset>
    );
}

function BrandsSection({ brands, canManage }: { brands: Brand[]; canManage: boolean }) {
    const form = useForm({ name: '', description: '', website: '', industry: '', is_active: true });
    return (
        <div className="grid gap-4 lg:grid-cols-[1fr_22rem]">
            <div className="divide-y divide-zinc-100 rounded-xl border border-zinc-200 bg-white">
                {brands.map((brand) => (
                    <div key={brand.id} className="px-4 py-3 text-sm">
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <p className="font-medium">{brand.name} {!brand.is_active && <span className="text-rose-700">(inactive)</span>}</p>
                                <p className="text-zinc-500">{brand.industry || 'No industry'} · {brand.website || 'No website'}</p>
                                {brand.description && <p className="mt-1 text-zinc-600">{brand.description}</p>}
                            </div>
                            {canManage && (
                                <button
                                    type="button"
                                    className="shrink-0 text-xs text-rose-700 hover:underline"
                                    onClick={async () => {
                                        const ok = await confirmAction({
                                            title: 'Delete brand?',
                                            message: `Delete “${brand.name}”? Related content stays, but this brand is removed from settings.`,
                                            confirmLabel: 'Delete brand',
                                        });
                                        if (ok) router.delete(`/settings/brands/${brand.id}`, { preserveScroll: true });
                                    }}
                                >
                                    Delete
                                </button>
                            )}
                        </div>
                        {canManage && <BrandEditor brand={brand} />}
                    </div>
                ))}
                {brands.length === 0 && <p className="px-4 py-6 text-sm text-zinc-500">No brands yet.</p>}
            </div>
            {canManage && (
                <form className="space-y-3 rounded-xl border border-zinc-200 bg-white p-4" onSubmit={(event) => { event.preventDefault(); form.post('/settings/brands', { onSuccess: () => form.reset('name', 'description', 'website', 'industry') }); }}>
                    <h2 className="font-semibold">New brand</h2>
                    <Field label="Name" error={form.errors.name}><Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required /></Field>
                    <Field label="Industry"><Input value={form.data.industry} onChange={(event) => form.setData('industry', event.target.value)} /></Field>
                    <Field label="Website" error={form.errors.website}><Input value={form.data.website} onChange={(event) => form.setData('website', event.target.value)} placeholder="https://" /></Field>
                    <Field label="Description"><Textarea value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} /></Field>
                    <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.data.is_active} onChange={(event) => form.setData('is_active', event.target.checked)} /> Active</label>
                    <Button type="submit" disabled={form.processing}>Create brand</Button>
                </form>
            )}
        </div>
    );
}

function BrandEditor({ brand }: { brand: Brand }) {
    const form = useForm({
        name: brand.name,
        description: brand.description ?? '',
        website: brand.website ?? '',
        industry: brand.industry ?? '',
        is_active: brand.is_active,
    });

    return (
        <details className="mt-2">
            <summary className="cursor-pointer text-brand-700">Edit brand</summary>
            <form className="mt-2 grid gap-2" onSubmit={(event) => { event.preventDefault(); form.put(`/settings/brands/${brand.id}`, { preserveScroll: true }); }}>
                <Field label="Name" error={form.errors.name}><Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required /></Field>
                <Field label="Industry"><Input value={form.data.industry} onChange={(event) => form.setData('industry', event.target.value)} /></Field>
                <Field label="Website" error={form.errors.website}><Input value={form.data.website} onChange={(event) => form.setData('website', event.target.value)} placeholder="https://" /></Field>
                <Field label="Description"><Textarea value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} /></Field>
                <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.data.is_active} onChange={(event) => form.setData('is_active', event.target.checked)} /> Active</label>
                <Button type="submit" size="sm" disabled={form.processing}>Save brand</Button>
            </form>
        </details>
    );
}

function PlatformsSection({ platforms }: { platforms: PlatformRow[] }) {
    const form = useForm({ name: '', color: 'zinc' });
    const [editing, setEditing] = useState<PlatformRow | null>(null);
    const editForm = useForm({
        name: '',
        color: 'zinc',
        is_active: true,
        sort_order: 0,
    });

    function startEdit(platform: PlatformRow) {
        setEditing(platform);
        editForm.setData({
            name: platform.name,
            color: platform.color || 'zinc',
            is_active: platform.is_active,
            sort_order: platform.sort_order,
        });
        editForm.clearErrors();
    }

    function saveEdit(event: FormEvent) {
        event.preventDefault();
        if (!editing) return;
        editForm.put(`/settings/platforms/${editing.id}`, {
            preserveScroll: true,
            onSuccess: () => setEditing(null),
        });
    }

    return (
        <div className="grid gap-4 lg:grid-cols-[1fr_20rem]">
            <div className="space-y-3">
                <ul className="divide-y divide-zinc-100 rounded-xl border border-zinc-200 bg-white">
                    {platforms.map((platform) => (
                        <li key={platform.id} className="flex items-center justify-between gap-3 px-4 py-3">
                            <div className="flex min-w-0 items-center gap-3">
                                <PlatformIcon slug={platform.slug} name={platform.name} color={platform.color} />
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-medium text-zinc-900">{platform.name}</p>
                                    <p className="text-xs text-zinc-500">
                                        {platform.is_active ? 'Active' : 'Hidden'}
                                        {platform.is_custom ? ' · Custom' : ''}
                                        {' · '}
                                        <span className="capitalize">{platform.color}</span>
                                    </p>
                                </div>
                            </div>
                            <span className="flex shrink-0 items-center gap-0.5">
                                <button
                                    type="button"
                                    aria-label={`Edit ${platform.name}`}
                                    className="rounded-md p-1.5 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900"
                                    onClick={() => startEdit(platform)}
                                >
                                    <Pencil className="h-4 w-4" />
                                </button>
                                {platform.is_custom && (
                                    <button
                                        type="button"
                                        aria-label={`Delete ${platform.name}`}
                                        className="rounded-md p-1.5 text-zinc-500 hover:bg-rose-50 hover:text-rose-700"
                                        onClick={async () => {
                                            const ok = await confirmAction({
                                                title: 'Delete platform?',
                                                message: `Delete “${platform.name}”? This cannot be undone.`,
                                                confirmLabel: 'Delete',
                                            });
                                            if (ok) router.delete(`/settings/platforms/${platform.id}`, { preserveScroll: true });
                                        }}
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </button>
                                )}
                            </span>
                        </li>
                    ))}
                    {platforms.length === 0 && <li className="px-4 py-6 text-sm text-zinc-500">No platforms yet.</li>}
                </ul>
                {editing && (
                    <form className="space-y-3 rounded-xl border border-zinc-200 bg-zinc-50 p-4" onSubmit={saveEdit}>
                        <div className="flex items-center gap-3">
                            <PlatformIcon slug={editing.slug} name={editForm.data.name || editing.name} color={editForm.data.color} />
                            <p className="text-sm font-medium text-zinc-800">Editing {editing.name}</p>
                        </div>
                        <Field label="Name" error={editForm.errors.name}>
                            <Input value={editForm.data.name} onChange={(event) => editForm.setData('name', event.target.value)} required />
                        </Field>
                        <Field label="Color">
                            <div className="flex items-center gap-2">
                                <Select
                                    value={colorOptions.includes(editForm.data.color) ? editForm.data.color : 'zinc'}
                                    onChange={(event) => editForm.setData('color', event.target.value)}
                                >
                                    {colorOptions.map((color) => <option key={color} value={color}>{color}</option>)}
                                </Select>
                                <span className={`shrink-0 rounded-full px-2.5 py-1 text-xs font-medium capitalize ${tone[editForm.data.color] ?? tone.zinc}`}>
                                    {editForm.data.color}
                                </span>
                            </div>
                        </Field>
                        <Field label="Order">
                            <Input
                                type="number"
                                value={editForm.data.sort_order}
                                onChange={(event) => editForm.setData('sort_order', Number(event.target.value))}
                            />
                        </Field>
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={editForm.data.is_active}
                                onChange={(event) => editForm.setData('is_active', event.target.checked)}
                            />
                            Active (shown in content filters)
                        </label>
                        {(editForm.errors.is_active || editForm.errors.sort_order) && (
                            <p className="text-sm text-rose-700">{editForm.errors.is_active || editForm.errors.sort_order}</p>
                        )}
                        <div className="flex gap-2">
                            <Button type="submit" size="sm" disabled={editForm.processing}>Save platform</Button>
                            <Button type="button" size="sm" variant="secondary" onClick={() => setEditing(null)}>Cancel</Button>
                        </div>
                    </form>
                )}
            </div>
            <form className="h-fit space-y-3 rounded-xl border border-zinc-200 bg-white p-4" onSubmit={(event) => { event.preventDefault(); form.post('/settings/platforms', { onSuccess: () => form.reset('name') }); }}>
                <h2 className="font-semibold">Custom platform</h2>
                <Field label="Name" error={form.errors.name}><Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required /></Field>
                <Field label="Color">
                    <div className="flex items-center gap-2">
                        <Select value={form.data.color} onChange={(event) => form.setData('color', event.target.value)}>
                            {colorOptions.map((color) => <option key={color} value={color}>{color}</option>)}
                        </Select>
                        <PlatformIcon slug="custom" name={form.data.name || 'New'} color={form.data.color} size="sm" />
                    </div>
                </Field>
                <Button type="submit" disabled={form.processing}>Add platform</Button>
            </form>
        </div>
    );
}

function WorkflowSection({ statuses }: { statuses: StatusRow[] }) {
    return (
        <div className="space-y-3">
            <p className="text-sm text-zinc-600">Moving a card still checks the destination permission and required fields. Changing the graph here does not grant approval or publishing rights.</p>
            {statuses.map((status) => <WorkflowRow key={status.id} status={status} statuses={statuses} />)}
        </div>
    );
}

function WorkflowRow({ status, statuses }: { status: StatusRow; statuses: StatusRow[] }) {
    const form = useForm({
        name: status.name,
        color: status.color,
        sort_order: status.sort_order,
        allowed_transitions: status.allowed_transitions ?? [],
    });

    return (
        <form className="rounded-xl border border-zinc-200 bg-white p-4" onSubmit={(event) => { event.preventDefault(); form.put(`/settings/workflow/${status.id}`); }}>
            <div className="grid gap-3 md:grid-cols-3">
                <Field label={`${status.slug} label`}><Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} /></Field>
                <Field label="Color">
                    <div className="flex items-center gap-2">
                        <Select
                            value={colorOptions.includes(form.data.color) ? form.data.color : 'zinc'}
                            onChange={(event) => form.setData('color', event.target.value)}
                        >
                            {colorOptions.map((color) => <option key={color} value={color}>{color}</option>)}
                        </Select>
                        <span className={`shrink-0 rounded-full px-2.5 py-1 text-xs font-medium capitalize ${tone[form.data.color] ?? tone.zinc}`}>
                            {form.data.color}
                        </span>
                    </div>
                </Field>
                <Field label="Order"><Input type="number" value={form.data.sort_order} onChange={(event) => form.setData('sort_order', Number(event.target.value))} /></Field>
            </div>
            <div className="mt-3 flex flex-wrap gap-3">
                {statuses.filter((option) => option.slug !== status.slug).map((option) => (
                    <label key={option.slug} className="flex items-center gap-2 text-sm">
                        <input type="checkbox" checked={form.data.allowed_transitions.includes(option.slug)} onChange={() => form.setData('allowed_transitions', form.data.allowed_transitions.includes(option.slug) ? form.data.allowed_transitions.filter((slug) => slug !== option.slug) : [...form.data.allowed_transitions, option.slug])} />
                        {option.name}
                    </label>
                ))}
            </div>
            <Button className="mt-3" size="sm" type="submit" disabled={form.processing}>Save stage</Button>
        </form>
    );
}

function TaxonomySection({ pillars, objectives, segments, tags, brands, abilities }: { pillars: Named[]; objectives: Named[]; segments: Named[]; tags: Named[]; brands: Brand[]; abilities: Record<string, boolean> }) {
    return (
        <div className="grid gap-4 lg:grid-cols-2">
            {abilities.taxonomy && <NameForm title="Content pillars" endpoint="/settings/pillars" rows={pillars} canManage />}
            {abilities.taxonomy && <NameForm title="Objectives" endpoint="/settings/objectives" rows={objectives} canManage />}
            {abilities.taxonomy && <NameForm title="Audience segments" endpoint="/settings/segments" rows={segments} brands={brands} canManage />}
            {abilities.tags && <NameForm title="Tags" endpoint="/settings/tags" rows={tags} brands={brands} canManage />}
        </div>
    );
}

function NameForm({ title, endpoint, rows, brands, canManage }: { title: string; endpoint: string; rows: Named[]; brands?: Brand[]; canManage?: boolean }) {
    const form = useForm({ name: '', description: '', brand_id: '' });
    const [editing, setEditing] = useState<Named | null>(null);
    const editForm = useForm({ name: '', description: '', brand_id: '' });

    function startEdit(row: Named) {
        setEditing(row);
        editForm.setData({
            name: row.name,
            description: row.description ?? '',
            brand_id: row.brand_id != null ? String(row.brand_id) : '',
        });
        editForm.clearErrors();
    }

    function saveEdit(event: FormEvent) {
        event.preventDefault();
        if (!editing) return;
        editForm.put(`${endpoint}/${editing.id}`, {
            preserveScroll: true,
            onSuccess: () => setEditing(null),
        });
    }

    return (
        <section className="rounded-xl border border-zinc-200 bg-white p-4">
            <h2 className="font-semibold">{title}</h2>
            <ul className="my-3 space-y-1 text-sm text-zinc-700">
                {rows.map((row) => (
                    <li key={row.id} className="flex items-center justify-between gap-2 rounded-md px-1 py-1 hover:bg-zinc-50">
                        <span>{row.name}{row.brand?.name ? ` · ${row.brand.name}` : ''}</span>
                        {canManage && (
                            <span className="flex shrink-0 items-center gap-0.5">
                                <button type="button" aria-label={`Edit ${row.name}`} className="rounded-md p-1.5 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900" onClick={() => startEdit(row)}>
                                    <Pencil className="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    aria-label={`Delete ${row.name}`}
                                    className="rounded-md p-1.5 text-zinc-500 hover:bg-rose-50 hover:text-rose-700"
                                    onClick={async () => {
                                        const ok = await confirmAction({
                                            title: 'Delete item?',
                                            message: `Delete “${row.name}”? This cannot be undone.`,
                                            confirmLabel: 'Delete',
                                        });
                                        if (ok) router.delete(`${endpoint}/${row.id}`, { preserveScroll: true });
                                    }}
                                >
                                    <Trash2 className="h-4 w-4" />
                                </button>
                            </span>
                        )}
                    </li>
                ))}
                {rows.length === 0 && <li className="px-1 py-2 text-zinc-500">None yet.</li>}
            </ul>
            {editing && (
                <form className="mb-3 space-y-2 rounded-lg border border-zinc-200 bg-zinc-50 p-3" onSubmit={saveEdit}>
                    <p className="text-xs font-medium text-zinc-600">Editing {editing.name}</p>
                    <Input value={editForm.data.name} onChange={(event) => editForm.setData('name', event.target.value)} required />
                    {brands && (
                        <Select value={editForm.data.brand_id} onChange={(event) => editForm.setData('brand_id', event.target.value)}>
                            <option value="">All brands</option>
                            {brands.map((brand) => <option key={brand.id} value={brand.id}>{brand.name}</option>)}
                        </Select>
                    )}
                    {editForm.errors.name && <p className="text-sm text-rose-700">{editForm.errors.name}</p>}
                    <div className="flex gap-2">
                        <Button type="submit" size="sm" disabled={editForm.processing}>Save</Button>
                        <Button type="button" size="sm" variant="secondary" onClick={() => setEditing(null)}>Cancel</Button>
                    </div>
                </form>
            )}
            <form className="space-y-2" onSubmit={(event) => { event.preventDefault(); form.post(endpoint, { onSuccess: () => form.reset('name', 'description', 'brand_id') }); }}>
                <Input value={form.data.name} placeholder="Name" onChange={(event) => form.setData('name', event.target.value)} required />
                {brands && <Select value={form.data.brand_id} onChange={(event) => form.setData('brand_id', event.target.value)}><option value="">All brands</option>{brands.map((brand) => <option key={brand.id} value={brand.id}>{brand.name}</option>)}</Select>}
                {form.errors.name && <p className="text-sm text-rose-700">{form.errors.name}</p>}
                <Button type="submit" size="sm">Add</Button>
            </form>
        </section>
    );
}
