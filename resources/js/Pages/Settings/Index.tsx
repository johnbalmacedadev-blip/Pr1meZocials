import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { Button, Field, Input, PageHeader, Select, Textarea } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';

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
}
interface StatusRow { id: number; name: string; slug: string; color: string; sort_order: number; allowed_transitions: string[] | null }
interface Named { id: number; name: string; description?: string | null; brand?: { name: string } | null }
interface PlatformRow { id: number; name: string; color: string; is_active: boolean; sort_order: number; is_custom: boolean }

const sections = ['general', 'users', 'brands', 'platforms', 'workflow', 'taxonomy'] as const;

export default function Settings({ section, users, roles, brands, platforms, statuses, pillars, objectives, segments, tags, settings, abilities }: {
    section: string;
    users: Person[];
    roles: Role[];
    brands: Brand[];
    platforms: PlatformRow[];
    statuses: StatusRow[];
    pillars: Named[];
    objectives: Named[];
    segments: Named[];
    tags: Named[];
    settings: { deadline_warning_days: number; organization_name: string };
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
            {section === 'users' && <UsersSection users={users} roles={roles} brands={brands} canManage={abilities.users} />}
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

function UsersSection({ users, roles, brands, canManage }: { users: Person[]; roles: Role[]; brands: Brand[]; canManage: boolean }) {
    const form = useForm({ name: '', email: '', password: '', job_title: '', is_active: true, role_ids: [] as number[], brand_ids: [] as number[] });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/settings/users', { onSuccess: () => form.reset() });
    }

    return (
        <div className="grid gap-4 lg:grid-cols-[1fr_22rem]">
            <div className="divide-y divide-zinc-100 rounded-xl border border-zinc-200 bg-white">
                {users.map((person) => (
                    <div key={person.id} className="px-4 py-3 text-sm">
                        <p className="font-medium">{person.name} {!person.is_active && <span className="text-rose-700">(inactive)</span>}</p>
                        <p className="text-zinc-500">{person.email} · {person.job_title || 'No title'}</p>
                        <p className="text-zinc-600">{person.roles.map((role) => role.name).join(', ')} · {person.brands.map((brand) => brand.name).join(', ') || 'No brands'}</p>
                        {canManage && <UserEditor person={person} roles={roles} brands={brands} />}
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
                    <ChoiceGroup label="Brand access" options={brands} selected={form.data.brand_ids} onChange={(ids) => form.setData('brand_ids', ids)} />
                    <Button type="submit" disabled={form.processing}>Create user</Button>
                </form>
            )}
        </div>
    );
}

function UserEditor({ person, roles, brands }: { person: Person; roles: Role[]; brands: Brand[] }) {
    const form = useForm({
        name: person.name,
        email: person.email,
        password: '',
        job_title: person.job_title ?? '',
        is_active: person.is_active,
        role_ids: person.roles.map((role) => role.id),
        brand_ids: person.brands.map((brand) => brand.id),
    });

    return (
        <details className="mt-2">
            <summary className="cursor-pointer text-teal-800">Edit access</summary>
            <form className="mt-2 grid gap-2" onSubmit={(event) => { event.preventDefault(); form.put(`/settings/users/${person.id}`); }}>
                <Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />
                <Input type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} />
                <Input type="password" placeholder="Leave blank to keep the password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} />
                <label className="flex items-center gap-2"><input type="checkbox" checked={form.data.is_active} onChange={(event) => form.setData('is_active', event.target.checked)} /> Active</label>
                <ChoiceGroup label="Roles" error={form.errors.role_ids} options={roles} selected={form.data.role_ids} onChange={(ids) => form.setData('role_ids', ids)} />
                <ChoiceGroup label="Brands" options={brands} selected={form.data.brand_ids} onChange={(ids) => form.setData('brand_ids', ids)} />
                {form.errors.email && <p className="text-sm text-rose-700">{form.errors.email}</p>}
                <Button type="submit" size="sm" disabled={form.processing}>Save user</Button>
            </form>
        </details>
    );
}

function ChoiceGroup({ label, options, selected, onChange, error }: { label: string; options: { id: number; name: string }[]; selected: number[]; onChange: (ids: number[]) => void; error?: string }) {
    return (
        <fieldset>
            <legend className="mb-1 text-sm font-medium">{label}</legend>
            <div className="grid gap-1">
                {options.map((option) => (
                    <label key={option.id} className="flex items-center gap-2 text-sm">
                        <input type="checkbox" checked={selected.includes(option.id)} onChange={() => onChange(selected.includes(option.id) ? selected.filter((id) => id !== option.id) : [...selected, option.id])} />
                        {option.name}
                    </label>
                ))}
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
                {brands.map((brand) => <div key={brand.id} className="px-4 py-3 text-sm"><p className="font-medium">{brand.name}</p><p className="text-zinc-500">{brand.industry || 'No industry'} · {brand.website || 'No website'}</p></div>)}
            </div>
            {canManage && (
                <form className="space-y-3 rounded-xl border border-zinc-200 bg-white p-4" onSubmit={(event) => { event.preventDefault(); form.post('/settings/brands', { onSuccess: () => form.reset('name', 'description', 'website', 'industry') }); }}>
                    <h2 className="font-semibold">New brand</h2>
                    <Field label="Name" error={form.errors.name}><Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required /></Field>
                    <Field label="Industry"><Input value={form.data.industry} onChange={(event) => form.setData('industry', event.target.value)} /></Field>
                    <Field label="Website" error={form.errors.website}><Input value={form.data.website} onChange={(event) => form.setData('website', event.target.value)} placeholder="https://" /></Field>
                    <Field label="Description"><Textarea value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} /></Field>
                    <Button type="submit" disabled={form.processing}>Create brand</Button>
                </form>
            )}
        </div>
    );
}

function PlatformsSection({ platforms }: { platforms: PlatformRow[] }) {
    const form = useForm({ name: '', color: 'zinc' });
    return (
        <div className="grid gap-4 lg:grid-cols-[1fr_20rem]">
            <ul className="divide-y divide-zinc-100 rounded-xl border border-zinc-200 bg-white">
                {platforms.map((platform) => <li key={platform.id} className="px-4 py-3 text-sm">{platform.name} · {platform.is_active ? 'Active' : 'Hidden'} {platform.is_custom ? '· Custom' : ''}</li>)}
            </ul>
            <form className="space-y-3 rounded-xl border border-zinc-200 bg-white p-4" onSubmit={(event) => { event.preventDefault(); form.post('/settings/platforms', { onSuccess: () => form.reset('name') }); }}>
                <Field label="Custom platform" error={form.errors.name}><Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required /></Field>
                <Field label="Color"><Select value={form.data.color} onChange={(event) => form.setData('color', event.target.value)}>{['zinc', 'sky', 'rose', 'teal', 'amber', 'indigo'].map((color) => <option key={color} value={color}>{color}</option>)}</Select></Field>
                <Button type="submit">Add platform</Button>
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
                <Field label="Color"><Input value={form.data.color} onChange={(event) => form.setData('color', event.target.value)} /></Field>
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
            {abilities.taxonomy && <NameForm title="Content pillars" endpoint="/settings/pillars" rows={pillars} />}
            {abilities.taxonomy && <NameForm title="Objectives" endpoint="/settings/objectives" rows={objectives} />}
            {abilities.taxonomy && <NameForm title="Audience segments" endpoint="/settings/segments" rows={segments} brands={brands} />}
            {abilities.tags && <NameForm title="Tags" endpoint="/settings/tags" rows={tags} brands={brands} />}
        </div>
    );
}

function NameForm({ title, endpoint, rows, brands }: { title: string; endpoint: string; rows: Named[]; brands?: Brand[] }) {
    const form = useForm({ name: '', description: '', brand_id: '' });
    return (
        <section className="rounded-xl border border-zinc-200 bg-white p-4">
            <h2 className="font-semibold">{title}</h2>
            <ul className="my-3 space-y-1 text-sm text-zinc-700">{rows.map((row) => <li key={row.id}>{row.name}{row.brand?.name ? ` · ${row.brand.name}` : ''}</li>)}</ul>
            <form className="space-y-2" onSubmit={(event) => { event.preventDefault(); form.post(endpoint, { onSuccess: () => form.reset('name', 'description') }); }}>
                <Input value={form.data.name} placeholder="Name" onChange={(event) => form.setData('name', event.target.value)} required />
                {brands && <Select value={form.data.brand_id} onChange={(event) => form.setData('brand_id', event.target.value)}><option value="">All brands</option>{brands.map((brand) => <option key={brand.id} value={brand.id}>{brand.name}</option>)}</Select>}
                {form.errors.name && <p className="text-sm text-rose-700">{form.errors.name}</p>}
                <Button type="submit" size="sm">Add</Button>
            </form>
        </section>
    );
}
