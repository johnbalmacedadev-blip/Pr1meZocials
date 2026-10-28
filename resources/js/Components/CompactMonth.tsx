import { format, isSameMonth, isToday, parseISO } from 'date-fns';
import { ReactNode, useEffect, useState } from 'react';
import { cn } from '@/lib/utils';

/**
 * Phone-sized month: the whole month fits on screen with a dot per item, and
 * tapping a day lists that day's items below (same pattern as the dashboard schedule).
 */
export function CompactMonth<T extends { id: string | number }>({
    days,
    anchor,
    items,
    dateOf,
    dotClass,
    renderItem,
    dayAction,
    noun = 'item',
    className,
}: {
    days: Date[];
    anchor: Date;
    items: T[];
    dateOf: (item: T) => string;
    dotClass: (item: T) => string;
    renderItem: (item: T) => ReactNode;
    dayAction?: (dateKey: string) => ReactNode;
    noun?: string;
    className?: string;
}) {
    const todayKey = format(new Date(), 'yyyy-MM-dd');
    const initial = () => (days.some((day) => format(day, 'yyyy-MM-dd') === todayKey) ? todayKey : format(isSameMonth(days[0], anchor) ? days[0] : anchor, 'yyyy-MM-dd'));
    const [selected, setSelected] = useState(initial);
    const rangeKey = `${format(days[0], 'yyyy-MM-dd')}:${days.length}`;

    // Moving to another month or week selects a day inside it.
    useEffect(() => setSelected(initial()), [rangeKey]); // eslint-disable-line react-hooks/exhaustive-deps

    const byDay = new Map<string, T[]>();
    for (const item of items) {
        const key = dateOf(item);
        byDay.set(key, [...(byDay.get(key) ?? []), item]);
    }
    const selectedItems = byDay.get(selected) ?? [];

    return (
        <div className={cn('rounded-xl border border-zinc-200 bg-white p-2', className)}>
            <div className="mb-1 grid grid-cols-7 text-center text-[11px] font-medium text-zinc-500">
                {['M', 'T', 'W', 'T', 'F', 'S', 'S'].map((day, index) => <div key={index}>{day}</div>)}
            </div>
            <div className="grid grid-cols-7 gap-1">
                {days.map((day) => {
                    const key = format(day, 'yyyy-MM-dd');
                    const dayItems = byDay.get(key) ?? [];
                    const isSelected = key === selected;
                    return (
                        <button
                            key={key}
                            type="button"
                            onClick={() => setSelected(key)}
                            className={cn(
                                'flex h-11 flex-col items-center justify-start rounded-lg pt-1 text-xs',
                                isSameMonth(day, anchor) ? 'text-zinc-800' : 'text-zinc-300',
                                isSelected ? 'bg-brand-700 font-semibold text-white' : isToday(day) ? 'bg-brand-50 font-semibold text-brand-700' : 'hover:bg-zinc-50',
                            )}
                            aria-label={`${format(day, 'MMMM d')}${dayItems.length ? `, ${dayItems.length} ${noun}${dayItems.length === 1 ? '' : 's'}` : ''}`}
                            aria-pressed={isSelected}
                        >
                            {format(day, 'd')}
                            {dayItems.length > 0 && (
                                <span className="mt-1 flex items-center gap-0.5">
                                    {dayItems.slice(0, 3).map((item) => (
                                        <span key={item.id} className={cn('h-1.5 w-1.5 rounded-full', isSelected ? 'bg-white' : dotClass(item))} />
                                    ))}
                                    {dayItems.length > 3 && <span className={cn('text-[9px] leading-none', isSelected ? 'text-white' : 'text-zinc-500')}>+</span>}
                                </span>
                            )}
                        </button>
                    );
                })}
            </div>
            <div className="mt-3 border-t border-zinc-100 px-1 pt-3">
                <div className="mb-2 flex items-center justify-between gap-2">
                    <p className="text-xs font-semibold uppercase tracking-wide text-zinc-500">{format(parseISO(selected), 'EEEE, MMM d')}</p>
                    {dayAction?.(selected)}
                </div>
                {selectedItems.length === 0
                    ? <p className="pb-1 text-sm text-zinc-500">Nothing on this day.</p>
                    : <div className="space-y-1.5">{selectedItems.map((item) => <div key={item.id}>{renderItem(item)}</div>)}</div>}
            </div>
        </div>
    );
}
