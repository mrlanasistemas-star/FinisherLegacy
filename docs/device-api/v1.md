# Finisher Legacy Device API v1

> **OBSOLETO (2026-11-11).** Ya no se producen placas grabadas: este flujo y su código se retiraron. Se conserva solo como referencia histórica. Ver `docs/renovacion/retiro-modulos.md`.

Base URL: `/api/v1` — still one versioned API (see ADR 0002). No parallel
API surface: this is a `devices`/`device`/`production` sub-namespace of the
same `/api/v1` used by the mobile app and other Finisher Legacy sites
(`docs/api/v1.md`), with its own auth and its own error envelope.

Consumed by a Finisher Event Desktop station (or `php artisan
finisher:simulate-station`, the mock-driver CLI client used to test this
contract without hardware — see `docs/adr/0002-device-production-api.md`
§Mock Driver and `docs/adr/0003-production-state-machine.md` §Simulator).
The actual LaserDriver (Cloudray/BSL, EZCAD/JCZ, LightBurn bridge) lives in
that separate desktop app, not in this repo — this document is the
contract it must implement.

## Authentication

Token via Sanctum, same mechanism as `/api/v1` user auth, but a device
token is a completely separate principal — `App\Http\Middleware\
EnsureProductionDeviceToken` rejects a User's token on every route below,
and `EnsureUserToken` rejects a device's token on every non-device
`/api/v1` route. A device token can never do anything a User token can
(and vice versa).

```
Authorization: Bearer {device_token}
```

Abilities on a device token (`App\Enums\DeviceAbility`): `device:heartbeat`,
`production:read`, `production:claim`, `production:update`,
`production:engrave`. `production:update` gates coordination steps
(prepare, flip confirm, QR verify, deliver, fail, cancel);
`production:engrave` gates the four steps that represent actual laser
activity (front/back start and complete).

A device can never reach `/admin/*`, `users`, `roles`, `settings`, or
`platetemplates` — there is no ability that grants any of that, by
construction.

## Errors

Every error under this surface — validation, auth, domain — renders as:

```json
{
  "error": {
    "code": "NO_PRODUCTION_JOB_AVAILABLE",
    "message": "No hay trabajos disponibles.",
    "details": {}
  }
}
```

`code` is a stable, machine-readable value from `App\Enums\DeviceErrorCode`
— branch on it, never on `message`. See that enum for the full list,
including the Slice 2 production-workflow codes:
`INVALID_PRODUCTION_TRANSITION`, `JOB_NOT_ASSIGNED`,
`JOB_OWNED_BY_OTHER_DEVICE`, `ARTIFACT_NOT_READY`, `FRONT_NOT_COMPLETED`,
`FLIP_NOT_CONFIRMED`, `BACK_NOT_COMPLETED`, `QR_VERIFICATION_FAILED`,
`JOB_ALREADY_COMPLETED`, `JOB_FAILED`, `DEVICE_LEASE_EXPIRED`.

## Pairing

Unregistered devices only. Not authenticated — a device has no token yet.

### `POST /devices/pair`

Rate limited (`api-register`, 5/min/IP).

**Body:** `name` (required), `station_code`, `app_version`, `capabilities`
(all optional).

**201** →

```json
{
  "data": {
    "code": "K7X2QP",
    "poll_token": "…64 chars, shown once…",
    "expires_at": "2026-08-20T18:10:00+00:00"
  },
  "message": "Muestra este código a un Super Admin para vincular la estación."
}
```

`code` is a human correlator only — shown on the desktop screen so a Super
Admin can tell two simultaneous pairing attempts apart in `/admin/
production-devices`. It is never accepted as a credential anywhere. `poll_token`
is the real secret: keep it in memory only, never write it to disk/logs.

### `POST /devices/pair/confirm`

Rate limited (`device-pairing-confirm`, 30/min/IP — meant to be polled every
few seconds while waiting).

**Body:** `poll_token` (required).

**200**, still waiting:

```json
{ "data": { "status": "pending" }, "message": "Esperando aprobación de un Super Admin." }
```

**200**, approved — the device token, delivered exactly once:

```json
{
  "data": {
    "status": "completed",
    "device": { "uuid": "…", "name": "…", "status": "active", "online": false, "...": "..." },
    "token": "…the device's Sanctum token, shown once…"
  },
  "message": "Dispositivo vinculado."
}
```

Persist `token` immediately — a second confirm call for the same pairing
always fails with `PAIRING_ALREADY_COMPLETED` (410), even for the same
legitimate desktop. If that happens, pair again from scratch.

**410** `PAIRING_EXPIRED` — request a new pairing. **404**
`PAIRING_REQUEST_NOT_FOUND` — the poll token doesn't match any request.

## Device 🔒

### `GET /device`

**Ability:** `device:heartbeat`. Returns the authenticated device's own
record (never another device's).

### `POST /device/heartbeat`

**Ability:** `device:heartbeat`. Updates `last_seen_at` always;
`app_version`/`capabilities` only if provided. A device must have
heartbeat'd recently (`ProductionDevice::isOnline()`) before it's allowed
to claim a job — see `POST .../claim` below.

**Body:** `app_version`, `capabilities` (both optional).

**200** →

```json
{
  "data": {
    "server_time": "2026-08-20T18:00:00+00:00",
    "device": { "...": "ProductionDeviceResource" },
    "active_event": { "id": 12, "name": "IRONMAN 70.3 Cozumel — 2026" },
    "status": "active"
  }
}
```

## Production jobs 🔒

A device only ever sees jobs for its assigned event edition and machine
profile (or every generic queued job, if it isn't scoped to either).

### The physical workflow

```
queued → assigned → preparing → engraving_front → awaiting_flip
       → engraving_back → verifying_qr → ready → delivered
```

See `docs/adr/0003-production-state-machine.md` for the full state
machine, the Plate↔Job sync rules, and why `failed`/`cancelled` behave the
way they do. Every transition endpoint below returns the updated job in
the same shape as `GET .../jobs/{job}` — always check `data.status` and
`data.next_action` rather than assuming the call succeeded from the HTTP
status alone... though the HTTP status is also reliable (2xx = applied,
4xx/409 = rejected, job unchanged).

### `GET /production/jobs/next`

**Ability:** `production:read`. Read-only peek at the best next candidate —
never assigns it, never has an artifact yet. `data` is `null` (not an
error) when the queue is empty. Only ever returns a job in `queued` — a
claimed job is never returned here again, claimed or not.

### `GET /production/jobs/{job}`

**Ability:** `production:read`. Only the device that currently holds the
claim on `{job}` may read it — `403 PRODUCTION_JOB_FORBIDDEN` otherwise.

**200** (this shape is shared by every endpoint on this page) →

```json
{
  "data": {
    "job_id": 501,
    "status": "awaiting_flip",
    "next_action": "confirm_flip",
    "plate": { "id": 4210, "serial": "FL-2026-004210", "legacy_code": "FL-Q8K2MX7P" },
    "dimensions": { "width_mm": 60.0, "height_mm": 40.0 },
    "front": { "download_url": "https://…/production/jobs/501/artifact/front", "sha256": "…" },
    "back": { "download_url": "https://…/production/jobs/501/artifact/back", "sha256": "…", "transform": "mirror_x" }
  }
}
```

`front`/`back` are `null` until the job has been claimed (no artifact
exists yet — see §Artifact below). `next_action` is one of `prepare`,
`start_front`, `complete_front`, `confirm_flip`, `start_back`,
`complete_back`, `verify_qr`, `deliver`, or `null` once terminal — never
guess the next UI action from `status` text.

No power/speed/frequency anywhere in this payload, ever — those are
calibrated physically per machine/material and never leave the driver (see
ADR 0002 §Invariantes de seguridad).

### `POST /production/jobs/{job}/claim`

**Ability:** `production:claim`. Atomic — two devices racing for the same
job, only one gets `200`; the other gets `409
PRODUCTION_JOB_ALREADY_CLAIMED`. Safe to retry with the same job id if this
device already holds it (refreshes the lease instead of erroring).
Requires the device to be online (recent heartbeat) — `409` otherwise.

Moves the job `queued -> assigned` and **freezes its ProductionArtifact**
in the same call — front/back download URLs and hashes are present in the
response from here on.

Supports `Idempotency-Key`.

### `POST /production/jobs/{job}/release`

**Ability:** `production:claim`. Only valid from `assigned`/`preparing` —
puts the job back in `queued` for any device to claim. `409
INVALID_PRODUCTION_TRANSITION` once engraving has started; there is no
undo past that point (see ADR 0003 §Irreversibilidad física).

### `POST /production/jobs/{job}/prepare`

**Ability:** `production:update`. `assigned -> preparing`.

### `POST /production/jobs/{job}/front/start`

**Ability:** `production:engrave`. `preparing -> engraving_front`. `409
ARTIFACT_NOT_READY` if somehow called before the artifact exists (should
never happen given claim already froze it).

### `POST /production/jobs/{job}/front/complete`

**Ability:** `production:engrave`. `engraving_front -> awaiting_flip`.
Records `front_engraved_at` + the calling actor.

### `POST /production/jobs/{job}/flip/confirm`

**Ability:** `production:update`. Does **not** change `status` — the job
stays `awaiting_flip`. Records `flip_confirmed_at`, a precondition
`back/start` checks explicitly (`409 FLIP_NOT_CONFIRMED` otherwise).
Finishing the front is never assumed to mean the plate got flipped.

### `POST /production/jobs/{job}/back/start`

**Ability:** `production:engrave`. `awaiting_flip -> engraving_back`.
Requires `flip_confirmed_at` to be set.

### `POST /production/jobs/{job}/back/complete`

**Ability:** `production:engrave`. `engraving_back -> verifying_qr`.
Records `back_engraved_at` + the calling actor.

### `POST /production/jobs/{job}/qr/verify`

**Ability:** `production:update`. **Body:** `decoded_value` (required) —
whatever the scanner actually decoded, full URL
(`https://…/l/FL-Q8K2MX7P`) or bare code. Never send a bare boolean.

Compared against the plate's real Legacy Code
(`App\Support\Production\LegacyQrVerifier`). Wrong code: **422
QR_VERIFICATION_FAILED**, job stays `verifying_qr`, `qr_verified_at` stays
null — never a false positive. Correct: `verifying_qr -> ready`, records
`qr_verified_at`, `ready_at`, and the actor; the Plate moves to `ready`
too.

Supports `Idempotency-Key`.

### `POST /production/jobs/{job}/deliver`

**Ability:** `production:update`. `ready -> delivered`. Never reachable
from `verifying_qr` directly — the state machine has no such transition.

### `POST /production/jobs/{job}/fail`

**Ability:** `production:update`. **Body:** `error_code` (required, one of
`App\Enums\ProductionFailureCode`: `device_not_connected`,
`laser_not_ready`, `engraving_failed`, `qr_unreadable`, `user_cancelled`,
`unknown`), `message`, `metadata` (both optional). Valid from any
operational state (`assigned` through `verifying_qr`). Never clears
`front_engraved_at`/`back_engraved_at` — physical evidence is permanent
(ADR 0003 §Irreversibilidad física). A failed job never auto-resumes; a
human decides retry/reprint.

### `POST /production/jobs/{job}/cancel`

**Ability:** `production:update`. Only valid from `queued`/`assigned`/
`preparing` — the state machine has no `cancelled` destination from any
engraving state, so this is safe by construction.

### `GET /production/jobs/{job}/artifact/{face}`

**Ability:** `production:read`. `{face}` is `front` or `back`. Returns the
raw, **persisted** SVG bytes (`Content-Type: image/svg+xml`) from the
job's frozen `ProductionArtifact` — never re-rendered, so downloading
twice is always byte-identical, no matter what changed elsewhere in the
meantime (event result, template default, anything). `409
ARTIFACT_NOT_READY` if the job hasn't been claimed yet. Only the owning
device can download it — `403 PRODUCTION_JOB_FORBIDDEN` otherwise, even if
the job id is known.

## Idempotency

Every `POST` above except pairing supports an optional `Idempotency-Key`
header. Send the same key on a retried request (dropped connection, client
timeout) and get back the exact same response — no re-run of the
transition, no duplicate ActivityLog entry, no new timestamp.

## What's still not in scope (Slice 3+)

- No real driver (Cloudray/BSL/JCZ/EZCAD/LightBurn) — the backend still
  never talks to the laser; it ends at the frozen artifact file.
- No real desktop app — `php artisan finisher:simulate-station` is the
  reference client that exercises this whole contract.
- No explicit retry endpoint — today "retry" is a manual reprint from
  `/admin/plates/{id}`.
- No WebSocket/push updates — the web kanban still polls.
