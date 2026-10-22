import { Head, Link, router, useForm } from '@inertiajs/react';
import { Clapperboard, Image as ImageIcon, Megaphone, Moon, Plus, Sun, SunMedium, X } from 'lucide-react';
import { FormEvent, useEffect, useMemo, useState } from 'react';
import { Button, Field, Input, Modal, Select } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate } from '@/lib/utils';

type Format = 'reel' | 'image' | 'company';

interface Slot {
    format: Format;
    title: string;
    detail: string;
}

const flexNote = 'Any flexible content: announcements, events, promotions, activities, testimonials, recognition, photos, posters, or reels.';

const flexSlot: Slot = { format: 'company', title: 'General Post', detail: flexNote };

const days: { name: string; morning: Slot; afternoon: Slot; evening: Slot }[] = [
    {
        name: 'Monday',
        morning: { format: 'reel', title: 'Motivation Reel', detail: 'Start the week with positivity and purpose.' },
        afternoon: { format: 'image', title: 'Product Poster', detail: 'Showcase a specific product and key benefits.' },
        evening: { format: 'reel', title: 'Business Opportunity', detail: 'Invite and inspire with the Prime Z opportunity.' },
    },
    {
        name: 'Tuesday',
        morning: { format: 'image', title: 'Inspirational Quote', detail: 'Share a positive message or life reminder.' },
        afternoon: { format: 'reel', title: 'Product Showcase', detail: 'Quick product demo or how to use it.' },
        evening: { format: 'image', title: 'Product Benefits Infographic', detail: 'Highlight key ingredients and benefits.' },
    },
    {
        name: 'Wednesday',
        morning: { format: 'reel', title: 'Funny Taglish Reel', detail: 'Keep it fun, relatable, and authentic.' },
        afternoon: { format: 'image', title: 'Product Information', detail: 'Share product details, usage, and FAQs.' },
        evening: { format: 'reel', title: 'Member Testimonial', detail: 'Real stories. Real people. Real results.' },
    },
    {
        name: 'Thursday',
        morning: { format: 'image', title: 'Motivational Poster', detail: 'Encourage and uplift the community.' },
        afternoon: { format: 'reel', title: 'Product Education', detail: 'Explain ingredients, usage or health tips.' },
        evening: { format: 'image', title: 'How to Join Infographic', detail: 'Step-by-step guide to become a member.' },
    },
    {
        name: 'Friday',
        morning: { format: 'reel', title: 'Relatable Reel', detail: 'Daily life, humor, or trending content.' },
        afternoon: { format: 'image', title: 'Product Promo', detail: 'Highlight offers, bundles or special deals.' },
        evening: { format: 'reel', title: 'Business Invitation Reel', detail: 'Share the opportunity and next steps.' },
    },
    {
        name: 'Saturday',
        morning: { format: 'reel', title: 'Lifestyle Reel', detail: 'Family, travel, wellness, or everyday moments.' },
        afternoon: { format: 'image', title: 'Member Testimonial', detail: 'Feature success stories and real experiences.' },
        evening: { format: 'reel', title: 'Funny / Trending Reel', detail: 'Use trending sounds or popular topics.' },
    },
    {
        name: 'Sunday',
        morning: { format: 'image', title: 'Inspirational Poster', detail: 'Faith, hope, and a better tomorrow.' },
        afternoon: { format: 'reel', title: 'Community Reel', detail: 'Show your team, events, or member activities.' },
        evening: { format: 'image', title: 'Weekly Highlights', detail: 'Recap the week, upcoming plans, or key updates.' },
    },
];

interface PlannedContent {
    id: number;
    weekday: string;
    slot: string;
    post_on: string;
    content: { id: number | null; title: string; reference_number: string | null; status: string | null; href: string | null };
}

interface ContentOption {
    id: number;
    title: string;
    reference_number: string;
    status: string | null;
}

const columns = [
    { key: 'morning', label: 'Morning', time: '8:00 – 9:00 AM', purpose: 'Engage • Inspire • Start the Day', icon: Sun },
    { key: 'afternoon', label: 'Afternoon', time: '12:00 – 1:00 PM', purpose: 'Educate • Showcase • Inform', icon: SunMedium },
    { key: 'flex', label: 'Flex slot', time: '4:00 – 6:00 PM', purpose: 'Images • Videos • Any Category', icon: Megaphone },
    { key: 'evening', label: 'Evening', time: '7:00 – 8:30 PM', purpose: 'Convert • Build Community', icon: Moon },
] as const;

export default function PostingSchedule({
    entries,
    contentOptions,
    canAssign,
}: {
    entries: PlannedContent[];
    contentOptions: ContentOption[];
    canAssign: boolean;
}) {
    const today = new Intl.DateTimeFormat('en-US', { weekday: 'long', timeZone: 'Asia/Manila' }).format(new Date());
    const [adding, setAdding] = useState<{ weekday: string; slot: string; label: string } | null>(null);

    return (
        <AppLayout>
            <Head title="Posting Schedule" />
            <section className="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm">
                <div className="border-b border-zinc-200 px-5 py-5 text-center">
                    <h1 className="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Facebook Content Schedule</h1>
                    <p className="mt-1 text-sm text-zinc-600">3 core posts per day + flexible company updates</p>
                </div>
                <div className="hidden lg:block">
                    <div className="grid grid-cols-[7.5rem_repeat(4,minmax(0,1fr))]">
                        <div className="border-b border-zinc-200 bg-white px-3 py-4 text-center text-sm font-semibold text-zinc-900">Day</div>
                        {columns.map((column) => {
                            const Icon = column.icon;
                            return (
                                <div key={column.key} className="border-b border-l border-zinc-200 bg-white px-3 py-3 text-center text-zinc-900">
                                    <Icon className="mx-auto h-4 w-4" />
                                    <p className="text-sm font-semibold uppercase">{column.label}</p>
                                    <p className="text-xs font-medium text-zinc-600">{column.time}</p>
                                    <p className="mt-1 text-[11px] leading-snug text-zinc-500">{column.purpose}</p>
                                </div>
                            );
                        })}
                        {days.map((day) => {
                            const current = day.name === today;
                            return (
                                <div key={day.name} className="contents">
                                    <div className={`flex items-center justify-center border-t p-3 ${current ? 'border-brand-700/30 bg-[#dce6f8]' : 'border-zinc-200 bg-zinc-50'}`}>
                                        <div className="rounded-lg bg-brand-700 px-2 py-3 text-center text-xs font-semibold uppercase tracking-wide text-white">
                                            {day.name}
                                            {current && <span className="mt-1 block text-[10px] font-medium normal-case text-white/90">Today</span>}
                                        </div>
                                    </div>
                                    <Cell template={day.morning} weekday={day.name.toLowerCase()} slot="morning" current={current} entries={entries} canAssign={canAssign} onAdd={setAdding} />
                                    <Cell template={day.afternoon} weekday={day.name.toLowerCase()} slot="afternoon" current={current} entries={entries} canAssign={canAssign} onAdd={setAdding} />
                                    <Cell template={flexSlot} weekday={day.name.toLowerCase()} slot="flex" current={current} entries={entries} canAssign={canAssign} onAdd={setAdding} />
                                    <Cell template={day.evening} weekday={day.name.toLowerCase()} slot="evening" current={current} entries={entries} canAssign={canAssign} onAdd={setAdding} />
                                </div>
                            );
                        })}
                    </div>
                </div>
                <div className="space-y-3 p-3 lg:hidden">
                    {days.map((day) => (
                        <article key={day.name} className={`rounded-xl border p-3 ${day.name === today ? 'border-brand-700 bg-brand-50' : 'border-zinc-200 bg-white'}`}>
                            <h2 className="rounded-lg bg-brand-700 px-3 py-2 text-sm font-semibold uppercase tracking-wide text-white">{day.name}{day.name === today ? ' · Today' : ''}</h2>
                            <div className="mt-3 space-y-3">
                                <MobileSlot label="Morning · 8:00 – 9:00 AM" template={day.morning} weekday={day.name.toLowerCase()} slot="morning" entries={entries} canAssign={canAssign} onAdd={setAdding} />
                                <MobileSlot label="Afternoon · 12:00 – 1:00 PM" template={day.afternoon} weekday={day.name.toLowerCase()} slot="afternoon" entries={entries} canAssign={canAssign} onAdd={setAdding} />
                                <MobileSlot label="Flex slot · 4:00 – 6:00 PM" template={flexSlot} weekday={day.name.toLowerCase()} slot="flex" entries={entries} canAssign={canAssign} onAdd={setAdding} />
                                <MobileSlot label="Evening · 7:00 – 8:30 PM" template={day.evening} weekday={day.name.toLowerCase()} slot="evening" entries={entries} canAssign={canAssign} onAdd={setAdding} />
                            </div>
                        </article>
                    ))}
                </div>
            </section>
            <AddContentModal
                target={adding}
                options={contentOptions}
                onClose={() => setAdding(null)}
            />
        </AppLayout>
    );
}

function Cell({
    template,
    weekday,
    slot,
    current,
    entries,
    canAssign,
    onAdd,
}: {
    template: Slot;
    weekday: string;
    slot: string;
    current: boolean;
    entries: PlannedContent[];
    canAssign: boolean;
    onAdd: (target: { weekday: string; slot: string; label: string }) => void;
}) {
    const filled = entries.some((entry) => entry.weekday === weekday && entry.slot === slot);
    const tone = filled
        ? current ? 'border-brand-700/30 bg-[#dce6f8]' : 'border-zinc-200 bg-brand-50'
        : current ? 'border-brand-700/30 bg-brand-50' : 'border-zinc-200 bg-zinc-50';

    return (
        <div className={`border-l border-t p-3 ${tone}`}>
            <SlotPlan template={template} weekday={weekday} slot={slot} entries={entries} canAssign={canAssign} onAdd={onAdd} />
        </div>
    );
}

function MobileSlot({
    label,
    template,
    weekday,
    slot,
    entries,
    canAssign,
    onAdd,
}: {
    label: string;
    template: Slot;
    weekday: string;
    slot: string;
    entries: PlannedContent[];
    canAssign: boolean;
    onAdd: (target: { weekday: string; slot: string; label: string }) => void;
}) {
    const filled = entries.some((entry) => entry.weekday === weekday && entry.slot === slot);

    return (
        <div className={`rounded-lg border p-3 ${filled ? 'border-brand-700/20 bg-brand-50' : 'border-zinc-200 bg-zinc-50'}`}>
            <p className="text-xs font-semibold text-zinc-500">{label}</p>
            <div className="mt-1">
                <SlotPlan template={template} weekday={weekday} slot={slot} entries={entries} canAssign={canAssign} onAdd={onAdd} />
            </div>
        </div>
    );
}

function SlotPlan({
    template,
    weekday,
    slot,
    entries,
    canAssign,
    onAdd,
}: {
    template: Slot;
    weekday: string;
    slot: string;
    entries: PlannedContent[];
    canAssign: boolean;
    onAdd: (target: { weekday: string; slot: string; label: string }) => void;
}) {
    const planned = entries.filter((entry) => entry.weekday === weekday && entry.slot === slot);
    const filled = planned.length > 0;
    const label = `${weekday.charAt(0).toUpperCase()}${weekday.slice(1)} ${slot === 'flex' ? 'flex slot' : slot}`;

    return (
        <>
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0 flex-1">
                    <SlotBody slot={template} filled={filled} />
                </div>
                {canAssign && (
                    <button type="button" aria-label={`Add content for ${label}`} className={`inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-white ${filled ? 'bg-brand-700 hover:bg-brand-800' : 'bg-zinc-400 hover:bg-zinc-500'}`} onClick={() => onAdd({ weekday, slot, label })}>
                        <Plus className="h-4 w-4" />
                    </button>
                )}
            </div>
            {filled && (
                <ul className="mt-3 max-h-40 space-y-2 overflow-auto border-t border-brand-700/15 pt-2">
                    {planned.map((entry) => (
                        <li key={entry.id} className="flex items-start justify-between gap-2 rounded-md bg-white/80 px-2 py-1.5">
                            <div className="min-w-0">
                                <p className="text-[11px] font-semibold text-brand-700">{formatDate(entry.post_on)}</p>
                                {entry.content.href ? (
                                    <Link href={entry.content.href} className="block truncate text-sm font-medium text-zinc-900 hover:text-brand-700">{entry.content.title}</Link>
                                ) : (
                                    <p className="truncate text-sm font-medium text-zinc-900">{entry.content.title}</p>
                                )}
                                <p className="truncate text-[11px] text-zinc-500">{[entry.content.reference_number, entry.content.status].filter(Boolean).join(' · ')}</p>
                            </div>
                            {canAssign && (
                                <button type="button" aria-label={`Remove ${entry.content.title} from ${label}`} className="rounded p-1 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700" onClick={() => router.delete(`/posting-schedule/${entry.id}`, { preserveScroll: true })}>
                                    <X className="h-3.5 w-3.5" />
                                </button>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </>
    );
}

function AddContentModal({
    target,
    options,
    onClose,
}: {
    target: { weekday: string; slot: string; label: string } | null;
    options: ContentOption[];
    onClose: () => void;
}) {
    const form = useForm({ weekday: '', slot: '', post_on: '', content_item_id: '' });
    const [search, setSearch] = useState('');
    const filtered = useMemo(() => {
        const needle = search.trim().toLowerCase();
        if (needle === '') {
            return options;
        }
        return options.filter((item) => `${item.title} ${item.reference_number} ${item.status ?? ''}`.toLowerCase().includes(needle));
    }, [options, search]);

    useEffect(() => {
        if (!target) {
            return;
        }
        form.clearErrors();
        form.setData({
            weekday: target.weekday,
            slot: target.slot,
            post_on: nextWeekdayDate(target.weekday),
            content_item_id: '',
        });
        setSearch('');
    }, [target]);

    function save(event: FormEvent) {
        event.preventDefault();
        form.post('/posting-schedule', {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    }

    return (
        <Modal
            open={target !== null}
            onOpenChange={(open) => { if (!open) onClose(); }}
            title={target ? `Add content for ${target.label}` : 'Add content'}
        >
            <form className="space-y-4" onSubmit={save}>
                <p className="text-sm text-zinc-600">Pick the date this post goes out. Use the plus again to fill the next {target?.weekday ?? 'day'}.</p>
                <Field label="Date" error={form.errors.post_on}>
                    <Input type="date" value={form.data.post_on} onChange={(event) => form.setData('post_on', event.target.value)} required />
                </Field>
                <Field label="Content" error={form.errors.content_item_id} hint="Search by title or reference, then choose the finished content.">
                    <Input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search content" aria-label="Search content" />
                    <Select className="mt-2" value={form.data.content_item_id} onChange={(event) => form.setData('content_item_id', event.target.value)} required>
                        <option value="">Choose content</option>
                        {filtered.map((item) => (
                            <option key={item.id} value={item.id}>{item.reference_number} · {item.title}{item.status ? ` · ${item.status}` : ''}</option>
                        ))}
                    </Select>
                </Field>
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="secondary" onClick={onClose}>Cancel</Button>
                    <Button type="submit" disabled={form.processing}>{form.processing ? 'Saving…' : 'Add to schedule'}</Button>
                </div>
            </form>
        </Modal>
    );
}

function nextWeekdayDate(weekday: string): string {
    const index = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'].indexOf(weekday);
    const manila = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Manila' }));
    const delta = (index - manila.getDay() + 7) % 7;
    manila.setDate(manila.getDate() + delta);
    const month = String(manila.getMonth() + 1).padStart(2, '0');
    const day = String(manila.getDate()).padStart(2, '0');
    return `${manila.getFullYear()}-${month}-${day}`;
}

function SlotBody({ slot, filled }: { slot: Slot; filled: boolean }) {
    return (
        <>
            <FormatBadge format={slot.format} filled={filled} />
            <p className={`mt-2 text-sm font-semibold ${filled ? 'text-zinc-900' : 'text-zinc-500'}`}>{slot.title}</p>
            <p className={`mt-1 text-xs leading-relaxed ${filled ? 'text-zinc-600' : 'text-zinc-400'}`}>{slot.detail}</p>
        </>
    );
}

function FormatBadge({ format, filled }: { format: Format; filled: boolean }) {
    const tone = filled ? 'bg-brand-700 text-white' : 'bg-zinc-200 text-zinc-600';
    const label = format === 'reel' ? 'Reels' : format === 'image' ? 'Image / Poster' : 'Company update';
    const Icon = format === 'reel' ? Clapperboard : format === 'image' ? ImageIcon : Megaphone;

    return (
        <span className={`inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide ${tone}`}>
            <Icon className="h-3 w-3" /> {label}
        </span>
    );
}
