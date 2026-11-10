/**
 * JSON calls of the Web NFC activation (`/activar/nfc/*`). Same shapes as
 * the app API `/api/v1/tags/activation/*` (docs/api/v1.md §Activación NFC).
 * Session auth + XSRF cookie, like lib/commerce.ts.
 */
import { t } from '@/i18n';

export type ActivationPlatform = 'android' | 'ios' | 'web';

export type ActivationStart = {
    intent_id: string;
    tag_code: string;
    write_url: string;
    expires_at: string;
    status: 'ready_to_write' | 'already_activated';
    product: {
        name: string | null;
        variant: string | null;
        collection: string | null;
        image_url: string | null;
    };
    story: { public_id: string; url: string } | null;
};

export type ActivationConfirm = {
    status: 'activated';
    tag_code: string;
    story: { public_id: string; url: string; edit_url: string };
};

/** Stable codes the server answers with (plus client-side ones). */
export type ActivationErrorCode =
    | 'ACTIVATION_CODE_INVALID'
    | 'TAG_ALREADY_ACTIVATED'
    | 'TAG_NOT_ACTIVATABLE'
    | 'TAG_UID_MISMATCH'
    | 'WRITTEN_URL_MISMATCH'
    | 'INTENT_EXPIRED'
    | 'TOO_MANY_REQUESTS'
    | 'VALIDATION'
    | 'SESSION_EXPIRED'
    | 'NETWORK'
    | 'UNKNOWN';

export class ActivationError extends Error {
    constructor(
        public readonly code: ActivationErrorCode,
        message: string,
    ) {
        super(message);
    }
}

type ErrorBody = {
    message?: string;
    code?: string;
    errors?: Record<string, string[]>;
};

const KNOWN: ActivationErrorCode[] = [
    'ACTIVATION_CODE_INVALID',
    'TAG_ALREADY_ACTIVATED',
    'TAG_NOT_ACTIVATABLE',
    'TAG_UID_MISMATCH',
    'WRITTEN_URL_MISMATCH',
    'INTENT_EXPIRED',
    'TOO_MANY_REQUESTS',
];

function xsrfToken(): string {
    if (typeof document === 'undefined') {
        return '';
    }

    const match = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='));

    return match ? decodeURIComponent(match.split('=')[1]) : '';
}

async function post<T>(
    url: string,
    body: Record<string, unknown>,
): Promise<T | null> {
    let response: Response;

    try {
        response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: JSON.stringify(body),
        });
    } catch {
        throw new ActivationError(
            'NETWORK',
            t(
                'Parece que no hay conexión. Revisa tu internet e inténtalo de nuevo.',
            ),
        );
    }

    if (response.status === 204) {
        return null;
    }

    const json = (await response.json().catch(() => ({}))) as ErrorBody & {
        data?: T;
    };

    if (response.ok && json.data !== undefined) {
        return json.data;
    }

    if (response.status === 401 || response.status === 419) {
        throw new ActivationError(
            'SESSION_EXPIRED',
            t('Tu sesión expiró. Recarga la página e inicia sesión de nuevo.'),
        );
    }

    if (json.errors) {
        const first = Object.values(json.errors)[0]?.[0];

        throw new ActivationError(
            'VALIDATION',
            first ?? t('Revisa los datos e inténtalo de nuevo.'),
        );
    }

    const code = KNOWN.find((known) => known === json.code) ?? 'UNKNOWN';

    throw new ActivationError(
        response.status === 429 ? 'TOO_MANY_REQUESTS' : code,
        json.message ??
            t('Algo salió mal. Inténtalo de nuevo en un momento o escríbenos.'),
    );
}

export async function startActivation(
    url: string,
    body: {
        activation_code?: string;
        tag_code?: string;
        nfc_uid?: string;
        platform: ActivationPlatform;
    },
): Promise<ActivationStart> {
    return (await post<ActivationStart>(url, body)) as ActivationStart;
}

export async function confirmActivation(
    url: string,
    body: { written_url: string; nfc_uid?: string },
): Promise<ActivationConfirm> {
    return (await post<ActivationConfirm>(url, body)) as ActivationConfirm;
}

/** Best effort: a failure report must never break the screen. */
export async function reportActivationFailure(
    url: string,
    reason: string,
): Promise<void> {
    try {
        await post<null>(url, { reason: reason.slice(0, 120) });
    } catch {
        // Support visibility only.
    }
}
