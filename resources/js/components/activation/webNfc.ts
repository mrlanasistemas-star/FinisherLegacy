/**
 * Minimal, typed wrapper over Web NFC (Chrome for Android, secure context
 * only). TypeScript's DOM lib does not ship these types yet, so only what
 * the activation wizard uses is declared here.
 *
 * Rules (docs/renovacion/manual-activacion-nfc.md):
 * - scan() / write() are only ever called from a user gesture (button).
 * - We never erase existing data without explicit consent: `overwrite` is
 *   only true after the person accepted replacing it, or when the chip was
 *   read as empty / not formatted.
 * - We never lock a chip read-only (no makeReadOnly call anywhere).
 */

type NdefRecordInit = { recordType: 'url'; data: string };

type NdefRecordLike = {
    recordType: string;
    mediaType?: string;
    encoding?: string;
    data?: DataView;
};

type NdefReadingEventLike = Event & {
    serialNumber: string;
    message: { records: readonly NdefRecordLike[] };
};

type NdefReaderLike = EventTarget & {
    scan(options?: { signal?: AbortSignal }): Promise<void>;
    write(
        message: { records: NdefRecordInit[] },
        options?: { overwrite?: boolean; signal?: AbortSignal },
    ): Promise<void>;
};

type NdefReaderCtor = new () => NdefReaderLike;

export type NfcSupport = 'supported' | 'insecure' | 'ios' | 'unsupported';

export type NfcRead = {
    serialNumber: string;
    /** URLs found on the chip (url / absolute-url records). */
    urls: string[];
    /** True when the chip carries no NDEF records at all. */
    empty: boolean;
};

/** Friendly failure kinds — mapped to on-screen copy by the page. */
export type NfcFailure =
    'permission' | 'locked' | 'incompatible' | 'moved' | 'timeout' | 'unknown';

export class NfcError extends Error {
    constructor(
        public readonly kind: NfcFailure,
        public readonly detail: string,
    ) {
        super(detail);
    }
}

function ctor(): NdefReaderCtor | null {
    if (typeof window === 'undefined' || !('NDEFReader' in window)) {
        return null;
    }

    return (window as unknown as { NDEFReader: NdefReaderCtor }).NDEFReader;
}

export function detectNfcSupport(): NfcSupport {
    if (typeof window === 'undefined') {
        return 'unsupported';
    }

    const ua = navigator.userAgent;
    const isIos =
        /iPhone|iPad|iPod/.test(ua) ||
        (ua.includes('Macintosh') && navigator.maxTouchPoints > 1);

    if (ctor() !== null) {
        return window.isSecureContext ? 'supported' : 'insecure';
    }

    return isIos ? 'ios' : 'unsupported';
}

function decodeUrl(record: NdefRecordLike): string | null {
    if (
        (record.recordType !== 'url' && record.recordType !== 'absolute-url') ||
        !record.data
    ) {
        return null;
    }

    return new TextDecoder(record.encoding ?? 'utf-8').decode(record.data);
}

function toNfcError(error: unknown): NfcError {
    const name = error instanceof DOMException ? error.name : '';
    const detail = error instanceof Error ? `${name}: ${error.message}` : name;

    switch (name) {
        case 'NotAllowedError':
            // Permission refused, or the chip is write-protected.
            return new NfcError('permission', detail);
        case 'NotSupportedError':
            return new NfcError('incompatible', detail);
        case 'NotReadableError':
        case 'NetworkError':
            return new NfcError('moved', detail);
        case 'AbortError':
        case 'TimeoutError':
            return new NfcError('timeout', detail);
        default:
            return new NfcError('unknown', detail || 'unknown');
    }
}

/**
 * One NFC session: scanning stays on while the wizard needs it so the
 * person only has to keep the piece near the phone.
 */
export class NfcSession {
    private reader: NdefReaderLike;

    private controller = new AbortController();

    constructor() {
        const Reader = ctor();

        if (Reader === null) {
            throw new NfcError('incompatible', 'NDEFReader unavailable');
        }

        this.reader = new Reader();
    }

    /** Must be called from a click. Resolves once scanning is on. */
    async start(
        onRead: (read: NfcRead) => void,
        onUnreadable: () => void,
    ): Promise<void> {
        this.reader.addEventListener('reading', (event) => {
            const e = event as NdefReadingEventLike;
            const records = e.message?.records ?? [];

            onRead({
                serialNumber: e.serialNumber ?? '',
                urls: records
                    .map(decodeUrl)
                    .filter((url): url is string => url !== null),
                empty: records.length === 0,
            });
        });
        this.reader.addEventListener('readingerror', () => onUnreadable());

        try {
            await this.reader.scan({ signal: this.controller.signal });
        } catch (error) {
            throw toNfcError(error);
        }
    }

    /** Writes exactly one URL record. Times out so the UI never hangs. */
    async writeUrl(
        url: string,
        overwrite: boolean,
        timeoutMs = 20000,
    ): Promise<void> {
        const timeout = new AbortController();
        const timer = window.setTimeout(() => timeout.abort(), timeoutMs);

        try {
            await this.reader.write(
                { records: [{ recordType: 'url', data: url }] },
                { overwrite, signal: timeout.signal },
            );
        } catch (error) {
            throw toNfcError(error);
        } finally {
            window.clearTimeout(timer);
        }
    }

    stop(): void {
        this.controller.abort();
    }
}

/** A Finisher Legacy chip URL (any code) on the official host. */
export function isFinisherLegacyUrl(
    url: string,
    officialHost: string,
): boolean {
    try {
        const parsed = new URL(url);

        return (
            parsed.hostname === officialHost &&
            /^\/t\/[A-Za-z0-9-]+$/.test(parsed.pathname)
        );
    } catch {
        return false;
    }
}
