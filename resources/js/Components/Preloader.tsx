import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

export function Preloader() {
    const [visible, setVisible] = useState(false);
    const [message, setMessage] = useState('Loading…');
    const showTimer = useRef<number | null>(null);
    const active = useRef(0);

    useEffect(() => {
        function clearShowTimer() {
            if (showTimer.current !== null) {
                window.clearTimeout(showTimer.current);
                showTimer.current = null;
            }
        }

        function start(event?: { detail?: { visit?: { method?: string } } }) {
            active.current += 1;
            const method = event?.detail?.visit?.method?.toUpperCase() ?? 'GET';
            setMessage(method === 'GET' ? 'Loading…' : 'Saving…');
            clearShowTimer();
            showTimer.current = window.setTimeout(() => {
                if (active.current > 0) {
                    setVisible(true);
                }
            }, 120);
        }

        function finish() {
            active.current = Math.max(0, active.current - 1);
            if (active.current === 0) {
                clearShowTimer();
                setVisible(false);
            }
        }

        const offStart = router.on('start', start);
        const offFinish = router.on('finish', finish);
        const offCancel = router.on('cancel', finish);
        const offError = router.on('error', finish);

        return () => {
            clearShowTimer();
            offStart();
            offFinish();
            offCancel();
            offError();
        };
    }, []);

    if (!visible) {
        return null;
    }

    return (
        <div className="preloader" role="status" aria-live="polite" aria-busy="true">
            <div className="preloader__panel">
                <span className="preloader__spinner" aria-hidden="true" />
                <p className="preloader__label">{message}</p>
            </div>
        </div>
    );
}
