# Despliegue

Dos partes: **A** actualizar Laravel en el VPS y **B** preparar las
compilaciones de la app móvil. La configuración inicial del servidor (Nginx,
PHP-FPM, MySQL, Redis, Reverb, Supervisor) está en
[`docs/DEPLOYMENT.md`](DEPLOYMENT.md); el procedimiento detallado con
respaldos y reversión, en [`docs/renovacion/despliegue-vps.md`](renovacion/despliegue-vps.md).
Este documento es la versión corta y vigente.

---

## A. Actualizar Laravel en el VPS

Como el usuario de la app (no root), desde el directorio de la app.

### A.1 Antes

```bash
git rev-parse HEAD > ~/deploy-prev-commit
git fetch --all --prune
git diff --stat HEAD..origin/main -- database/migrations   # migraciones nuevas
mysqldump --single-transaction --routines --triggers -u "$DB_USERNAME" -p "$DB_DATABASE" \
  | gzip > ~/backups/db-$(date +%F-%H%M).sql.gz
cp .env ~/backups/env-$(date +%F-%H%M)
```

Snapshot de `fl-media` en el Synology (Snapshot Replication).

### A.2 Código, dependencias y base de datos

```bash
php artisan down --retry=60 --refresh=15 --secret="<token-aleatorio>"
git checkout main && git pull --ff-only
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci && npm run build
php artisan migrate --force
php artisan i18n:sync --no-translate
php artisan i18n:import-content
php artisan i18n:status            # "en" en 100 %
```

### A.3 Migraciones del 2026-11-20

Todas son **aditivas** (crean tablas o columnas, o agregan un índice/permiso);
ninguna borra datos.

| Migración | Qué hace |
|---|---|
| `2026_11_20_300000_add_shipping_minor_to_orders_table` | Costo de envío congelado en el pedido (`orders.shipping_minor`). |
| `2026_11_20_300000_create_athlete_family_links_table` | Vínculos familiares (`athlete_family_links`). |
| `2026_11_20_300000_create_portrait_designs_table` | Diseños guardados del portarretrato. |
| `2026_11_20_300000_create_training_workouts_table` | Modo entrenamiento (`training_workouts`). |
| `2026_11_20_300100_add_family_audience_to_tracking_shares` | Compartir seguimiento con la familia (`tracking_shares.audience`, `ends_with_session`, `ended_at`). |
| `2026_11_20_300100_link_tracking_sessions_to_training_workouts` | Liga sesiones de seguimiento con entrenamientos. |
| `2026_11_20_400000_add_content_pin_protection` | NIP opcional (`users.content_pin_*`, `pin_protected` en recuerdos, momentos y medios de evento). |
| `2026_11_20_400000_make_legacy_tag_nfc_uid_hash_unique` | Índice único: un chip NFC solo puede pertenecer a una pieza. Si ya hay chips duplicados, la migración **no** crea el índice y registra `make_legacy_tag_nfc_uid_hash_unique: skipped` en el log; revisar antes con `SELECT nfc_uid_hash, COUNT(*) FROM legacy_tags WHERE nfc_uid_hash IS NOT NULL GROUP BY nfc_uid_hash HAVING COUNT(*) > 1;` |
| `2026_11_20_600000_create_payments_refund_permission` | Permiso `payments.refund` (reembolsos desde el admin). |

Variables nuevas de ese día (opcionales, con valor por defecto):
`FINISHER_SHIPPING_FEE_MINOR` (0), `FINISHER_SHIPPING_FREE_OVER_MINOR`
(vacío = nunca gratis), `TRAINING_WORKOUT_STALE_HOURS` (12).

### A.4 Cachés y procesos

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan storage:link              # idempotente
php artisan horizon:terminate         # Supervisor relanza Horizon con el código nuevo
php artisan queue:restart             # si hay workers queue:work además de Horizon
php artisan reverb:restart
sudo systemctl reload php8.4-fpm      # limpia OPcache
```

Procesos permanentes (Supervisor): `php artisan horizon` (con
`stopwaitsecs=960` para que terminen los jobs de medios) y
`php artisan reverb:start`. Cron: `* * * * * php artisan schedule:run`
(+ `horizon:snapshot` cada 5 min para métricas). Sin Redis:
`php artisan queue:work --queue=realtime,default,media --tries=3 --timeout=900`.

### A.5 Verificación

```bash
php artisan about | head -30          # production, debug off, caches on
php artisan migrate:status | tail -12 # las del 2026_11_20 en "Ran"
php artisan storage:health
php artisan schedule:list
curl -fsS https://finisherlegacy.com/up
curl -fsS https://finisherlegacy.com/api/v1/health
curl -fsS https://finisherlegacy.com/.well-known/apple-app-site-association   # 404 hasta definir APPLE_TEAM_ID
curl -fsS https://finisherlegacy.com/.well-known/assetlinks.json              # 404 hasta definir ANDROID_SHA256_CERT_FINGERPRINTS
php artisan up
```

Manual: inicio, `/tienda`, un `/t/{código}` nuevo y uno activado, login,
subir una foto a un recuerdo de prueba, `/admin`, Horizon sin jobs fallidos,
`storage/logs/laravel-*.log` y `payments-*.log` sin errores nuevos.

Reversión: `docs/renovacion/despliegue-vps.md` §7.

---

## B. Compilaciones de la app móvil

Repositorio `FinisherLegacyMobile`. Identidad fija (no cambiar):
`com.finisherlegacy.app` (iOS y Android), esquema `finisherlegacy`, proyecto
EAS ya configurado (`extra.eas.projectId` en `app.json`).

### B.1 Perfiles (`eas.json`)

| Perfil | Uso | Salida |
|---|---|---|
| `development` | Dev client para desarrollo | APK / build de dispositivo iOS; usa tu `.env.local` |
| `preview` | Pruebas internas contra producción | APK / ad-hoc iOS |
| `production` | Tiendas | AAB / IPA, `autoIncrement` del build number y versionCode (remoto en EAS) |

`EXPO_PUBLIC_API_URL=https://finisherlegacy.com/api/v1` y
`EXPO_PUBLIC_WEB_URL=https://finisherlegacy.com` en `preview` y `production`.
La versión visible (`version` en `app.json`, hoy `1.0.0`) se sube a mano en
cada release.

### B.2 Antes de compilar

```bash
npm ci
npx tsc --noEmit && npx eslint . && npx jest
npx expo-doctor
npx expo config --type public          # revisa plugins, permisos, entitlements
npx expo export --platform android --output-dir <carpeta-temporal>   # el bundle compila
eas whoami                             # sesión de la cuenta dueña del proyecto
```

- `expo-doctor` (2026-11-20) solo marca 5 parches de SDK pendientes (`expo`,
  `expo-constants`, `expo-linking`, `expo-router`, `expo-task-manager`).
  Aplícalos con `npx expo install --fix` **con Metro detenido**, vuelve a
  correr las pruebas y haz commit de `package.json`/`package-lock.json` antes
  de la build de tienda.
- `android/` e `ios/` no están en git (Continuous Native Generation): EAS
  ejecuta `expo prebuild` en cada build desde `app.json` + `app.config.ts`.
  La carpeta `android/` local puede estar desfasada (p. ej. su manifiesto solo
  tiene `/n/` y `/t/`); para regenerarla en tu equipo usa
  `npx expo prebuild --platform android --no-install` o `npx expo run:android`
  con Metro detenido.

Secretos de compilación (nunca en el repo):

- FCM para push en Android: `eas env:create --environment production --name GOOGLE_SERVICES_JSON --type file --value ./secrets/google-services.json` (lo lee `app.config.ts`).
- APNs: `eas credentials -p ios` → Push Notifications key.
- `eas.json › submit.production`: reemplazar `REEMPLAZAR_APPLE_TEAM_ID` y
  `REEMPLAZAR_ASC_APP_ID`; colocar `./secrets/google-play-service-account.json`.

### B.3 Compilar y enviar (requiere aprobación explícita: consume cuota de EAS)

```bash
eas build -p android --profile preview          # APK interno
eas build -p android --profile production       # AAB para Play
eas build -p ios --profile production           # IPA para TestFlight / App Store
eas submit -p android --profile production      # pista internal, borrador
eas submit -p ios --profile production
```

El **primer AAB** se sube a mano en Play Console. Cualquier cambio en
`app.json`/`app.config.ts` (permisos, plugins, entitlements) exige una
compilación nativa nueva; una actualización OTA no lo aplica.

### B.4 Después de instalar una build nueva

- Enlaces universales: con `APPLE_TEAM_ID` y `ANDROID_SHA256_CERT_FINGERPRINTS`
  ya definidos en el servidor, tocar un tag debe abrir la app.
- Push: registrar el dispositivo (Ajustes › Notificaciones) y usar «Enviarme una
  prueba» en Admin › Campañas push.
- Compras dentro de la app: probar con cuentas sandbox / de licencias
  (`docs/renovacion/compras-dentro-de-app.md` §E).

Publicación en tiendas: [PUBLICACION_TIENDAS.md](PUBLICACION_TIENDAS.md).

## C. Desarrollo local (nota)

- Usa un puerto propio para este proyecto: en este equipo `127.0.0.1:8000`
  lo ocupa otro proyecto. Por ejemplo `php artisan serve --port=8020` y
  `APP_URL=http://localhost:8020` en tu `.env` (no se versiona). `APP_URL`
  arma los enlaces de correos y de las páginas, así que debe coincidir con la
  dirección real. Los Tags NFC usan `LEGACY_TAG_PUBLIC_URL` (por defecto
  `https://finisherlegacy.com`, no `APP_URL`).
- Con Vite (`npm run dev`) se crea `public/hot`; bórralo (o detén Vite) antes
  de `npm run build` y nunca lo subas al repo.
- Las pruebas de navegador (Playwright) que se usaron en el QA web no viven en
  el repo; ver `docs/CIERRE_FINAL.md`.
