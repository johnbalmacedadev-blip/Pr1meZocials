import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Eye, EyeOff, Heart, ImagePlus, MessageCircle, Pencil, RefreshCw, Share2, Trash2, Upload } from 'lucide-react';
import { DragEvent, FormEvent, useEffect, useMemo, useRef, useState } from 'react';
import { EmojiPickerButton } from '@/Components/EmojiPicker';
import FacebookGlyph from '@/Components/FacebookGlyph';
import { Button, EmptyState, Field, Input, PageHeader, Select, Textarea } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { confirmAction } from '@/lib/confirm';
import { formatDate, withBase } from '@/lib/utils';
import type { SharedProps } from '@/types';

const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
const VIDEO_TYPES = ['video/mp4', 'video/quicktime', 'video/x-m4v'];
const MAX_IMAGE_BYTES = 80 * 1024 * 1024;
const MAX_VIDEO_BYTES = 500 * 1024 * 1024;
const MAX_IMAGES = 10;

function detectMediaType(file: File): 'image' | 'video' | null {
    if (IMAGE_TYPES.includes(file.type) || /\.(jpe?g|png|webp|gif)$/i.test(file.name)) {
        return 'image';
    }
    if (VIDEO_TYPES.includes(file.type) || /\.(mp4|mov|m4v)$/i.test(file.name)) {
        return 'video';
    }
    return null;
}

type TabId = 'compose' | 'history' | 'pages';

interface BrandOption { id: number; name: string }
interface PageOption {
    id: number;
    name: string;
    handle: string | null;
    external_id: string | null;
    is_active: boolean;
    is_ready: boolean;
    can_post: boolean;
    brand: { id: number; name: string } | null;
    profile_url: string | null;
}
interface ContentOption {
    id: number;
    title: string;
    reference_number: string;
    status: string | null;
}
interface PostRow {
    id: number;
    caption: string | null;
    media_type: 'image' | 'video';
    media_name: string | null;
    media_count?: number;
    status: string;
    error_message: string | null;
    facebook_post_id: string | null;
    permalink_url: string | null;
    likes_count: number;
    comments_count: number;
    shares_count: number;
    views_count: number;
    reach: number;
    engagement_rate: number | null;
    metrics_synced_at: string | null;
    published_at: string | null;
    scheduled_publish_at: string | null;
    page: { id: number; name: string; external_id: string | null } | null;
    author: { id: number; name: string } | null;
    content: { id: number; title: string; reference_number: string } | null;
}

function toLocalInputValue(date: Date): string {
    const pad = (value: number) => String(value).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

const TABS: { id: TabId; label: string }[] = [
    { id: 'compose', label: 'Compose' },
    { id: 'history', label: 'History & analytics' },
    { id: 'pages', label: 'Page settings' },
];

interface ComposeDefaults {
    publish_mode: 'now' | 'schedule';
    scheduled_publish_at: string | null;
    media_type: 'image' | 'video';
    caption: string;
    slot: string | null;
    weekday: string | null;
    slot_label: string | null;
    return_to: string | null;
}

export default function PostingIndex({
    pages,
    posts,
    contentOptions = [],
    brands,
    metaConnected,
    metaConfigured,
    metaAppId = '',
    metaAppSecret = '',
    metaRedirectUri = '',
    canManageMeta = false,
    canManagePages = false,
    workspacePagesReady = false,
    composeDefaults,
    focus,
    loadError = null,
}: {
    pages: PageOption[];
    posts: PostRow[];
    contentOptions?: ContentOption[];
    brands: BrandOption[];
    metaConnected: boolean;
    metaConfigured: boolean;
    metaAppId?: string;
    metaAppSecret?: string;
    metaRedirectUri?: string;
    canManageMeta?: boolean;
    canManagePages?: boolean;
    workspacePagesReady?: boolean;
    composeDefaults?: ComposeDefaults | null;
    focus?: { tab?: TabId | null; post_id?: number | null } | null;
    loadError?: string | null;
}) {
    const basePath = usePage<SharedProps>().props.basePath ?? '';
    const managedPages = useMemo(
        () => pages.filter((page) => page.is_active && page.is_ready && page.can_post !== false),
        [pages],
    );
    const hasAnyPages = pages.length > 0;
    const pagesNeedingToken = useMemo(() => pages.filter((page) => !page.is_ready), [pages]);
    const connectMetaUrl = withBase('/posting/facebook/redirect', basePath);
    const scheduleBounds = useMemo(() => {
        const min = new Date(Date.now() + 10 * 60 * 1000);
        const max = new Date(Date.now() + 75 * 24 * 60 * 60 * 1000);
        return { min: toLocalInputValue(min), max: toLocalInputValue(max) };
    }, []);
    const fromSchedule = Boolean(composeDefaults?.return_to?.startsWith('/posting-schedule') || composeDefaults?.slot);
    const postForm = useForm({
        draft_id: '',
        social_account_id: managedPages[0] ? String(managedPages[0].id) : '',
        caption: composeDefaults?.caption ?? '',
        media_type: (composeDefaults?.media_type ?? 'image') as 'image' | 'video',
        media: [] as File[],
        content_item_id: '',
        publish_mode: (composeDefaults?.publish_mode ?? 'now') as 'now' | 'schedule',
        scheduled_publish_at: composeDefaults?.scheduled_publish_at ?? '',
        return_to: composeDefaults?.return_to ?? '',
    });
    const editingDraftId = postForm.data.draft_id ? Number(postForm.data.draft_id) : null;
    const editingDraft = editingDraftId ? posts.find((post) => post.id === editingDraftId) ?? null : null;
    const [tab, setTab] = useState<TabId>(() => {
        if (focus?.tab === 'history' || focus?.tab === 'pages' || focus?.tab === 'compose') {
            return focus.tab;
        }
        return focus?.post_id ? 'history' : 'compose';
    });
    const [previewUrls, setPreviewUrls] = useState<string[]>([]);
    const [refreshing, setRefreshing] = useState(false);
    const [togglingPageId, setTogglingPageId] = useState<number | null>(null);
    const [dragging, setDragging] = useState(false);
    const [editingPostId, setEditingPostId] = useState<number | null>(focus?.post_id ?? null);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const captionRef = useRef<HTMLTextAreaElement>(null);
    const dragDepth = useRef(0);
    const focusedPostRef = useRef<HTMLLIElement | null>(null);

    // fromSchedule depends on composeDefaults — keep initial tab when focusing history
    useEffect(() => {
        if (focus?.tab === 'history' || focus?.post_id) {
            setTab('history');
        }
        if (focus?.post_id) {
            setEditingPostId(focus.post_id);
        }
    }, [focus?.tab, focus?.post_id]);

    useEffect(() => {
        if (!focus?.post_id || tab !== 'history') {
            return;
        }
        focusedPostRef.current?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }, [focus?.post_id, tab, posts]);

    const selectedPage = managedPages.find((page) => String(page.id) === postForm.data.social_account_id) ?? null;
    const mediaFiles = postForm.data.media;
    const hasMedia = mediaFiles.length > 0;
    const hasReusableDraftMedia = Boolean(editingDraft && (editingDraft.media_count ?? 0) > 0 && !hasMedia);
    const canSubmitMedia = hasMedia || hasReusableDraftMedia;

    useEffect(() => {
        const stillManaged = managedPages.some((page) => String(page.id) === postForm.data.social_account_id);
        if (!stillManaged) {
            postForm.setData('social_account_id', managedPages[0] ? String(managedPages[0].id) : '');
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [managedPages]);

    useEffect(() => {
        const urls = mediaFiles.map((file) => URL.createObjectURL(file));
        setPreviewUrls(urls);
        return () => {
            urls.forEach((url) => URL.revokeObjectURL(url));
        };
    }, [mediaFiles]);

    function submitPost(event: FormEvent) {
        event.preventDefault();
        postForm.post('/posting', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                if (postForm.data.return_to?.startsWith('/posting-schedule')) {
                    return;
                }
                postForm.setData({
                    draft_id: '',
                    social_account_id: managedPages[0] ? String(managedPages[0].id) : postForm.data.social_account_id,
                    caption: '',
                    media: [],
                    media_type: 'image',
                    content_item_id: '',
                    publish_mode: 'now',
                    scheduled_publish_at: '',
                    return_to: '',
                });
                setTab('history');
            },
        });
    }

    function editDraft(post: PostRow) {
        const pageStillManaged = managedPages.some((page) => page.id === post.page?.id);
        postForm.clearErrors();
        postForm.setData({
            draft_id: String(post.id),
            social_account_id: pageStillManaged && post.page ? String(post.page.id) : (managedPages[0] ? String(managedPages[0].id) : ''),
            caption: post.caption ?? '',
            media_type: post.media_type,
            media: [],
            content_item_id: post.content ? String(post.content.id) : '',
            publish_mode: post.scheduled_publish_at ? 'schedule' : 'now',
            scheduled_publish_at: post.scheduled_publish_at
                ? toLocalInputValue(new Date(post.scheduled_publish_at))
                : '',
            return_to: '',
        });
        setEditingPostId(null);
        setTab('compose');
    }

    function clearDraftEdit() {
        postForm.setData({
            ...postForm.data,
            draft_id: '',
            caption: '',
            media: [],
            media_type: 'image',
            content_item_id: '',
            publish_mode: 'now',
            scheduled_publish_at: '',
            return_to: '',
        });
        postForm.clearErrors();
    }

    async function deleteHistoryPost(post: PostRow) {
        const isDraft = post.status === 'draft';
        const ok = await confirmAction({
            title: isDraft ? 'Delete draft?' : 'Delete failed post?',
            message: isDraft
                ? 'Remove this draft from history? Saved media for it will be deleted.'
                : 'Remove this failed post from history? This cannot be undone.',
            confirmLabel: 'Delete',
        });
        if (!ok) {
            return;
        }
        router.delete(`/posting/${post.id}`, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (String(postForm.data.draft_id) === String(post.id)) {
                    clearDraftEdit();
                }
            },
        });
    }

    function refreshPages() {
        setRefreshing(true);
        router.post('/posting/facebook/refresh', {
            brand_id: brands[0]?.id,
        }, {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => setRefreshing(false),
        });
    }

    function applyMediaFiles(incoming: FileList | File[] | null, { append = false }: { append?: boolean } = {}) {
        postForm.clearErrors('media');
        const list = incoming ? Array.from(incoming) : [];
        if (list.length === 0) {
            if (!append) {
                postForm.setData({ ...postForm.data, media: [], media_type: 'image' });
            }
            return;
        }

        const kinds = list.map((file) => detectMediaType(file));
        if (kinds.some((kind) => kind === null)) {
            postForm.setError('media', 'Use JPG, PNG, WEBP, GIF, MP4, or MOV files only.');
            return;
        }

        const hasVideo = kinds.includes('video');
        const hasImage = kinds.includes('image');
        if (hasVideo && hasImage) {
            postForm.setError('media', 'Choose either images or one video — not both.');
            return;
        }

        if (hasVideo) {
            if (list.length > 1) {
                postForm.setError('media', 'Only one video can be posted at a time.');
                return;
            }
            const video = list[0];
            if (video.size > MAX_VIDEO_BYTES) {
                postForm.setError('media', 'Video must be 500MB or smaller.');
                return;
            }
            postForm.setData({ ...postForm.data, media_type: 'video', media: [video] });
            return;
        }

        const validImages: File[] = [];
        for (const file of list) {
            if (file.size > MAX_IMAGE_BYTES) {
                postForm.setError('media', `"${file.name}" is over 80MB. Each image must be 80MB or smaller.`);
                return;
            }
            validImages.push(file);
        }

        const existing = append && postForm.data.media_type === 'image' ? postForm.data.media : [];
        const merged = [...existing, ...validImages].slice(0, MAX_IMAGES);
        if (existing.length + validImages.length > MAX_IMAGES) {
            postForm.setError('media', `You can attach up to ${MAX_IMAGES} images. Extra files were skipped.`);
        }

        postForm.setData({ ...postForm.data, media_type: 'image', media: merged });
    }

    function removeMediaAt(index: number) {
        const next = mediaFiles.filter((_, i) => i !== index);
        postForm.clearErrors('media');
        postForm.setData({
            ...postForm.data,
            media: next,
            media_type: next.length === 0 ? 'image' : postForm.data.media_type,
        });
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    }

    function onDragEnter(event: DragEvent) {
        event.preventDefault();
        event.stopPropagation();
        dragDepth.current += 1;
        setDragging(true);
    }

    function onDragLeave(event: DragEvent) {
        event.preventDefault();
        event.stopPropagation();
        dragDepth.current = Math.max(0, dragDepth.current - 1);
        if (dragDepth.current === 0) {
            setDragging(false);
        }
    }

    function onDragOver(event: DragEvent) {
        event.preventDefault();
        event.stopPropagation();
    }

    function onDrop(event: DragEvent) {
        event.preventDefault();
        event.stopPropagation();
        dragDepth.current = 0;
        setDragging(false);
        const files = event.dataTransfer.files;
        if (!files?.length) {
            return;
        }
        const append = postForm.data.media_type === 'image' && postForm.data.media.length > 0
            && Array.from(files).every((file) => detectMediaType(file) === 'image');
        applyMediaFiles(files, { append });
    }

    function clearMedia() {
        applyMediaFiles(null);
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    }

    function togglePage(page: PageOption, isActive: boolean) {
        setTogglingPageId(page.id);
        router.patch(`/posting/pages/${page.id}`, { is_active: isActive }, {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => setTogglingPageId(null),
        });
    }

    const formError = postForm.errors.media || postForm.errors.caption || postForm.errors.social_account_id || postForm.errors.media_type || postForm.errors['media.0'];

    return (
        <AppLayout>
            <Head title="Posting" />
            <PageHeader
                title="Posting"
                description="Compose Facebook posts, review analytics, and choose which Pages appear in the dropdown."
                actions={canManageMeta ? (
                    <div className="flex flex-wrap gap-2">
                        {tab === 'pages' && metaConnected && (
                            <>
                                <Button type="button" variant="secondary" disabled={refreshing || brands.length === 0} onClick={refreshPages}>
                                    <RefreshCw className={`h-3.5 w-3.5 ${refreshing ? 'animate-spin' : ''}`} />
                                    {refreshing ? 'Refreshing…' : 'Refresh pages'}
                                </Button>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={() => router.delete('/posting/facebook', { preserveScroll: true, preserveState: true })}
                                >
                                    Disconnect Meta
                                </Button>
                            </>
                        )}
                        {tab === 'pages' && metaConfigured && (!metaConnected || pagesNeedingToken.length > 0) && (
                            <Button
                                type="button"
                                disabled={!metaConfigured}
                                onClick={() => { window.location.href = connectMetaUrl; }}
                            >
                                {metaConnected ? 'Reconnect Meta' : 'Connect with Meta'}
                            </Button>
                        )}
                    </div>
                ) : undefined}
            />

            {loadError && (
                <div className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    <p className="font-medium">Posting data could not load completely.</p>
                    <p className="mt-1 break-words font-mono text-xs">{loadError}</p>
                </div>
            )}

            <div className="mb-4 flex gap-2 overflow-x-auto">
                {TABS.map((item) => (
                    <button
                        key={item.id}
                        type="button"
                        className={`rounded-full px-3 py-1 text-sm ${tab === item.id ? 'bg-zinc-900 text-white' : 'bg-white text-zinc-700'}`}
                        onClick={() => setTab(item.id)}
                    >
                        {item.label}
                        {item.id === 'history' && posts.length > 0 ? ` (${posts.length})` : ''}
                        {item.id === 'pages' && pages.length > 0 ? ` (${pages.filter((p) => p.is_active).length}/${pages.length})` : ''}
                    </button>
                ))}
            </div>

            {tab === 'compose' && (
                <div className="grid gap-4 xl:grid-cols-[22rem_minmax(0,1fr)]">
                    <form className="space-y-3 rounded-xl border border-zinc-200 bg-white p-4" onSubmit={submitPost}>
                        <h2 className="font-semibold">{editingDraft ? 'Resume draft' : 'New Facebook post'}</h2>
                        {editingDraft && (
                            <div className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950">
                                <p className="font-medium">Editing draft #{editingDraft.id}</p>
                                <p className="mt-0.5">
                                    {(editingDraft.media_count ?? 0) > 0
                                        ? `Existing media (${editingDraft.media_count} file${(editingDraft.media_count ?? 0) === 1 ? '' : 's'}) will be reused unless you attach new files.`
                                        : 'Attach media before posting this draft.'}
                                </p>
                                <button type="button" className="mt-2 text-xs font-medium text-brand-700 hover:underline" onClick={clearDraftEdit}>
                                    Cancel draft edit
                                </button>
                            </div>
                        )}
                        {fromSchedule && composeDefaults?.slot_label && (
                            <div className="rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-sm text-brand-900">
                                <p className="font-medium">From posting schedule</p>
                                <p className="mt-0.5 text-brand-800">{composeDefaults.slot_label}</p>
                                <p className="mt-1 text-xs text-brand-700">
                                    {composeDefaults.media_type === 'video'
                                        ? 'Upload a video/reel for this slot, then schedule on Facebook.'
                                        : 'Upload image(s) for this slot, then schedule on Facebook.'}
                                </p>
                            </div>
                        )}
                        {managedPages.length === 0 ? (
                            <div className="space-y-3">
                                <p className="text-sm text-zinc-600">
                                    {pagesNeedingToken.length > 0
                                        ? canManageMeta
                                            ? 'Your Facebook Pages are listed, but Page ID/access tokens are missing or expired. Open Page settings, Connect with Meta, then Refresh pages — those values fill in automatically from Meta (you never type them).'
                                            : 'Facebook Pages need a fresh connection from an admin or manager. Ask them to reconnect Meta in Page settings — you can post with the shared workspace pages after that.'
                                        : hasAnyPages
                                            ? canManageMeta
                                                ? 'No active pages yet. Mark at least one Connected page Active in Page settings.'
                                                : 'No pages are Active for posting yet. Ask an admin or manager to turn a page Active in Page settings.'
                                            : workspacePagesReady || metaConnected
                                                ? 'No Pages loaded for your brands yet. Ask an admin or manager to refresh Facebook pages.'
                                                : canManageMeta
                                                    ? 'Connect with Meta in Page settings to load the Pages you manage. Page ID and access token are filled automatically.'
                                                    : 'Posting uses the shared Facebook pages set up by an admin. Ask them to connect Meta once — you do not need your own Facebook login.'}
                                </p>
                                {(canManageMeta || hasAnyPages) && (
                                    <Button type="button" variant="secondary" onClick={() => setTab('pages')}>
                                        Open Page settings
                                    </Button>
                                )}
                            </div>
                        ) : (
                            <>
                                {formError && (
                                    <p className="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">
                                        {formError}
                                    </p>
                                )}
                                <Field label="Facebook Page" error={postForm.errors.social_account_id}>
                                    <Select
                                        value={postForm.data.social_account_id}
                                        onChange={(event) => postForm.setData('social_account_id', event.target.value)}
                                        required
                                    >
                                        {managedPages.map((page) => (
                                            <option key={page.id} value={page.id}>
                                                {page.name}{page.handle ? ` (@${page.handle})` : ''}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                                <div className="space-y-1.5">
                                    <div className="flex items-center justify-between gap-2">
                                        <span className="text-sm font-medium text-zinc-800">Caption</span>
                                        <EmojiPickerButton
                                            fieldRef={captionRef}
                                            value={postForm.data.caption}
                                            onChange={(next) => postForm.setData('caption', next)}
                                        />
                                    </div>
                                    <Textarea
                                        ref={captionRef}
                                        value={postForm.data.caption}
                                        onChange={(event) => postForm.setData('caption', event.target.value)}
                                        required
                                        rows={5}
                                        placeholder="Write the caption for Facebook…"
                                    />
                                    {postForm.errors.caption && (
                                        <span className="block text-sm text-rose-700">{postForm.errors.caption}</span>
                                    )}
                                </div>
                                <Field label="Link creative content (optional)" error={postForm.errors.content_item_id}>
                                    <Select
                                        value={postForm.data.content_item_id}
                                        onChange={(event) => postForm.setData('content_item_id', event.target.value)}
                                    >
                                        <option value="">No linked content</option>
                                        {contentOptions.map((item) => (
                                            <option key={item.id} value={item.id}>
                                                {item.reference_number} · {item.title}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                                <Field label="When to publish" error={postForm.errors.publish_mode}>
                                    <Select
                                        value={postForm.data.publish_mode}
                                        onChange={(event) => postForm.setData('publish_mode', event.target.value as 'now' | 'schedule')}
                                    >
                                        <option value="now">Post now</option>
                                        <option value="schedule">Schedule on Facebook</option>
                                    </Select>
                                    <p className="mt-1 text-[11px] text-zinc-500">
                                        Schedule uploads to Facebook with a publish time — Facebook posts it, not this app.
                                    </p>
                                </Field>
                                {postForm.data.publish_mode === 'schedule' && (
                                    <Field label="Facebook publish time" error={postForm.errors.scheduled_publish_at}>
                                        <input
                                            type="datetime-local"
                                            className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm"
                                            value={postForm.data.scheduled_publish_at}
                                            min={scheduleBounds.min}
                                            max={scheduleBounds.max}
                                            required
                                            onChange={(event) => postForm.setData('scheduled_publish_at', event.target.value)}
                                        />
                                        <p className="mt-1 text-[11px] text-zinc-500">Between 10 minutes and 75 days from now. Appears on Content calendar.</p>
                                    </Field>
                                )}
                                {hasMedia && (
                                    <p className="text-xs text-zinc-500">
                                        Media:{' '}
                                        {postForm.data.media_type === 'video'
                                            ? `Video · ${mediaFiles[0]?.name}`
                                            : mediaFiles.length === 1
                                                ? `Image · ${mediaFiles[0]?.name}`
                                                : `${mediaFiles.length} images`}
                                    </p>
                                )}
                                {hasReusableDraftMedia && (
                                    <p className="text-xs text-zinc-500">
                                        Media: reusing {editingDraft?.media_count} saved draft file{(editingDraft?.media_count ?? 0) === 1 ? '' : 's'}
                                        {editingDraft?.media_name ? ` (${editingDraft.media_name})` : ''}
                                    </p>
                                )}
                                <Button type="submit" disabled={postForm.processing || !canSubmitMedia} className="w-full">
                                    {postForm.processing
                                        ? (postForm.data.publish_mode === 'schedule' ? 'Scheduling on Facebook…' : 'Posting to Facebook…')
                                        : editingDraft
                                            ? (postForm.data.publish_mode === 'schedule' ? 'Schedule draft on Facebook' : 'Post draft now')
                                            : (postForm.data.publish_mode === 'schedule' ? 'Schedule on Facebook' : 'Post now')}
                                </Button>
                            </>
                        )}
                    </form>

                    <section className="rounded-xl border border-zinc-200 bg-white">
                        <div className="border-b border-zinc-100 px-4 py-3">
                            <h2 className="font-semibold">Draft preview</h2>
                            <p className="text-sm text-zinc-600">Drag and drop images or a video, or click to browse — preview appears instantly.</p>
                        </div>
                        <div className="p-4">
                            <input
                                ref={fileInputRef}
                                type="file"
                                multiple
                                className="hidden"
                                accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/quicktime,video/x-m4v,.jpg,.jpeg,.png,.webp,.gif,.mp4,.mov,.m4v"
                                onChange={(event) => {
                                    const files = event.target.files;
                                    const append = postForm.data.media_type === 'image'
                                        && postForm.data.media.length > 0
                                        && !!files
                                        && Array.from(files).every((file) => detectMediaType(file) === 'image');
                                    applyMediaFiles(files, { append });
                                    event.target.value = '';
                                }}
                            />
                            <div
                                role="button"
                                tabIndex={0}
                                onClick={() => fileInputRef.current?.click()}
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter' || event.key === ' ') {
                                        event.preventDefault();
                                        fileInputRef.current?.click();
                                    }
                                }}
                                onDragEnter={onDragEnter}
                                onDragLeave={onDragLeave}
                                onDragOver={onDragOver}
                                onDrop={onDrop}
                                className={`relative cursor-pointer rounded-xl border border-dashed px-4 py-8 text-center transition ${
                                    dragging
                                        ? 'border-brand-600 bg-brand-50'
                                        : postForm.errors.media
                                            ? 'border-rose-300 bg-rose-50/40'
                                            : 'border-zinc-300 bg-white hover:border-zinc-400 hover:bg-zinc-50'
                                }`}
                            >
                                {dragging && (
                                    <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center rounded-xl bg-brand-50/95">
                                        <Upload className="h-8 w-8 text-brand-700" />
                                        <p className="mt-2 text-sm font-medium text-brand-800">
                                            {hasMedia && postForm.data.media_type === 'image' ? 'Drop to add images' : 'Drop to preview'}
                                        </p>
                                    </div>
                                )}

                                {hasMedia && previewUrls.length > 0 ? (
                                    <div className="text-left" onClick={(event) => event.stopPropagation()}>
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <p className="text-xs text-zinc-500">
                                                {selectedPage?.name ?? 'Select a page'}
                                                {postForm.data.media_type === 'video'
                                                    ? ' · Video'
                                                    : mediaFiles.length > 1
                                                        ? ` · ${mediaFiles.length} images`
                                                        : ' · Image'}
                                            </p>
                                            <div className="flex gap-2">
                                                {postForm.data.media_type === 'image' && mediaFiles.length < MAX_IMAGES && (
                                                    <Button type="button" size="sm" variant="secondary" onClick={() => fileInputRef.current?.click()}>
                                                        Add images
                                                    </Button>
                                                )}
                                                <Button type="button" size="sm" variant="secondary" onClick={() => {
                                                    clearMedia();
                                                    fileInputRef.current?.click();
                                                }}>
                                                    Replace
                                                </Button>
                                                <Button type="button" size="sm" variant="secondary" onClick={clearMedia}>
                                                    Remove all
                                                </Button>
                                            </div>
                                        </div>
                                        {postForm.data.media_type === 'image' ? (
                                            <div className={`mt-3 grid gap-2 ${mediaFiles.length > 1 ? 'grid-cols-2' : 'grid-cols-1'}`}>
                                                {previewUrls.map((url, index) => (
                                                    <div key={`${mediaFiles[index]?.name}-${index}`} className="relative">
                                                        <img
                                                            src={url}
                                                            alt={mediaFiles[index]?.name ?? `Image ${index + 1}`}
                                                            className="max-h-72 w-full rounded-lg border border-zinc-200 bg-zinc-50 object-contain"
                                                        />
                                                        <button
                                                            type="button"
                                                            className="absolute right-2 top-2 rounded-md bg-white/90 px-2 py-1 text-[11px] font-medium text-zinc-700 shadow-sm ring-1 ring-zinc-200 hover:bg-white"
                                                            onClick={() => removeMediaAt(index)}
                                                        >
                                                            Remove
                                                        </button>
                                                        <p className="mt-1 truncate text-[11px] text-zinc-500">{mediaFiles[index]?.name}</p>
                                                    </div>
                                                ))}
                                            </div>
                                        ) : (
                                            <>
                                                <video
                                                    src={previewUrls[0]}
                                                    controls
                                                    className="mt-3 max-h-80 w-full rounded-lg border border-zinc-200 bg-zinc-950"
                                                />
                                                <p className="mt-2 text-[11px] text-zinc-500">{mediaFiles[0]?.name}</p>
                                            </>
                                        )}
                                        {postForm.data.caption && (
                                            <p className="mt-3 whitespace-pre-wrap text-sm text-zinc-900">{postForm.data.caption}</p>
                                        )}
                                        <p className="mt-3 text-center text-xs text-zinc-500">
                                            {postForm.data.media_type === 'image' && mediaFiles.length < MAX_IMAGES
                                                ? `Drop more images to add (up to ${MAX_IMAGES}), or a video to replace`
                                                : 'Drop another file here to replace'}
                                        </p>
                                    </div>
                                ) : (
                                    <div className="flex flex-col items-center">
                                        <ImagePlus className="h-8 w-8 text-zinc-400" />
                                        <p className="mt-3 text-base font-semibold text-zinc-900">Drop images or video</p>
                                        <p className="mt-1 max-w-sm text-sm text-zinc-600">
                                            Up to {MAX_IMAGES} images (JPG, PNG, WEBP, GIF) at 80MB each, or one video (MP4, MOV) up to 500MB. Multiple images post together on Facebook.
                                        </p>
                                        <p className="mt-3 text-sm font-medium text-brand-700">or click to browse</p>
                                    </div>
                                )}
                            </div>
                            {(postForm.errors.media || postForm.errors['media.0']) && (
                                <p className="mt-2 text-sm text-rose-700">{postForm.errors.media || postForm.errors['media.0']}</p>
                            )}
                            {!hasMedia && postForm.data.caption && (
                                <p className="mt-3 whitespace-pre-wrap rounded-lg border border-zinc-100 bg-zinc-50 px-3 py-2 text-sm text-zinc-900">
                                    {postForm.data.caption}
                                </p>
                            )}
                        </div>
                    </section>
                </div>
            )}

            {tab === 'history' && (
                <section className="rounded-xl border border-zinc-200 bg-white">
                    <div className="border-b border-zinc-100 px-4 py-3">
                        <h2 className="font-semibold">Post history & analytics</h2>
                        <p className="text-sm text-zinc-600">
                            {posts.length === 0
                                ? 'Published posts appear here with likes, comments, shares, views, and reach.'
                                : `${posts.length} recent post${posts.length === 1 ? '' : 's'} — refresh metrics anytime.`}
                        </p>
                    </div>

                    {posts.length === 0 ? (
                        <div className="p-4">
                            <EmptyState title="No posts yet" body="After you click Post now, the live Facebook post and its engagement will show up here." />
                        </div>
                    ) : (
                        <ul className="divide-y divide-zinc-100">
                            {posts.map((post) => {
                                const isFocused = focus?.post_id === post.id;
                                // Published posts are view-only; scheduled can still update caption before Facebook posts.
                                const canEditCaption = post.status === 'scheduled';
                                const canEditDraft = post.status === 'draft';
                                const canDelete = post.status === 'draft' || post.status === 'failed';
                                return (
                                    <li
                                        key={post.id}
                                        id={`post-${post.id}`}
                                        ref={isFocused ? focusedPostRef : undefined}
                                        className={`px-4 py-4 ${isFocused ? 'bg-brand-50/60 ring-1 ring-inset ring-brand-200' : ''}`}
                                    >
                                        <div className="flex flex-wrap items-start justify-between gap-3">
                                            <div className="min-w-0 flex-1">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <StatusBadge status={post.status} />
                                                    <p className="text-xs font-medium uppercase tracking-wide text-zinc-500">
                                                        {post.page?.name} · {(post.media_count ?? 1) > 1 ? `${post.media_count} images` : post.media_type}
                                                        {post.published_at ? ` · ${formatDate(post.published_at)}` : ''}
                                                        {post.scheduled_publish_at ? ` · schedules ${formatDate(post.scheduled_publish_at)}` : ''}
                                                    </p>
                                                </div>
                                                <p className="mt-1 text-xs text-zinc-500">
                                                    Posted by {post.author?.name ?? 'Unknown user'}
                                                </p>
                                                {post.content && (
                                                    <Link href={`/content/${post.content.id}`} className="mt-1 inline-block text-xs text-brand-700 hover:underline">
                                                        Linked content: {post.content.reference_number} · {post.content.title}
                                                    </Link>
                                                )}
                                                {editingPostId === post.id && canEditCaption ? (
                                                    <HistoryCaptionEditor
                                                        post={post}
                                                        onCancel={() => setEditingPostId(null)}
                                                        onSaved={() => setEditingPostId(null)}
                                                    />
                                                ) : (
                                                    <p className="mt-1 whitespace-pre-wrap text-sm text-zinc-900">{post.caption}</p>
                                                )}
                                                {post.error_message && <p className="mt-1 text-sm text-rose-700">{post.error_message}</p>}
                                            </div>
                                            <div className="flex items-center gap-1">
                                                {canEditDraft && (
                                                    <button
                                                        type="button"
                                                        aria-label="Edit draft in Compose"
                                                        title="Edit draft in Compose"
                                                        className="rounded-md p-1.5 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-800"
                                                        onClick={() => editDraft(post)}
                                                    >
                                                        <Pencil className="h-4 w-4" />
                                                    </button>
                                                )}
                                                {canEditCaption && editingPostId !== post.id && (
                                                    <button
                                                        type="button"
                                                        aria-label="Edit caption"
                                                        title="Edit caption"
                                                        className="rounded-md p-1.5 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-800"
                                                        onClick={() => setEditingPostId(post.id)}
                                                    >
                                                        <Pencil className="h-4 w-4" />
                                                    </button>
                                                )}
                                                {post.permalink_url && (
                                                    <a
                                                        href={post.permalink_url}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        aria-label="Open on Facebook"
                                                        title="Open on Facebook"
                                                        className="rounded-md p-1.5 text-[#1877F2] hover:bg-sky-50"
                                                    >
                                                        <FacebookGlyph className="h-4 w-4" />
                                                    </a>
                                                )}
                                                {post.status === 'published' && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="secondary"
                                                        onClick={() => router.post(`/posting/${post.id}/sync`, {}, { preserveScroll: true, preserveState: true })}
                                                    >
                                                        <RefreshCw className="h-3.5 w-3.5" /> Refresh metrics
                                                    </Button>
                                                )}
                                                {canDelete && (
                                                    <button
                                                        type="button"
                                                        aria-label={post.status === 'draft' ? 'Delete draft' : 'Delete failed post'}
                                                        title={post.status === 'draft' ? 'Delete draft' : 'Delete failed post'}
                                                        className="rounded-md p-1.5 text-zinc-500 hover:bg-rose-50 hover:text-rose-700"
                                                        onClick={() => { void deleteHistoryPost(post); }}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                        <div className="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-5">
                                            <Metric icon={Heart} label="Likes" value={post.likes_count} />
                                            <Metric icon={MessageCircle} label="Comments" value={post.comments_count} />
                                            <Metric icon={Share2} label="Shares" value={post.shares_count} />
                                            <Metric icon={Eye} label="Views" value={post.views_count} />
                                            <Metric icon={Eye} label="Reach" value={post.reach} extra={post.engagement_rate != null ? `${post.engagement_rate}% eng.` : undefined} />
                                        </div>
                                        {post.metrics_synced_at && (
                                            <p className="mt-2 text-[11px] text-zinc-500">Metrics synced {formatDate(post.metrics_synced_at)}</p>
                                        )}
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </section>
            )}

            {tab === 'pages' && (
                <section className="rounded-xl border border-zinc-200 bg-white">
                    <div className="border-b border-zinc-100 px-4 py-3">
                        <h2 className="font-semibold">Page settings</h2>
                        <p className="text-sm text-zinc-600">
                            {canManageMeta
                                ? 'Page ID and access token are filled automatically when you Connect with Meta and Refresh pages. Turn pages Active to show them in Compose.'
                                : 'These are the shared Facebook pages for your brands. An admin or manager connects Meta once — you can post without linking your own Facebook account.'}
                        </p>
                    </div>

                    {canManageMeta && (
                        <MetaCredentialsPanel
                            configured={metaConfigured}
                            appId={metaAppId}
                            appSecret={metaAppSecret}
                            redirectUri={metaRedirectUri}
                        />
                    )}

                    {canManageMeta && metaConfigured && (!metaConnected || pagesNeedingToken.length > 0) && (
                        <div className="m-4 space-y-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-950">
                            <p className="font-medium">
                                {!metaConnected
                                    ? 'Meta is not connected (or the saved login expired).'
                                    : `${pagesNeedingToken.length} page${pagesNeedingToken.length === 1 ? '' : 's'} need a fresh Page ID / access token from Meta.`}
                            </p>
                            <p>
                                You do not enter Page ID or tokens by hand. Click <strong>Connect with Meta</strong>, approve access — pages and tokens load automatically.
                            </p>
                            <div className="flex flex-wrap gap-2">
                                <Button type="button" onClick={() => { window.location.href = connectMetaUrl; }}>
                                    {!metaConnected ? 'Connect with Meta' : 'Reconnect Meta'}
                                </Button>
                                {metaConnected && (
                                    <Button type="button" variant="secondary" disabled={refreshing || brands.length === 0} onClick={refreshPages}>
                                        <RefreshCw className={`h-3.5 w-3.5 ${refreshing ? 'animate-spin' : ''}`} />
                                        {refreshing ? 'Refreshing…' : 'Refresh pages'}
                                    </Button>
                                )}
                            </div>
                        </div>
                    )}

                    {!canManageMeta && !hasAnyPages && (
                        <div className="p-4">
                            <EmptyState
                                title="Waiting on shared pages"
                                body="Ask an admin or manager to connect Meta and activate Facebook pages for your brand. You can post as soon as those pages are ready."
                            />
                        </div>
                    )}

                    {hasAnyPages ? (
                        <ul className="divide-y divide-zinc-100">
                            {pages.map((page) => (
                                <li key={page.id} className="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                                    <div className="min-w-0">
                                        <p className="font-medium text-zinc-900">
                                            {page.name}
                                            {page.handle ? <span className="font-normal text-zinc-500"> @{page.handle}</span> : null}
                                        </p>
                                        <p className="text-xs text-zinc-500">
                                            {page.brand?.name ? `${page.brand.name} · ` : ''}
                                            {page.is_ready ? 'Connected (token ready)' : 'Missing Page ID / token — reconnect Meta'}
                                            {' · '}
                                            {page.is_active ? 'Active for posting' : 'Inactive'}
                                            {page.can_post === false ? ' · View only' : ''}
                                        </p>
                                    </div>
                                    {canManagePages ? (
                                        <label className="flex items-center gap-2 text-sm text-zinc-700">
                                            <input
                                                type="checkbox"
                                                className="h-4 w-4 rounded border-zinc-300 text-brand-700 focus:ring-brand-600"
                                                checked={page.is_active}
                                                disabled={!page.is_ready || togglingPageId === page.id}
                                                onChange={(event) => togglePage(page, event.target.checked)}
                                            />
                                            {togglingPageId === page.id ? 'Saving…' : page.is_active ? 'Active' : 'Inactive'}
                                        </label>
                                    ) : (
                                        <span className={`text-xs font-medium ${page.is_active && page.is_ready ? 'text-emerald-700' : 'text-zinc-500'}`}>
                                            {page.is_active && page.is_ready ? 'Ready to post' : page.is_active ? 'Needs token' : 'Inactive'}
                                        </span>
                                    )}
                                </li>
                            ))}
                        </ul>
                    ) : canManageMeta && metaConnected ? (
                        <div className="p-4">
                            <EmptyState title="No pages loaded" body="Click Refresh pages to pull every Facebook Page you manage from Meta." />
                        </div>
                    ) : null}
                </section>
            )}
        </AppLayout>
    );
}

function MetaCredentialsPanel({
    configured,
    appId,
    appSecret,
    redirectUri,
}: {
    configured: boolean;
    appId: string;
    appSecret: string;
    redirectUri: string;
}) {
    const basePath = usePage<SharedProps>().props.basePath ?? '';
    const [showSecret, setShowSecret] = useState(false);
    const form = useForm({
        app_id: appId || '',
        app_secret: appSecret || '',
    });

    useEffect(() => {
        form.setData({
            app_id: appId || '',
            app_secret: appSecret || '',
        });
    }, [appId, appSecret]);

    function save(event: FormEvent) {
        event.preventDefault();
        form.post('/posting/facebook/credentials', {
            preserveScroll: true,
        });
    }

    return (
        <div className={`m-4 space-y-3 rounded-lg border px-3 py-3 text-sm ${configured ? 'border-zinc-200 bg-zinc-50 text-zinc-800' : 'border-amber-200 bg-amber-50 text-amber-950'}`}>
            <div>
                <p className="font-medium">
                    {configured ? 'Meta App credentials' : 'Save Meta App ID and Secret'}
                </p>
                <p className="mt-1 text-xs opacity-90">
                    Paste the App ID and App Secret from Meta Developer → your app → Settings → Basic.
                    Saved here (not only in .env) so a git pull does not wipe them. In Meta, add this exact OAuth redirect URI:
                </p>
                {redirectUri ? (
                    <code className="mt-2 block break-all rounded-md bg-white/80 px-2 py-1.5 text-[11px] text-zinc-900 ring-1 ring-zinc-200">
                        {redirectUri}
                    </code>
                ) : null}
                <p className="mt-2 text-xs opacity-90">
                    Also set Privacy Policy URL and Terms of Service URL in Meta App settings to:{' '}
                    <a className="font-medium underline" href={withBase('/privacy', basePath)} target="_blank" rel="noreferrer">Privacy Policy</a>
                    {' · '}
                    <a className="font-medium underline" href={withBase('/terms', basePath)} target="_blank" rel="noreferrer">Terms of Service</a>
                </p>
            </div>
            <form className="grid gap-2 sm:grid-cols-2" onSubmit={save}>
                <Field label="App ID" error={form.errors.app_id}>
                    <Input
                        value={form.data.app_id}
                        onChange={(event) => form.setData('app_id', event.target.value)}
                        placeholder="Facebook App ID"
                        required
                        autoComplete="off"
                    />
                </Field>
                <Field label="App Secret" error={form.errors.app_secret}>
                    <div className="relative">
                        <Input
                            type={showSecret ? 'text' : 'password'}
                            value={form.data.app_secret}
                            onChange={(event) => form.setData('app_secret', event.target.value)}
                            placeholder="Facebook App Secret"
                            required={!configured}
                            autoComplete="off"
                            className="pr-10"
                        />
                        <button
                            type="button"
                            className="absolute inset-y-0 right-0 flex items-center px-3 text-zinc-500 hover:text-zinc-800"
                            onClick={() => setShowSecret((open) => !open)}
                            aria-label={showSecret ? 'Hide app secret' : 'Show app secret'}
                            title={showSecret ? 'Hide' : 'Show'}
                        >
                            {showSecret ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                        </button>
                    </div>
                </Field>
                <div className="sm:col-span-2">
                    <Button type="submit" size="sm" disabled={form.processing}>
                        {form.processing ? 'Saving…' : configured ? 'Update credentials' : 'Save credentials'}
                    </Button>
                </div>
            </form>
        </div>
    );
}

function HistoryCaptionEditor({
    post,
    onCancel,
    onSaved,
}: {
    post: PostRow;
    onCancel: () => void;
    onSaved: () => void;
}) {
    const form = useForm({ caption: post.caption ?? '' });
    const captionRef = useRef<HTMLTextAreaElement>(null);

    function save(event: FormEvent) {
        event.preventDefault();
        form.put(`/posting/${post.id}`, {
            preserveScroll: true,
            onSuccess: () => onSaved(),
        });
    }

    return (
        <form className="mt-2 space-y-2 rounded-lg border border-zinc-200 bg-white p-3" onSubmit={save}>
            <div className="space-y-1.5">
                <div className="flex items-center justify-between gap-2">
                    <span className="text-sm font-medium text-zinc-800">Caption</span>
                    <EmojiPickerButton
                        fieldRef={captionRef}
                        value={form.data.caption}
                        onChange={(next) => form.setData('caption', next)}
                    />
                </div>
                <Textarea
                    ref={captionRef}
                    value={form.data.caption}
                    onChange={(event) => form.setData('caption', event.target.value)}
                    rows={4}
                    required
                />
                {form.errors.caption && (
                    <span className="block text-sm text-rose-700">{form.errors.caption}</span>
                )}
            </div>
            <div className="flex flex-wrap gap-2">
                <Button type="submit" size="sm" disabled={form.processing}>
                    {form.processing ? 'Saving…' : 'Save on Facebook'}
                </Button>
                <Button type="button" size="sm" variant="secondary" onClick={onCancel} disabled={form.processing}>
                    Cancel
                </Button>
            </div>
        </form>
    );
}

function StatusBadge({ status }: { status: string }) {
    const styles =
        status === 'published'
            ? 'bg-emerald-50 text-emerald-800'
            : status === 'scheduled'
                ? 'bg-sky-50 text-sky-800'
                : status === 'failed'
                    ? 'bg-rose-50 text-rose-800'
                    : 'bg-zinc-100 text-zinc-700';

    return (
        <span className={`inline-flex rounded px-1.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide ${styles}`}>
            {status}
        </span>
    );
}

function Metric({ icon: Icon, label, value, extra }: { icon: typeof Heart; label: string; value: number; extra?: string }) {
    return (
        <div className="rounded-lg bg-zinc-50 px-3 py-2">
            <p className="flex items-center gap-1 text-[11px] font-medium uppercase tracking-wide text-zinc-500">
                <Icon className="h-3.5 w-3.5" /> {label}
            </p>
            <p className="mt-1 text-lg font-semibold text-zinc-900">{value.toLocaleString()}</p>
            {extra && <p className="text-[11px] text-zinc-500">{extra}</p>}
        </div>
    );
}
