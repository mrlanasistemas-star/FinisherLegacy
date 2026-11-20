# Protección opcional con NIP

El atleta puede proteger con un NIP (4–6 números) sus recuerdos (`legacy_stories`), momentos (`legacy_moments`) y fotos/videos de evento (`athlete_event_media`). Es **opcional**: nadie está obligado a crearlo. Código: `App\Services\Privacy\ContentPin` (todo el modelo de seguridad está comentado ahí).

## Qué se guarda

| Dato | Dónde | Notas |
|---|---|---|
| NIP | `users.content_pin_hash` | HMAC-SHA256 con `APP_KEY` y luego `Hash::make` (bcrypt/argon). Nunca en texto plano, nunca en logs, nunca en `flash` (`bootstrap/app.php` → `dontFlash`), oculto en `User` (`#[Hidden]`). Rotar `APP_KEY` invalida los NIP: el atleta usa la recuperación. |
| Intentos / bloqueo | `content_pin_failed_attempts`, `content_pin_locked_until` | En la fila del usuario, con `lockForUpdate` (sin carreras entre dispositivos). |
| Marca por elemento | `pin_protected` (boolean, índice) en las tres tablas | Migración aditiva `2026_11_20_400000_add_content_pin_protection`. |
| Permiso de desbloqueo | caché (`content-pin:grant:{sha256}`) o sesión web | 10 minutos, ligado a usuario + token Sanctum + "época" del NIP. |

La app **nunca** guarda el NIP; el token de desbloqueo vive sólo en memoria y se descarta al pasar a segundo plano.

## Reglas

- **Proteger ⇒ privado.** Al guardar un elemento con `pin_protected = true` el modelo fuerza su visibilidad a privada (`App\Models\Concerns\PinProtection`), sin importar qué pantalla lo guarde. Así, feeds, perfiles públicos, apertura del tag, enlaces "Para mamá" y URLs públicas de medios lo ocultan solos; además las políticas lo revisan explícitamente (defensa en profundidad). Quitar la protección **no** lo vuelve público: sigue privado hasta que el atleta cambie su visibilidad.
- **Sólo el dueño desbloquea.** Otros usuarios nunca lo ven (404 / no aparece). El dueño sin desbloqueo recibe marcadores bloqueados (`locked: true`, sin texto ni URLs) y las acciones de lectura/edición del contenido responden `423 CONTENT_LOCKED`.
- **Marcar no pide NIP** (sólo reduce exposición); **desmarcar sí** pide desbloqueo.
- **Desactivar el NIP** quita la marca de todos los elementos (siguen privados) y revoca todos los permisos.

## Intentos fallidos

5 intentos; a partir del 5.º fallo el NIP se bloquea 1, 2, 4, 8 … hasta 60 minutos por cada fallo adicional. Mientras está bloqueado ni siquiera se compara el hash (no hay oráculo). Un NIP correcto reinicia el contador. Las rutas tienen además límites por usuario (`throttle`). La app muestra la cuenta regresiva con `retry_after`.

## Recuperación (NIP olvidado)

Se eligió la opción más simple y segura:

1. **Contraseña de la cuenta** (re-autenticación), o
2. **Código de 6 dígitos por correo** (para cuentas creadas con Google/Apple que no conocen su contraseña): se guarda sólo su hash en caché 15 minutos, 5 intentos, un solo uso; envío limitado a 3 cada 10 minutos.

Tras restablecer: los elementos siguen protegidos, todos los permisos se revocan y se envía un correo de aviso ("Tu NIP de Finisher Legacy cambió").

## Medios

Los archivos nunca salen por URL directa del disco/NAS (`MediaFileController`, `AthleteEventMediaFileController`). Para un elemento protegido:

- `MediaFile::url()` devuelve `null` si el dueño no desbloqueó en esta petición.
- Desbloqueado: URL firmada de **10 minutos** con la marca firmada `pin=1`. Los controladores **sólo** aceptan para estos archivos firmas con `pin=1`, así que cualquier URL emitida antes de proteger deja de funcionar al instante (las públicas de larga duración también: el archivo ya no es público).
- `MediaFilePolicy`: sin atajo de "quien subió" ni de staff (`media.manage`).
- Excepción heredada: adjuntos de momentos antiguos guardados en el disco `public` (anteriores al pipeline) siguen teniendo su URL pública de siempre si alguien la conserva; ya no se publican mientras el momento esté bloqueado.
- Fotos compradas importadas a un recuerdo conservan la política de su propia venta; el recuerdo bloqueado no las lista.

## Administración y moderación

El staff no tiene acceso a contenido protegido: ni medios (`MediaFilePolicy`) ni texto (moderación de comunidad muestra "Contenido protegido con NIP"). **Única excepción:** un momento que ya tenía un reporte abierto o en revisión antes de protegerse sigue siendo revisable por moderadores (`ContentPin::underModeration`). No se pueden crear reportes nuevos sobre él porque nadie más puede verlo.

## Web

La gestión (crear, cambiar, desactivar, recuperar) está en la app. En la web, `/settings/content-pin` desbloquea por 10 minutos con un permiso en la sesión; `EnsureContentPinUnlocked` envía ahí al dueño cuando abre un recuerdo protegido (`/legacy/{id}`, `/mi-legacy/{id}/…`). Otras pantallas web del dueño muestran los medios protegidos sin URL hasta desbloquear.

## API

Ver `docs/api/v1.md` §NIP de contenido.
