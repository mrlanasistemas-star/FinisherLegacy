# Finisher Legacy — Deployment checklist

Target: one Linux host (or a few) with Nginx + PHP-FPM 8.4, MySQL 8, Redis,
Laravel Reverb, Horizon workers, and the Synology NAS reachable **only** over
Tailscale. No Kubernetes. Never commit secrets — every value below goes in the
server `.env` / secret store.

## A. CODE COMPLETE (in the repo, nothing to build)

- Realtime: Reverb config, `routes/channels.php`, web + API broadcasting auth,
  `notification.created` / `order.updated` / `payment.updated`, lazy Echo client
  with polling fallback.
- Notifications: bell dropdown, notification center, toasts, Expo push gateway
  (`PUSH_PROVIDER=expo`), invalid-token deactivation.
- Queues: logical queues `realtime` / `default` / `media`, Horizon supervisors,
  `/horizon` gated to super_admin.
- Observability: `X-Request-ID` + log Context, JSON log channel, `payments`
  log channel, failed-job logging.
- Rate limiters for public / ingest endpoints (docs/ARCHITECTURE.md §7).
- k6 scenarios + `LoadTestSeeder` (tools/loadtest).

## B. INFRA CONFIGURATION REQUIRED

### 1. App basics
```
APP_ENV=production  APP_DEBUG=false  APP_URL=https://<domain>  APP_KEY=<generated>
APP_LOCALE=es  LOG_LEVEL=info  LOG_STACK=daily,json
SESSION_SECURE_COOKIE=true  SESSION_DOMAIN=<domain>
SANCTUM_STATEFUL_DOMAINS=<domain>
```
Deploy: `composer install --no-dev -o` → `npm ci && npm run build` →
`php artisan migrate --force` → `php artisan optimize` (config/route/view/event
cache) → `php artisan horizon:terminate` (supervisor restarts it) →
`php artisan reverb:restart`.

### 2. MySQL 8
`DB_CONNECTION=mysql DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD`.
utf8mb4, `innodb_buffer_pool_size` ≈ 50–70 % of DB RAM, slow query log on
(`long_query_time=0.5`). Daily dumps off-host.

### 3. Redis
```
REDIS_CLIENT=phpredis REDIS_HOST REDIS_PASSWORD REDIS_PORT
QUEUE_CONNECTION=redis  CACHE_STORE=redis  (rate limiter uses the cache store)
QUEUE_REALTIME=realtime  QUEUE_MEDIA=media
```
Not exposed publicly; `maxmemory-policy noeviction` for the queue DB (or a
separate instance/DB for cache with `allkeys-lru`).

### 4. Queue workers / Horizon
Supervisor program: `php artisan horizon` (autorestart, `stopwaitsecs=960` so
media jobs finish). Schedule: `* * * * * php artisan schedule:run` and add
`horizon:snapshot` every 5 min for metrics. Without Redis, fall back to
`php artisan queue:work --queue=realtime,default,media --tries=3 --timeout=900`.
Tune `HORIZON_MAX_PROCESSES`, `HORIZON_MEDIA_PROCESSES`. Note: Horizon needs
`ext-pcntl`/`ext-posix` (Linux only; composer.json declares them as platform
overrides so Windows dev installs still resolve).

### 5. Reverb (WebSockets)
```
BROADCAST_CONNECTION=reverb
REVERB_APP_ID / REVERB_APP_KEY / REVERB_APP_SECRET   (random, per environment)
REVERB_HOST=ws.<domain>  REVERB_PORT=443  REVERB_SCHEME=https
REVERB_SERVER_HOST=127.0.0.1  REVERB_SERVER_PORT=8080
VITE_REVERB_APP_KEY / VITE_REVERB_HOST / VITE_REVERB_PORT / VITE_REVERB_SCHEME  (build-time!)
```
Run `php artisan reverb:start` under Supervisor; Nginx proxies
`wss://ws.<domain>` → `127.0.0.1:8080` (Upgrade/Connection headers,
`proxy_read_timeout 300s`). Raise `ulimit -n` (≥ 10 000) for many viewers.
For more than one app server set `REVERB_SCALING_ENABLED=true` (Redis pub/sub).
If Reverb is down the site keeps working (polling fallback).

### 6. Stripe / OpenPay
```
FINISHER_PAYMENT_GATEWAY / FINISHER_API_PAYMENT_GATEWAY
STRIPE_KEY (pk_live)  STRIPE_SECRET (sk_live)  STRIPE_WEBHOOK_SECRET (whsec_)
OPENPAY_MERCHANT_ID OPENPAY_PRIVATE_KEY OPENPAY_PUBLIC_KEY OPENPAY_PRODUCTION_MODE=true
```
Stripe dashboard webhook → `https://<domain>/api/webhooks/stripe` with the
PaymentIntent events commerce handles (docs/architecture/commerce.md).
Openpay webhook → `/api/webhooks/openpay`. Watch `storage/logs/payments-*.log`.

### 7. Media — Synology over Tailscale
Tailscale on the app host; NAS share mounted (NFS/SMB) at a fixed path, e.g.
`/mnt/synology/finisher` (systemd automount, `_netdev`, uid/gid of PHP-FPM).
The NAS is never exposed publicly and its credentials stay in the mount
config, not in the repo. Disk/env names and fallbacks: `docs/SYNOLOGY.md`.
Media jobs run on the `media` queue.

### 8. Push (Expo)
```
PUSH_PROVIDER=expo
EXPO_ACCESS_TOKEN=   # only if "Enhanced push security" is enabled in EAS
```
APNs key / FCM credentials are uploaded to **EAS** (expo.dev → project →
credentials), not to this server. Outbound HTTPS to `exp.host` must be allowed.
(Receipt polling — `getReceipts` after ~15 min — is not implemented yet;
ticket-level `DeviceNotRegistered` already deactivates tokens.)

### 9. Domain, TLS and app links
- TLS for `<domain>` and `ws.<domain>`; HSTS.
- Universal / App Links for NFC (`https://<domain>/n/{token}`) need, served
  with `Content-Type: application/json` and no redirect:
  - `https://<domain>/.well-known/apple-app-site-association` (team id +
    bundle id, paths `/n/*`, `/live/*`)
  - `https://<domain>/.well-known/assetlinks.json` (Android package + release
    signing SHA-256)
  These files are **not** in the repo yet — they depend on the final team id /
  package / signing key.
- Mail: `MAIL_MAILER` + credentials, `MAIL_FROM_ADDRESS`.

### 10. Social sign-in (optional)
`GOOGLE_OAUTH_CLIENT_IDS`, `APPLE_SIGNIN_CLIENT_IDS` (empty = disabled).

### 11. Before go-live
- [ ] `php artisan about` shows production, caches on, debug off
- [ ] `/up` health check in the load balancer / uptime monitor
- [ ] `/horizon` reachable by super_admin only
- [ ] Browser devtools: WS connected to `wss://ws.<domain>`; a test
      notification shows the toast + badge
- [ ] Stripe test-mode E2E (success, decline, 3DS, duplicate webhook)
- [ ] k6 smoke + ramp on staging, numbers recorded in `tools/loadtest/README.md`
- [ ] Backups (DB + NAS) restored once
