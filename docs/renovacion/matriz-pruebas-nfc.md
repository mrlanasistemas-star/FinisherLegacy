# Matriz de pruebas NFC con hardware real

Estado: **ninguna prueba ejecutada**. Todas las filas están en "Pendiente".
Se llena con tags de muestra del proveedor (ver
`especificacion-nfc-proveedor.md` §8) y con la plataforma desplegada en un
dominio HTTPS real (Web NFC y Universal/App Links no funcionan en `localhost`
ni con certificados no válidos).

## Equipos

| Id | Equipo | SO | Canal |
|---|---|---|---|
| A1 | Android gama media (p. ej. Samsung A-series) | Android 13+ | Chrome (Web NFC) + app |
| A2 | Android de otra marca (Xiaomi / Motorola) | Android 12+ | Chrome (Web NFC) + app |
| I1 | iPhone XS o posterior | iOS actual | Safari (lectura en segundo plano) + app |
| I2 | iPhone 7 / 8 (si hay) | iOS 15/16 | Lector del Centro de control + app |

Canales: **WEB-L** = lectura en segundo plano que abre el navegador;
**WEB-NFC** = escritura/lectura con `NDEFReader` en Chrome Android;
**APP** = app Expo (dev build EAS con NFC; hoy la app **no** tiene NFC nativo
ni ruta `/t/`, ver auditoría).

Leyenda de resultado: Pendiente · OK · Falla (con nota) · N/A.

## 1. Lectura / apertura

| # | Caso | Esperado | A1 | A2 | I1 | I2 |
|---|---|---|---|---|---|---|
| L1 | Tag nuevo sin activar, sin app instalada | Abre `/t/{code}` en el navegador → pantalla "Activa tu tag" | Pendiente | Pendiente | Pendiente | Pendiente |
| L2 | Tag activado con historia pública, sin app | Abre el recuerdo sin login (p. 11 "no necesita instalar") | Pendiente | Pendiente | Pendiente | Pendiente |
| L3 | Tag activado, con app instalada y App/Universal Links verificados | Abre la app en el recuerdo | Pendiente | Pendiente | Pendiente | Pendiente |
| L4 | Tag activado, app instalada pero `assetlinks`/AASA ausentes | Abre el navegador (no falla) | Pendiente | Pendiente | Pendiente | Pendiente |
| L5 | Historia privada / con PIN, visitante sin sesión | Pide PIN o login; no filtra contenido | Pendiente | Pendiente | Pendiente | Pendiente |
| L6 | Tag desactivado / reemplazado por soporte | Página honesta "ya no está activo" | Pendiente | Pendiente | Pendiente | Pendiente |
| L7 | Código inexistente (tag clonado/alterado) | 404 amable, sin datos | Pendiente | Pendiente | Pendiente | Pendiente |
| L8 | Pantalla bloqueada / teléfono recién encendido | Android: no lee hasta desbloquear; iOS: no lee hasta primer desbloqueo | Pendiente | Pendiente | Pendiente | Pendiente |
| L9 | Lectura a través de la correa/funda | ≥ 2.0 cm | Pendiente | Pendiente | Pendiente | Pendiente |
| L10 | Tag apoyado en una medalla real | ≥ 1.5 cm, ≥ 95 % | Pendiente | Pendiente | Pendiente | Pendiente |
| L11 | Tag en el portarretrato acrílico (con imanes) | Abre álbum + mensaje de voz | Pendiente | Pendiente | Pendiente | Pendiente |
| L12 | Teléfono con funda gruesa / funda con imán (MagSafe) | Documentar distancia | Pendiente | Pendiente | Pendiente | Pendiente |
| L13 | 50 lecturas seguidas | ≥ 98 % OK (meta p. 33) | Pendiente | Pendiente | Pendiente | Pendiente |
| L14 | Sin conexión de datos | Error de red legible; reintento funciona | Pendiente | Pendiente | Pendiente | Pendiente |
| L15 | NFC apagado en el sistema | Android: no lee; la app indica cómo activarlo | Pendiente | Pendiente | N/A | N/A |

## 2. Activación (cuenta + pieza)

| # | Caso | Esperado | A1 | A2 | I1 | I2 |
|---|---|---|---|---|---|---|
| A1 | Visitante sin cuenta toca tag nuevo → registro → vuelve y activa | Queda ligado a su cuenta; regresa al tag tras registro | Pendiente | Pendiente | Pendiente | Pendiente |
| A2 | Usuario con sesión activa tag nuevo | Activado en 1 paso de confirmación | Pendiente | Pendiente | Pendiente | Pendiente |
| A3 | Activación escribiendo el código impreso en `/activar` (sin NFC) | Mismo resultado que A2 | Pendiente | Pendiente | Pendiente | Pendiente |
| A4 | Tag ya activado por otra persona | "Este tag ya tiene dueño", sin datos del dueño, sin reasignar | Pendiente | Pendiente | Pendiente | Pendiente |
| A5 | Dueño vuelve a activar su propio tag | Idempotente, sin error | Pendiente | Pendiente | Pendiente | Pendiente |
| A6 | Dos teléfonos activan el mismo tag a la vez | Un solo dueño; el otro recibe "ya tiene dueño" | Pendiente | Pendiente | Pendiente | Pendiente |
| A7 | Código de activación incorrecto 5+ veces | Throttle / bloqueo temporal | Pendiente | Pendiente | Pendiente | Pendiente |
| A8 | Tag vendido pero pedido no pagado / cancelado | No se puede activar (o regla que defina negocio) | Pendiente | Pendiente | Pendiente | Pendiente |
| A9 | Tag de inventario de aliado aún no vendido | No activable hasta venta registrada (regla de negocio) | Pendiente | Pendiente | Pendiente | Pendiente |
| A10 | Sesión expira a mitad de la activación | Vuelve a login y retoma el tag | Pendiente | Pendiente | Pendiente | Pendiente |
| A11 | Tiempo compra → entrega → primera lectura | Mediana ≤ 90 s (p. 33) | Pendiente | Pendiente | Pendiente | Pendiente |
| A12 | Activación ligada a participación (atleta/evento/edición) | Crea o elige participación; resultado manual o enlace | Pendiente | Pendiente | Pendiente | Pendiente |

## 3. Escritura (reemplazo / soporte)

| # | Caso | Esperado | A1 WEB-NFC | A2 WEB-NFC | A1 APP | I1 APP | I2 APP |
|---|---|---|---|---|---|---|---|
| W1 | Escribir URL en tag NDEF vacío | URL correcta, verificada por relectura | Pendiente | Pendiente | Pendiente | Pendiente | Pendiente |
| W2 | Reescribir tag con otra URL | Sobrescribe; relectura OK | Pendiente | Pendiente | Pendiente | Pendiente | Pendiente |
| W3 | Tag sin formatear NDEF (CC vacío) | Android app: formatea (`NdefFormatable`); iOS: error claro "tag no compatible" | N/A | N/A | Pendiente | Pendiente | Pendiente |
| W4 | Tag bloqueado (read-only) | Error claro, no "éxito" falso | Pendiente | Pendiente | Pendiente | Pendiente | Pendiente |
| W5 | Tag con contraseña | Error claro | Pendiente | Pendiente | Pendiente | Pendiente | Pendiente |
| W6 | Tag de otro tipo (MIFARE Classic, NTAG424, tarjeta bancaria) | Rechazo sin colgar la app | Pendiente | Pendiente | Pendiente | Pendiente | Pendiente |
| W7 | Retirar el tag a mitad de escritura | Error; reintento deja URL íntegra | Pendiente | Pendiente | Pendiente | Pendiente | Pendiente |
| W8 | Cancelar la hoja de NFC de iOS / timeout de 60 s | Vuelve a la pantalla sin estado roto | N/A | N/A | N/A | Pendiente | Pendiente |
| W9 | Permiso Web NFC denegado | Mensaje con cómo reactivar | Pendiente | Pendiente | N/A | N/A | N/A |
| W10 | Web NFC en navegador no soportado (Firefox, Samsung Internet, iOS Safari) | Oculta la opción y ofrece app / código | Pendiente | Pendiente | N/A | N/A | N/A |
| W11 | Escritura con AAR o registros extra (no debe ocurrir) | Solo 1 registro URI | Pendiente | Pendiente | Pendiente | Pendiente | Pendiente |

## 4. Contenido y álbum tras la activación

| # | Caso | Esperado | A1 | A2 | I1 | I2 |
|---|---|---|---|---|---|---|
| C1 | Subir fotos desde el teléfono | Procesa; aparece en el recuerdo | Pendiente | Pendiente | Pendiente | Pendiente |
| C2 | Grabar / subir mensaje de voz (m4a en iOS, webm/ogg en Android) | Se reproduce en ambos SO | Pendiente | Pendiente | Pendiente | Pendiente |
| C3 | Certificado PDF o enlace de resultados | Visible y descargable | Pendiente | Pendiente | Pendiente | Pendiente |
| C4 | Enlace de álbum por destinatario, luego revocado | Tras revocar, el enlace deja de abrir | Pendiente | Pendiente | Pendiente | Pendiente |
| C5 | NAS fuera de línea al subir | Error controlado o "procesando" y reintento; sin filas huérfanas | Pendiente | Pendiente | Pendiente | Pendiente |
| C6 | Plantilla de impresión 20 × 25 cm descargada | Archivo al tamaño correcto | Pendiente | Pendiente | Pendiente | Pendiente |

## Registro

Por cada falla anotar: equipo, versión de SO/navegador/app, número de serie
del tag, distancia, pasos, captura y hora (para cruzar con `nfc_taps` /
logs).
