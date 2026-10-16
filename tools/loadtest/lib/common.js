// Shared config/helpers for the Finisher Legacy k6 scenarios.
// NEVER point this at production (see ../README.md).
import http from 'k6/http';
import { check, fail } from 'k6';
import exec from 'k6/execution';

export const BASE_URL = (__ENV.BASE_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');
export const API = `${BASE_URL}/api/v1`;
export const PASSWORD = __ENV.LOADTEST_PASSWORD || 'loadtest-password';
export const USER_COUNT = parseInt(__ENV.LOADTEST_USERS || '50', 10);
export const EMAIL_PATTERN = __ENV.LOADTEST_EMAIL_PATTERN || 'loadtest+{n}@finisherlegacy.test';

if (/finisherlegacy\.(com|mx)/i.test(BASE_URL) && __ENV.I_KNOW_THIS_IS_NOT_PROD !== 'yes') {
    fail(`Refusing to run against ${BASE_URL}. Use a staging/local copy (see README).`);
}

/**
 * PROFILE=smoke  → 20 VUs for 30 s (sanity check, safe on a laptop)
 * PROFILE=ramp   → 100 → 250 → 500 VUs, 3 min plateau each (spec §113–114)
 * PROFILE=100|250|500 → a single 5 min plateau at that level
 */
export function stagesFor(profile = __ENV.PROFILE || 'smoke') {
    if (profile === 'smoke') {
        return [
            { duration: '5s', target: 20 },
            { duration: '25s', target: 20 },
            { duration: '5s', target: 0 },
        ];
    }

    if (profile === 'ramp') {
        return [
            { duration: '1m', target: 100 },
            { duration: '3m', target: 100 },
            { duration: '1m', target: 250 },
            { duration: '3m', target: 250 },
            { duration: '1m', target: 500 },
            { duration: '3m', target: 500 },
            { duration: '1m', target: 0 },
        ];
    }

    const vus = parseInt(profile, 10);
    if (!Number.isFinite(vus)) {
        fail(`Unknown PROFILE "${profile}"`);
    }

    return [
        { duration: '1m', target: vus },
        { duration: '5m', target: vus },
        { duration: '30s', target: 0 },
    ];
}

/** Thresholds are reporting aids, not capacity claims. */
export function optionsFor(name, extra = {}) {
    return {
        scenarios: {
            [name]: {
                executor: 'ramping-vus',
                startVUs: 0,
                stages: stagesFor(),
                gracefulRampDown: '20s',
            },
        },
        thresholds: {
            http_req_failed: ['rate<0.01'],
            http_req_duration: ['p(95)<800', 'p(99)<2000'],
        },
        summaryTrendStats: ['avg', 'min', 'med', 'p(90)', 'p(95)', 'p(99)', 'max'],
        ...extra,
    };
}

export function jsonHeaders(token) {
    const headers = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Request-ID': `k6-${exec.vu.idInTest}-${exec.vu.iterationInScenario}-${Date.now()}`,
    };

    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }

    return headers;
}

/** Log in LOADTEST_USERS seeded users once (setup) → array of tokens. */
export function loginUsers(count = USER_COUNT) {
    const tokens = [];

    for (let n = 1; n <= count; n++) {
        const email = EMAIL_PATTERN.replace('{n}', String(n));
        const res = http.post(`${API}/auth/login`, JSON.stringify({ email, password: PASSWORD }), {
            headers: jsonHeaders(),
            tags: { name: 'setup:login' },
        });

        if (res.status !== 200) {
            fail(`Login failed for ${email} (${res.status}). Run: php artisan db:seed --class=LoadTestSeeder`);
        }

        tokens.push(res.json('data.token') || res.json('token'));
    }

    return tokens;
}

/** Each VU sticks to one user so carts/orders don't collide. */
export function tokenForVu(tokens) {
    return tokens[(exec.vu.idInTest - 1) % tokens.length];
}

export function ok(res, label) {
    return check(res, { [`${label} 2xx`]: (r) => r.status >= 200 && r.status < 300 });
}

export function pick(list) {
    return list[Math.floor(Math.random() * list.length)];
}
