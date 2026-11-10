# Manual de activación NFC — Finisher Legacy Tag

> Estado: implementado 2026-11-10. Contrato API exacto: `docs/api/v1.md` §Activación NFC.
> Modelo de pieza: `docs/architecture/commerce-os.md` §Tag.

## 1. Modelo

El proveedor entrega cada producto terminado con un chip **NTAG21x en blanco** (sin URL). La pieza es **solo NFC: no lleva QR** y no se ofrece escaneo QR en ningún flujo (los enlaces viejos `?src=qr` siguen resolviendo en silencio por compatibilidad). La persona activa su pieza ella misma:

1. Inicia sesión (app Finisher Legacy Android/iOS, o Chrome Android en `/activar/nfc`).
2. Escribe el **código de activación** de la tarjeta del empaque (o, si compró en línea con esa cuenta, no lo necesita).
3. El backend valida la pieza y su derecho a ella (`POST /api/v1/tags/activation/start`) y entrega la **única** URL que se puede grabar: `{LEGACY_TAG_PUBLIC_URL}/t/{public_code}` (default `https://finisherlegacy.com`).
4. El teléfono graba esa URL como registro NDEF URI.
5. El teléfono **vuelve a leer** el chip.
6. El backend confirma (`…/{intent}/confirm`): la URL leída debe ser exactamente la emitida; liga el chip (hash del UID) a la pieza y la activa a nombre de la persona (`ActivateTag`, idempotente).
7. La persona completa su recuerdo (editor `/mi-legacy/{id}/editar`).

### Piezas de backend

| Pieza | Dónde |
|---|---|
| Código de activación (prueba de compra) | `legacy_tags.activation_code_hash` (HMAC-SHA256 con `APP_KEY`, único) + `activation_code_last4` para soporte. Nunca en claro en la BD. |
| Generación | `CreateLot` (al crear un lote) y `php artisan legacy-tags:generate-activation-codes {--lot=}` (solo rellena faltantes; nunca rota uno ya impreso). Ambos escriben el CSV en claro **una sola vez** en `storage/app/private/exports/activation-codes/…csv` (privado). Bórralo después de imprimir las tarjetas. |
| Intentos | Tabla `legacy_tag_activation_intents` (`pending → confirmed / failed / expired`, 15 min, recuperable 30 días). |
| Acciones | `StartTagActivation`, `ConfirmTagActivation`, `FailTagActivation` (app/Actions/LegacyTags) — las usan la API y la página web. |
| Config | `config/legacy_tags.php` (`public_base_url`, TTL, recuperación, límites de intentos, enlaces a tiendas de la app). |

Formato del código: `XXXX-XXXX-XXXX`, Crockford base32 (sin I, L, O, U), CSPRNG, ≈60 bits. Al teclearlo se aceptan minúsculas y sin guiones; O se lee como 0 e I/L como 1.

## 2. Reglas que no se rompen

- **Solo se graba nuestra URL.** El servidor la construye; ningún endpoint acepta una URL a grabar. `confirm` exige igualdad exacta (otro host, `http`, parámetros extra u otro código → `WRITTEN_URL_MISMATCH`).
- **Nunca borrar sin consentimiento.** Si el chip ya trae otra información, la app/web muestra qué pasará y pide confirmación explícita antes de escribir con `overwrite`.
- **Nunca bloquear el chip.** Ni la app ni la web llaman a `makeReadOnly`/lock. El bloqueo permanente es una decisión **solo de administración** (fuera de este flujo; no implementado).
- **El UID nunca en claro.** Se guarda HMAC (`nfc_uid_hash`) en la pieza y en el intento.
- **Fuerza bruta:** códigos incorrectos limitados a 5 / 10 min por usuario+IP y 20 / día por usuario (`429 TOO_MANY_REQUESTS`, `Retry-After`). Un `tag_code` sin derecho responde igual que un código incorrecto (no revela si existe).

## 3. Casos y qué hace cada parte

| # | Caso | Qué pasa | Pantalla / acción |
|---|---|---|---|
| 1 | **Pieza nueva (en blanco, NDEF formateado vacío)** | Lectura sin registros → se graba sin pedir nada. | "Grabando… no la muevas" → "Aleja y vuelve a acercar" → confirm. |
| 2 | **NDEF sin formato** | Chrome dispara `readingerror`; la app ve tag no NDEF. Al grabar, el sistema lo formatea. | Pedimos confirmación ("Esta pieza necesita prepararse") y grabamos. |
| 3 | **Chip incompatible** (no NTAG/NDEF) | Escritura falla (`NotSupportedError`). | Mensaje + `fail("incompatible")`; sugerir la app o escribirnos. Soporte: posible reemplazo por defecto (caso 15). |
| 4 | **Protegido contra escritura** | Escritura falla (`NotAllowedError` en web también cubre permiso negado). | Mensaje "revisa NFC y permiso; si sigue, la pieza podría estar protegida" + `fail("write_protected")`. Nunca intentamos desbloquear. |
| 5 | **Datos previos (no FL)** | Lectura trae otros registros. | **Pantalla de consentimiento**: "Lo anterior se borra y no se puede recuperar". Solo con "Sí, reemplazar" se escribe; "No" → `fail("user_declined_overwrite")`, no se toca nada. |
| 6 | **Ya trae nuestra URL exacta** | Doble toque, o se grabó antes y no se confirmó. | Se salta la escritura y se confirma directo. |
| 7 | **Trae otra URL FL (otro código)** | Pieza equivocada o mezclada. | No se sobrescribe. Mensaje "esta pieza ya tiene otro código" + `fail("other_finisher_legacy_url")`. Soporte revisa. |
| 8 | **Ligada a otra cuenta** | `start` → `TAG_ALREADY_ACTIVATED`. | "Ya está activada en otra cuenta. Si es tuya, escríbenos." Soporte: transferencia autorizada (caso 13) o revocación (`ManageTag::revokeOwnership`). |
| 9 | **Error de escritura** (se movió, timeout) | `NetworkError`/`AbortError`. | "Déjala quieta pegada a la parte de atrás" + reintentar con el **mismo intento**. Se registra `fail`. |
| 10 | **Falla de red** | `start` o `confirm` no llegan. | `start`: reintentar. `confirm`: la pieza ya está grabada → botón "Terminar la activación" reintenta solo el confirm (idempotente). |
| 11 | **Grabado pero no confirmado** (app cerrada, batería, red) | El chip ya tiene la URL; el intento quedó `pending/expired`. | Al volver: `start` de nuevo (o reusar el intento) → la lectura encuentra la URL exacta → confirm. Un intento vencido se acepta hasta 30 días si la URL coincide. Mientras tanto, abrir el chip lleva a `/t/{code}` → flujo de activación web normal. |
| 12 | **Doble toque / peticiones concurrentes** | Dos `confirm` del mismo intento o de dos intentos. | Lock por intento + lock por pieza en `ActivateTag` + `lockForUpdate`: una sola activación, una historia, un evento `nfc_bound`. La segunda respuesta es la misma historia. |
| 13 | **Transferencia autorizada** | Regalo / reventa con aviso. | Soporte: `ManageTag::revokeOwnership` (la historia queda del dueño anterior, desligada) → el nuevo dueño activa con el código de la tarjeta (o soporte usa `TransferTags` si la pieza vuelve a inventario). El chip no se regraba: la URL es permanente. |
| 14 | **Devolución** | `ManageTag::return` (propiedad revocada, historia desligada, opcional regreso a stock). | `start` → `TAG_NOT_ACTIVATABLE` (`returned`). Si regresa a stock, el código de activación impreso sigue siendo el mismo: **reponer la tarjeta** con el empaque. |
| 15 | **Reemplazo por defecto** | `ManageTag::replace(old, new)`: la nueva hereda dueño e historia (`replaces_tag_id`); la vieja queda `replaced`. | La nueva llega en blanco y **ya es de la persona**: la graba con `tag_code` (sin tarjeta) → `already_activated` → escribir → confirm. La vieja responde `TAG_NOT_ACTIVATABLE` (`replaced`). |
| 16 | **Extraviada / robada** | Estados `lost` / `stolen` (solo staff: `ManageTag::reportMissing($tag, $staff, $stolen, $motivo)`; reversible con `ManageTag::recover`). | `start`/`confirm` → `TAG_NOT_ACTIVATABLE` (`lost`/`stolen`). Nadie puede ligar otro chip ni reactivarla hasta recuperarla. Si se repone, usar reemplazo (caso 15). |
| 17 | **UID distinto** | La pieza ya está ligada a otro chip, o el chip leído ya pertenece a otra pieza, o el chip de `confirm` no es el de `start`. | `TAG_UID_MISMATCH`: "Este chip no corresponde a la pieza". Soporte revisa (posible mezcla en empaque). |
| 18 | **Teléfono sin NFC web** (iPhone/Safari, escritorio, Firefox) | La página `/activar/nfc` lo detecta. | **No** muestra botones de grabar: "Usa la app Finisher Legacy" (enlaces si `LEGACY_APP_IOS_URL` / `LEGACY_APP_ANDROID_URL` están configurados) + activación manual escribiendo el código en `/activar`. Nunca se ofrece QR. |

## 4. Web NFC (`/activar/nfc`)

- Solo Chrome Android en contexto seguro (`'NDEFReader' in window` + `isSecureContext`). Requiere sesión (`auth`).
- Cada lectura/escritura empieza con un toque de botón (gesto del usuario).
- Pasos: **1 Tu código → 2 Graba tu pieza → 3 Comprobamos → Listo** (enlace a "Completar mi recuerdo").
- Endpoints de sesión (mismo contrato JSON que la API): `POST /activar/nfc/start`, `/activar/nfc/{intent}/confirm`, `/activar/nfc/{intent}/fail`.
- Copia en español sin jerga técnica (no se dice "token", "UID", "NDEF" en pantalla).

## 5. Soporte

- Historial de la pieza (`legacy_tag_events`): `nfc_bound`, `activation_failed` (con plataforma y motivo), `activated`, `reported_missing`, `recovered`.
- Intentos: `legacy_tag_activation_intents` (estado, plataforma, motivo de falla, fechas).
- Confirmar una tarjeta por teléfono: pedir los últimos 4 caracteres del código y compararlos con `activation_code_last4`. Nunca pedir el código completo por canales inseguros.
- Código perdido: verificar compra (pedido / punto de venta) y activar por soporte, o regenerar código **solo** borrando `activation_code_hash` de esa pieza y corriendo `legacy-tags:generate-activation-codes` (queda en el CSV privado).
