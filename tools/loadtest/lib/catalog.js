import http from 'k6/http';
import { fail } from 'k6';
import { API, jsonHeaders } from './common.js';

/** UUIDs of active variants of purchasable products (setup-time only). */
export function purchasableVariants(limit = 20) {
    const list = http.get(`${API}/store/products?per_page=50`, { headers: jsonHeaders() });
    const variants = [];

    for (const product of list.json('data') || []) {
        if (variants.length >= limit) {
            break;
        }

        const detail = http.get(`${API}/store/products/${product.slug}`, { headers: jsonHeaders() });
        for (const variant of detail.json('data.variants') || []) {
            if (variant.uuid && variant.in_stock !== false) {
                variants.push(variant.uuid);
            }
        }
    }

    if (!variants.length) {
        fail('No purchasable variants found — seed products with stock first (see README).');
    }

    return variants;
}
