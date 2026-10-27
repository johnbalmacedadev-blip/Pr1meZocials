import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { CalendarClock, ChevronLeft, ChevronRight, Clapperboard, GalleryHorizontal, Image as ImageIcon, Megaphone, Moon, Pencil, Plus, Repeat2, Sun, SunMedium, X } from 'lucide-react';
import { FormEvent, useEffect, useMemo, useState } from 'react';
import FacebookGlyph from '@/Components/FacebookGlyph';
import { Button, Field, Input, Modal, Select } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { confirmAction } from '@/lib/confirm';
import { formatDate } from '@/lib/utils';

type Format = 'poster' | 'reel' | 'carousel' | 'image' | 'reel_poster' | 'ugc';

interface Slot {
    format: Format;
    title: string;
}

type SlotKey = 'morning' | 'afternoon' | 'flex' | 'evening';

/** Weekly Facebook plan ("The upgraded posting schedule"). */
const days: ({ name: string } & Record<SlotKey, Slot>)[] = [
    {
        name: 'Monday',
        morning: { format: 'poster', title: 'Motivation Monday' },
        afternoon: { format: 'reel', title: 'Product Education' },
        flex: { format: 'reel_poster', title: 'Company News' },
        evening: { format: 'reel', title: 'Business Opportunity' },
    },
    {
        name: 'Tuesday',
        morning: { format: 'reel', title: 'Funny Taglish' },
        afternoon: { format: 'carousel', title: 'Benefits Carousel' },
        flex: { format: 'reel_poster', title: 'Behind the Scenes' },
        evening: { format: 'poster', title: 'Customer Story' },
    },
    {
        name: 'Wednesday',
        morning: { format: 'image', title: 'Relatable Meme' },
        afternoon: { format: 'reel', title: 'Product Education' },
        flex: { format: 'ugc', title: 'Member Content' },
        evening: { format: 'reel', title: 'Member Journey' },
    },
    {
        name: 'Thursday',
        morning: { format: 'reel', title: 'Office Chronicles' },
        afternoon: { format: 'poster', title: 'Product FAQ' },
        flex: { format: 'image', title: 'Customer Photo Review' },
        evening: { format: 'poster', title: 'How to Join' },
    },
    {
        name: 'Friday',
        morning: { format: 'reel', title: 'Trending Reel' },
        afternoon: { format: 'poster', title: 'Product Promo' },
        flex: { format: 'reel_poster', title: 'Member Recognition' },
        evening: { format: 'reel', title: 'Business Invitation' },
    },
    {
        name: 'Saturday',
        morning: { format: 'image', title: 'Lifestyle Photo' },
        afternoon: { format: 'reel', title: 'Member Testimonial' },
        flex: { format: 'carousel', title: 'Event Coverage' },
        evening: { format: 'poster', title: 'Bundle Offer' },
    },
    {
        name: 'Sunday',
        morning: { format: 'poster', title: 'Faith & Hope' },
        afternoon: { format: 'reel', title: 'Community Reel' },
        flex: { format: 'reel_poster', title: 'Ask Pr1me Z' },
        evening: { format: 'reel', title: 'Member Success Story' },
    },
];

/** Platforms with their own weekly plan. Only Facebook has one so far. */
const platforms = [
    { key: 'facebook', label: 'Facebook' },
    { key: 'tiktok', label: 'TikTok' },
    { key: 'instagram', label: 'Instagram' },
    { key: 'youtube', label: 'YouTube' },
] as const;

type PlatformKey = (typeof platforms)[number]['key'];

interface PlannedContent {
    id: number;
    weekday: string;
    slot: string;
    post_on: string;
    content: { id: number | null; title: string; reference_number: string | null; status: string | null; href: string | null };
}

interface FacebookSchedulePost {
    id: number;
    weekday: string;
    slot: string;
    date: string;
    at: string;
    caption: string;
    media_type: 'image' | 'video';
    media_name: string | null;
    status: string;
    permalink_url: string | null;
    page: string | null;
    author: string | null;
}

interface ContentOption {
    id: number;
    title: string;
    reference_number: string;
    status: string | null;
}

interface WeekDay {
    key: string;
    name: string;
    date: string;
    label: string;
    is_today: boolean;
}

interface WeekMeta {
    start: string;
    end: string;
    label: string;
    prev: string;
    next: string;
    is_current: boolean;
    days: WeekDay[];
}

const SLOT_TIMES: Record<string, string> = {
    morning: '08:00',
    afternoon: '12:00',
    flex: '16:00',
    evening: '19:00',
};

const columns = [
    { key: 'morning', label: 'Morning', time: '8:00 – 9:00 AM', purpose: 'Engage • inspire', icon: Sun },
    { key: 'afternoon', label: 'Afternoon', time: '12:00 – 1:00 PM', purpose: 'Educate • showcase', icon: SunMedium },
    { key: 'flex', label: 'Flex', time: '4:00 – 6:00 PM', purpose: 'Proof • company life', icon: Megaphone },
    { key: 'evening', label: 'Evening', time: '7:00 – 8:30 PM', purpose: 'Convert • community', icon: Moon },
] as const;

export default function PostingSchedule({
    entries,
    facebookPosts = [],
    contentOptions,
    canAssign,
    canPost = false,
    week,
}: {
    entries: PlannedContent[];
    facebookPosts?: FacebookSchedulePost[];
    contentOptions: ContentOption[];
    canAssign: boolean;
    canPost?: boolean;
    week: WeekMeta;
}) {
    const [adding, setAdding] = useState<{ weekday: string; slot: string; label: string; date: string } | null>(null);
    const dayByKey = useMemo(() => Object.fromEntries(week.days.map((day) => [day.key, day])), [week.days]);
    const cellProps = {
        entries,
        facebookPosts,
        canAssign,
        canPost,
        weekStart: week.start,
        onAssign: setAdding,
        dayByKey,
    };

    const requested = new URLSearchParams(usePage().url.split('?')[1] ?? '').get('platform');
    const platform: PlatformKey = platforms.some((item) => item.key === requested) ? (requested as PlatformKey) : 'facebook';
    const platformLabel = platforms.find((item) => item.key === platform)?.label ?? 'Facebook';

    function visit(params: { week?: string; platform?: PlatformKey }) {
        const next = { week: params.week ?? week.start, platform: params.platform ?? platform };
        router.get('/posting-schedule', next.platform === 'facebook' ? { week: next.week } : next, { preserveScroll: true, preserveState: true });
    }

    function goToWeek(date: string) {
        visit({ week: date });
    }

    return (
        <AppLayout>
            <Head title="Posting Schedule" />
            <section className="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm">
                <div className="border-b border-zinc-200 px-5 py-5">
                    <div className="text-center">
                        <h1 className="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">{platformLabel} Content Schedule</h1>
                        <p className="mt-1 text-sm text-zinc-600">
                            {platform !== 'facebook'
                                ? `No ${platformLabel} posting plan yet.`
                                : canPost
                                    ? 'Tap + to compose for that day and time. Browse weeks to see what was scheduled or posted.'
                                    : '4 posts per day: morning, afternoon, flex, and evening'}
                        </p>
                        <label className="mt-3 inline-flex items-center gap-2 text-sm text-zinc-600">
                            Platform
                            <Select className="w-auto min-w-[9rem]" value={platform} onChange={(event) => visit({ platform: event.target.value as PlatformKey })} aria-label="Choose platform schedule">
                                {platforms.map((item) => <option key={item.key} value={item.key}>{item.label}</option>)}
                            </Select>
                        </label>
                    </div>
                    {/* One line on phones: arrow-only buttons, the week label takes the rest. */}
                    <div className="mx-auto mt-4 flex max-w-md items-center justify-between gap-2 sm:max-w-none sm:justify-center">
                        <Button type="button" size="sm" variant="secondary" className="shrink-0" onClick={() => goToWeek(week.prev)} aria-label="Previous week">
                            <ChevronLeft className="h-4 w-4" />
                            <span className="hidden sm:inline">Previous</span>
                        </Button>
                        <div className="min-w-0 flex-1 text-center sm:min-w-[12rem] sm:flex-none">
                            <p className="text-sm font-semibold text-zinc-900">{week.label}</p>
                            {!week.is_current && (
                                <button type="button" className="mt-0.5 text-xs font-medium text-brand-700 hover:underline" onClick={() => goToWeek(new Date().toISOString().slice(0, 10))}>
                                    Jump to this week
                                </button>
                            )}
                        </div>
                        <Button type="button" size="sm" variant="secondary" className="shrink-0" onClick={() => goToWeek(week.next)} aria-label="Next week">
                            <span className="hidden sm:inline">Next</span>
                            <ChevronRight className="h-4 w-4" />
                        </Button>
                    </div>
                </div>
                {platform !== 'facebook' ? (
                    <EmptyPlatform label={platformLabel} />
                ) : (
                <>
                <div className="hidden lg:block">
                    <div className="grid grid-cols-[7.5rem_repeat(4,minmax(0,1fr))]">
                        <div className="border-b border-zinc-200 bg-white px-3 py-4 text-center text-sm font-semibold text-zinc-900">Day</div>
                        {columns.map((column) => {
                            const Icon = column.icon;
                            return (
                                <div key={column.key} className={`border-b border-l border-zinc-200 px-3 py-3 text-center text-zinc-900 ${column.key === 'flex' ? 'bg-amber-100' : 'bg-white'}`}>
                                    <Icon className="mx-auto h-4 w-4" />
                                    <p className="text-sm font-semibold uppercase">{column.label}</p>
                                    <p className="text-xs font-medium text-zinc-600">{column.time}</p>
                                    <p className="mt-1 text-[11px] leading-snug text-zinc-500">{column.purpose}</p>
                                </div>
                            );
                        })}
                        {days.map((day) => {
                            const meta = dayByKey[day.name.toLowerCase()];
                            const current = Boolean(meta?.is_today);
                            const weekday = day.name.toLowerCase();
                            const dayDate = meta?.date ?? week.start;
                            return (
                                <div key={`${day.name}-${dayDate}`} className="contents">
                                    <div className={`flex items-center justify-center border-t p-3 ${current ? 'border-brand-700/30 bg-[#dce6f8]' : 'border-zinc-200 bg-zinc-50'}`}>
                                        <div className="rounded-lg bg-brand-700 px-2 py-3 text-center text-xs font-semibold uppercase tracking-wide text-white">
                                            {day.name}
                                            <span className="mt-1 block text-[10px] font-medium normal-case text-white/90">{meta?.label ?? dayDate}</span>
                                            {current && <span className="mt-0.5 block text-[10px] font-medium normal-case text-white/90">Today</span>}
                                        </div>
                                    </div>
                                    <Cell template={day.morning} weekday={weekday} dayDate={dayDate} slot="morning" current={current} {...cellProps} />
                                    <Cell template={day.afternoon} weekday={weekday} dayDate={dayDate} slot="afternoon" current={current} {...cellProps} />
                                    <Cell template={day.flex} weekday={weekday} dayDate={dayDate} slot="flex" current={current} {...cellProps} />
                                    <Cell template={day.evening} weekday={weekday} dayDate={dayDate} slot="evening" current={current} {...cellProps} />
                                </div>
                            );
                        })}
                    </div>
                </div>
                <div className="space-y-3 p-3 lg:hidden">
                    {days.map((day) => {
                        const meta = dayByKey[day.name.toLowerCase()];
                        const weekday = day.name.toLowerCase();
                        const dayDate = meta?.date ?? week.start;
                        return (
                            <article key={`${day.name}-${dayDate}`} className={`rounded-xl border p-3 ${meta?.is_today ? 'border-brand-700 bg-brand-50' : 'border-zinc-200 bg-white'}`}>
                                <h2 className="rounded-lg bg-brand-700 px-3 py-2 text-sm font-semibold uppercase tracking-wide text-white">
                                    {day.name}
                                    <span className="mt-0.5 block text-xs font-medium normal-case text-white/90">{meta?.label}{meta?.is_today ? ' · Today' : ''}</span>
                                </h2>
                                <div className="mt-3 space-y-3">
                                    <MobileSlot label="Morning · 8:00 – 9:00 AM" template={day.morning} weekday={weekday} dayDate={dayDate} slot="morning" {...cellProps} />
                                    <MobileSlot label="Afternoon · 12:00 – 1:00 PM" template={day.afternoon} weekday={weekday} dayDate={dayDate} slot="afternoon" {...cellProps} />
                                    <MobileSlot label="Flex · 4:00 – 6:00 PM" template={day.flex} weekday={weekday} dayDate={dayDate} slot="flex" {...cellProps} />
                                    <MobileSlot label="Evening · 7:00 – 8:30 PM" template={day.evening} weekday={weekday} dayDate={dayDate} slot="evening" {...cellProps} />
                                </div>
                            </article>
                        );
                    })}
                </div>
                </>
                )}
            </section>
            {canAssign && (
                <AddContentModal
                    target={adding}
                    options={contentOptions}
                    onClose={() => setAdding(null)}
                />
            )}
        </AppLayout>
    );
}

type SlotHandlers = {
    entries: PlannedContent[];
    facebookPosts: FacebookSchedulePost[];
    canAssign: boolean;
    canPost: boolean;
    weekStart: string;
    dayByKey: Record<string, WeekDay>;
    onAssign: (target: { weekday: string; slot: string; label: string; date: string }) => void;
};

function Cell({
    template,
    weekday,
    dayDate,
    slot,
    current,
    entries,
    facebookPosts,
    canAssign,
    canPost,
    weekStart,
    dayByKey,
    onAssign,
}: {
    template: Slot;
    weekday: string;
    dayDate: string;
    slot: string;
    current: boolean;
} & SlotHandlers) {
    const filled = entries.some((entry) => entry.weekday === weekday && entry.slot === slot && entry.post_on === dayDate)
        || facebookPosts.some((post) => post.weekday === weekday && post.slot === slot && post.date === dayDate);
    const tone = filled
        ? current ? 'border-brand-700/30 bg-[#dce6f8]' : 'border-zinc-200 bg-brand-50'
        : current ? 'border-brand-700/30 bg-brand-50' : 'border-zinc-200 bg-zinc-50';

    return (
        <div className={`border-l border-t p-3 ${tone}`}>
            <SlotPlan template={template} weekday={weekday} dayDate={dayDate} slot={slot} entries={entries} facebookPosts={facebookPosts} canAssign={canAssign} canPost={canPost} weekStart={weekStart} dayByKey={dayByKey} onAssign={onAssign} />
        </div>
    );
}

function MobileSlot({
    label,
    template,
    weekday,
    dayDate,
    slot,
    entries,
    facebookPosts,
    canAssign,
    canPost,
    weekStart,
    dayByKey,
    onAssign,
}: {
    label: string;
    template: Slot;
    weekday: string;
    dayDate: string;
    slot: string;
} & SlotHandlers) {
    const filled = entries.some((entry) => entry.weekday === weekday && entry.slot === slot && entry.post_on === dayDate)
        || facebookPosts.some((post) => post.weekday === weekday && post.slot === slot && post.date === dayDate);

    return (
        <div className={`rounded-lg border p-3 ${filled ? 'border-brand-700/20 bg-brand-50' : 'border-zinc-200 bg-zinc-50'}`}>
            <p className="text-xs font-semibold text-zinc-500">{label}</p>
            <div className="mt-1">
                <SlotPlan template={template} weekday={weekday} dayDate={dayDate} slot={slot} entries={entries} facebookPosts={facebookPosts} canAssign={canAssign} canPost={canPost} weekStart={weekStart} dayByKey={dayByKey} onAssign={onAssign} />
            </div>
        </div>
    );
}

function SlotPlan({
    template,
    weekday,
    dayDate,
    slot,
    entries,
    facebookPosts,
    canAssign,
    canPost,
    weekStart,
    onAssign,
}: {
    template: Slot;
    weekday: string;
    dayDate: string;
    slot: string;
} & SlotHandlers) {
    const planned = entries.filter((entry) => entry.weekday === weekday && entry.slot === slot && entry.post_on === dayDate);
    const posts = facebookPosts.filter((post) => post.weekday === weekday && post.slot === slot && post.date === dayDate);
    const filled = planned.length > 0 || posts.length > 0;
    const label = `${weekday.charAt(0).toUpperCase()}${weekday.slice(1)} ${slot === 'flex' ? 'flex slot' : slot}`;

    function openCompose() {
        const time = SLOT_TIMES[slot] ?? '12:00';
        const params = new URLSearchParams({
            date: dayDate,
            time,
            slot,
            weekday,
            format: template.format,
            title: template.title,
            return: 'schedule',
            week: weekStart,
        });
        router.visit(`/posting?${params.toString()}`);
    }

    return (
        <>
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0 flex-1">
                    <SlotBody slot={template} filled={filled} />
                </div>
                <div className="flex shrink-0 items-center gap-1">
                    {canAssign && !canPost && (
                        <button
                            type="button"
                            aria-label={`Assign creative for ${label}`}
                            className={`inline-flex h-7 w-7 items-center justify-center rounded-full text-white ${filled ? 'bg-brand-700 hover:bg-brand-800' : 'bg-zinc-400 hover:bg-zinc-500'}`}
                            onClick={() => onAssign({ weekday, slot, label, date: dayDate })}
                        >
                            <Plus className="h-4 w-4" />
                        </button>
                    )}
                    {canPost && (
                        <button
                            type="button"
                            aria-label={`Compose Facebook post for ${label} on ${dayDate}`}
                            title={`Compose for ${dayDate}`}
                            className={`inline-flex h-7 w-7 items-center justify-center rounded-full text-white ${filled ? 'bg-brand-700 hover:bg-brand-800' : 'bg-zinc-400 hover:bg-zinc-500'}`}
                            onClick={openCompose}
                        >
                            <Plus className="h-4 w-4" />
                        </button>
                    )}
                </div>
            </div>
            {(posts.length > 0 || planned.length > 0) && (
                <ul className="mt-3 max-h-48 space-y-2 overflow-auto border-t border-brand-700/15 pt-2">
                    {posts.map((post) => (
                        <li key={`fb-${post.id}`} className="rounded-md bg-white/90 px-2 py-1.5 ring-1 ring-brand-100">
                            <button
                                type="button"
                                className="w-full text-left"
                                onClick={() => router.visit(`/posting?tab=history&post=${post.id}`)}
                            >
                                <p className="text-[11px] font-semibold text-brand-700">
                                    {formatDate(post.at)} · {post.status === 'scheduled' ? 'Scheduled' : 'Posted'}
                                </p>
                                <p className="truncate text-sm font-medium text-zinc-900">{post.caption || 'Facebook post'}</p>
                                <p className="truncate text-[11px] text-zinc-500">
                                    {[post.page, post.media_type === 'video' ? 'Video' : 'Image', post.author].filter(Boolean).join(' · ')}
                                </p>
                            </button>
                            <div className="mt-1 flex items-center gap-1">
                                <button
                                    type="button"
                                    aria-label="View and edit in history"
                                    title="View and edit in history"
                                    className="rounded-md p-1 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-800"
                                    onClick={() => router.visit(`/posting?tab=history&post=${post.id}`)}
                                >
                                    <Pencil className="h-3.5 w-3.5" />
                                </button>
                                {post.permalink_url && (
                                    <a
                                        href={post.permalink_url}
                                        target="_blank"
                                        rel="noreferrer"
                                        aria-label="Open on Facebook"
                                        title="Open on Facebook"
                                        className="rounded-md p-1 text-[#1877F2] hover:bg-sky-50"
                                        onClick={(event) => event.stopPropagation()}
                                    >
                                        <FacebookGlyph className="h-3.5 w-3.5" />
                                    </a>
                                )}
                            </div>
                        </li>
                    ))}
                    {planned.map((entry) => (
                        <li key={entry.id} className="flex items-start justify-between gap-2 rounded-md bg-white/80 px-2 py-1.5">
                            <div className="min-w-0">
                                <p className="text-[11px] font-semibold text-zinc-500">Creative · {formatDate(entry.post_on)}</p>
                                {entry.content.href ? (
                                    <Link href={entry.content.href} className="block truncate text-sm font-medium text-zinc-900 hover:text-brand-700">{entry.content.title}</Link>
                                ) : (
                                    <p className="truncate text-sm font-medium text-zinc-900">{entry.content.title}</p>
                                )}
                                <p className="truncate text-[11px] text-zinc-500">{[entry.content.reference_number, entry.content.status].filter(Boolean).join(' · ')}</p>
                            </div>
                            {canAssign && (
                                <button
                                    type="button"
                                    aria-label={`Remove ${entry.content.title} from ${label}`}
                                    className="rounded p-1 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700"
                                    onClick={async () => {
                                        const ok = await confirmAction({
                                            title: 'Remove from schedule?',
                                            message: `Remove “${entry.content.title}” from ${label}?`,
                                            confirmLabel: 'Remove',
                                            tone: 'warning',
                                        });
                                        if (ok) router.delete(`/posting-schedule/${entry.id}`, { preserveScroll: true });
                                    }}
                                >
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
    target: { weekday: string; slot: string; label: string; date: string } | null;
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
            post_on: target.date,
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

function EmptyPlatform({ label }: { label: string }) {
    return (
        <div className="px-5 py-16 text-center">
            <CalendarClock className="mx-auto h-10 w-10 text-zinc-300" />
            <p className="mt-3 font-semibold text-zinc-800">No {label} schedule yet</p>
            <p className="mt-1 text-sm text-zinc-500">The weekly {label} posting plan will appear here once it's set up.</p>
        </div>
    );
}

function SlotBody({ slot, filled }: { slot: Slot; filled: boolean }) {
    return (
        <>
            <FormatBadge format={slot.format} filled={filled} />
            <p className={`mt-1 text-sm font-semibold ${filled ? 'text-zinc-900' : 'text-zinc-700'}`}>{slot.title}</p>
        </>
    );
}

const FORMAT_STYLES: Record<Format, { label: string; tone: string; icon: typeof ImageIcon }> = {
    poster: { label: 'Poster', tone: 'text-brand-700', icon: ImageIcon },
    reel: { label: 'Reel', tone: 'text-orange-700', icon: Clapperboard },
    carousel: { label: 'Carousel', tone: 'text-brand-700', icon: GalleryHorizontal },
    image: { label: 'Image', tone: 'text-brand-700', icon: ImageIcon },
    reel_poster: { label: 'Reel / Poster', tone: 'text-brand-700', icon: Clapperboard },
    ugc: { label: 'UGC Repost', tone: 'text-emerald-700', icon: Repeat2 },
};

function FormatBadge({ format, filled }: { format: Format; filled: boolean }) {
    const style = FORMAT_STYLES[format];
    const Icon = style.icon;

    return (
        <span className={`inline-flex items-center gap-1 text-[11px] font-semibold uppercase tracking-wide ${style.tone} ${filled ? '' : 'opacity-80'}`}>
            <Icon className="h-3 w-3" /> {style.label}
        </span>
    );
}
