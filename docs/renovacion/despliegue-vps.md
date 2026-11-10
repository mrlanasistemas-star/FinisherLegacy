# Despliegue y actualización en el VPS

Procedimiento para actualizar producción. Complementa `docs/DEPLOYMENT.md`
(configuración inicial de infraestructura). Ejecutar como el usuario de la
app (p. ej. `deploy`), no como root, desde el directorio de la app.

## 0. Antes de empezar

- [ ] La rama a desplegar pasó `php artisan test`, `pint --test` y `phpstan` en CI.
- [ ] Hay una ventana acordada y alguien que pueda revertir.
- [ ] Anotar el commit actual: `git rev-parse HEAD > ~/deploy-prev-commit`.
- [ ] Revisar migraciones nuevas: `git diff --stat HEAD..origin/main -- database/migrations`. Si alguna es destructiva o larga, planearla aparte.

## 1. Respaldos primero

```bash
mysqldump --single-transaction --routines --triggers -u "$DB_USERNAME" -p "$DB_DATABASE" \
  | gzip > ~/backups/db-$(date +%F-%H%M).sql.gz
cp .env ~/backups/env-$(date +%F-%H%M)
```

En el Synology: snapshot manual de `fl-media` (Snapshot Replication →
"Tomar snapshot"). Verificar que el dump no está vacío (`zcat … | head`).

## 2. Mantenimiento

```bash
php artisan down --retry=60 --refresh=15 --secret="<token-aleatorio>"
```

(El `--secret` permite entrar con `/<token>` para revisar antes de abrir.)

## 3. Código y dependencias

```bash
git fetch --all --prune
git checkout main && git pull --ff-only
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build
```

## 4. Base de datos

```bash
php artisan migrate --force
```

## 5. Cachés y procesos

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan storage:link        # idempotente
php artisan queue:restart       # workers toman el código nuevo
php artisan horizon:terminate   # si se usa Horizon (Supervisor lo relanza)
php artisan reverb:restart      # si se usa Reverb
sudo systemctl reload php8.4-fpm   # limpia OPcache
```

## 6. Verificación (smoke tests)

```bash
php artisan about | head -30          # production, debug off, caches on
php artisan storage:health            # NAS montado y con espacio
php artisan schedule:list | grep storage:health
curl -fsS https://finisherlegacy.com/up
curl -fsS https://finisherlegacy.com/api/v1/health
curl -fsSI https://finisherlegacy.com/.well-known/apple-app-site-association
curl -fsSI https://finisherlegacy.com/.well-known/assetlinks.json
```

Manual (con el `--secret`): inicio, `/tienda`, un `/t/{code}` de prueba
(nuevo y activado), login, subir una foto a una historia de prueba y ver que
pasa a "lista", panel admin. Revisar `storage/logs/laravel-*.log` y
`payments-*.log` sin errores nuevos.

```bash
php artisan up
```

## 7. Reversión

Si algo falla y no se arregla en minutos:

```bash
php artisan down
git checkout "$(cat ~/deploy-prev-commit)"
composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build
# Solo si la migración nueva es reversible y no se escribieron datos que se perderían:
php artisan migrate:rollback --step=<n> --force
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
sudo systemctl reload php8.4-fpm
php artisan up
```

Si una migración no es reversible: restaurar el dump del paso 1 (detiene el
sitio; se pierde lo escrito desde el respaldo — documentar el incidente).
Los archivos del NAS no se tocan en un despliegue; si hiciera falta, restaurar
del snapshot del paso 1.

## 8. Después

- Revisar Horizon/colas (sin jobs fallidos nuevos) y `media_files` en `failed`.
- Registrar fecha, commit desplegado y resultado.
- Borrar respaldos locales viejos (mantener los últimos 7; los oficiales viven en Hyper Backup).
