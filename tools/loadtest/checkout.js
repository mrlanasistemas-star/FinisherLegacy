// Checkout up to ORDER CREATION only — never calls /payments/online, never
// touches Stripe/OpenPay. Creates real pending orders and reserves stock:
// run only on a disposable database (README).
import { sleep } from 'k6';
import http from 'k6/http';
import { purchasableVariants } from './lib/catalog.js';
import { API, jsonHeaders, loginUsers, ok, optionsFor, pick, tokenForVu } from './lib/common.js';

export const options = optionsFor('checkout');

export function setup() {
    return { tokens: loginUsers(), variants: purchasableVariants() };
}

export default function (data) {
    const headers = jsonHeaders(tokenForVu(data.tokens));

    ok(
        http.post(`${API}/cart/items`, JSON.stringify({ product_variant_uuid: pick(data.variants), quantity: 1 }), {
            headers,
            tags: { name: 'api:POST /cart/items' },
        }),
        'add item',
    );
    sleep(1 + Math.random());

    const order = http.post(`${API}/checkout`, '{}', { headers, tags: { name: 'api:POST /checkout' } });
    ok(order, 'checkout → order');

    const uuid = order.status === 201 ? order.json('data.uuid') : null;

    if (uuid) {
        sleep(1);
        ok(http.get(`${API}/orders/${uuid}`, { headers, tags: { name: 'api:/orders/{uuid}' } }), 'order detail');
    }

    sleep(3 + Math.random() * 3);
}
