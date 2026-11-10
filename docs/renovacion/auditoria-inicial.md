# Auditoría inicial frente a la nueva estrategia

Fecha: 2026-11-10. Repos: web/backend `finisherLegacy` (Laravel 13 + Inertia/Vue)
y móvil `FinisherLegacyMobile` (Expo SDK 57). Fuente de la estrategia:
`FL_Cambio_de_Estrategia.pdf` (26 oct 2026, 41 páginas; capturas en
`capturas/estrategia/`).

**Método.** La clasificación se basa en **leer el código**, no en
ejecutarlo. "Funcional y verificada" solo se usa cuando se corrió una
prueba que lo demuestra durante esta auditoría; en este caso eso aplica
únicamente al comando `storage:health` y al test de hosts del NAS (ver §4).
Las pruebas Pest que existen para otros módulos **no se ejecutaron** aquí.
El árbol de trabajo tenía ~100 cambios sin commit de otros agentes (retiro de
placa/producción/operador, activación NFC, cuotas, portarretrato):
se marcan **[WIP]**.

Clasificación: **FV** Funcional y verificada · **FM** Funcional mejorable ·
**P** Parcial · **D** Defectuosa · **I** Incompatible con nueva estrategia ·
**E** Pendiente de dependencia externa.

---

## 1. Qué pide la estrategia (resumen por página)

| Pág. | Contenido | Requisito para producto/plataforma |
|---|---|---|
| 1–2 | "Producto listo. Plataforma propia." Inversión en la plataforma | Productos NFC de catálogo terminados en fábrica; compra rápida; el atleta completa el contenido después; aliados + presencia propia |
| 3–4 | Modelo anterior (placa acero + clip + NFC + grabado en sitio) limita la venta: 7.5 vs 40 atenciones/h (5.3×) | Eliminar grabado/estaciones del flujo de venta |
| **5** | Qué cambia: producto = tag terminado con correa y NFC; personalización = diseño por colección + datos en la web; entrega = cobro, entrega y **activación posterior**; expansión = distribuidores e inventario; valor = historia, familia, nuevas compras | Activación posterior; datos del atleta en la web; inventario por distribuidor |
| **6** | El tag ya existe (USO Card): troquel existente, **correa, URL única y QR de respaldo**, probar lectura junto a una medalla | URL única por pieza; QR de respaldo (**choca con la decisión actual "solo NFC"**); prueba con medalla |
| **7–8** | Colección permanente (lugar, disciplina, año; frases FL) y diseño por sede (Cozumel, Valle de Bravo) | Catálogo por colección/sede; variantes de diseño en inventario |
| **9** | Portarretrato acrílico sin pantalla ni batería: al acercar el celular abre **álbum y mensaje de voz** | Segundo producto NFC que abre el mismo recuerdo; audio |
| **10** | Plantilla de autoservicio: subir foto propia/autorizada, elegir datos de carrera o dedicatoria, **descargar al tamaño del marco (20 × 25 cm)**, imprimir | Generador de imprimible 20×25 |
| **11** | El atleta decide qué comparte: atleta elige fotos/resultados/voz y revoca; familiar abre por NFC o QR **sin instalar la app**; persona con enlace ve lo publicado; FL no publica sin autorización. Privado → **PIN o acceso autenticado** | Enlaces por destinatario revocables, PIN o login, apertura web sin app |
| **12** | Primera versión vendible: cuenta y activación por pieza; participación por atleta/evento/edición; fotos, audio, certificado o enlace; álbum por destinatario + plantilla de impresión. Después: venta de fotos con fotógrafo, premium/almacenamiento, complementarios, patrocinios. Resultados manuales o enlazados | Alcance del MVP |
| 13 | Canales: stand aliado/gimnasio, tienda en línea, presencia propia, franquicia futura | Inventario, margen y soporte por aliado |
| **14** | Accesorios por evaluar: llavero de silicona, destapador con NFC aislado (probar con metal), pulsera deportiva, pulsera tejida | Más SKUs NFC; inlay on-metal |
| **15–16** | Ropa de catálogo (gorra, visera, trisuit) y gorra con hielo (OEM, marca blanca) | Productos físicos sin NFC en el catálogo |
| 17–18 | Proveedores (USO Card, Zhihong Acrylic, Ultra Cool Tech, Tarstone) y calendario por evento | — (compras) |
| 19–23 | Ahorros, costo por recuerdo ($215 → $95 MXN), contribución por canal ($399 directo / $240 aliado), ingresos digitales con margen (fotos 20 % comisión, almacenamiento incluido por compra) | Precios por canal; comisión de fotos; cuotas de almacenamiento |
| 24–31 | Mercado (HYROX, IRONMAN, running), TAM/SAM/SOM; SOM 1 240 tags/12 meses (496 aliados, 480 eventos, 264 en línea); equilibrio 1 633 tags | Medir ventas por canal |
| **32** | Plan 90 días: d1–15 cotizaciones, muestras y **alcance web**; d16–30 prueba con 10 atletas y 10 familiares (activación sin fallas críticas); d31–60 lote pequeño y piloto con aliados/evento; d61–90 reposición | Web + activación listas en ~30 días |
| **33** | Indicadores del piloto: compra y entrega ≤ 90 s (mediana); **activación en 72 h ≥ 70 %**; **NFC/QR sin fallas críticas ≥ 98 % de lecturas**; devoluciones/defectos < 3 %; contribución por canal positiva. Medir conversión, abandono y uso por familiares | Instrumentación y reportes |
| 34–35 | Viaje a China (agenda de compra), responsables (Producto + tecnología: web, activación y álbum por destinatario) | — |
| 36–41 | Cierre, anexos de costos, cotización TEAM FL, fuentes | — |

---

## 2. Matriz de funcionalidades — Web / backend

| Área | Funcionalidad | Dónde | Clase | Nota |
|---|---|---|---|---|
| Tag | Tabla `legacy_tags` + ciclo de vida (fabricado → en stock → vendido → entregado → activado; reemplazo, devolución, baja) | `create_legacy_tags_and_channels` migración, `Services/LegacyTags/TagLifecycle.php`, `Actions/LegacyTags/ManageTag.php` | FM | Pruebas en `LegacyTags/TagLifecycleTest.php` (no ejecutadas aquí) |
| Tag | Resolver `/t/{code}` (propio → historia; ajeno → según política; activable → `/activar/{code}`) | `LegacyTags/TagOpenController.php`, `TagActivationState.php` | FM | Contadores de aperturas no atómicos (`open_count + 1` desde el modelo) |
| Tag | Código público 12 caracteres CSPRNG, alfabeto sin ambigüedades | `Support/LegacyTags/TagCode.php` | FM | URL cabe en NTAG213 (41 B) |
| Activación | `/activar`, `/activar/{code}`, login/registro y regreso, lock + `lockForUpdate`, idempotente; crea `AthleteOwnedProduct` + `LegacyStory`; registra "venta en campo no reportada" si el tag estaba en stock de un aliado | `LegacyTags/ActivationController.php`, `Actions/LegacyTags/ActivateTag.php` | FM | El flujo estándar **no exige código de activación** (solo el flujo NFC WIP) |
| Activación | Escritura Web NFC / intents de activación / códigos de activación hasheados | `ActivationNfcController`, `{Start,Confirm,Fail}TagActivation`, `GenerateActivationCodes` **[WIP]** | D | Renderiza `activate/NfcWrite`, página Vue inexistente; sin pruebas; `CreateLot` exporta códigos en claro a CSV |
| Activación | Recordatorio para meta 72 h | `TagActivationReminder` (envío manual desde Admin → Activaciones) | P | Nada programado; meta p. 33 sin automatización |
| Tag | Bitácora de lecturas (HMAC de IP rotado diario) | `Actions/LegacyTags/RecordTagScan.php`, `legacy_tag_scans` | FM | Sirve para tasa de uso; no mide tasa de lectura física |
| Tag | QR de respaldo (p. 6/11) | `TagAccessSource::Qr`, `?src=qr` aceptados | P | Decisión de producto actual "solo NFC" contradice p. 6/11 → **decidir** |
| NFC antiguo | `/n/{token}`, `NfcTag/NfcAssignment/NfcTap`, "Mi equipo", `admin/legacy-nfc/*` | `routes/web/nfc.php`, `routes/api/nfc.php` | I | Sistema paralelo a `legacy_tags`; la app móvil usa este, no `/t/` |
| Historia | Una historia por tag: participación o carrera manual, resultado, dedicatoria, `external_url` (enlace de resultados), visibilidad pública/no listada/privada | `Models/LegacyStory.php`, `Actions/LegacyTags/UpdateStory.php`, `StoryEditorController` | FM | Cumple p. 12 punto 2–3 a nivel de modelo |
| Historia | Fotos (12), audio (≤180 s/15 MB), certificado PDF/imagen vía MediaStorage | `Actions/LegacyTags/ManageStoryMedia.php` | FM | Ver brechas NAS en §4 |
| Álbum | Enlaces por destinatario (token 40, hasta 20, revocación inmediata, funcionan sin cuenta) | `LegacyStoryShare`, `ManageStoryShares`, `/legacy/para/{token}` | FM | **No existe PIN** ni acceso para invitado autenticado (p. 11) → P frente a p. 11 |
| Impresión | PDF dompdf: 15×21, A4, 20×20 | `Services/LegacyTags/StoryPrintRenderer.php` | P | **Falta 20 × 25 cm** (p. 10); `config/portrait.php` + `Services/Portrait/*` **[WIP]** sin renderer, ruta ni pruebas |
| Atleta | Identidad, fusión, conflictos, perfil público `/@username` | `Actions/Athletes/*`, `AthleteIdentityMatcher` | FM | — |
| Resultados | Import CSV/XLSX, integraciones con organizadores (REST genérico, mock), sync jobs | `Imports/ParticipantsImport.php`, `Actions/Integrations/*` | FM | La estrategia no presupone integración; resultado manual existe solo en la historia (no hay alta manual de resultado en admin) |
| Tienda | Catálogo, colecciones, precios por canal, `/tienda`, carrito, checkout, pedidos, cupones | `Admin/ProductController`, `Catalog/ChannelPriceController`, `Actions/Commerce/*` | FM | Catálogo recortado a Tag, Trisuit, Chill Band, Racepack |
| Pagos | OpenPay (web, configurable en admin, llave cifrada) y Stripe (API), webhooks | `Services/Commerce/Payments/*` | E | Requieren llaves y webhooks reales |
| Inventario | Ubicaciones, lotes (CSV de códigos), transferencias, stock de tags, proveedores | `InventoryService`, `InventoryLotController`, `TagStockController` | FM | — |
| Aliados | Portal `/aliado`, venta rápida, POS admin, `Ally.commission_percent` | `Admin/Commercial/PosController`, `RecordInPersonSale` | P | Comisión solo almacenada; no hay cálculo ni liquidación (p. 13, p. 22) |
| Envíos | Manual (paquetería + guía, marcar entregado) | `OrderOperationsController` | FM | Sin integración de paquetería ni tarifas |
| Fotógrafos | Marketplace `/fotos`, portal `/fotografo`, comisión 20 %, pagos | `PhotosController`, `PhotoFeeCalculator` | FM | Es fase posterior (p. 12/23); 1 solo archivo de prueba |
| Premium / almacenamiento | Cuotas de fotos y paquetes de expansión | `StoryPhotoQuota`, `PhotoExpansionPack`, `Actions/Quotas/` **[WIP]** | P | Sin pruebas |
| Patrocinios | — | — | — | No existe (fase posterior, p. 12) |
| Comunidad / apoyo | `/comunidad`, `/support/{code}` (audios de apoyo con URL firmada) | `Community*`, `SupportAudioController` | FM | Fuera del MVP; el audio de apoyo puede reutilizarse |
| Medios | `MediaStorage` (temp local → cola → NAS), entrega firmada `/media/f/{uuid}/{variant}` | `Services/Media/*`, `Jobs/Media/*` | FM | Brechas de NAS caído en §4 |
| Medios | `storage:health` (montaje, centinela, escritura/lectura/borrado, espacio libre) + scheduler | `app/Console/Commands/StorageHealth.php` | **FV** | 8 pruebas Pest en verde (§4) |
| Medios | Sin hosts/IPs/rutas del NAS en el código | `tests/Feature/Media/NoHardcodedMediaHostsTest.php` | **FV** | Ejecutado: 2/2 en verde |
| App links | AASA / assetlinks con `/n/*` y `/t/*` | `Http/Controllers/Nfc/AppLinksController.php`, `config/nfc.php` | E | 404 hasta tener `APPLE_TEAM_ID` y SHA-256 de Play |
| Auth | Fortify (registro, reset, verificación), Sanctum, Google/Apple | `config/fortify.php` | FM | 2FA no está en las features de Fortify |
| Admin | ~35 secciones con permisos | `pages/admin/*`, `config/permissions.php` | FM | Limpiar secciones del modelo anterior |
| Métricas piloto | Tiempo compra→entrega, % activación 72 h, tasa de lectura, devoluciones, contribución por canal | — | P | Hay datos crudos (timestamps de tag, scans, pedidos) pero no un reporte del piloto |
| Modelo anterior | Legacy Plate, Plate Studio, producción, máquinas, dispositivos/desktop API, operador, event-ops, tracking en vivo, `/l/{code}` + QR, comandos `finisher:simulate-*`, `tracking:prune-points` | `Actions/LegacyPlates`, `Actions/Production`, `routes/web/tracking.php`, `/api/v1/devices/*`, etc. | I | En retiro por otro agente (`retiro-modulos.md`); riesgo de rutas apuntando a controladores borrados |

## 3. Matriz de funcionalidades — App móvil

| Área | Funcionalidad | Dónde | Clase | Nota |
|---|---|---|---|---|
| NFC | Lectura/escritura nativa | `react-native-nfc-manager` en `package.json` (sin commit) | P | **Nadie lo importa**; sin config plugin, sin entitlement iOS, sin `NFCReaderUsageDescription`, sin permiso `android.permission.NFC` |
| NFC | Pantalla de aterrizaje `/n/{token}` (cargando, no encontrado, red, desactivado, reclamado, sin reclamar, 409) | `src/app/nfc/[token].tsx`, `src/hooks/use-nfc.ts` | I | Usa el sistema antiguo `/n/{token}`; el Tag nuevo usa `/t/{code}` |
| Enlaces | Universal/App Links | `app.config.ts` (`associatedDomains`, intent filter `autoVerify` con `pathPrefix: '/n/'`), `map-incoming-path.ts` | D | **No hay ruta ni mapeo `/t/`**: un tap del Tag nuevo no resuelve en la app |
| Activación | Tres flujos paralelos: NFC antiguo, Legacy Codes (`/l/`), códigos de equipo `AST…` | `src/api/{nfc,legacyCodes,gear}.ts` | I | Reclamar va a "Mi equipo", no a una participación ni a la historia del Tag |
| Auth | Login, registro, reset, Apple/Google, token en SecureStore | `src/api/auth.ts`, `src/stores/authStore.ts` | FM | — |
| Historia | Medios por participación (fotos/video, progreso, visibilidad, orden) | `src/api/eventMedia.ts`, `media-upload-sheet.tsx` | P | No usa las historias `legacy_stories`; **sin grabación de voz** (no hay `expo-audio`); sin certificado/enlace de resultados |
| Álbum | Compartir | `Share.share` con URLs web | P | Sin enlaces por destinatario; `profile-view.tsx` tiene `https://finisherlegacy.com/@` fijo |
| Tienda | Catálogo, carrito, checkout Stripe PaymentSheet, pedidos | `src/features/commerce/*`, `src/payments/*` | E | Necesita llave `pk_` real; Apple Pay/Google Pay apagados |
| Bienes digitales | StoreKit / Play Billing | — | P | No existe; `memory-upgrades.tsx` es un stub informativo |
| Modelo anterior | Tracking GPS en segundo plano, placa, Legacy Codes, sesiones de apoyo | `src/features/tracking/*`, `src/api/legacyPlateModels.ts` | I | Permisos de ubicación en segundo plano complican la revisión de tiendas si quedan sin uso |
| Calidad | Jest (19 archivos de lógica pura), CI con typecheck/lint/test/expo-doctor | `.github/workflows/mobile-ci.yml` | FM | Sin pruebas de pantallas ni de NFC |

## 4. Almacenamiento (Synology) — verificado en esta auditoría

- `php artisan test --compact tests/Feature/Storage` → **8 pasadas, 17 aserciones**
  (raíz sana, salida JSON con espacio libre, raíz faltante sin crear la
  carpeta, centinela ausente, espacio bajo, disco desconocido, disco `Storage::fake`,
  comando programado). Ejecutado en Windows; la comprobación de punto de
  montaje Linux (`FINISHER_MEDIA_REQUIRE_MOUNT`) **no** se probó.
- `tests/Feature/Media/NoHardcodedMediaHostsTest.php` → 2/2.
- Brechas en la subida con NAS caído (detalle en `synology-smb.md` §1):
  posible fila `media_files` huérfana en `ManageStoryMedia::add()` si falla la
  transacción del pivote; excepción no controlada (500) si falla el disco
  temporal; `media:retry-failed` no programado; sin aviso al usuario cuando la
  transferencia falla. Las subidas **no** dependen del NAS en la petición
  (van a disco temporal y cola), lo cual es correcto.

## 5. Conflictos y decisiones abiertas

1. **QR de respaldo**: p. 6 y p. 11 lo piden; el producto actual es "solo NFC".
2. **Dos sistemas NFC** (`/n/{token}` antiguo y `/t/{code}` nuevo); la app solo conoce el antiguo.
3. **PIN** para contenido privado (p. 11) no existe.
4. **20 × 25 cm** (p. 10) no existe en el renderer (WIP en otro agente).
5. **Comisiones de aliados** almacenadas pero no calculadas (p. 13, 22).
6. **Métricas del piloto** (p. 33) sin reporte.
7. **Bienes digitales en la app** (premium, almacenamiento, fotos): requieren StoreKit/Play Billing o no venderse dentro de la app.
8. **Código de activación**: el flujo estándar activa sin código; definir si un tag de aliado no vendido puede activarse.

## 6. Prioridad sugerida (para cumplir p. 32 días 1–30)

1. App: ruta `/t/[code]`, intent filter y mapeo `/t/`; activación del Tag nuevo vía `api/v1/tags/{code}/activate`.
2. Web: completar o retirar la página `activate/NfcWrite`; pruebas de intents/códigos.
3. Álbum: PIN opcional por enlace; plantilla 20 × 25.
4. Voz en la app (grabación m4a) y en la web (ya soportada por `ManageStoryMedia`).
5. Recordatorio automático de activación (24 h / 48 h) y reporte de métricas del piloto.
6. Corregir brechas de subida con NAS caído.
7. Ejecutar `matriz-pruebas-nfc.md` con muestras reales.
