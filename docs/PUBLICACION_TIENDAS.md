# Publicación en tiendas (Google Play y App Store)

**Fuente única de las fichas, formularios y notas de revisión:**
`FinisherLegacyMobile/docs/store/` ([en GitHub](https://github.com/mrlanasistemas-star/FinisherLegacyMobile/tree/main/docs/store)).
Este documento solo ordena el proceso y dice qué toca al servidor; no repite
el contenido de esas guías.

| Guía (repo móvil) | Para qué |
|---|---|
| `docs/store/README.md` | Índice, qué hace cada clave de `app.json` / `app.config.ts` / `eas.json`, URLs públicas, estado de cumplimiento. |
| `docs/store/google-play.md` | Ficha, clasificación, Data safety, servicios en primer plano, pruebas cerradas, productos integrados. |
| `docs/store/app-store.md` | App Store Connect, App Privacy, exportación, TestFlight, compras dentro de la app. |
| `docs/store/review-notes-es-en.md` | Notas para el revisor (es/en) y guion del video de demostración. |
| `docs/store/screenshots.md` | Qué capturar y cómo. |
| `docs/store/pendientes-externos.md` | Lista operativa de lo que solo el dueño puede hacer. |

Cuentas, llaves y contratos con su URL y dónde va cada dato:
[PENDIENTES_EXTERNOS.md](PENDIENTES_EXTERNOS.md).

## 1. Requisitos en el servidor (antes del primer envío)

1. Web desplegada con la versión vigente ([DESPLIEGUE.md](DESPLIEGUE.md) §A) y
   accesibles sin sesión: `/privacy`, `/terms`, `/eliminar-cuenta`,
   `/contact`.
2. OpenPay en producción (las tiendas rechazan una app cuyo pago no funciona).
3. `APPLE_TEAM_ID` y `ANDROID_SHA256_CERT_FINGERPRINTS` en `.env` →
   `/.well-known/apple-app-site-association` y `/.well-known/assetlinks.json`
   responden JSON.
4. `PUSH_PROVIDER=expo`.
5. Credenciales de compras dentro de la app (`APPLE_IAP_*`, `GOOGLE_PLAY_*`)
   y los IDs de producto en **Admin › Ventas › Límites y tarifas**.
6. Cuenta de revisión: `php artisan app:review-account revision@finisherlegacy.com`
   (con un recuerdo «[DEMO]» activado para que el revisor llegue a
   «Ampliar espacio»).

## 2. Google Play

1. Crear la app en Play Console y completar ficha, clasificación de
   contenido, público objetivo, Data safety, declaraciones de ubicación y
   servicios en primer plano (`location`, `mediaPlayback`) con el video — todo
   en `docs/store/google-play.md`.
2. `eas build -p android --profile production` → **subir el primer AAB a
   mano** en Pruebas › Prueba interna.
3. Copiar el SHA-256 de Play App Signing al servidor
   (`ANDROID_SHA256_CERT_FINGERPRINTS`) y verificar App Links.
4. Con el AAB subido aparece Monetizar › Productos: crear los productos
   únicos y configurar RTDN.
5. Cuenta personal: prueba cerrada con 12 testers durante 14 días antes de
   pedir producción. Cuenta de organización: no aplica.
6. Siguientes versiones: `eas submit -p android --profile production`
   (pista `internal`, borrador) → promover.

## 3. App Store

1. App ID con NFC Tag Reading, Associated Domains, Push e In-App Purchase;
   app creada en App Store Connect; Paid Apps Agreement activo.
2. Reemplazar `REEMPLAZAR_APPLE_TEAM_ID` y `REEMPLAZAR_ASC_APP_ID` en
   `eas.json` (repo móvil).
3. `eas build -p ios --profile production` → `eas submit -p ios --profile production`
   → TestFlight (probar NFC, ubicación, audio, push y compra sandbox).
4. Completar App Privacy (debe coincidir con el Privacy Manifest de
   `app.config.ts`), clasificación, capturas, notas de revisión y el primer
   producto de compra dentro de la app **en el mismo envío** que la versión.
5. Configurar App Store Server Notifications V2 →
   `https://finisherlegacy.com/api/webhooks/app-store`.
6. Enviar a revisión.

## 4. Cada versión nueva

- Subir `version` en `app.json` (el número de build lo incrementa EAS).
- Cambios en permisos, plugins o entitlements → compilación nativa nueva y
  revisar Data safety / App Privacy.
- Volver a correr la cuenta de revisión si se reinició la base.
