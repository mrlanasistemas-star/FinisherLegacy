// Notifications: the app polling its inbox + marking read (what the bell
// fallback poll costs when Reverb is down).
import http from 'k6/http';
import { sleep } from 'k6';
import { API, jsonHeaders, loginUsers, ok, optionsFor, tokenForVu } from './lib/common.js';

export const options = optionsFor('notifications');

export function setup() {
    return { tokens: loginUsers() };
}

export default function (data) {
    const headers = jsonHeaders(tokenForVu(data.tokens));

    const list = http.get(`${API}/me/notifications`, { headers, tags: { name: 'api:/me/notifications' } });
    ok(list, 'inbox');

    const unread = (list.status === 200 ? list.json('data') || [] : []).find((n) => !n.read_at);
    if (unread) {
        ok(
            http.post(`${API}/me/notifications/${unread.id}/read`, '{}', {
                headers,
                tags: { name: 'api:POST /me/notifications/{id}/read' },
            }),
            'mark read',
        );
    }

    sleep(15 + Math.random() * 10);
}
