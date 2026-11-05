import { Head, router, usePage } from '@inertiajs/react';
import { Gift, Loader2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { SharedProps } from '@/types';

interface Props {
    names: string[];
    people: { id: number; label: string }[];
    draw: { name: string; picker: string | null } | null;
    claimed: { name: string; picker: string } | null;
    allTaken: boolean;
}

const SPIN_MS = 2600;

const pageClass = 'relative flex min-h-screen flex-col items-center justify-center overflow-hidden bg-gradient-to-br from-emerald-950 via-zinc-950 to-red-950 px-4 py-10 text-center text-white';
const buttonClass = 'rounded-2xl bg-gradient-to-b from-amber-300 to-amber-500 font-black text-zinc-950 shadow-[0_8px_0_0_#92400e,0_20px_40px_rgba(0,0,0,0.5)] transition hover:brightness-110 active:translate-y-1.5 active:shadow-[0_2px_0_0_#92400e] disabled:opacity-50';

function nextIndex(previous: number, length: number): number {
    if (length < 2) {
        return 0;
    }
    const next = Math.floor(Math.random() * (length - 1));
    return next >= previous ? next + 1 : next;
}

export default function Rollet({ names, people, draw, claimed, allTaken }: Props) {
    const errors = (usePage<SharedProps>().props.errors ?? {}) as Record<string, string>;
    const [index, setIndex] = useState(0);
    const [tick, setTick] = useState(0);
    const [choosing, setChoosing] = useState(false);
    const [pickerId, setPickerId] = useState<number | null>(null);
    const [spinning, setSpinning] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const [dismissed, setDismissed] = useState(false);
    const serverDone = useRef(false);
    const spinStart = useRef(0);

    const showClaimed = claimed !== null && !dismissed && !spinning && !draw;
    const flashing = !spinning && !draw && !showClaimed && !allTaken && !choosing;
    const drawError = errors.rollet ?? errors.picker_id;

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

    // After submitting, the names race, then slow down until the server's answer can be shown.
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

    function openChooser() {
        setDismissed(true);
        setPickerId(null);
        setChoosing(true);
    }

    function startDraw() {
        if (pickerId === null) {
            return;
        }
        serverDone.current = false;
        spinStart.current = performance.now();
        setChoosing(false);
        setSpinning(true);
        router.post('/rollet/draw', { picker_id: pickerId }, {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => { serverDone.current = true; },
        });
    }

    function confirm() {
        setDismissed(false);
        router.post('/rollet/confirm', {}, {
            preserveScroll: true,
            preserveState: true,
            onStart: () => setConfirming(true),
            onFinish: () => setConfirming(false),
        });
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
                    <p className="text-lg text-white/80">{draw.picker ? `${draw.picker}, you picked` : 'You picked'}</p>
                    <p className="rollet-win my-5 break-words text-6xl font-black text-amber-300 sm:text-7xl">{draw.name}</p>
                    <button type="button" onClick={confirm} disabled={confirming} className={`${buttonClass} mx-auto mt-6 flex w-full max-w-sm items-center justify-center gap-2 py-3.5 text-xl`}>
                        {confirming && <Loader2 className="h-5 w-5 animate-spin" />}
                        Confirm
                    </button>
                </section>
            ) : choosing ? (
                <section className="w-full max-w-2xl">
                    <h1 className="text-3xl font-black sm:text-4xl">Who are you?</h1>
                    <p className="mt-2 text-white/80">Select your name so you won't pick yourself.</p>
                    {people.length > 0 ? (
                        <div role="radiogroup" aria-label="Who are you?" className="mt-6 grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4">
                            {people.map((person) => (
                                <button
                                    key={person.id}
                                    type="button"
                                    role="radio"
                                    aria-checked={pickerId === person.id}
                                    onClick={() => setPickerId(person.id)}
                                    className={`min-h-12 rounded-xl border-2 px-3 py-2 font-bold transition ${pickerId === person.id ? 'border-amber-300 bg-amber-300 text-zinc-950' : 'border-white/25 bg-white/10 text-white hover:bg-white/20'}`}
                                >
                                    {person.label}
                                </button>
                            ))}
                        </div>
                    ) : (
                        <p className="mt-6 rounded-lg bg-white/10 p-3 text-white/90">Everyone left is already picking. Please try again in a few minutes.</p>
                    )}
                    <div className="mt-8 flex flex-wrap items-center justify-center gap-4">
                        <button type="button" onClick={() => setChoosing(false)} className="rounded-xl px-4 py-3 font-semibold text-white/80 hover:text-white">Back</button>
                        <button type="button" onClick={startDraw} disabled={pickerId === null} className={`${buttonClass} px-10 py-3.5 text-xl`}>Submit</button>
                    </div>
                </section>
            ) : (
                <section className="w-full max-w-2xl">
                    <p key={tick} aria-live="off" className={`rollet-flash flex min-h-[8rem] items-center justify-center break-words text-6xl font-black sm:text-8xl ${spinning ? 'text-amber-300' : 'text-white/90'}`}>
                        {names[index]}
                    </p>
                    {drawError && !spinning && <p role="alert" className="mx-auto mt-2 max-w-md rounded-lg bg-red-100 p-3 text-sm font-medium text-red-900">{drawError}</p>}
                    {allTaken ? (
                        <p className="mt-8 text-xl font-semibold text-amber-300">All names have been picked. Merry Christmas!</p>
                    ) : (
                        <button type="button" onClick={openChooser} disabled={spinning} className={`${buttonClass} mt-10 px-10 py-6 text-3xl sm:text-4xl`}>
                            PINDUTIN MO NA!
                        </button>
                    )}
                </section>
            )}
        </main>
    );
}
