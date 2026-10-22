import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { Button, Field, Input, PageHeader, Select, Textarea } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import type { FormOptions } from '@/types';

interface CampaignForm {
    id?: number;
    brand_id?: number;
    name?: string;
    description?: string | null;
    objective?: string | null;
    start_date?: string | null;
    end_date?: string | null;
    status?: string;
    budget?: string | null;
    owner_id?: number | null;
    member_ids?: number[];
}

export default function CampaignFormPage({ campaign, options }: { campaign: CampaignForm | null; options: FormOptions }) {
    const form = useForm({
        brand_id: String(campaign?.brand_id ?? options.brands[0]?.id ?? ''),
        name: campaign?.name ?? '',
        description: campaign?.description ?? '',
        objective: campaign?.objective ?? '',
        start_date: campaign?.start_date ?? '',
        end_date: campaign?.end_date ?? '',
        status: campaign?.status ?? 'planning',
        budget: campaign?.budget ?? '',
        owner_id: campaign?.owner_id ? String(campaign.owner_id) : '',
        member_ids: campaign?.member_ids ?? [],
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        if (campaign?.id) form.put(`/campaigns/${campaign.id}`);
        else form.post('/campaigns');
    }

    return (
        <AppLayout>
            <Head title={campaign ? 'Edit campaign' : 'New campaign'} />
            <PageHeader title={campaign ? 'Edit campaign' : 'New campaign'} description="A campaign collects the ideas that share a goal. It does not replace the content records." />
            <form onSubmit={submit} className="grid max-w-3xl gap-4 rounded-xl border border-zinc-200 bg-white p-5">
                <Field label="Brand" error={form.errors.brand_id}><Select value={form.data.brand_id} onChange={(event) => form.setData('brand_id', event.target.value)}>{options.brands.map((brand) => <option key={brand.id} value={brand.id}>{brand.name}</option>)}</Select></Field>
                <Field label="Name" error={form.errors.name}><Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required /></Field>
                <Field label="Objective" error={form.errors.objective}><Textarea value={form.data.objective} onChange={(event) => form.setData('objective', event.target.value)} /></Field>
                <Field label="Description" error={form.errors.description}><Textarea value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} /></Field>
                <div className="grid gap-4 md:grid-cols-2">
                    <Field label="Starts"><Input type="date" value={form.data.start_date ?? ''} onChange={(event) => form.setData('start_date', event.target.value)} /></Field>
                    <Field label="Ends" error={form.errors.end_date}><Input type="date" value={form.data.end_date ?? ''} onChange={(event) => form.setData('end_date', event.target.value)} /></Field>
                    <Field label="Status"><Select value={form.data.status} onChange={(event) => form.setData('status', event.target.value)}><option value="planning">Planning</option><option value="active">Active</option><option value="paused">Paused</option><option value="completed">Completed</option></Select></Field>
                    <Field label="Budget"><Input type="number" min="0" step="0.01" value={form.data.budget ?? ''} onChange={(event) => form.setData('budget', event.target.value)} /></Field>
                    <Field label="Owner"><Select value={form.data.owner_id} onChange={(event) => form.setData('owner_id', event.target.value)}><option value="">Unassigned</option>{options.people.map((person) => <option key={person.id} value={person.id}>{person.name}</option>)}</Select></Field>
                </div>
                <Field label="Members" hint="Members can see the campaign's ideas when their role is limited to shared work.">
                    <div className="grid gap-1 sm:grid-cols-2">
                        {options.people.map((person) => (
                            <label key={person.id} className="flex items-center gap-2 text-sm">
                                <input type="checkbox" checked={form.data.member_ids.includes(person.id)} onChange={() => form.setData('member_ids', form.data.member_ids.includes(person.id) ? form.data.member_ids.filter((id) => id !== person.id) : [...form.data.member_ids, person.id])} />
                                {person.name}
                            </label>
                        ))}
                    </div>
                </Field>
                <Button type="submit" disabled={form.processing}>Save campaign</Button>
            </form>
        </AppLayout>
    );
}
