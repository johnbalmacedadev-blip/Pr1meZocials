import { withBase } from '@/lib/utils';

/**
 * Uploads a large file in small pieces so no single request exceeds the server's
 * post_max_size. Resolves with the upload id to submit instead of the file.
 */
export async function uploadInChunks(file: File, basePath: string, onProgress: (percent: number) => void): Promise<string> {
    const token = () => {
        const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
        return match ? decodeURIComponent(match[1]) : '';
    };

    async function send<T>(path: string, body: FormData | string, json = false): Promise<T> {
        const response = await fetch(withBase(path, basePath), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': token(),
                ...(json ? { 'Content-Type': 'application/json' } : {}),
            },
            body,
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            const errors = (data as { errors?: Record<string, string[]> }).errors;
            const message = errors ? Object.values(errors)[0]?.[0] : (data as { message?: string }).message;
            throw new Error(message || (response.status === 413 ? 'The server rejected an upload piece as too large.' : `Upload failed (${response.status}).`));
        }
        return data as T;
    }

    const { upload_id: id, chunk_size: chunkSize } = await send<{ upload_id: string; chunk_size: number }>(
        '/posting/uploads',
        JSON.stringify({ name: file.name, size: file.size }),
        true,
    );

    let offset = 0;
    onProgress(0);
    while (offset < file.size) {
        const piece = file.slice(offset, offset + chunkSize);
        let attempt = 0;
        // Retry each piece a few times so a flaky mobile connection doesn't restart the whole video.
        for (;;) {
            try {
                const form = new FormData();
                form.append('offset', String(offset));
                form.append('chunk', piece, 'chunk');
                const { received } = await send<{ received: number }>(`/posting/uploads/${id}/chunk`, form);
                offset = received;
                break;
            } catch (error) {
                attempt += 1;
                if (attempt >= 4) throw error;
                await new Promise((resolve) => setTimeout(resolve, 1000 * attempt));
            }
        }
        onProgress(Math.round((offset / file.size) * 100));
    }

    await send(`/posting/uploads/${id}/finish`, '{}', true);
    return id;
}
