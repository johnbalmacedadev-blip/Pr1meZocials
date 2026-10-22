import { Head, Link, router } from '@inertiajs/react';
import { PageHeader } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';

interface Member {
    id: number;
    name: string;
    job_title: string | null;
    roles: { name: string }[];
    active: number;
    in_progress: number;
    awaiting_review: number;
    overdue: number;
    completed: number;
    upcoming: number;
    average_complexity: number | null;
}

export default function TeamIndex({ members, filters }: { members: Member[]; filters: { from: string; to: string } }) {
    return (
        <AppLayout>
            <Head title="Team" />
            <PageHeader title="Team" description="These counts describe current assignments, deadlines, and complexity. They are not a performance ranking." />
            <form className="mb-4 flex flex-wrap items-end gap-2" onSubmit={(event) => { event.preventDefault(); const data = new FormData(event.currentTarget); router.get('/team', Object.fromEntries(data.entries()), { preserveState: true }); }}>
                <label className="text-sm">Completed from<input className="mt-1 block rounded-lg border border-zinc-300 px-2 py-1.5" type="date" name="from" defaultValue={filters.from} /></label>
                <label className="text-sm">to<input className="mt-1 block rounded-lg border border-zinc-300 px-2 py-1.5" type="date" name="to" defaultValue={filters.to} /></label>
                <button className="rounded-lg bg-zinc-900 px-3 py-2 text-sm text-white" type="submit">Update period</button>
            </form>
            <div className="overflow-x-auto rounded-xl border border-zinc-200 bg-white">
                <table className="w-full min-w-[48rem] text-left text-sm">
                    <thead className="text-zinc-500"><tr>{['Name', 'Role', 'Active', 'In progress', 'Awaiting review', 'Overdue', 'Upcoming', 'Completed', 'Avg complexity'].map((heading) => <th key={heading} className="px-3 py-2 font-medium">{heading}</th>)}</tr></thead>
                    <tbody>
                        {members.map((member) => (
                            <tr key={member.id} className="border-t border-zinc-100">
                                <td className="px-3 py-2"><Link href={`/team/${member.id}`} className="font-medium text-teal-900">{member.name}</Link><span className="block text-xs text-zinc-500">{member.job_title}</span></td>
                                <td className="px-3 py-2">{member.roles.map((role) => role.name).join(', ')}</td>
                                <td className="px-3 py-2">{member.active}</td>
                                <td className="px-3 py-2">{member.in_progress}</td>
                                <td className="px-3 py-2">{member.awaiting_review}</td>
                                <td className={`px-3 py-2 ${member.overdue ? 'font-medium text-rose-700' : ''}`}>{member.overdue}</td>
                                <td className="px-3 py-2">{member.upcoming}</td>
                                <td className="px-3 py-2">{member.completed}</td>
                                <td className="px-3 py-2">{member.average_complexity ?? '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppLayout>
    );
}
