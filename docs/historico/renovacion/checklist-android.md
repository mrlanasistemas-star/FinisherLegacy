# Checklist Android (Expo SDK 57)

Estado observado en `C:\wamp64\www\FinisherLegacyMobile` (lectura de código,
nada probado en dispositivo).

## NFC

- [ ] `react-native-nfc-manager` instalado **y usado** (hoy está en `package.json` sin importarse) + su config plugin en `app.json`.
- [ ] Permiso `android.permission.NFC` y `<uses-feature android:name="android.hardware.nfc" android:required="false"/>` (no excluir de Play a equipos sin NFC; la activación por código sigue funcionando).
- [ ] Lectura en app: `Ndef` → registro URI → extraer `{code}` de `/t/{code}`.
- [ ] Escritura (solo soporte/reemplazo): un único registro URI, sin AAR; `NdefFormatable` para tags vacíos; errores claros para read-only / contraseña / tipo no NDEF.
- [ ] Manejo de NFC apagado (abrir ajustes) y de equipo sin NFC.

## Enlaces

- [ ] Intent filter `autoVerify` con `pathPrefix` **`/t/`** (hoy solo `/n/` en `app.config.ts`).
- [ ] Ruta `src/app/t/[code].tsx` + mapeo en `src/features/links/map-incoming-path.ts` (hoy `/t/` no se mapea).
- [ ] `assetlinks.json` del servidor con el **SHA-256 de la firma de Play App Signing** (no el de upload key) y el de la build interna si se prueba con ella.
- [ ] Verificar: `adb shell pm get-app-links com.finisherlegacy.app` → `verified`.

## Build y tienda

- [ ] EAS: perfil `development` (dev client) para probar NFC; `preview` APK interno; `production` AAB.
- [ ] Credenciales de firma en EAS; registrar SHA-256 resultante en `ANDROID_SHA256_CERT_FINGERPRINTS`.
- [ ] Revisar permisos sobrantes del modelo anterior (ubicación en segundo plano, servicio en primer plano de ubicación, `SYSTEM_ALERT_WINDOW`) si se retira el tracking: Play exige justificar ubicación en segundo plano.
- [ ] Play Console: ficha, Data safety (fotos, audio, ubicación si queda), clasificación de contenido, cuenta de prueba para revisión.
- [ ] Bienes digitales: el Tag es físico → Stripe/OpenPay permitido. Funciones premium o almacenamiento extra consumidos **dentro de la app** deben usar **Google Play Billing** (o venderse solo en la web sin enlace de compra en la app, según la política vigente del país).

## Pruebas

- [ ] `npm test`, `npm run lint`, `npx tsc --noEmit` en verde.
- [ ] Filas A1/A2 de `matriz-pruebas-nfc.md` ejecutadas en dos marcas distintas.
