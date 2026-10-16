// Community feed: web page (anonymous) + authenticated API feed with cursor pagination.
import { sleep } from 'k6';
import http from 'k6/http';
import { API, BASE_URL, jsonHeaders, loginUsers, ok, optionsFor, tokenForVu } from './lib/common.js';

export const options = optionsFor('feed');

export function setup() {
    return { tokens: loginUsers() };
}

export default function (data) {
    const token = tokenForVu(data.tokens);

    ok(http.get(`${BASE_URL}/comunidad`, { tags: { name: 'web:/comunidad' } }), 'community page');

    let cursor = null;

    // A person scrolls ~3 pages.
    for (let page = 0; page < 3; page++) {
        const url = cursor ? `${API}/feed?cursor=${encodeURIComponent(cursor)}` : `${API}/feed`;
        const res = http.get(url, { headers: jsonHeaders(token), tags: { name: 'api:/feed' } });
        ok(res, 'feed page');
        cursor = res.status === 200 ? res.json('meta.next_cursor') : null;

        if (!cursor) {
            break;
        }

        sleep(2 + Math.random() * 3);
    }

    sleep(3);
}
