import { Head, Link, router, useForm } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { FormEvent, useEffect, useMemo, useState } from 'react';
import { Button, Field, Input, Modal, PageHeader, Select } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { confirmAction } from '@/lib/confirm';

interface RoleOption {
    id: number;
    name: string;
}

interface RoleMatrix {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    matrix: Record<string, string[]>;
    extras: string[];
}

interface PageDef {
    key: string;
    label: string;
    actions: string[];
}

interface ExtraDef {
    slug: string;
    label: string;
    hint: string;
}

interface Preset {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    matrix: Record<string, string[]>;
    extras: string[];
    is_system: boolean;
}

interface Member {
    id: number;
    name: string;
    email: string;
    username: string | null;
    job_title: string | null;
    roles: { id: number; name: string }[];
    can_edit: boolean;
    can_delete: boolean;
    active: number;
    in_progress: number;
    awaiting_review: number;
    overdue: number;
    completed: number;
    upcoming: number;
    average_complexity: number | null;
}

const emptyMember = { name: '', username: '', email: '', password: '', job_title: '', role_ids: [] as number[] };
const actionOrder = ['view', 'create', 'update', 'delete'] as const;

function cloneMatrix(matrix: Record<string, string[]>): Record<string, string[]> {
    return Object.fromEntries(Object.entries(matrix).map(([key, actions]) => [key, [...actions]]));
}

export default function TeamIndex({
    members,
    roles,
    canManage,
    canManageRoles = false,
    pageAccess,
    presets = [],
    filters,
}: {
    members: Member[];
    roles: Array<RoleOption & Partial<RoleMatrix>>;
    canManage: boolean;
    canManageRoles?: boolean;
    pageAccess?: { pages: PageDef[]; actions: Record<string, string>; extras: ExtraDef[] };
    presets?: Preset[];
    filters: { from: string; to: string };
}) {
    const form = useForm(emptyMember);
    const [editing, setEditing] = useState<Member | null>(null);
    const [adding, setAdding] = useState(false);
    const roleMatrices = useMemo(
        () => roles.filter((role): role is RoleMatrix => typeof role.slug === 'string' && !!role.matrix),
        [roles],
    );
    const [selectedRoleId, setSelectedRoleId] = useState<number | null>(roleMatrices[0]?.id ?? null);
    const selectedRole = roleMatrices.find((role) => role.id === selectedRoleId) ?? roleMatrices[0] ?? null;
    const [matrix, setMatrix] = useState<Record<string, string[]>>(selectedRole ? cloneMatrix(selectedRole.matrix) : {});
    const [extras, setExtras] = useState<string[]>(selectedRole?.extras ?? []);
    const [presetName, setPresetName] = useState('');
    const [savingRole, setSavingRole] = useState(false);
    const [savingPreset, setSavingPreset] = useState(false);

    useEffect(() => {
        const role = roleMatrices.find((item) => item.id === selectedRoleId) ?? roleMatrices[0];
        if (!role) {
            return;
        }
        setMatrix(cloneMatrix(role.matrix));
        setExtras([...(role.extras ?? [])]);
    }, [selectedRoleId, roleMatrices]);

    function openAdd() {
        form.clearErrors();
        form.setData({ ...emptyMember, role_ids: roles[0] ? [roles[0].id] : [] });
        setEditing(null);
        setAdding(true);
    }

    function openEdit(member: Member) {
        form.clearErrors();
        form.setData({
            name: member.name,
            username: member.username ?? '',
            email: member.email,
            password: '',
            job_title: member.job_title ?? '',
            role_ids: member.roles.map((role) => role.id),
        });
        setAdding(false);
        setEditing(member);
    }

    function closeForm() {
        setAdding(false);
        setEditing(null);
    }

    function save(event: FormEvent) {
        event.preventDefault();
        form.transform((data) => ({ ...data, username: data.username || null, job_title: data.job_title || null, password: data.password || null }));
        const options = { preserveScroll: true, onSuccess: () => closeForm() };
        if (editing) {
            form.put(`/team/${editing.id}`, options);
            return;
        }
        form.post('/team', options);
    }

    async function remove(member: Member) {
        const ok = await confirmAction({
            title: 'Remove team member?',
            message: `Remove ${member.name} from the team? This cannot be undone from here.`,
            confirmLabel: 'Remove',
        });
        if (ok) {
            router.delete(`/team/${member.id}`, { preserveScroll: true });
        }
    }

    function toggleAction(page: string, action: string) {
        setMatrix((current) => {
            const existing = new Set(current[page] ?? []);
            if (existing.has(action)) {
                existing.delete(action);
            } else {
                existing.add(action);
                if (action !== 'view') {
                    existing.add('view');
                }
            }
            return { ...current, [page]: Array.from(existing) };
        });
    }

    function toggleExtra(slug: string) {
        setExtras((current) => (current.includes(slug) ? current.filter((item) => item !== slug) : [...current, slug]));
    }

    function saveRolePermissions() {
        if (!selectedRole) {
            return;
        }
        setSavingRole(true);
        router.put(`/team/roles/${selectedRole.id}/permissions`, { matrix, extras }, {
            preserveScroll: true,
            onFinish: () => setSavingRole(false),
        });
    }

    function savePreset() {
        if (!presetName.trim()) {
            return;
        }
        setSavingPreset(true);
        router.post('/team/permission-presets', { name: presetName.trim(), matrix, extras }, {
            preserveScroll: true,
            onSuccess: () => setPresetName(''),
            onFinish: () => setSavingPreset(false),
        });
    }

    function applyPreset(preset: Preset) {
        setMatrix(cloneMatrix(preset.matrix ?? {}));
        setExtras([...(preset.extras ?? [])]);
        if (!selectedRole) {
            return;
        }
        router.post(`/team/roles/${selectedRole.id}/presets/${preset.id}`, {}, { preserveScroll: true });
    }

    async function removePreset(preset: Preset) {
        if (preset.is_system) {
            return;
        }
        const ok = await confirmAction({
            title: 'Delete preset?',
            message: `Remove the “${preset.name}” preset?`,
            confirmLabel: 'Delete',
        });
        if (ok) {
            router.delete(`/team/permission-presets/${preset.id}`, { preserveScroll: true });
        }
    }

    return (
        <AppLayout>
            <Head title="Team" />
            <PageHeader title="Team" description="These counts describe current assignments, deadlines, and complexity. They are not a performance ranking." actions={canManage ? <Button type="button" onClick={openAdd}>Add member</Button> : undefined} />
            <form className="mb-4 flex flex-wrap items-end gap-2" onSubmit={(event) => { event.preventDefault(); const data = new FormData(event.currentTarget); router.get('/team', Object.fromEntries(data.entries()), { preserveState: true }); }}>
                <label className="text-sm">Completed from<input className="mt-1 block rounded-lg border border-zinc-300 px-2 py-1.5" type="date" name="from" defaultValue={filters.from} /></label>
                <label className="text-sm">to<input className="mt-1 block rounded-lg border border-zinc-300 px-2 py-1.5" type="date" name="to" defaultValue={filters.to} /></label>
                <button className="rounded-lg bg-zinc-900 px-3 py-2 text-sm text-white" type="submit">Update period</button>
            </form>
            <div className="overflow-x-auto rounded-xl border border-zinc-200 bg-white">
                <table className="w-full min-w-[48rem] text-left text-sm">
                    <thead className="text-zinc-500"><tr>{['Name', 'Role', 'Active', 'In progress', 'Awaiting review', 'Overdue', 'Upcoming', 'Completed', 'Avg complexity', ''].map((heading) => <th key={heading || 'actions'} className="px-3 py-2 font-medium">{heading}</th>)}</tr></thead>
                    <tbody>
                        {members.map((member) => (
                            <tr key={member.id} className="border-t border-zinc-100">
                                <td className="px-3 py-2"><Link href={`/team/${member.id}`} className="font-medium text-brand-800">{member.name}</Link><span className="block text-xs text-zinc-500">{member.job_title}</span></td>
                                <td className="px-3 py-2">{member.roles.map((role) => role.name).join(', ')}</td>
                                <td className="px-3 py-2">{member.active}</td>
                                <td className="px-3 py-2">{member.in_progress}</td>
                                <td className="px-3 py-2">{member.awaiting_review}</td>
                                <td className={`px-3 py-2 ${member.overdue ? 'font-medium text-rose-700' : ''}`}>{member.overdue}</td>
                                <td className="px-3 py-2">{member.upcoming}</td>
                                <td className="px-3 py-2">{member.completed}</td>
                                <td className="px-3 py-2">{member.average_complexity ?? '—'}</td>
                                <td className="px-3 py-2">
                                    {canManage && (member.can_edit || member.can_delete) && (
                                        <div className="flex justify-end gap-1">
                                            {member.can_edit && (
                                            <button type="button" aria-label={`Edit ${member.name}`} className="rounded-md p-1.5 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900" onClick={() => openEdit(member)}>
                                                <Pencil className="h-4 w-4" />
                                            </button>
                                            )}
                                            {member.can_delete && (
                                                <button type="button" aria-label={`Delete ${member.name}`} className="rounded-md p-1.5 text-zinc-500 hover:bg-rose-50 hover:text-rose-700" onClick={() => remove(member)}>
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            )}
                                        </div>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {canManageRoles && pageAccess && selectedRole && (
                <section className="mt-8 space-y-4">
                    <div className="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h2 className="text-lg font-semibold text-zinc-900">Role permissions</h2>
                            <p className="text-sm text-zinc-500">Choose View / Add / Edit / Delete for each page, then save or store a preset.</p>
                        </div>
                        <div className="flex flex-wrap items-end gap-2">
                            <Field label="Role">
                                <Select value={String(selectedRole.id)} onChange={(event) => setSelectedRoleId(Number(event.target.value))}>
                                    {roleMatrices.map((role) => (
                                        <option key={role.id} value={role.id}>{role.name}</option>
                                    ))}
                                </Select>
                            </Field>
                            <Button type="button" onClick={saveRolePermissions} disabled={savingRole}>
                                {savingRole ? 'Saving…' : 'Save role'}
                            </Button>
                        </div>
                    </div>

                    {selectedRole.description && <p className="text-sm text-zinc-600">{selectedRole.description}</p>}

                    <div className="overflow-x-auto rounded-xl border border-zinc-200 bg-white">
                        <table className="w-full min-w-[40rem] text-left text-sm">
                            <thead className="bg-zinc-50 text-zinc-500">
                                <tr>
                                    <th className="px-3 py-2 font-medium">Page</th>
                                    {actionOrder.map((action) => (
                                        <th key={action} className="px-3 py-2 text-center font-medium">{pageAccess.actions[action] ?? action}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {pageAccess.pages.map((page) => (
                                    <tr key={page.key} className="border-t border-zinc-100">
                                        <td className="px-3 py-2 font-medium text-zinc-800">{page.label}</td>
                                        {actionOrder.map((action) => {
                                            const available = page.actions.includes(action);
                                            const checked = (matrix[page.key] ?? []).includes(action);
                                            return (
                                                <td key={action} className="px-3 py-2 text-center">
                                                    {available ? (
                                                        <input
                                                            type="checkbox"
                                                            className="h-4 w-4 rounded border-zinc-300 text-brand-700 focus:ring-brand-700"
                                                            checked={checked}
                                                            onChange={() => toggleAction(page.key, action)}
                                                            aria-label={`${pageAccess.actions[action]} ${page.label}`}
                                                        />
                                                    ) : (
                                                        <span className="text-zinc-300">—</span>
                                                    )}
                                                </td>
                                            );
                                        })}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className="rounded-xl border border-zinc-200 bg-white p-4">
                        <h3 className="text-sm font-semibold text-zinc-900">Extra capabilities</h3>
                        <div className="mt-3 grid gap-3 sm:grid-cols-2">
                            {pageAccess.extras.map((extra) => (
                                <label key={extra.slug} className="flex items-start gap-2 rounded-lg border border-zinc-100 p-3">
                                    <input
                                        type="checkbox"
                                        className="mt-0.5 h-4 w-4 rounded border-zinc-300 text-brand-700 focus:ring-brand-700"
                                        checked={extras.includes(extra.slug)}
                                        onChange={() => toggleExtra(extra.slug)}
                                    />
                                    <span>
                                        <span className="block text-sm font-medium text-zinc-800">{extra.label}</span>
                                        <span className="block text-xs text-zinc-500">{extra.hint}</span>
                                    </span>
                                </label>
                            ))}
                        </div>
                    </div>

                    <div className="rounded-xl border border-zinc-200 bg-white p-4">
                        <h3 className="text-sm font-semibold text-zinc-900">Presets</h3>
                        <p className="mt-1 text-sm text-zinc-500">Apply a saved layout to this role, or save the current checkboxes as a new preset.</p>
                        <div className="mt-3 flex flex-wrap gap-2">
                            {presets.map((preset) => (
                                <div key={preset.id} className="inline-flex items-center gap-1 rounded-lg border border-zinc-200 bg-zinc-50 px-2 py-1.5">
                                    <button type="button" className="text-sm font-medium text-brand-800 hover:underline" onClick={() => applyPreset(preset)}>
                                        {preset.name}
                                    </button>
                                    {!preset.is_system && (
                                        <button type="button" aria-label={`Delete ${preset.name}`} className="rounded p-1 text-zinc-400 hover:bg-rose-50 hover:text-rose-700" onClick={() => removePreset(preset)}>
                                            <Trash2 className="h-3.5 w-3.5" />
                                        </button>
                                    )}
                                </div>
                            ))}
                        </div>
                        <div className="mt-3 flex flex-wrap items-end gap-2">
                            <Field label="New preset name">
                                <Input value={presetName} onChange={(event) => setPresetName(event.target.value)} placeholder="e.g. Creative read-only" />
                            </Field>
                            <Button type="button" variant="secondary" onClick={savePreset} disabled={savingPreset || !presetName.trim()}>
                                {savingPreset ? 'Saving…' : 'Save preset'}
                            </Button>
                        </div>
                    </div>
                </section>
            )}

            <Modal open={adding || editing !== null} onOpenChange={(open) => { if (!open) closeForm(); }} title={editing ? `Edit ${editing.name}` : 'Add team member'}>
                <form onSubmit={save} className="space-y-4">
                    <Field label="Name" error={form.errors.name}>
                        <Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required />
                    </Field>
                    <Field label="Username" error={form.errors.username}>
                        <Input value={form.data.username} onChange={(event) => form.setData('username', event.target.value)} />
                    </Field>
                    <Field label="Email" error={form.errors.email}>
                        <Input type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} required />
                    </Field>
                    <Field label="Job title" error={form.errors.job_title}>
                        <Input value={form.data.job_title} onChange={(event) => form.setData('job_title', event.target.value)} />
                    </Field>
                    <Field label="Role" error={form.errors.role_ids}>
                        <Select value={form.data.role_ids[0] ? String(form.data.role_ids[0]) : ''} onChange={(event) => form.setData('role_ids', event.target.value ? [Number(event.target.value)] : [])} required>
                            <option value="">Select a role</option>
                            {roles.map((role) => <option key={role.id} value={role.id}>{role.name}</option>)}
                        </Select>
                    </Field>
                    <Field label={editing ? 'New password' : 'Password'} error={form.errors.password} hint={editing ? 'Leave blank to keep the current password.' : undefined}>
                        <Input type="password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} required={!editing} />
                    </Field>
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="secondary" onClick={closeForm}>Cancel</Button>
                        <Button type="submit" disabled={form.processing}>{form.processing ? 'Saving…' : editing ? 'Save changes' : 'Add member'}</Button>
                    </div>
                </form>
            </Modal>
        </AppLayout>
    );
}
