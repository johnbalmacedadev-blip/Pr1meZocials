import { router, useForm } from '@inertiajs/react';
import { FormEvent, useEffect, useState } from 'react';
import { Button, Field, Input, Modal } from '@/Components/ui';
import { formatDateTime, philippineInputValue } from '@/lib/utils';

export interface MovablePost {
    id: number;
    /** Where the post shows on calendars now. */
    at: string;
    /** When it really went out. */
    published_at: string | null;
    moved: boolean;
}

/**
 * Moves a published post on the team's calendars. The post on Facebook keeps its real date.
 */
export function PostCalendarDateModal({ post, onClose }: { post: MovablePost | null; onClose: () => void }) {
    const form = useForm({ calendar_at: '' });
    const [resetting, setResetting] = useState(false);

    useEffect(() => {
        if (post) {
            form.setData('calendar_at', philippineInputValue(post.at));
            form.clearErrors();
        }
    }, [post?.id]); // eslint-disable-line react-hooks/exhaustive-deps

    function save(event: FormEvent) {
        event.preventDefault();
        if (post) {
            form.put(`/posting/${post.id}/calendar-date`, { preserveScroll: true, onSuccess: onClose });
        }
    }

    function useRealDate() {
        if (!post) {
            return;
        }
        setResetting(true);
        router.put(`/posting/${post.id}/calendar-date`, { calendar_at: null }, {
            preserveScroll: true,
            onSuccess: onClose,
            onFinish: () => setResetting(false),
        });
    }

    return (
        <Modal open={post !== null} onOpenChange={(open) => { if (!open) onClose(); }} title="Change calendar date">
            <form onSubmit={save} className="space-y-4">
                <Field label="Show on calendar at (Philippine time)" error={form.errors.calendar_at}>
                    <Input type="datetime-local" value={form.data.calendar_at} onChange={(event) => form.setData('calendar_at', event.target.value)} required />
                </Field>
                <p className="text-sm text-zinc-600">
                    {post?.published_at ? `Posted to the page on ${formatDateTime(post.published_at)}. ` : ''}
                    This only moves the post on your calendars. The date shown on Facebook stays the same.
                </p>
                <div className="flex flex-wrap gap-2">
                    <Button type="submit" disabled={form.processing || resetting}>{form.processing ? 'Saving…' : 'Save date'}</Button>
                    {post?.moved && <Button type="button" variant="secondary" disabled={form.processing || resetting} onClick={useRealDate}>Use real posted date</Button>}
                    <Button type="button" variant="ghost" onClick={onClose}>Cancel</Button>
                </div>
            </form>
        </Modal>
    );
}
