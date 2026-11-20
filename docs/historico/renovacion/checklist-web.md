# Checklist web (Laravel) — nueva estrategia

Marcar solo con evidencia (prueba Pest, prueba manual con fecha, o captura).
Ver detalle en `auditoria-inicial.md`.

## Producto mínimo vendible (estrategia p. 12, p. 32–33)

- [ ] `/t/{code}` resuelve: tag nuevo → activar; activado → recuerdo; desactivado → página honesta; inexistente → 404.
- [ ] Activación por NFC y por código impreso (`/activar`); un solo dueño con concurrencia.
- [ ] Participación por atleta + evento + edición; resultado manual o enlace (sin integración automática).
- [ ] Historia: fotos, mensaje de voz (m4a/webm/ogg), certificado PDF/imagen o enlace.
- [ ] Álbum por destinatario: enlace propio, revocable, PIN/login opcional para contenido privado (p. 11).
- [ ] Plantilla de impresión 20 × 25 cm descargable (p. 10).
- [ ] FL no publica contenido sin autorización del atleta (p. 11).
- [ ] Tienda: Tag + accesorios de catálogo; checkout con la pasarela elegida (OpenPay/Stripe) con llaves reales en sandbox y luego producción.
- [ ] Venta por aliado: inventario por ubicación/lote, venta registrada, margen (p. 13, p. 22).
- [ ] Métricas del piloto (p. 33): tiempo compra→entrega, % activación en 72 h, tasa de lectura, devoluciones, contribución por canal.

## Retiro del modelo anterior

- [ ] Módulos del modelo placa/grabado/estaciones retirados u ocultos según `retiro-modulos.md` (otro agente).
- [ ] Navegación (`navigation.ts`) sin enlaces a módulos retirados.

## Infraestructura

- [ ] `.env` de producción completo (`dependencias-externas.md`).
- [ ] Synology montado por SMB 3 y `php artisan storage:health` en verde (`synology-smb.md`).
- [ ] `FINISHER_MEDIA_SENTINEL` y `FINISHER_MEDIA_REQUIRE_MOUNT=true` en producción.
- [ ] Scheduler (`* * * * * php artisan schedule:run`) y workers de cola (`media`, `default`, `realtime`) activos.
- [ ] `/.well-known/apple-app-site-association` y `/.well-known/assetlinks.json` responden 200 JSON sin redirección, con `/t/*` incluido (hoy dependen de `APPLE_TEAM_ID`, `ANDROID_SHA256_CERT_FINGERPRINTS`).
- [ ] HTTPS válido (requisito de Web NFC y de App/Universal Links).
- [ ] Respaldo de BD + NAS restaurado al menos una vez.
- [ ] Webhooks de pago apuntando a producción y probados (pago, rechazo, duplicado).

## Calidad antes de cada despliegue

- [ ] `php artisan test --compact` en verde.
- [ ] `vendor/bin/pint --test` y `vendor/bin/phpstan analyse`.
- [ ] `npm run build` sin errores (en CI o en el servidor, no durante pruebas en paralelo).
- [ ] Matriz `matriz-pruebas-nfc.md` ejecutada con muestras reales.
