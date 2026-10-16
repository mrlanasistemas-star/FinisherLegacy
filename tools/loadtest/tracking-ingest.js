// Tracking ingestion (contract in AGENT_BRIEF / docs/ARCHITECTURE.md):
// each VU = one runner whose phone posts a batch of GPS points every ~10 s.
// Needs seeded users with an event participation (LoadTestSeeder does it).
import { check, sleep } from 'k6';
import exec from 'k6/execution';
import http from 'k6/http';
import { API, jsonHeaders, loginUsers, ok, optionsFor, tokenForVu } from './lib/common.js';

export const options = optionsFor('tracking_ingest');

const BATCH = parseInt(__ENV.TRACKING_BATCH || '10', 10);
const sessions = {};

export function setup() {
    return { tokens: loginUsers() };
}

function sessionFor(token) {
    if (sessions[token]) {
        return sessions[token];
    }

    const headers = jsonHeaders(token);
    const events = http.get(`${API}/me/events`, { headers, tags: { name: 'api:/me/events' } });
    const participant = (events.json('data') || [])[0];

    if (!participant) {
        return null;
    }

    const participantId = participant.participant_id || participant.id;
    const res = http.post(`${API}/me/events/${participantId}/tracking/sessions`, '{}', {
        headers,
        tags: { name: 'api:POST tracking/sessions' },
    });

    if (res.status === 404) {
        exec.test.abort('Tracking endpoints not available on this build (404).');
    }

    sessions[token] = res.status === 200 || res.status === 201 ? res.json('data.uuid') : null;

    return sessions[token];
}

export default function (data) {
    const token = tokenForVu(data.tokens);
    const uuid = sessionFor(token);

    if (!check(uuid, { 'has tracking session': (u) => !!u })) {
        sleep(10);

        return;
    }

    const now = Date.now();
    const base = { lat: 19.4326 + Math.random() / 100, lng: -99.1332 + Math.random() / 100 };
    const points = Array.from({ length: BATCH }, (_, i) => ({
        lat: base.lat + i * 0.00005,
        lng: base.lng + i * 0.00005,
        accuracy: 8,
        speed: 3.1,
        battery: 0.8,
        recorded_at: new Date(now - (BATCH - i) * 1000).toISOString(),
    }));

    ok(
        http.post(`${API}/tracking/sessions/${uuid}/points`, JSON.stringify({ points }), {
            headers: jsonHeaders(token),
            tags: { name: 'api:POST tracking/points' },
        }),
        'ingest batch',
    );

    sleep(10);
}
