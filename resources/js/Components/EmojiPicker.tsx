import * as Dropdown from '@radix-ui/react-dropdown-menu';
import { SmilePlus } from 'lucide-react';
import { RefObject } from 'react';

const EMOJIS = [
    '😀', '😃', '😄', '😁', '😆', '😅', '😂', '🤣', '😊', '😇',
    '🙂', '😉', '😍', '🥰', '😘', '😗', '🤗', '🤩', '🤔', '🤨',
    '😐', '😑', '😶', '🙄', '😏', '😣', '😥', '😮', '🤐', '😯',
    '😪', '😫', '🥱', '😴', '😌', '😛', '😜', '😝', '🤤', '😒',
    '😓', '😔', '😕', '🙃', '🤑', '😲', '☹️', '🙁', '😖', '😞',
    '😟', '😤', '😢', '😭', '😦', '😧', '😨', '😩', '🤯', '😬',
    '😰', '😱', '🥵', '🥶', '😳', '🤪', '😵', '😡', '😠', '🤬',
    '😷', '🤒', '🤕', '🤢', '🤮', '🥴', '🥺', '🤠', '🥳', '😎',
    '🤓', '🧐', '👍', '👎', '👏', '🙌', '👐', '🤝', '🙏', '💪',
    '✌️', '🤞', '🤟', '🤘', '👌', '🤌', '👈', '👉', '👆', '👇',
    '👋', '🫡', '❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍',
    '💔', '❣️', '💕', '💞', '💓', '💗', '💖', '💘', '💝', '✨',
    '⭐', '🌟', '💫', '🔥', '💯', '🎉', '🎊', '🎈', '🎁', '🏆',
    '🥇', '🎯', '🚀', '💡', '📸', '🎬', '🎤', '🎧', '🎵', '🎶',
    '☀️', '🌙', '⭐', '🌈', '☁️', '⚡', '❄️', '🌸', '🌺', '🌻',
    '✅', '❌', '⚠️', '📌', '📍', '🔗', '💬', '💭', '📢', '🔔',
];

export function insertEmojiInField(
    field: HTMLTextAreaElement | HTMLInputElement | null,
    emoji: string,
    value: string,
    onChange: (next: string) => void,
) {
    if (!field) {
        onChange(`${value}${emoji}`);
        return;
    }

    const start = field.selectionStart ?? value.length;
    const end = field.selectionEnd ?? value.length;
    const next = `${value.slice(0, start)}${emoji}${value.slice(end)}`;
    onChange(next);

    requestAnimationFrame(() => {
        field.focus();
        const cursor = start + emoji.length;
        field.setSelectionRange(cursor, cursor);
    });
}

export function EmojiPickerButton({
    fieldRef,
    value,
    onChange,
}: {
    fieldRef: RefObject<HTMLTextAreaElement | HTMLInputElement | null>;
    value: string;
    onChange: (next: string) => void;
}) {
    return (
        <Dropdown.Root>
            <Dropdown.Trigger asChild>
                <button
                    type="button"
                    className="inline-flex items-center gap-1.5 rounded-lg border border-zinc-300 bg-white px-2.5 py-1.5 text-xs font-medium text-zinc-700 hover:bg-zinc-50"
                    aria-label="Insert emoji"
                    title="Insert emoji"
                >
                    <SmilePlus className="h-3.5 w-3.5" />
                    Emoji
                </button>
            </Dropdown.Trigger>
            <Dropdown.Portal>
                <Dropdown.Content
                    className="z-50 max-h-64 w-[18.5rem] overflow-y-auto rounded-xl border border-zinc-200 bg-white p-2 shadow-xl"
                    align="start"
                    sideOffset={6}
                >
                    <div className="grid grid-cols-8 gap-0.5">
                        {EMOJIS.map((emoji, index) => (
                            <Dropdown.Item
                                key={`${emoji}-${index}`}
                                className="flex h-8 w-8 cursor-pointer items-center justify-center rounded-md text-lg outline-none data-[highlighted]:bg-zinc-100"
                                onSelect={(event) => {
                                    event.preventDefault();
                                    insertEmojiInField(fieldRef.current, emoji, value, onChange);
                                }}
                            >
                                {emoji}
                            </Dropdown.Item>
                        ))}
                    </div>
                </Dropdown.Content>
            </Dropdown.Portal>
        </Dropdown.Root>
    );
}
