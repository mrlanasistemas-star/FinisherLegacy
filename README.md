# Finisher Legacy — web, panel de administración y API

Finisher Legacy guarda la historia deportiva de cada atleta: carreras,
medallas, fotos, videos, mensajes de voz y recuerdos. Esa historia se conecta
con un objeto físico, el **Finisher Legacy Tag NFC**: un tag terminado de
fábrica, sin QR, que el atleta **activa en digital** (con su teléfono o en la
web) y que, al acercarle un celular, abre su recuerdo en `/t/{código}`.

Este repositorio contiene **todo el servidor**:

- **Sitio público y área del atleta** (Inertia v3 + Vue 3 + TypeScript): tienda,
  colecciones, activación del tag, recuerdos, comunidad, entrenamientos,
  familia, seguimiento en vivo, fotos de eventos.
- **Panel de administración** (`/admin`): ventas, pedidos, pagos y reembolsos,
  catálogo, inventario y unidades NFC, contenido, moderación, campañas push,
  límites y tarifas, idiomas, roles.
- **API REST `/api/v1`** (Sanctum) que consume la app móvil.

La app móvil (Expo SDK 57, Android e iOS) vive en un repositorio aparte:
**[FinisherLegacyMobile](https://github.com/mrlanasistemas-star/FinisherLegacyMobile)**
(`C:\wamp64\www\FinisherLegacyMobile` en la máquina de desarrollo). Laravel es
la única fuente de verdad para ambos clientes.

## Documentación

| Documento | Contenido |
|---|---|
| [docs/FUNCIONALIDADES.md](docs/FUNCIONALIDADES.md) | Inventario completo de funciones (web, admin, API, app). |
| [docs/ARQUITECTURA.md](docs/ARQUITECTURA.md) | Backend, app, API, almacenamiento NAS, tiempo real, colas, pagos, push, NFC, idiomas. |
| [docs/DESPLIEGUE.md](docs/DESPLIEGUE.md) | Actualizar el VPS y preparar las compilaciones de la app. |
| [docs/PUBLICACION_TIENDAS.md](docs/PUBLICACION_TIENDAS.md) | Google Play y App Store (enlaza a `docs/store/` del repo móvil). |
| [docs/PENDIENTES_EXTERNOS.md](docs/PENDIENTES_EXTERNOS.md) | Lo que no se resuelve con código: cuentas, llaves, decisiones, pruebas en hardware. |
| [docs/CIERRE_FINAL.md](docs/CIERRE_FINAL.md) | Estado de cierre: funciones, pruebas, migraciones, commits. |
| `docs/renovacion/*` | Detalle por módulo: pagos, compras dentro de la app, NFC, entrenamientos, familia, NIP, portarretrato, notificaciones, idiomas, Synology… |
| `docs/api/v1.md`, `docs/api/openapi.yaml` | Contrato de la API. |
| `docs/historico/` | Documentos de productos retirados (placas metálicas, Device API, Stripe en la app). **No son decisiones vigentes.** |

## Stack

PHP 8.4 · Laravel 13 · MySQL 8 · Redis + Horizon · Laravel Reverb · Fortify
(web) · Sanctum (app) · Inertia v3 · Vue 3 · TypeScript · Tailwind CSS v4 ·
Wayfinder · spatie/laravel-permission · spatie/laravel-activitylog · OpenPay
(`openpay/sdk`) · Intervention Image · ffmpeg · Pest.

## Instalación local

Requisitos: PHP 8.4 (con `intl`, `gd` o `imagick`, `pdo_mysql`), Composer,
Node.js LTS, MySQL 8 (o SQLite para pruebas). Redis es opcional en local.

```bash
composer install
cp .env.example .env
php artisan key:generate
# configurar DB_* en .env
php artisan migrate
npm install
npm run build          # o `npm run dev` para trabajar con Vite
```

`composer run setup` hace los pasos anteriores de una vez.

## Ejecutar

```bash
composer run dev       # servidor + cola (queue:listen) + Vite, en paralelo
```

Por separado: `php artisan serve`, `php artisan queue:listen`, `npm run dev`,
`php artisan reverb:start` (tiempo real, opcional) y
`php artisan schedule:work` (tareas programadas).

## Configuración

Todo va en `.env` (nunca en el repo). Lo mínimo para desarrollar es `APP_*` y
`DB_*`. El resto tiene valores por defecto seguros: sin llaves, los pagos,
las compras dentro de la app, el push y el traductor quedan **desactivados**
(responden "no configurado"; nunca simulan éxito). Variables por área:

| Área | Variables (definidas en `config/*.php`) |
|---|---|
| Pagos OpenPay | `OPENPAY_MERCHANT_ID`, `OPENPAY_PUBLIC_KEY`, `OPENPAY_PRIVATE_KEY`, `OPENPAY_PRODUCTION_MODE`, `OPENPAY_WEBHOOK_USER`, `OPENPAY_WEBHOOK_PASSWORD` (también editables en Admin › Configuración › Pagos) |
| Envío | `FINISHER_SHIPPING_FEE_MINOR`, `FINISHER_SHIPPING_FREE_OVER_MINOR` |
| Compras dentro de la app | `APPLE_IAP_*`, `GOOGLE_PLAY_*` (`config/iap.php`) |
| Enlaces universales | `APPLE_TEAM_ID`, `ANDROID_SHA256_CERT_FINGERPRINTS` (`config/nfc.php`) |
| Tag NFC | `LEGACY_TAG_PUBLIC_URL` y `LEGACY_TAG_*` (`config/legacy_tags.php`) |
| Push | `PUSH_PROVIDER=expo`, `EXPO_ACCESS_TOKEN` (`config/services.php`) |
| Medios / NAS | `FINISHER_MEDIA_*`, `FFMPEG_BINARY`, `FFPROBE_BINARY` |
| Tiempo real | `BROADCAST_CONNECTION`, `REVERB_*`, `VITE_REVERB_*` |
| Traductor | `I18N_TRANSLATOR`, `ANTHROPIC_API_KEY` o `DEEPL_API_KEY` |

Lista completa con origen de cada valor: [docs/PENDIENTES_EXTERNOS.md](docs/PENDIENTES_EXTERNOS.md)
y [docs/DESPLIEGUE.md](docs/DESPLIEGUE.md).

## Comandos esenciales

```bash
php artisan test --compact                  # pruebas (Pest)
vendor/bin/pint --test                      # estilo PHP (`vendor/bin/pint` corrige)
vendor/bin/phpstan analyse                  # análisis estático (phpstan.neon + baseline)
npx vue-tsc --noEmit                        # tipos del frontend
npx eslint resources/js                     # lint del frontend
php artisan route:list --except-vendor      # rutas
php artisan wayfinder:generate              # funciones tipadas de rutas para Vue

php artisan storage:health                  # NAS montado, escribible y con espacio
php artisan i18n:sync --no-translate        # sincroniza traducciones de lang/
php artisan i18n:import-content             # contenido del catálogo en inglés
php artisan i18n:status                     # porcentaje traducido
php artisan legacy-tags:generate-activation-codes --lot=<id>   # códigos de activación faltantes
php artisan catalog:publish-portrait-frame  # publica el portarretrato (borrador)
php artisan quotas:seed-defaults            # límites iniciales de fotos/videos/audio
php artisan app:review-account <correo>     # cuenta para revisores de las tiendas
php artisan media:retry-failed --pending-transcode
```

Tareas programadas (`routes/console.php`; requieren `* * * * * php artisan
schedule:run`): expirar pedidos sin pagar, limpiar tokens, salud del NAS,
campañas push, recibos de Expo, stock bajo, avisos de planes por vencer,
alerta de pasarela sin configurar y cierre de entrenamientos abandonados.

## Pruebas en Windows

Ver `docs/dev-local-testing.md` (memoria de PHP y Xdebug para `php artisan test`).
