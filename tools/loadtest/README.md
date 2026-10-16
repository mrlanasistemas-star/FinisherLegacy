# Load tests (k6)

Scenarios from spec §113–114. **They measure; they never prove capacity.**
Only numbers recorded from a real run on a named environment may be quoted.

| Script | What it simulates | Auth | Writes data |
|---|---|---|---|
| `browse-store.js` | `/tienda`, product pages, API catalog | — | no |
| `feed.js` | `/comunidad` + API feed, 3 pages with cursor | token | no |
| `participant-search.js` | `/buscar?q=` + `/api/v1/search` (+ event page) | — | no |
| `cart.js` | add → read → update → remove | token | cart rows |
| `checkout.js` | add → `POST /checkout` → order detail. **No payment call.** | token | pending orders, stock reservations |
| `tracking-ingest.js` | one runner = one session, batch of 10 points / 10 s | token | tracking points |
| `live-viewers.js` | `/live/{token}` + snapshot polling, or Reverb WS on `live.{token}` | — | no |
| `notifications.js` | app inbox poll + mark read | token | read_at |

## Never against production

`lib/common.js` refuses `*.finisherlegacy.com|mx` hosts. Use a **staging copy
or a local clone with a disposable database** — checkout/cart/tracking write
real rows and reserve inventory.

## Prepare the target

```bash
# 1. A copy of the app with realistic data (anonymised prod dump or seeders),
#    APP_ENV=staging|local, APP_DEBUG=false, cached config/routes/views:
php artisan config:cache && php artisan route:cache && php artisan view:cache

# 2. Load-test users (+ athlete + one participation each), never in production:
LOADTEST_USERS=100 php artisan db:seed --class=LoadTestSeeder

# 3. Products with stock (store scenarios need at least one purchasable variant).

# 4. Raise/neutralise per-user API throttles on the TARGET ONLY if you are
#    measuring the server rather than the limiter (document it in the results).
```

## Run

```bash
k6 version                                   # https://grafana.com/docs/k6/latest/set-up/install-k6/
BASE_URL=https://staging.example.test PROFILE=smoke k6 run tools/loadtest/browse-store.js
BASE_URL=... PROFILE=ramp LOADTEST_USERS=100 k6 run --summary-export=results/feed-ramp.json tools/loadtest/feed.js
BASE_URL=... PROFILE=250 k6 run tools/loadtest/checkout.js
BASE_URL=... LIVE_TOKENS=tok1,tok2 REVERB_WS=wss://ws.staging.example.test REVERB_APP_KEY=... k6 run tools/loadtest/live-viewers.js
```

Profiles: `smoke` (20 VUs, 30 s), `ramp` (100 → 250 → 500 VUs, 3 min plateau
each), or a number (`100`, `250`, `500`: 5 min plateau). Other env:
`LOADTEST_USERS`, `LOADTEST_PASSWORD`, `SEARCH_TERMS`, `EVENT_SLUG`,
`TRACKING_BATCH`. Each request sends an `X-Request-ID: k6-…` header, so slow
requests can be found in the server logs (`request_id` in the log context).

## What to record (one row per script × profile)

| Metric | Where |
|---|---|
| p50 / p95 / p99 `http_req_duration`, per `name` tag | k6 summary / `--summary-export` |
| error rate (`http_req_failed`), 429 count | k6 summary |
| DB queries per request + slow queries | MySQL `slow_query_log` (long_query_time=0.2), `performance_schema` |
| CPU / memory: PHP-FPM, MySQL, Redis, Reverb | `htop`/`vmstat 5`, `docker stats`, host metrics |
| PHP-FPM saturation | `pm.status_path` (active / max children reached) |
| Queue lag (Horizon) | `/horizon` → wait time per queue |
| Reverb connections / messages | `php artisan reverb:start --debug` or host metrics |

Write results with date, git SHA, environment size (vCPU/RAM), PHP-FPM
`pm.max_children`, and data volume. Quote only those numbers.

## Results log

| Date | Env | Script | Profile | p95 | p99 | Errors | Notes |
|---|---|---|---|---|---|---|---|
| 2026-10-16 | local (Windows dev box) | — | — | — | — | — | Not run: k6 is not installed on the dev machine. No capacity figures exist yet. |
