/**
 * Small helpers for the store checkout's JSON calls (payment intent, order
 * status). The page itself is Inertia; these two endpoints answer JSON so
 * the payment widget can work without a full page visit.
 */

function xsrfToken(): string {
    if (typeof document === 'undefined') {
        return '';
    }

    const match = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='));

    return match ? decodeURIComponent(match.split('=')[1]) : '';
}

export async function postJson<T>(
    url: string,
    body: Record<string, unknown> = {},
): Promise<T> {
    const response = await fetch(url, {
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

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    return (await response.json()) as T;
}

export async function getJson<T>(url: string): Promise<T> {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    return (await response.json()) as T;
}

export type OrderPaymentState =
    'pending' | 'processing' | 'paid' | 'failed' | 'cancelled' | 'refunded';

export type CheckoutOrderState = {
    uuid: string;
    order_number: string;
    status: string;
    payment_status: string;
    fulfillment_status: string;
    payment_state: OrderPaymentState;
    payable: boolean;
    expires_at: string | null;
    total_minor: number;
    currency: string;
};
