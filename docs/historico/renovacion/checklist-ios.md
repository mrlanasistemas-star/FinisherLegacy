# Checklist iOS (Expo SDK 57)

Estado observado en `C:\wamp64\www\FinisherLegacyMobile` (lectura de código;
no existe carpeta `ios/`, nada probado en dispositivo).

## Cuenta y build

- [ ] Apple Developer Program activo; **Team ID** conocido (`APPLE_TEAM_ID` del servidor).
- [ ] App ID `com.finisherlegacy.app` con capacidades: **NFC Tag Reading**, **Associated Domains**, Sign in with Apple, Push.
- [ ] **EAS development build** (`eas build --profile development --platform ios`): Expo Go no incluye NFC; hace falta dev client en un iPhone registrado.
- [ ] `react-native-nfc-manager` con config plugin, que añade:
  - entitlement `com.apple.developer.nfc.readersession.formats` = `["NDEF", "TAG"]` (o solo `NDEF` si no se lee la firma de originalidad);
  - `NFCReaderUsageDescription` en Info.plist (texto en español: "Para leer y activar tu Finisher Legacy Tag").
- [ ] Mensajes de la hoja de Core NFC (`alertMessage`) en español; manejo de cancelación y timeout (60 s).

## NFC

- [ ] Lectura en app iPhone 7+; en segundo plano iPhone XS+ abre el Universal Link sin la app abierta (se usa el **primer** registro URI).
- [ ] Escritura solo iOS 13+ y solo en tags ya formateados NDEF (iOS no formatea) → el proveedor debe entregar los tags inicializados (`especificacion-nfc-proveedor.md` §4).
- [ ] Error claro para tag bloqueado / no NDEF / sin espacio.

## Universal Links

- [ ] `associatedDomains: applinks:finisherlegacy.com` (hoy se genera en `app.config.ts` desde `EXPO_PUBLIC_WEB_URL`).
- [ ] AASA del servidor con `appID` `<TEAMID>.com.finisherlegacy.app` y rutas `/t/*` y `/n/*` (el controlador ya lo hace si hay `APPLE_TEAM_ID`).
- [ ] Ruta `/t/[code]` en la app y mapeo en `map-incoming-path.ts` (hoy no existe).
- [ ] AASA servido sin redirección, `application/json`, HTTPS válido; validar con el CDN de Apple (`app-site-association.cdn-apple.com/a/v1/finisherlegacy.com`).

## App Store review

- [ ] Notas de revisión: explicar que el NFC lee el tag físico del producto; incluir cuenta demo y un código de activación de prueba (el revisor no tiene el tag).
- [ ] **Bienes físicos** (Tag, ropa, portarretrato): pago externo (Stripe/OpenPay) permitido (guía 3.1.3(e)).
- [ ] **Bienes digitales** consumidos en la app (premium, almacenamiento extra, fotos del fotógrafo descargadas en la app): requieren **StoreKit / In-App Purchase** (guía 3.1.1), salvo las excepciones vigentes por país. Decidir antes de exponer compras digitales en la app; hasta entonces, no mostrar botones de compra digital en iOS.
- [ ] Privacy manifest y "App Privacy" (fotos, audio, ubicación si queda tracking).
- [ ] Si se retira el tracking GPS, quitar `UIBackgroundModes: location` y los textos de ubicación en segundo plano (Apple rechaza permisos sin uso).
- [ ] Sign in with Apple presente porque hay login social (guía 4.8).

## Pruebas

- [ ] Filas I1/I2 de `matriz-pruebas-nfc.md`.
- [ ] TestFlight interno con al menos 10 atletas y 10 familiares (estrategia p. 32, días 16–30).
