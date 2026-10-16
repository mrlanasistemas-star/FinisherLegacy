# NFC — el vínculo físico-digital

> "Acerca tu teléfono." Cada Legacy Plate y cada producto conectado lleva una
> etiqueta NFC pasiva. Al tocarla, el teléfono abre la historia a la que
> pertenece. NFC es el vínculo principal para producto nuevo; el QR quedó
> como histórico (ver §QR).

## 1. Modelo

| Tabla / modelo | Qué es |
|---|---|
| `nfc_tags` / `App\Models\NfcTag` | Un chip físico. `token` público aleatorio, `uid_hash` opcional (HMAC del UID de hardware; **nunca** se guarda ni se muestra en claro), `status` (`available` · `assigned` · `disabled`), `batch_code`, `activated_at`, `last_tapped_at`, `tap_count`, `metadata`. |
| `nfc_assignments` / `App\Models\NfcAssignment` | Qué abre la etiqueta y de quién es. `target_type` ∈ `legacy_plate` · `product` · `profile` · `event` (enum `App\Enums\NfcTargetType`) con **FKs explícitas y restringidas** (`plate_id`, `product_id` + `product_variant_id`, `athlete_owned_product_id`, `event_id`) — nunca un morph libre. `athlete_id` = dueño; `activated_at` ≠ null = conectada. Una sola fila abierta por etiqueta (`ended_at` null); transferir/liberar/reemplazar cierra la fila y abre otra, así el historial nunca se sobrescribe. |
| `nfc_taps` / `App\Models\NfcTap` | Bitácora corta: `ip_hash` (HMAC), `user_agent` (≤120), `user_id` opcional, `channel` (`web`/`app`). Se purga a los `NFC_TAP_RETENTION_DAYS` (90) con `model:prune` diario (registrado en `App\Providers\NfcServiceProvider`). |

### Por qué no se extendió LegacyCode / AthleteOwnedProduct

- `LegacyCode` es la identidad permanente de una **placa** (1:1 con `Plate`,
  `/l/{code}`). Una etiqueta NFC puede abrir también productos, perfiles y
  eventos, puede reemplazarse (chip dañado) y cambiar de dueño — meter eso en
  `LegacyCode` mezclaría dos ciclos de vida. Se conectan por la placa: una
  etiqueta `legacy_plate` resuelve a `plate.legacyCode` y el claim de una se
  refleja en la otra (`ClaimLegacyCodeService` activa la etiqueta abierta de
  la placa; `ClaimNfcTag` delega en `ClaimLegacyCodeService`).
- `AthleteOwnedProduct` exige `athlete_id` (columna NOT NULL), así que no
  puede representar "unidad vendida sin dueño todavía". La etiqueta guarda el
  producto/variante y el `AthleteOwnedProduct` se crea al conectar.
- `LegacyId` es un identificador de usuario; no aplica.

## 2. Token y URL

- `https://<APP_URL>/n/{token}` (ruta `nfc.tap`). Nada más se graba en el chip.
- `token`: 32 caracteres `[A-Za-z0-9]` vía `Str::random` (CSPRNG) ≈ **190 bits**.
  Nunca secuencial, nunca derivado de nombre, correo, id ni dorsal
  (`App\Support\Nfc\NfcToken`). Patrón aceptado en rutas: `[A-Za-z0-9]{24,64}`.
- El público **no puede elegir ni cambiar el destino**: solo staff
  (`AssignNfcTag`, auditado) y el dueño (`ClaimNfcTag`, `TransferNfcAssignment`).

## 3. Tap (web) — `GET /n/{token}`

Throttle `nfc-resolve` (definido por la plataforma; `NfcServiceProvider` crea
un fallback 30/min + 300/h por IP si no existe). Registra el tap.

| Estado | Qué ve la persona |
|---|---|
| Conectada (`claimed`) | Redirect al destino: Legacy Plate → historia `/l/{code}`; producto → Mi equipo (dueño) / perfil público del dueño / ficha de tienda; perfil → `/@username`; evento → `/events/{slug}`. |
| Sin conectar (`unclaimed`) | Página `nfc/Tap` (`NfcActivationCard`): invitado → "Crear cuenta" / "Iniciar sesión" (`/n/{token}/continue/{login|register}` guarda `url.intended` y vuelve aquí) → "Conectar a mi Legacy" → confirmación → `POST /n/{token}/claim` → **Mi equipo**. |
| Desactivada / sin asignar (`disabled`) | Página honesta: "ya no está activa" / "todavía no está lista". |

Cero vocabulario técnico en pantalla (sin "token", "UID", "tag").

## 4. API (contrato para la app)

`GET /api/v1/nfc/{token}` — público, throttle `nfc-resolve`. Un Bearer opcional
personaliza `owned_by_me` / `can_claim`.

```json
{ "data": {
    "status": "unclaimed" | "claimed" | "disabled",
    "owned_by_me": false,
    "can_claim": true,
    "target": { "type": "legacy_plate"|"product"|"profile"|"event",
                "title": "…", "subtitle": "21K · 01:45:12" | null,
                "image_url": "…" | null, "web_url": "…",
                "profile_username": "…" | null, "event_slug": "…" | null } | null
}, "message": null, "meta": { "request_id": "…" } }
```

- `target` es `null` solo cuando `status = disabled`.
- Etiquetas de evento: siempre `claimed`, sin dueño, `can_claim = false`.
- Nunca incluye nombre/correo del dueño; `profile_username` solo si su perfil es público.
- Token desconocido → `404 NOT_FOUND`.

`POST /api/v1/nfc/{token}/claim` — `auth:sanctum`, throttle `api-claim`.
`200 {data: <gear item>}` (misma forma que `/me/gear`). Errores:
`409 NFC_ALREADY_CLAIMED` (otra persona ya la conectó), `422 NFC_NOT_CLAIMABLE`
(desactivada, sin asignar o de evento), `409` de identidad si la placa
pertenece a otro atleta (`AthleteIdentityConflictException`). Idempotente para
el dueño. Concurrencia: `Cache::lock("nfc-claim:{tag}")` + `lockForUpdate()`
en etiqueta, asignación y unidad → un solo dueño.

`GET /api/v1/me/gear` — productos + Legacy Plates conectadas. Claves
históricas intactas (`uuid, product_name, variant_name, status, asset_code,
acquired_at, activated_at, usage_history`); aditivas: `kind`
(`product`|`legacy_plate`), `connected`, `connection_label`
(`"Producto conectado"` | null), `connected_at`, `image_url`, `story_url`,
`event {name, edition, slug, date, race, official_time}`.

**Deprecados (siguen funcionando, responden `Deprecation: true`)**:
`GET /api/v1/gear/{code}`, `POST /api/v1/gear/{code}/claim` (código de activo
QR). `GET /api/v1/legacy-codes/{code}` y `POST …/claim` siguen vigentes para
placas históricas.

## 5. Deep links

- App: `finisherlegacy://nfc/{token}` (`MOBILE_APP_SCHEME`).
- Universal / App Links sobre `https://<dominio>/n/*`:
  - `GET /.well-known/apple-app-site-association` — JSON (`application/json`,
    sin redirect). `appID = APPLE_TEAM_ID + "." + IOS_BUNDLE_ID`
    (default `com.finisherlegacy.app`), `paths ["/n/*"]` (+ `components`).
  - `GET /.well-known/assetlinks.json` — `package_name = ANDROID_APP_PACKAGE`
    (default `com.finisherlegacy.app`), `sha256_cert_fingerprints` de
    `ANDROID_SHA256_CERT_FINGERPRINTS` (separadas por coma).
  - Sin `APPLE_TEAM_ID` / sin huellas → **404** (la web sigue atendiendo cada tap).
- `IOS_APP_STORE_ID` (opcional) agrega el smart app banner
  (`apple-itunes-app`, `app-argument=finisherlegacy://nfc/{token}`) en la
  página de activación. La web nunca depende de la app.
- Infra: servir `/.well-known/*` sin redirect a `www` ni auth; HTTPS válido.

| Variable | Uso |
|---|---|
| `APPLE_TEAM_ID` | Habilita apple-app-site-association |
| `IOS_BUNDLE_ID` | Bundle (default `com.finisherlegacy.app`) |
| `IOS_APP_STORE_ID` | Smart app banner (opcional) |
| `ANDROID_APP_PACKAGE` | Paquete (default `com.finisherlegacy.app`) |
| `ANDROID_SHA256_CERT_FINGERPRINTS` | Habilita assetlinks.json |
| `MOBILE_APP_SCHEME` | Esquema (default `finisherlegacy`) |
| `NFC_TAP_RETENTION_DAYS` | Retención de `nfc_taps` (default 90) |

## 6. Transferencia (solo dominio)

`App\Actions\Nfc\TransferNfcAssignment` — sin marketplace ni UI.
`request(assignment, owner, recipientAthlete)` devuelve un token de
confirmación (40 chars, solo se guarda su SHA-256, expira en
`nfc.transfer_ttl_hours` = 72 h). `confirm(assignment, token, recipientUser)`
cierra la asignación (`end_reason = transferred`), abre una nueva para el
destinatario y mueve el `AthleteOwnedProduct`. `cancel()` la retira.
Transferibles: `product`, `profile`. **Nunca** `legacy_plate` ni `event`.
Todo queda en `activity_log` (`nfc.transfer.*`).

## 7. Preparación y entrega (staff)

Admin → Legacy + NFC → **Preparación y entrega**
(`/admin/legacy-nfc/preparacion`). Seis etapas sobre la máquina de estados
existente (ADR 0003, intacta) — `App\Enums\PreparationStage::fromJob()`:

| Etapa | ProductionJob.status | Acción web |
|---|---|---|
| Pendiente | `queued` (+ entitlements pagados sin placa → "Generar") | Empezar preparación |
| Preparando | `assigned`, `preparing`, `engraving_front` | Marcar personalizada |
| Personalizada | `awaiting_flip`, `engraving_back`, `verifying_qr` sin NFC; `ready` sin NFC | Asignar NFC |
| NFC asignado | mismos estados con etiqueta asignada | Marcar lista |
| Lista | `ready` con NFC | Marcar entregada |
| Entregada | `delivered` | — |
| (Requiere atención) | `failed`, `cancelled` | se reimprime desde Legacy Plates |

- `App\Actions\Preparation\AdvancePlatePreparation` recorre las mismas
  transiciones vía `ProductionJobStateMachine` + `PlateProductionCoordinator`.
  Placa V3 (solo frente): "Personalizada" registra `front_engraved_at`; no
  inventa grabado de reverso. Placas históricas de molde registran ambas
  caras y congelan su artifact como antes.
- "Lista" exige una etiqueta NFC abierta en la placa (reemplaza la
  verificación de QR para producto nuevo).
- `PlateProductionCoordinator` ahora sincroniza el entitlement:
  `ready → produced`, `delivered → delivered`.
- **NFC asignado**: escanear (lector en modo teclado o pegar el enlace/código)
  o elegir una disponible; "Reemplazar" desactiva la etiqueta anterior y
  conserva el dueño.
- Sin USB, driver, potencia, frecuencia, volteo ni QR en la web. La **Device
  API** (`/api/v1/production/jobs/*`) sigue igual para el software de la máquina.
- Contrato con la máquina: `GET …/preparacion/placas/{plate}/datos` (JSON:
  `serial`, `spec_version`, `layout` congelado, `values`) y
  `GET …/preparacion/exportar` (CSV de lo que está en Pendiente/Preparando).
  Placas históricas conservan su paquete SVG/PNG/PDF en Legacy Plates.
- `/production` y `/admin/legacy-plates/production` redirigen aquí.

Permisos: ver `production.access`; acciones `production.manage`; "Generar"
`legacyplates.produce`. Etiquetas/Productos conectados: `nfc.view` / `nfc.manage`
(nuevos; migración `2026_10_16_120100_create_nfc_permissions`, admin los
recibe, `production_operator` recibe `nfc.view`).

## 8. Encoder (lotes de etiquetas)

Admin → Legacy + NFC → **Etiquetas NFC** → "Generar etiquetas" (1–500 por
lote, `batch_code` opcional) → "Enlaces (CSV)" exporta `id,url,lote,estado`
con `https://<dominio>/n/{token}` por fila para el software de grabado de
chips (NTAG213+ basta: ~61 bytes). Generar y exportar quedan auditados.
Desactivar/reactivar conserva la asignación; "Liberar" devuelve una etiqueta
no conectada al inventario.

**Productos conectados**: "Conectar producto" asigna una etiqueta a un
producto del catálogo (unidad sin dueño: el primero que la toque la conecta)
o a una unidad existente (`owned_product_uuid`, queda conectada a su dueño).

## 9. Seguridad

- Tokens ≥128 bits, CSPRNG, sin PII; endpoints públicos con throttle por IP.
- UID solo como HMAC (`APP_KEY`), oculto en serialización; IP de taps solo como HMAC.
- Destino inmutable para el público; asignaciones solo por staff con permiso.
- Toda acción de staff/dueño en `activity_log`: `nfc.tags.generated`,
  `nfc.tags.exported`, `nfc.assigned`, `nfc.unassigned`, `nfc.claimed`,
  `nfc.disabled`, `nfc.enabled`, `nfc.transfer.*`, `preparation.*`.
- Claim con lock distribuido + `lockForUpdate`; claim de placa reutiliza la
  resolución de identidad de atleta (nunca fusiona en silencio).

## 10. QR — clasificación (spec §130)

**HISTÓRICO** (se conserva para leer/reimprimir; no se usa en producto nuevo):
- `legacy_codes` + `/l/{code}` + `/l/{code}/qr.svg` + `/l/{code}/claim`
  (`LegacyCodeController`, `LegacyCodeQrService`, `QrCodeService`,
  `Services/Qr/*`): URL permanente de placas ya entregadas. La página de
  historia ya no muestra la imagen QR (el prop `qrUrl` se sigue enviando).
- Plate Studio / moldes (`PlateTemplate*`, elementos `qr` del renderer,
  `PlateExportService::exportQr/exportPackage`, `admin.plates.export-qr`,
  `CalibrationCardService`, `DownloadPlateDialog`): flujo de molde histórico.
- `production_jobs.qr_verified_at/qr_decoded_value/qr_actor_*`,
  `VerifyProductionQr`, `LegacyQrVerifier`, `BackNotCompleted`/
  `QrVerificationFailed`: evidencia de producción histórica.
- `athlete_owned_products.asset_code`, `products.qr_capable`,
  `AssetCodeService`, `/api/v1/gear/{code}` (deprecado): activos QR.
- Operador de evento (`operator/*`, `PlateResultPanel`, `QuickPlateResult`)
  y `admin/events/ProductionSetup` "probar QR": flujo de placa rápida en
  sitio para placas de molde.
- Migraciones con columnas `qr_*`: nunca se editan.

**NECESARIO INTERNO**:
- `preregistrations/{token}/qr.svg` (pase de prerregistro para check-in) y
  `support/{publicCode}/qr.svg` (compartir "Mi equipo de apoyo"): no son la
  estrategia de producto físico; se mantienen.
- `QrScannerDialog` (lector de cámara para operador/check-in) y
  `GeneratedPasswordField`/2FA de Fortify (QR TOTP — autenticación).
- Device API `qr/verify` y `ProductionJobAction`s: contrato con el software
  de máquina; se mantiene sin cambios.

**OBSOLETO** (retirado de la UI nueva, rutas compatibles):
- Kanban láser `/production` (`production/Index.vue`, con frente/volteo/
  reverso/QR): eliminado; `GET /production` redirige a Preparación y
  entrega. Endpoints `production.jobs.*` se conservan (sin UI) por compatibilidad.
- `/admin/legacy-plates/production` (`Production.vue`): redirige.
- `OwnedProductCard` (mostraba el asset code QR): reemplazado por
  `ConnectedGearCard` en Mi equipo.
- Textos "Escanea tu placa" en la historia de la Legacy Plate → copy NFC.

Pendiente fuera de mi propiedad (revisar en integración): menciones de QR en
`MobileBottomNav` (escáner), `dashboard/medals/*`, `LegadoShow`,
`store/Show`, `admin/products/*` (`qr_capable` → etiqueta "Producto
conectado"), `iconMap`/`statusLabels`.
