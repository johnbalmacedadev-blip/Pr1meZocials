export interface Person {
    id: number;
    name: string;
    job_title?: string | null;
}

export interface StatusRef {
    id?: number;
    name: string;
    slug: string;
    color: string;
}

export interface LookupOption {
    value: string;
    label: string;
}

export interface PlatformRef {
    id: number;
    name: string;
    slug: string;
    color: string;
}

export interface WorkflowStatus extends StatusRef {
    id: number;
    sort_order: number;
    enter_permission: string | null;
    allowed_transitions: string[] | null;
    required_fields: string[] | null;
}

export interface SharedProps {
    [key: string]: unknown;
    auth: {
        user: {
            id: number;
            name: string;
            email: string;
            job_title: string | null;
            roles: { name: string; slug: string }[];
            permissions: string[];
            brands: { id: number; name: string }[];
        } | null;
    };
    flash: { success: string | null; error: string | null };
    unreadNotifications: number;
    recentNotifications: { id: number; title: string; body: string; read_at: string | null; created_at: string | null }[];
    lookups: {
        statuses: WorkflowStatus[];
        formats: LookupOption[];
        priorities: LookupOption[];
        funnel_stages: LookupOption[];
        distributions: LookupOption[];
        publishing_statuses: LookupOption[];
        asset_kinds: LookupOption[];
        platforms: PlatformRef[];
        formulas: Record<string, string>;
    } | null;
    appName: string;
    basePath: string;
}

export interface ContentSummary {
    id: number;
    reference_number: string;
    title: string;
    description: string | null;
    format: string;
    format_label: string;
    priority: string;
    distribution: string;
    funnel_stage: string | null;
    deadline: string | null;
    target_production_date: string | null;
    published_at: string | null;
    updated_at: string | null;
    is_overdue: boolean;
    complexity: number | null;
    can_delete?: boolean;
    brand: { id: number; name: string } | null;
    campaign: { id: number; name: string } | null;
    status: StatusRef | null;
    owner: Person | null;
    reviewer: Person | null;
    platforms: {
        id: number | null;
        name: string | null;
        slug: string | null;
        color: string | null;
        format: string;
        scheduled_at: string | null;
        publishing_status: string;
    }[];
}

export interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    from?: number | null;
    to?: number | null;
    total: number;
    prev: string | null;
    next: string | null;
}

export interface FormOptions {
    brands: { id: number; name: string }[];
    campaigns: { id: number; name: string; brand_id: number; status?: string }[];
    pillars: { id: number; name: string }[];
    objectives: { id: number; name: string }[];
    segments: { id: number; name: string; brand_id: number | null }[];
    tags: { id: number; name: string; brand_id: number | null }[];
    resource_links: { id: number; name: string; url: string; brand_id: number }[];
    people: Person[];
    inspirations: { id: number; name: string | null; url: string; platform: { name: string; slug: string } | null }[];
}

export const tone: Record<string, string> = {
    slate: 'bg-slate-100 text-slate-800',
    sky: 'bg-sky-100 text-sky-950',
    indigo: 'bg-indigo-100 text-indigo-950',
    violet: 'bg-violet-100 text-violet-950',
    amber: 'bg-amber-100 text-amber-950',
    orange: 'bg-orange-100 text-orange-950',
    rose: 'bg-rose-100 text-rose-950',
    emerald: 'bg-emerald-100 text-emerald-950',
    cyan: 'bg-cyan-100 text-cyan-950',
    teal: 'bg-teal-100 text-teal-950',
    zinc: 'bg-zinc-200 text-zinc-800',
    red: 'bg-red-100 text-red-950',
};

export const priorityTone: Record<string, string> = {
    urgent: 'bg-rose-100 text-rose-950',
    high: 'bg-amber-100 text-amber-950',
    medium: 'bg-zinc-100 text-zinc-800',
    low: 'bg-slate-100 text-slate-700',
};
