import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Gift, Loader2 } from 'lucide-react';
import { FormEvent, useEffect, useRef, useState } from 'react';
import type { SharedProps } from '@/types';

interface Props {
    names: string[];
    draw: string | null;
    claimed: { name: string; picker: string } | null;
    allTaken: boolean;
}

const SPIN_MS = 2600;

const pageClass = 'relative flex min-h-screen flex-col items-center justify-center overflow-hidden bg-gradient-to-br from-emerald-950 via-zinc-950 to-red-950 px-4 py-10 text-center text-white';
const buttonClass = 'rounded-2xl bg-gradient-to-b from-amber-300 to-amber-500 font-black text-zinc-950 shadow-[0_8px_0_0_#92400e,0_20px_40px_rgba(0,0,0,0.5)] transition hover:brightness-110 active:translate-y-1.5 active:shadow-[0_2px_0_0_#92400e] disabled:opacity-60';

function nextIndex(previous: number, length: number): number {
    if (length < 2) {
        return 0;
    }
    const next = Math.floor(Math.random() * (length - 1));
    return next >= previous ? next + 1 : next;
}

export default function Rollet({ names, draw, claimed, allTaken }: Props) {
    const errors = (usePage<SharedProps>().props.errors ?? {}) as Record<string, string>;
    const [index, setIndex] = useState(0);
    const [tick, setTick] = useState(0);
    const [spinning, setSpinning] = useState(false);
    const [dismissed, setDismissed] = useState(false);
    const serverDone = useRef(false);
    const spinStart = useRef(0);
    const form = useForm({ picker_name: '' });

    const showClaimed = claimed !== null && !dismissed && !spinning && !draw;
    const flashing = !spinning && !draw && !showClaimed && !allTaken;

    // Names keep flashing while waiting.
    useEffect(() => {
        if (!flashing) {
            return;
        }
        const slow = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const timer = window.setInterval(() => {
            setIndex((previous) => nextIndex(previous, names.length));
            setTick((value) => value + 1);
        }, slow ? 900 : 170);
        return () => window.clearInterval(timer);
    }, [flashing, names.length]);

    // After the press the names race, then slow down until the server's answer can be shown.
    useEffect(() => {
        if (!spinning) {
            return;
        }
        let timer = 0;
        const step = () => {
            const elapsed = performance.now() - spinStart.current;
            if (elapsed >= SPIN_MS && serverDone.current) {
                setSpinning(false);
                return;
            }
            setIndex((previous) => nextIndex(previous, names.length));
            setTick((value) => value + 1);
            timer = window.setTimeout(step, elapsed < 1500 ? 60 : Math.min(60 + (elapsed - 1500) * 0.22, 320));
        };
        step();
        return () => window.clearTimeout(timer);
    }, [spinning, names.length]);

    function press() {
        form.reset();
        form.clearErrors();
        serverDone.current = false;
        spinStart.current = performance.now();
        setDismissed(true);
        setSpinning(true);
        router.post('/rollet/draw', {}, {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => { serverDone.current = true; },
        });
    }

    function confirm(event: FormEvent) {
        event.preventDefault();
        setDismissed(false);
        form.post('/rollet/confirm', { preserveScroll: true, preserveState: true, onSuccess: () => form.reset() });
    }

    return (
        <main className={pageClass}>
            <Head title="Rollet" />
            <style>{`
                @keyframes rollet-pop { 0% { transform: scale(.7); opacity: .2; } 100% { transform: scale(1); opacity: 1; } }
                @keyframes rollet-win { 0% { transform: scale(.4) rotate(-4deg); } 60% { transform: scale(1.12) rotate(2deg); } 100% { transform: scale(1) rotate(0); } }
                .rollet-flash { animation: rollet-pop 120ms ease-out; }
                .rollet-win { animation: rollet-win 550ms cubic-bezier(.2,.9,.3,1.2); }
                @media (prefers-reduced-motion: reduce) { .rollet-flash, .rollet-win { animation: none; } }
            `}</style>

            <div className="mb-6 flex items-center gap-2 text-sm font-semibold uppercase tracking-[0.3em] text-amber-300">
                <Gift className="h-5 w-5" /> Exchange Gift
            </div>

            {showClaimed && claimed ? (
                <section className="w-full max-w-lg">
                    <p className="text-lg text-white/80">{claimed.picker}, your exchange gift goes to</p>
                    <p className="rollet-win my-5 break-words text-6xl font-black text-amber-300 sm:text-7xl">{claimed.name}</p>
                    <p className="text-white/80">Saved! Keep it a secret.</p>
                    <button type="button" onClick={() => setDismissed(true)} className={`${buttonClass} mt-8 px-8 py-3 text-lg`}>Next person</button>
                </section>
            ) : draw && !spinning ? (
                <section className="w-full max-w-lg">
                    <p className="text-lg text-white/80">You picked</p>
                    <p className="rollet-win my-5 break-words text-6xl font-black text-amber-300 sm:text-7xl">{draw}</p>
                    <form onSubmit={confirm} className="mx-auto mt-8 max-w-sm space-y-3">
                        <label className="block text-left text-sm font-semibold text-white/90" htmlFor="rollet-name">Write your name</label>
                        <input
                            id="rollet-name"
                            value={form.data.picker_name}
                            onChange={(event) => form.setData('picker_name', event.target.value)}
                            maxLength={80}
                            autoComplete="name"
                            required
                            autoFocus
                            className="w-full rounded-xl border-2 border-white/20 bg-white px-4 py-3 text-center text-lg font-semibold text-zinc-900 outline-none focus:border-amber-400"
                        />
                        {form.errors.picker_name && <p role="alert" className="rounded-lg bg-red-100 p-2 text-sm font-medium text-red-900">{form.errors.picker_name}</p>}
                        <button type="submit" disabled={form.processing} className={`${buttonClass} flex w-full items-center justify-center gap-2 py-3.5 text-xl`}>
                            {form.processing && <Loader2 className="h-5 w-5 animate-spin" />}
                            Confirm
                        </button>
                    </form>
                </section>
            ) : (
                <section className="w-full max-w-2xl">
                    <p key={tick} aria-live="off" className={`rollet-flash flex min-h-[8rem] items-center justify-center break-words text-6xl font-black sm:text-8xl ${spinning ? 'text-amber-300' : 'text-white/90'}`}>
                        {names[index]}
                    </p>
                    {errors.rollet && !spinning && <p role="alert" className="mx-auto mt-2 max-w-md rounded-lg bg-red-100 p-3 text-sm font-medium text-red-900">{errors.rollet}</p>}
                    {allTaken ? (
                        <p className="mt-8 text-xl font-semibold text-amber-300">All names have been picked. Merry Christmas!</p>
                    ) : (
                        <button type="button" onClick={press} disabled={spinning} className={`${buttonClass} mt-10 px-10 py-6 text-3xl sm:text-4xl`}>
                            PINDUTIN MO NA!
                        </button>
                    )}
                </section>
            )}
        </main>
    );
}
