// Event participant search (race day: everyone looks up a runner by name/bib).
// Public web search + API search. SEARCH_TERMS="garcia,ana,1024" to match your data.
import http from 'k6/http';
import { sleep } from 'k6';
import { API, BASE_URL, jsonHeaders, ok, optionsFor, pick } from './lib/common.js';

export const options = optionsFor('participant_search');

const TERMS = (__ENV.SEARCH_TERMS || 'garcia,lopez,maria,juan,ana,10,21,marat').split(',');
const EVENT_SLUG = __ENV.EVENT_SLUG || '';

export default function () {
    const q = encodeURIComponent(pick(TERMS));

    ok(http.get(`${BASE_URL}/buscar?q=${q}`, { tags: { name: 'web:/buscar' } }), 'web search');
    sleep(1);
    ok(http.get(`${API}/search?q=${q}`, { headers: jsonHeaders(), tags: { name: 'api:/search' } }), 'api search');

    if (EVENT_SLUG) {
        ok(http.get(`${BASE_URL}/events/${EVENT_SLUG}?q=${q}`, { tags: { name: 'web:/events/{slug}' } }), 'event page');
    }

    sleep(2 + Math.random() * 3);
}
