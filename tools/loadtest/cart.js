// Cart: add → update → read → remove (no order is created).
import { sleep } from 'k6';
import http from 'k6/http';
import { purchasableVariants } from './lib/catalog.js';
import { API, jsonHeaders, loginUsers, ok, optionsFor, pick, tokenForVu } from './lib/common.js';

export const options = optionsFor('cart');

export function setup() {
    return { tokens: loginUsers(), variants: purchasableVariants() };
}

export default function (data) {
    const token = tokenForVu(data.tokens);
    const headers = jsonHeaders(token);

    const add = http.post(
        `${API}/cart/items`,
        JSON.stringify({ product_variant_uuid: pick(data.variants), quantity: 1 }),
        { headers, tags: { name: 'api:POST /cart/items' } },
    );
    ok(add, 'add item');
    sleep(1);

    const cart = http.get(`${API}/cart`, { headers, tags: { name: 'api:/cart' } });
    ok(cart, 'show cart');

    const items = cart.status === 200 ? cart.json('data.items') || [] : [];
    const item = items[0];

    if (item) {
        const id = item.uuid || item.id;
        ok(
            http.patch(`${API}/cart/items/${id}`, JSON.stringify({ quantity: 2 }), {
                headers,
                tags: { name: 'api:PATCH /cart/items/{item}' },
            }),
            'update item',
        );
        sleep(1);
        ok(http.del(`${API}/cart/items/${id}`, null, { headers, tags: { name: 'api:DELETE /cart/items/{item}' } }), 'remove item');
    }

    sleep(2 + Math.random() * 2);
}
