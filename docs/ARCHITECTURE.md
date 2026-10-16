# Finisher Legacy — Architecture

Concise map of how the system is put together. Domain details live next to
it: `docs/architecture/*` (commerce, social, legacy plates, ingestion…),
`docs/api/v1.md` + `docs/api/openapi.yaml`, `docs/NFC.md`, `docs/SYNOLOGY.md`,
`docs/MODULES.md`. Deployment: `docs/DEPLOYMENT.md`.

## 1. Shape

One Laravel 13 monolith, two clients:

```
Browser ──Inertia v3 (Vue 3 + TS)──┐
                                   ├── Laravel (Actions · Policies · Eloquent) ── MySQL
Expo app ──REST /api/v1 (Sanctum)──┘        │            │           │
                                            │ queues     │ broadcast │ media
                                         Redis/Horizon  Reverb (WS)  Synology disk (Tailscale)
```

- **Web never calls its own REST API.** Inertia controllers call the same
  Actions (`app/Actions/<Domain>`) the API controllers call.
- **REST is the authority.** WebSockets only push "something changed" hints;
  clients re-read REST/Inertia props. Cache never holds business truth.
- `/api/v1` is additive-only (mobile compatibility). Errors use the
  `{"error":{code,message,details},"request_id"}` envelope.

## 2. Domains

| Domain | Where | Notes |
|---|---|---|
| Identity / athletes | `User`, `Athlete`, `AthleteProfile` | one user ↔ one athlete; Fortify (web), Sanctum (app) |
| Events | `Event` → `EventEdition` → `EventRace`, `EventParticipant`, results | imports + external data sources |
| Legacy (plates, gear, NFC) | `LegacyPlate*`, `AthleteOwnedProduct`, NFC tags | public tokens ≥128-bit random (`docs/NFC.md`) |
| Commerce | `Cart`, `Order`, `Payment`, inventory, coupons | Stripe/OpenPay behind `PaymentGateway`; webhook authoritative for "paid" |
| Community | `LegacyMoment`, comments, reactions, follows, reports | cursor feeds, block/report/moderation |
| Tracking | sessions, points, share tokens, `/live/{token}` | public channel `live.{token}` |
| Media | private disks + authorized delivery | heavy work queued on `media` |
| Notifications | `AthleteAlert` (database + broadcast), push | §4–5 below |

Conventions: Form Requests, Policies on every action (no IDOR), API
Resources, Enums, Wayfinder route functions on the frontend, paginate every
list (cursor for feeds).

## 3. Request flow

1. `AssignRequestId` (global, first) — reuses a sane `X-Request-ID` or makes a
   UUID; echoes it; puts it in Laravel **Context** → every log line and every
   job dispatched from this request carries `request_id`.
2. Web: session + CSRF + `HandleInertiaRequests` (shared props: auth,
   `unreadNotificationsCount`, `cartCount`…). API: `auth:sanctum` + `user.token`.
3. Route `throttle:<limiter>` (§7) → Policy/Gate → Action → Resource / Inertia page.
4. Side effects (mail, push, broadcasts, media) are **queued**.

## 4. Realtime (Reverb)

- Server: `BROADCAST_CONNECTION=reverb` (also works with `log`/`null`;
  `App\Support\Realtime::enabled()` is false for `null` or Reverb without
  credentials, and then nothing is broadcast or queued).
- Auth: web `POST /broadcasting/auth` (session); app
  `POST /api/v1/broadcasting/auth` (Bearer token). Channel rules in
  `routes/channels.php`.
- Client: `resources/js/lib/echo.ts` → `getEcho(): RealtimeClient | null`
  (null without `VITE_REVERB_APP_KEY` or in SSR; laravel-echo/pusher-js are
  lazy-loaded). `onRealtimeStateChange()` tells callers when to poll.

| Channel | Type | Who | Events |
|---|---|---|---|
| `App.Models.User.{id}` | private | that user | `notification.created`, `order.updated`, `payment.updated` |
| `admin.commerce` | private | `can('orders.view')` | `order.updated`, `payment.updated`, `inventory.updated` |
| `live.{token}` | public | anyone with the share token | `tracking.point` (A8) |

Event names (all `broadcastAs`, clients listen with a leading dot, e.g.
`.listen('.order.updated', …)`): `notification.created`, `order.updated`,
`payment.updated`, `inventory.updated`, `event.tracking.updated`,
`community.post.created`, `community.comment.created`,
`support.message.triggered`.

**Adding an event** (any domain): extend `App\Events\Realtime\RealtimeEvent`
(ShouldBroadcast + after-commit + `realtime` queue), set `NAME`, implement
`broadcastOn()` with `userChannel($id)` / `adminCommerceChannel()` / a public
channel, return a **small payload** from `broadcastWith()` (ids/uuids +
statuses, no PII), and dispatch only `if (Realtime::enabled())`. New private
channels need a rule in `routes/channels.php`. Examples: `OrderUpdated`,
`PaymentUpdated` (dispatched by `App\Observers\CommerceBroadcastObserver` on
any status change — no commerce Action edits). `inventory.updated` is not
implemented yet: commerce should dispatch it from the inventory movement
Action on `admin.commerce` (payload: variant uuid, available quantity).

Web consumers must work when `getEcho()` is null: the notification bell polls
`GET /notifications/summary` every 60 s while realtime is unavailable.

## 5. Notifications & push

- One notification class: `App\Notifications\AthleteAlert`, sent only via
  `App\Actions\Notifications\SendAthleteNotification` (database always;
  `broadcast` when realtime is on; push per active device when `$push`).
- Web: header bell (`NotificationBell.vue` dropdown) + notification center
  `/notifications` (all / unread, mark one / all read). State in
  `resources/js/lib/notificationRealtime.ts`; toasts via vue-sonner.
- Push: `PushNotificationGateway` contract → `ExpoPushNotificationGateway`
  when `PUSH_PROVIDER=expo` (else `NullPushNotificationGateway`). One queued
  `SendPushNotificationJob` per device; 429/5xx retried with backoff;
  `DeviceNotRegistered` deactivates the device. A token re-registered by
  another account is deactivated on the previous one.
- Push `data` (mobile routing contract):
  `{notification_id, type, target, action_url}` with
  `target = {type, id|uuid, slug?, event_slug?}` (`NotificationTarget`).
  The realtime payload carries the same `notification_id`/`target`.

## 6. Queues

| Queue | Env | Work |
|---|---|---|
| `realtime` | `QUEUE_REALTIME` | broadcasts, push |
| `default` | — | notifications, imports, exports, misc |
| `media` | `QUEUE_MEDIA` (connection `redis-long`, retry_after 960 s) | image variants, ffmpeg, Synology transfer |

All names default to `default` locally (one `queue:listen` worker runs
everything, `QUEUE_CONNECTION=database`). Production: Redis + Horizon
(`config/horizon.php`: `supervisor-1` realtime+default, `supervisor-media`).
Payment webhook finalization is **not** queued (it runs inside the webhook
transaction; only its side effects are). `/horizon` = super_admin only, not in
the sidebar.

## 7. Rate limits (named, `AppServiceProvider`)

`api`, `api-register`, `api-claim`, `api-legacy-lookup`, `login`,
`password-reset`, `social-auth`, `social-write`, `social-comment`, `search`,
`reports`, `support-message`, `admin-notifications`,
`device-pairing-confirm`, and the platform set:

| Name | Budget | Use on |
|---|---|---|
| `nfc-resolve` | 30/min + 300/h per IP | `GET /n/{token}`, `GET /api/v1/nfc/{token}` |
| `tracking-ingest` | 60/min per session+user, 240/min per user | `POST …/tracking/sessions/{session}/points` |
| `live-view` | 30/min per IP+token | `/live/{token}`, `/api/v1/live/{token}` |
| `community-write` | 20/min + 300/h per user | posts, comments, reactions, follows, reports |
| `uploads` | 20/min + 200/h per user | any media upload |

Redis backs the limiter in production (`CACHE_STORE=redis`).

## 8. Observability

- `request_id` everywhere (header ↔ Context ↔ logs ↔ jobs).
- Logs: `LOG_STACK=daily,json` → `storage/logs/laravel.json.log` (JSON lines
  for a shipper). `payments` channel (`storage/logs/payments-*.log`, 90 days):
  webhook exceptions and amount mismatches (`OperationalLog`).
- Failed jobs: one `queue.job_failed` line (job, queue, attempts, exception)
  + `failed_jobs` table; Horizon for queue health/lag.
- Push: `push.expo.*` lines (device id only, never the token).
- Admins never see raw logs; ops reads them on the server/shipper.

## 9. Media

Private by default. Originals and athlete media live on private disks
(`synology_media` over a Tailscale mount in production, local disk in dev —
same path semantics); delivery through authorized/signed routes; variants and
transcodes on the `media` queue. See `docs/SYNOLOGY.md`.

## 10. Load testing

`tools/loadtest/` (k6): store, feed, participant search, cart, checkout (up
to order creation), tracking ingest, live viewers, notifications; profiles
smoke / 100 / 250 / 500 VUs. Capacity is only what a recorded run shows.
