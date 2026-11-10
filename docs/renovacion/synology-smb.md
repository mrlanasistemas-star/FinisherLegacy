# Synology por SMB 3 sobre Tailscale

Complementa `docs/SYNOLOGY.md` (pipeline de medios, rutas, entrega firmada).
Este documento fija el montaje **SMB 3** en el VPS, la cuenta de servicio, la
estructura de carpetas, respaldos y monitoreo. Nada de esto se ha probado
contra un NAS real todavía.

## 1. Cómo usa Laravel el almacenamiento (estado del código)

| Disco (`config/filesystems.php`) | Raíz (env) | Uso |
|---|---|---|
| `synology_media` | `FINISHER_MEDIA_ROOT` (p. ej. `/mnt/fl-media`) | Medios procesados (historias, eventos, momentos). Privado, sin URL directa. |
| `media_tmp` | `FINISHER_MEDIA_TMP_ROOT` | Subidas en curso. **Debe ser disco local del VPS**, nunca el NAS. |
| `athlete_media`, `event_photo_originals`, `support_audio`, `product_media` | `*_DISK_ROOT` | Discos heredados con raíz por env. |

- Selector del disco de medios: `finisher.media_disk` =
  `FINISHER_MEDIA_STORAGE_DISK` o, si está vacío, `synology_media` cuando
  `FINISHER_MEDIA_ROOT` tiene valor (si no, `local`). Se reutilizan estos
  nombres existentes en lugar de crear `MEDIA_DISK`/`MEDIA_DISK_ROOT` nuevos,
  para no tener dos variables con el mismo propósito.
- No hay IPs, usuarios ni rutas del NAS en el repo
  (`tests/Feature/Media/NoHardcodedMediaHostsTest.php` lo verifica).

### Comando de salud

```bash
php artisan storage:health            # tabla
php artisan storage:health --json     # para monitoreo
php artisan storage:health --disk=synology_media
```

Por cada disco: la raíz existe (se revisa **antes** de instanciar el disco,
porque el adaptador local de Flysystem crea la carpeta si no existe y eso
ocultaría un montaje caído), archivo centinela, punto de montaje (opcional),
escritura, escribir/leer/borrar un archivo de prueba en `.health/`, espacio
libre (`disk_free_space`). Sale con código ≠ 0 y registra
`storage.health_failed` en el log si algo falla. Programado cada 10 minutos
en `routes/console.php`.

| Variable | Default | Uso |
|---|---|---|
| `STORAGE_HEALTH_DISKS` | vacío = disco de medios + temporal | Lista separada por comas |
| `STORAGE_HEALTH_MIN_FREE_MB` | `2048` | Falla si hay menos espacio libre |
| `FINISHER_MEDIA_SENTINEL` | vacío | Archivo que debe existir en la raíz **del NAS** (p. ej. `.fl-media-mounted`) |
| `FINISHER_MEDIA_REQUIRE_MOUNT` | `false` | En Linux exige que la raíz sea un punto de montaje |

En producción usar `FINISHER_MEDIA_SENTINEL=.fl-media-mounted` y
`FINISHER_MEDIA_REQUIRE_MOUNT=true`: si el share no está montado, el punto de
montaje es una carpeta vacía del VPS y sin estas dos protecciones los archivos
caerían en el disco del VPS sin que nadie se entere.

No se añadió endpoint HTTP: el único controlador de salud existente
(`Api\V1\HealthController`) es público y deliberadamente mínimo; exponer
estado del almacenamiento ahí no corresponde. Monitoreo vía comando/log.

### NAS fuera de línea: comportamiento actual de las subidas

Revisado en `App\Services\Media\MediaStorage`, `App\Jobs\Media\TransferMediaFile`
y `App\Actions\LegacyTags\ManageStoryMedia` (no modificados):

- La petición **no toca el NAS**: valida, copia al disco temporal local y crea
  la fila `media_files` en `processing`. La cola transfiere al NAS
  (`TransferMediaFile`: 4 intentos, espera 30 s → 2 min → 10 min). Si el NAS
  sigue caído, la fila queda `failed` con el archivo fuente conservado y
  `php artisan media:retry-failed` la reencola. No se pierde el archivo.

Brechas encontradas (para el dueño de esos archivos):

1. **Fila huérfana posible** en `ManageStoryMedia::add()`: `accept()` crea la
   fila `media_files` y el archivo temporal **fuera** de la transacción que
   inserta el pivote `legacy_story_media`. Si esa transacción falla, quedan
   una fila `processing` sin dueño y su archivo en `media_tmp`, sin limpieza.
   Arreglo sugerido: `try { … } catch { $storage->delete($file); throw; }`
   alrededor de la transacción, o una tarea que borre filas `processing` sin
   `mediable` de más de 24 h.
2. **Error no controlado si falla el disco temporal**: `media_tmp` tiene
   `throw => true`, así que `putFileAs` lanza excepción (500) y la
   comprobación `is_string($sourcePath)` de `accept()` nunca se ejecuta. Debe
   capturarse `UnableToWriteFile` y devolver el `ValidationException` amigable.
3. **Sin aviso al usuario** cuando la transferencia falla: queda `failed` y
   solo se ve en logs. `media:retry-failed` **no está programado**; si se
   quiere reintento automático, programarlo (p. ej. cada hora) tras confirmar
   que no reencola indefinidamente archivos rotos.
4. **Crecimiento del temporal**: con el NAS caído horas, las subidas se
   acumulan en el disco del VPS. `storage:health` vigila el espacio libre de
   `media_tmp` también.
5. Las lecturas (`MediaFileController`) con el NAS caído devuelven error del
   sistema de archivos; con `x-systemd.automount` y un servidor inalcanzable
   la primera lectura puede bloquearse hasta el timeout de CIFS. Mantener
   `echo_interval`/timeouts cortos (abajo).

## 2. Red

- Tailscale en el VPS y en el Synology (paquete oficial), misma tailnet.
- ACL de Tailscale: solo `tag:fl-vps → tag:fl-nas:445`. Nada más.
- **Nunca** exponer SMB (445/139) a internet: sin reenvío de puertos, sin
  QuickConnect/DDNS para este share, firewall del Synology permitiendo 445
  solo desde la interfaz/rango de Tailscale.
- Usar el nombre MagicDNS del NAS en el montaje; no IPs en el repo.

## 3. Synology

1. Panel de control → Servicios de archivos → SMB: **SMB mínimo = SMB3**,
   máximo SMB3; activar *cifrado de transporte SMB* ("obligatorio" si la
   versión de DSM lo permite); desactivar SMB1/NTLMv1.
2. Carpeta compartida `fl-media` (Btrfs, con *checksum de datos*), sin acceso
   de invitado, sin papelera visible por SMB si no se necesita.
3. Usuario de servicio `svc-fl-media`:
   - lectura/escritura **solo** en `fl-media`; sin acceso al resto;
   - sin grupo `administrators`, sin acceso a DSM, ni SFTP/FTP/WebDAV/apps;
   - contraseña larga aleatoria; 2FA no aplica a SMB, por eso el aislamiento.
4. Crear el centinela una sola vez **en el NAS**: `.fl-media-mounted`.

## 4. Montaje en el VPS (cifs-utils)

```bash
sudo apt install cifs-utils
sudo mkdir -p /mnt/fl-media
sudo chattr +i /mnt/fl-media          # la carpeta vacía no acepta escrituras si el share no está montado
```

Credenciales (solo en el servidor, nunca en el repo):

```bash
sudo install -m 600 -o root -g root /dev/null /etc/fl-media.cred
# /etc/fl-media.cred
username=svc-fl-media
password=<secreto>
domain=WORKGROUP
```

`/etc/fstab` (una línea):

```
//<nas>.<tailnet>.ts.net/fl-media  /mnt/fl-media  cifs  credentials=/etc/fl-media.cred,vers=3.1.1,seal,uid=www-data,gid=www-data,file_mode=0640,dir_mode=0750,noexec,nosuid,nodev,_netdev,nofail,x-systemd.automount,x-systemd.idle-timeout=0,x-systemd.mount-timeout=30,x-systemd.after=tailscaled.service,x-systemd.requires=tailscaled.service,echo_interval=15,actimeo=1  0  0
```

- `vers=3.1.1` + `seal`: SMB 3.1.1 con cifrado AES (además del túnel WireGuard).
- `uid`/`gid`: el usuario de PHP-FPM **y** del worker de colas (deben ser el
  mismo o compartir grupo). Ajustar si no es `www-data`.
- `noexec,nosuid,nodev`: nada del NAS se ejecuta en el VPS.
- `_netdev,nofail,x-systemd.automount`: no bloquea el arranque si el NAS no
  responde; se monta al primer acceso.

```bash
sudo systemctl daemon-reload && sudo mount -a
sudo -u www-data touch /mnt/fl-media/.write-test && sudo -u www-data rm /mnt/fl-media/.write-test
php artisan storage:health
```

`.env` del servidor:

```
FINISHER_MEDIA_ROOT=/mnt/fl-media
FINISHER_MEDIA_TMP_ROOT=/var/lib/finisher/media-tmp     # disco local
FINISHER_MEDIA_SENTINEL=.fl-media-mounted
FINISHER_MEDIA_REQUIRE_MOUNT=true
FINISHER_MEDIA_QUEUE=media
STORAGE_HEALTH_MIN_FREE_MB=20480
```

## 5. Estructura de carpetas (ids opacos)

Objetivo para la nueva estrategia (identificadores UUID, nunca nombres,
correos ni números de tag):

```
fl-media/
  .fl-media-mounted
  users/{user_uuid}/            # medios propios del usuario sin historia
  stories/{story_uuid}/photos/
  stories/{story_uuid}/audio/
  stories/{story_uuid}/certificate/
  portraits/{story_uuid}/       # plantillas de impresión 20×25 generadas
  products/{product_uuid}/
  tmp/                          # NO usar para subidas en curso (va en disco local)
```

Estado actual del código (`MediaPathResolver`, ver `docs/SYNOLOGY.md` §4):
`athletes/{uuid}--{slug}/…`, `products/{uuid}/…`, `users/{id}/…` — el slug
y el id numérico de usuario **no** son opacos. Las historias Legacy se guardan
hoy bajo `forUser($user, 'legacy')`. Migrar al esquema de arriba es trabajo
del dueño de `MediaPathResolver`; las rutas guardadas en BD son relativas, así
que un cambio aplica solo a archivos nuevos (los existentes siguen sirviendo).

## 6. Respaldos

- **Snapshots Btrfs** de `fl-media`: cada hora, retención 7 días + diarias 30
  días; snapshots inmutables si el DSM lo soporta (protección ransomware desde
  el VPS: la cuenta `svc-fl-media` no puede borrar snapshots).
- **Hyper Backup** nocturno a un destino externo (Synology C2, otro NAS o
  disco rotado), cifrado del lado del cliente, con verificación de integridad
  mensual.
- **BD y medios juntos**: el dump de MySQL de la misma noche
  (`media_files` contiene las rutas). Restaurar uno sin el otro deja
  referencias rotas.
- Prueba de restauración trimestral: restaurar una carpeta `stories/{uuid}` y
  comparar con `media_files.checksum` (sha256).
- No respaldar `media_tmp`.

## 7. Monitoreo

- `storage:health` cada 10 min (scheduler). Alertar sobre la línea de log
  `storage.health_failed` (canal JSON) o ejecutar `storage:health --json` desde
  el monitor externo y alertar si `ok=false` / exit ≠ 0.
- Synology: notificaciones de DSM por correo (estado del volumen, SMART,
  espacio < 15 %, fallos de Hyper Backup).
- Tailscale: alerta si el nodo del NAS se desconecta (admin console).
- Métrica de negocio: filas `media_files` en `failed` > 0 durante más de 1 h →
  revisar y `php artisan media:retry-failed`.
