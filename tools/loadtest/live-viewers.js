// Live viewers: family/friends watching a runner on /live/{token}.
// HTTP snapshot polling (fallback) + optional Reverb WebSocket subscription
// to the public channel live.{token} when REVERB_APP_KEY is given.
//   LIVE_TOKENS=tok1,tok2   (share tokens from a seeded tracking session)
//   REVERB_WS=ws://127.0.0.1:8080  REVERB_APP_KEY=...
import http from 'k6/http';
import ws from 'k6/ws';
import { check, fail, sleep } from 'k6';
import { Counter } from 'k6/metrics';
import { API, BASE_URL, jsonHeaders, ok, optionsFor, pick } from './lib/common.js';

export const options = optionsFor('live_viewers');

const TOKENS = (__ENV.LIVE_TOKENS || '').split(',').filter(Boolean);
const WS_URL = __ENV.REVERB_WS || '';
const KEY = __ENV.REVERB_APP_KEY || '';
const wsMessages = new Counter('ws_tracking_messages');

if (!TOKENS.length) {
    fail('Set LIVE_TOKENS=<share token>[,...]');
}

export default function () {
    const token = pick(TOKENS);

    ok(http.get(`${BASE_URL}/live/${token}`, { tags: { name: 'web:/live/{token}' } }), 'live page');

    if (WS_URL && KEY) {
        const url = `${WS_URL.replace(/\/$/, '')}/app/${KEY}?protocol=7&client=k6&version=8.4.0`;
        const res = ws.connect(url, null, (socket) => {
            socket.on('open', () => {
                socket.send(JSON.stringify({ event: 'pusher:subscribe', data: { channel: `live.${token}` } }));
            });
            socket.on('message', (msg) => {
                if (msg.includes('tracking.point')) {
                    wsMessages.add(1);
                }
            });
            // A viewer stays ~60 s.
            socket.setTimeout(() => socket.close(), 60000);
        });
        check(res, { 'ws 101': (r) => r && r.status === 101 });

        return;
    }

    // Polling fallback viewer: snapshot every 15 s for ~1 min.
    for (let i = 0; i < 4; i++) {
        ok(http.get(`${API}/live/${token}`, { headers: jsonHeaders(), tags: { name: 'api:/live/{token}' } }), 'live snapshot');
        sleep(15);
    }
}
