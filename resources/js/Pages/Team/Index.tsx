import { Head, Link, router, useForm } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { Button, Field, Input, Modal, PageHeader, Select } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';

interface Role {
    id: number;
    name: string;
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

export default function TeamIndex({ members, roles, canManage, filters }: { members: Member[]; roles: Role[]; canManage: boolean; filters: { from: string; to: string } }) {
    const form = useForm(emptyMember);
    const [editing, setEditing] = useState<Member | null>(null);
    const [adding, setAdding] = useState(false);

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

    function remove(member: Member) {
        if (confirm(`Remove ${member.name} from the team?`)) {
            router.delete(`/team/${member.id}`, { preserveScroll: true });
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
