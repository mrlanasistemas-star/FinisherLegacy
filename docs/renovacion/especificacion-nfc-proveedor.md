# Especificación NFC para proveedor — Finisher Legacy Tag

Documento para cotizar y aceptar muestras de tags NFC terminados en fábrica
(estrategia p. 6: "chip, correa, URL por pieza, MOQ por diseño y muestra";
p. 14: "el destapador necesita muestra y lectura probada con metal";
p. 33: "NFC sin fallas críticas ≥ 98 % de lecturas en prueba").

Fuente de los datos del chip: NXP, *NTAG213/215/216 — NFC Forum Type 2 Tag
compliant IC with 144/504/888 bytes user memory*, Product data sheet Rev. 3.2
(2 jun 2015), descargado de `nxp.com/docs/en/data-sheet/NTAG213_215_216.pdf`
y leído para este documento. Los datos de iOS vienen de la documentación de
Apple Core NFC. **Nada de este documento se ha probado aún con hardware real.**

---

## 1. Qué se graba en el chip

Un solo registro NDEF URI, nada más:

```
https://finisherlegacy.com/t/{code}
```

- `{code}`: 12 caracteres del alfabeto `ABCDEFGHJKMNPQRSTUVWXYZ23456789`
  (`App\Support\LegacyTags\TagCode`, CSPRNG, ≈ 59 bits). La ruta acepta
  8–24 caracteres. El dominio sale de `LEGACY_TAG_PUBLIC_URL`.
- **Sin** registro AAR (`android.com:pkg`): un AAR obliga a Android a abrir
  la app o la Play Store, y la estrategia (p. 11) exige que un familiar abra
  el recuerdo **sin instalar la app**. La app se abre igual por App Links /
  Universal Links cuando está instalada.
- **Sin** registro de texto ni vCard. Sin QR impreso (decisión vigente del
  producto: el Tag es solo NFC; ver auditoría, conflicto con p. 6/p. 11).

### Tamaño del mensaje (compresión de prefijo URI, NFC Forum RTD URI)

El prefijo `https://` se codifica como un byte (`0x04`).

| Parte | Bytes |
|---|---|
| Cabecera del registro (MB/ME/SR/TNF=1) | 1 |
| Longitud del tipo | 1 |
| Longitud del payload (registro corto) | 1 |
| Tipo `U` | 1 |
| Código de prefijo `0x04` (`https://`) | 1 |
| `finisherlegacy.com/t/` (18 + 3) | 21 |
| Código (12) | 12 |
| **Mensaje NDEF** | **38** |
| TLV NDEF (`03` + longitud) + terminador `FE` | 3 |
| **Total escrito en memoria** | **41** |

Con un código de 24 caracteres el total es 53 bytes. Con `?src=nfc` se
añaden 8 bytes. Cualquier variante cabe con mucho margen en un NTAG213.

## 2. Chips candidatos (NXP NTAG21x, NFC Forum Type 2)

| | NTAG213 | NTAG215 | NTAG216 |
|---|---|---|---|
| Memoria total | 180 B (45 páginas) | 540 B (135 páginas) | 924 B (231 páginas) |
| Memoria de usuario | **144 B** | **504 B** | **888 B** |
| Área NDEF declarada en el CC (byte 2) | `12h` → 144 B | `3Eh` → 496 B | `6Dh` → 872 B |
| Mensaje NDEF útil aprox. (tras TLV) | ~137 B (lo que suelen reportar las apps) | ~492 B | ~868 B |
| Uso con nuestra URL (41 B) | 28 % | 8 % | 5 % |

Comunes a los tres (datasheet §§1–2, 8):

- UID de **7 bytes** (ISO/IEC 14443-3 cascade level 2), programado en fábrica.
- Retención de datos **10 años**; resistencia de escritura **100 000 ciclos**.
- Distancia de operación "hasta 100 mm", dependiente del campo y de la
  geometría de la antena (la antena no la define el chip).
- Contraseña de 32 bits (`PWD`, `PACK`, `AUTH0`, `PROT`, `AUTHLIM`),
  bits de bloqueo estáticos y dinámicos, CC con bits OTP.
- **Firma de originalidad ECC** (ECDSA secp128r1, 32 bytes, comando
  `READ_SIG`) del UID con la llave privada de NXP.
- Contador NFC opcional (24 bits) con espejo ASCII en el NDEF.

### Estado de fábrica (datasheet §8.5.6)

NXP entrega el CC ya inicializado (`E1 10 12 00` / `E1 10 3E 00` /
`E1 10 6D 00`) y un NDEF vacío en la página 4. Es decir, el chip sale de NXP
**formateado NDEF**. El riesgo es que el **convertidor** (quien hace el
inlay/tag) lo reescriba, lo bloquee o lo deje en un estado no NDEF. Por eso
se exige por contrato (§4).

## 3. Compatibilidad con teléfonos

| Plataforma | Lectura | Escritura (activación en app) |
|---|---|---|
| **iPhone** | iPhone 7+ lee NDEF dentro de una app (Core NFC, iOS 11+). **Lectura en segundo plano** (sin abrir app; abre Safari o la app por Universal Link) solo en **iPhone XS / XR y posteriores**, con pantalla encendida y desbloqueado al menos una vez tras el arranque; se usa el **primer** registro URI del mensaje. iPhone 7/8/X necesitan abrir una app o el lector del Centro de control. | iOS 13+ (iPhone 7+) con Core NFC `NFCNDEFReaderSession`/`NFCTagReaderSession` y el entitlement *NFC Tag Reading*. **iOS no formatea**: solo escribe en tags que ya son NDEF (CC escrito). |
| **Android** | Casi todos los equipos con NFC leen NDEF en segundo plano con la pantalla desbloqueada; el URL abre el navegador o la app (App Links verificados). La posición de la antena varía (centro/arriba de la espalda). | App nativa (`Ndef` o `NdefFormatable` → puede formatear un tag vacío). Chrome Android 89+ con **Web NFC** (`NDEFReader`) en HTTPS y con gesto del usuario. |
| **Web NFC en iOS / desktop** | — | No existe. En iPhone la escritura solo es posible desde la app. |

Conclusión: el tag debe llegar **ya formateado NDEF y con la URL ya escrita**
por el proveedor, para que la activación sea solo "tocar y ligar a mi cuenta"
(sin escritura en campo). Escribir en el teléfono queda como respaldo para
reemplazos.

## 4. Requisitos obligatorios al proveedor

1. Chip **NXP NTAG213** genuino (o NTAG215 si cuesta lo mismo; ver §7). No
   clones ("NTAG213 compatible", Fudan, etc.): se verifica con la firma de
   originalidad (§5).
2. **Formateado NDEF / inicializado**: página 3 (CC) escrita con `E1 10 xx 00`
   (byte 3 = `00` → lectura y escritura libres).
3. **Codificación por pieza**: cada tag con su URL única
   `https://finisherlegacy.com/t/{code}` según el archivo CSV que entrega
   Finisher Legacy (columnas: `serial`, `code`, `url`, `diseño/colección`).
   Devolver un CSV de verificación con `serial`, `code`, `UID` leído y
   resultado de lectura posterior a la codificación.
4. **NO bloquear**: sin lock bits estáticos/dinámicos activados, CC byte 3 =
   `00`, sin contraseña (`AUTH0` = `FFh` por defecto, `PWD` = `FFFFFFFFh`).
   Motivo: si un chip queda mal escrito o se reasigna, un tag bloqueado es
   basura; además un `PWD`/`AUTHLIM` mal configurado inutiliza el chip para
   siempre. (La protección contra reescritura maliciosa se decide después;
   ver §6.)
5. **Sin** registro AAR, sin registros extra, sin contador ni espejo UID
   (salvo acuerdo por escrito).
6. **Antena**: inlay de al menos ~25 mm de diámetro o 25 × 15 mm
   rectangular, el mayor que permita el troquel; indicar dimensiones y
   frecuencia de resonancia del inlay.
7. **Metal**: para piezas que conviven con medallas o con metal (destapador,
   llavero metálico, portarretrato con imanes), inlay **on-metal** con capa
   de ferrita. Declarar cuál se usa en cada SKU.
8. Material y encapsulado: PVC/PET/silicona/epoxi; temperatura y agua
   declaradas (sudor, lluvia, lavado si va en ropa).
9. Muestras: **mínimo 20 piezas por diseño/material** para el protocolo §8
   antes del lote.
10. Tasa de defectos aceptada en lote: **≤ 0.5 %** de tags no legibles en la
    inspección de entrada; reposición sin costo.

## 5. UID y firma de originalidad

- El **UID no es prueba criptográfica**: se lee sin autenticación y existen
  tags "magic" con UID reescribible, así que un tag puede clonarse (UID y
  NDEF). La plataforma ya guarda el UID solo como HMAC (`nfc_uid_hash`).
- La **firma de originalidad** (32 B, `READ_SIG`, ECDSA secp128r1, llave
  pública de NXP, AN11350) prueba que el **chip** es NXP, no que el tag sea
  nuestro, y puede copiarse junto con el UID a un clon que la emule. Uso
  propuesto: **verificación opcional** en la inspección de entrada (app
  interna o NFC Tools) para descartar chips falsos. No se usa como
  autenticación del recuerdo.
- Si en el futuro se necesita anti-clonación real: **NTAG 424 DNA** (URL
  dinámica SUN con CMAC AES-128 que el servidor valida). Más caro y requiere
  cambios en `/t/{code}`; fuera del alcance del piloto.

## 6. Riesgos

| Riesgo | Efecto | Mitigación |
|---|---|---|
| Tag sin formatear NDEF | iPhone no puede escribir; algunos lectores no lo abren | Requisito §4.2 + prueba §8 |
| Lock bits / contraseña puestos por el proveedor | No se puede corregir ni reasignar | Requisito §4.4 + lectura del CC en muestra |
| Tag sin bloquear | Un tercero con NFC Tools puede reescribir la URL | Aceptado en piloto: la URL solo abre; nada se activa sin cuenta + código. Monitorear `nfc_taps`; reemplazo de tag por soporte. Evaluar bloqueo tras el piloto. |
| Desintonía junto a la medalla / metal | Lectura corta o nula | Inlay on-metal (ferrita); prueba §8 T5 |
| Antena pequeña (troquel) | Hay que "buscar" la antena del teléfono | Antena ≥ 25 mm; prueba §8 T1 |
| Clones del tag | Copias de la URL | El código solo abre la historia publicada; los permisos están en la cuenta |
| iPhone 7/8/X | No lee en segundo plano | Mensaje en empaque: "En iPhone 7/8/X abre el lector NFC del Centro de control" |

## 7. Recomendación

**NTAG213**, codificado y sin bloquear, con inlay on-metal en los SKUs que
van junto a metal.

- La URL ocupa 41 B de 144 B; no hay contenido que justifique más memoria
  (todo vive en el servidor).
- Es el chip más barato y más extendido; la misma firma de originalidad y
  el mismo comportamiento iOS/Android que 215/216.
- El alcance depende de la **antena**, no del modelo de chip: gastar en
  antena/ferrita da más que gastar en memoria.
- NTAG215 solo si el proveedor lo ofrece al mismo precio (deja margen para
  un segundo registro). NTAG216 no aporta nada al caso de uso.

## 8. Protocolo de prueba de muestras (criterios medibles)

Equipos mínimos: **1 iPhone XS o posterior (iOS actual)**, **1 iPhone 7/8**
si está disponible, **2 Android de marcas distintas** (p. ej. Samsung gama
media + Xiaomi/Motorola). Regla milimétrica o calibrador. 20 piezas por SKU.
Registrar resultados en `matriz-pruebas-nfc.md`.

| # | Prueba | Método | Criterio de aceptación |
|---|---|---|---|
| T0 | Identificación | NFC Tools / TagInfo (NXP): tipo de chip, firma de originalidad, CC, lock bits | 20/20 NTAG213 genuino, CC `E1 10 12 00`, lock bits en 0, sin `PWD` (`AUTH0` = `FFh`) |
| T1 | Distancia de lectura en segundo plano | Acercar en perpendicular sobre la antena del teléfono, medir distancia máxima con apertura correcta de la URL, 3 intentos | Mediana **≥ 2.0 cm** en cada teléfono; ninguna pieza < 1.0 cm |
| T2 | Tasa de lectura | 50 lecturas por teléfono por pieza (muestra de 5 piezas) | **≥ 98 %** de aperturas correctas (meta p. 33) |
| T3 | Contenido | Comparar la URL leída con el CSV | 100 % coincide; 0 duplicados |
| T4 | A través de la correa / funda | Lectura con la correa o funda puesta y doblada | Mismo criterio que T1 (≥ 2.0 cm) |
| T5 | Junto a una medalla real | Tag colgado/apoyado sobre una medalla metálica y sobre el portarretrato con imanes | Mediana **≥ 1.5 cm**, tasa ≥ 95 % |
| T6 | Escritura/reescritura | Escribir y leer la URL 100 veces (app + Web NFC Android) | 100/100 correctas; sigue sin bloquear |
| T7 | Durabilidad | 24 h en agua con sal al 3.5 %, 500 flexiones de correa, 1 m de caída × 10 | Pasa T1 y T2 después |
| T8 | Temperatura | 2 h a 60 °C (auto al sol) y 2 h a −10 °C | Pasa T1 después |
| T9 | Actividad real | Flujo completo `/t/{code}` → activar en iPhone y Android (web y app) | Sin fallas críticas (ver matriz) |

Rechazo del lote de muestra: cualquier falla en T0/T3, o T1/T2/T5 fuera de
criterio en más de 1 de 20 piezas.

## 9. QA de lote (inspección de entrada)

1. Muestreo AQL nivel II (p. ej. 1 250 piezas → 80 muestras; aceptar con
   ≤ 1 defecto, rechazar con ≥ 2), más **lectura del 100 %** si la línea de
   empaque lo permite (lector USB ACR122U/ACR1252 + script que compara
   URL vs. CSV).
2. Por pieza muestreada: T0 (tipo, CC, locks, firma), T3 (URL = CSV).
3. Por caja: T1 con un iPhone y un Android.
4. Importar el CSV de verificación a la plataforma (series/lotes,
   `inventory_lot_id`) antes de mover inventario a aliados.
5. Guardar el reporte de inspección por lote (fecha, proveedor, % defectos).
