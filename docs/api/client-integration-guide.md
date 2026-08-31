# Guía de integración para clientes externos

Para cualquier cliente futuro (Desktop, Mobile, un tercero autorizado) que
vaya a construirse en un repositorio separado — ver `docs/architecture/README.md`
§"Tres clientes, un backend". Ningún código de cliente vive en este repo;
esta guía es el contrato, no una implementación.

## 1. Autenticar

Dos guardas separadas — nunca mezcladas (`docs/adr/0002-device-production-api.md`):

- **Personal/operador** (Event Ops, medallas, perfil): `POST /api/v1/auth/login` con email/password → token Sanctum de `User`.
- **Estación de producción**: pairing (`POST /api/v1/devices/pair` → código humano → `POST /api/v1/devices/pair/confirm` en poll hasta que un Super Admin lo apruebe en `/admin/production-devices`) → token Sanctum de `ProductionDevice`. Ver `docs/device-api/v1.md`.

```bash
curl -X POST https://tu-dominio/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "operador@ejemplo.com", "password": "..."}'
# → { "data": { "user": {...}, "token": "1|xxxxxxxxxxxx" } }
```

Guarda el token de forma segura (para un Desktop: almacén de credenciales
del SO, nunca un archivo plano — ver `docs/adr/0007-desktop-event-station.md`
§Secure token storage). Envíalo en cada request siguiente:

```bash
curl https://tu-dominio/api/v1/me \
  -H "Authorization: Bearer 1|xxxxxxxxxxxx"
```

## 2. Bootstrap

Un cliente de personal (Event Ops) llama `GET /me` para conocer sus
permisos. Una estación llama `GET /device/bootstrap` para conocer su
evento activo, perfil de máquina, y job actual en un solo request:

```bash
curl https://tu-dominio/api/v1/device/bootstrap \
  -H "Authorization: Bearer {device_token}"
```

## 3. Heartbeat (solo estaciones)

```bash
curl -X POST https://tu-dominio/api/v1/device/heartbeat \
  -H "Authorization: Bearer {device_token}" \
  -H "Content-Type: application/json" \
  -d '{"app_version": "1.0.0"}'
```

Llamar cada `heartbeat_interval` configurado localmente (recomendado:
15-30s). Ver `docs/adr/0007` §Backoff para la estrategia de reintento
cuando el backend no responde.

## 4. Buscar y producir (Event Ops)

```bash
# Dashboard del evento
curl https://tu-dominio/api/v1/event-ops/42 \
  -H "Authorization: Bearer {token}"

# Buscar por dorsal
curl "https://tu-dominio/api/v1/event-ops/42/participants/search?q=1425" \
  -H "Authorization: Bearer {token}"

# Detalle + elegibilidad
curl https://tu-dominio/api/v1/event-ops/participants/8123 \
  -H "Authorization: Bearer {token}"

# Producir — con Idempotency-Key para que un reintento de red no duplique
curl -X POST https://tu-dominio/api/v1/event-ops/participants/8123/plate \
  -H "Authorization: Bearer {token}" \
  -H "Idempotency-Key: $(uuidgen)"
```

## 5. Manejo de errores

Nunca compares `error.message` (cambia, es texto para humanos) — compara
`error.code`:

```json
{ "error": { "code": "PLATE_ALREADY_EXISTS", "message": "...", "details": {} }, "request_id": "..." }
```

- `401 UNAUTHENTICATED` → el token es inválido/revocado. Muestra
  "Estación desvinculada" / "Sesión expirada", nunca reintentes en loop.
- `403 FORBIDDEN` → el actor no tiene el permiso/ability necesario.
- `404 NOT_FOUND` → el recurso no existe (o el id es de otro tipo de
  actor — nunca revela cuál).
- `409 CONFLICT` (o un código específico) → ya se hizo, o hay una
  incompatibilidad de estado. No reintentar automáticamente salvo que
  sea idempotente por diseño (ver `Idempotency-Key`).
- `422` → validación — usa el formato estándar de Laravel (`errors`),
  distinto del resto (ver `docs/api/v1.md` §Errores).
- `429 TOO_MANY_REQUESTS` → backoff exponencial, nunca reintento inmediato.
- `5xx` → backoff exponencial (`docs/adr/0007` §Backoff: 2s, 5s, 10s, 30s).

## 6. Idempotencia

Cualquier escritura crítica acepta `Idempotency-Key` (UUID generado por
el cliente, **antes** de enviar la request — no después de que falle).
Reenviar la misma clave con el mismo `route` + actor replica la respuesta
original en vez de re-ejecutar la acción — seguro para reintentos de red.

## 7. Paginación

Listas usan el formato estándar de Laravel — nunca asumas que `data` es
la lista completa sin revisar `meta.last_page`.

## 8. Qué el cliente NUNCA debe hacer

- Decidir si una placa puede producirse, si un QR es correcto, o si un
  `ProductionJob` puede avanzar — todo eso lo decide el backend
  (`docs/adr/0007-desktop-event-station.md` §Responsabilidades).
- Cachear/persistir más que lo necesario para operar offline — nunca una
  copia completa de Athletes/Events/Participants/Results
  (`docs/api/v1.md`, principio de "backend sigue siendo source of truth").
- Enviar parámetros de láser (potencia/frecuencia/velocidad) al backend,
  ni recibirlos de él — esos viven en el perfil de máquina calibrado
  localmente.
- **Calcular o enviar un precio.** `POST /checkout`, `POST .../payments/online`
  y `POST .../payments/manual` siempre recalculan el monto server-side
  (`App\Actions\Commerce\ResolveProductPrice`) — un cliente que envía
  `amount`/`price` lo ve ignorado, nunca confiado (brief §68/§93,
  ver `docs/architecture/commerce.md`).

## 9. Tienda / Legacy Plate / Digital Closet (ecosistema comercial)

Mismo patrón que el resto: navega el catálogo público
(`GET /store/products`), agrega al carrito autenticado, `POST /checkout`,
y consulta `GET /orders`. Un producto QR-capable (Trisuit, FAST T1 Socks,
Chill Band, Racepack, y Legacy Plate vía su propio Legacy Code) aparece
después en `GET /me/gear` una vez que la Order se marca `fulfilled`. Ver
`docs/architecture/commerce.md` y `docs/architecture/athlete-assets.md`
para el detalle de dominio completo — esta guía no lo repite.
