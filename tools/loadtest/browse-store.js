// Anonymous store browsing: web pages (Inertia HTML) + the mobile API catalog.
import { sleep } from 'k6';
import http from 'k6/http';
import { API, BASE_URL, jsonHeaders, ok, optionsFor, pick } from './lib/common.js';

export const options = optionsFor('browse_store');

export function setup() {
    const res = http.get(`${API}/store/products?per_page=24`, { headers: jsonHeaders() });
    const slugs = (res.json('data') || []).map((p) => p.slug).filter(Boolean);

    return { slugs };
}

export default function (data) {
    ok(http.get(`${BASE_URL}/tienda`, { tags: { name: 'web:/tienda' } }), 'store home');
    sleep(1 + Math.random() * 2);

    ok(http.get(`${API}/store/products?page=1`, { headers: jsonHeaders(), tags: { name: 'api:/store/products' } }), 'api catalog');

    if (data.slugs.length) {
        const slug = pick(data.slugs);
        ok(http.get(`${BASE_URL}/tienda/${slug}`, { tags: { name: 'web:/tienda/{slug}' } }), 'product page');
        ok(
            http.get(`${API}/store/products/${slug}`, { headers: jsonHeaders(), tags: { name: 'api:/store/products/{slug}' } }),
            'api product',
        );
    }

    sleep(2 + Math.random() * 3);
}
