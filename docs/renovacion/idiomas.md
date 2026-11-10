# Idiomas y traducciones

<!-- Español + inglés (y los que vengan) en el sitio, el backend y la app: arquitectura, cómo marcar textos, cómo agregar un idioma, traductor automático, panel y flujo de revisión. -->

El sitio y la app se escriben **en español**. Cualquier otro idioma se genera
solo: un comando encuentra todos los textos, traduce los que faltan con un
traductor automático y genera los catálogos. Nadie traduce texto por texto;
una persona solo **revisa y corrige** desde el panel, y lo que corrige nunca
vuelve a ser tocado por la máquina.

## Idea central

- **El español es la fuente de verdad y el texto en español es la llave**
  (estilo gettext): `t('Tu esfuerzo merece una historia')`. No hay llaves
  tipo `home.hero.title` que mantener.
- En español no se carga ningún catálogo: `t()` devuelve su argumento.
- En otro idioma, un texto aún sin traducir **cae al español** (nunca queda
  vacío).
- Los idiomas se declaran en `config/i18n.php` (`locales`). El resto se
  deriva de ahí.

## Agregar un idioma (3 pasos)

1. En `config/i18n.php` agrega la entrada a `locales` y su etiqueta regional:
   `'pt' => 'Português'` y `'regional' => ['pt' => 'pt-BR']`
   (para DeepL, también `deepl.targets`).
2. Ejecuta `php artisan i18n:sync` (traduce todo el sitio, los correos y la
   app) y `php artisan i18n:translate-content` (productos, colecciones…).
3. Revisa en **Administración → Configuración → Idiomas** y deja el idioma
   visible. Las apps instaladas lo reciben solas (catálogo por API); el sitio
   lo sirve en `/pt/...`.

No hay que tocar rutas, páginas, ni publicar una nueva versión de la app.

## Cómo marcar un texto

| Dónde | Cómo |
| --- | --- |
| Vue (template) | `{{ $t('Colecciones') }}` · `<T>Compra tu Tag</T>` |
| Vue / TS (script) | `const { t, tc } = useI18n()` o `import { t, tc } from '@/i18n'` |
| PHP / Blade (correos, notificaciones, mensajes del servidor) | `__('Tu pedido :number va en camino', ['number' => $n])` · `@lang('…')` |
| App (React Native) | `import { t, tc, useI18n } from '@/i18n'` |

Convenciones:

- **Solo cadenas literales.** `t(variable)` o una plantilla con `${…}` no se
  pueden extraer ni traducir. Usa marcadores.
- **Marcadores:** `{name}` en web y app (`t('Hola, {name}', { name })`),
  `:name` en PHP. El traductor los conserva tal cual; si los pierde, la
  traducción se descarta.
- **Plurales:** `tc('{count} foto|{count} fotos', n)` — singular y plural
  separados por `|`; `{count}` se rellena solo. En PHP, `trans_choice`.
- **Frases completas**, no pedazos: `t('Tienes {count} historias')`, nunca
  `t('Tienes') + n + t('historias')` (el orden cambia entre idiomas).
- HTML dentro del texto se conserva (`<strong>`, `<a href>`), pero es mejor
  dejar el marcado en la plantilla y traducir solo el texto.
- Dentro de componentes usa `useI18n()` o `$t` (correctos también con SSR);
  el `t` importado directo es para código que solo corre en el navegador
  (toasts, manejadores, `lib/`).

Qué **no** se traduce:

- La marca y los nombres de producto: "Finisher Legacy", "MAKE IT LAST",
  "Tag", "Legacy Tag"… (lista `i18n.glossary`; el traductor los copia tal
  cual y el validador lo comprueba).
- **Contenido de las personas**: historias, comentarios, mensajes de ánimo,
  nombres. Nunca pasa por el traductor.
- Nombres de eventos, carreras, ciudades, correos, URLs, códigos.
- El panel de administración (se queda en español; se puede marcar después
  igual que lo demás).

## Arquitectura

### Resolución del idioma — `App\Http\Middleware\SetLocale`

Web: prefijo `/en` en la URL (solo páginas públicas) → idioma guardado en la
cuenta (`users.locale`) → cookie `fl_locale` → `Accept-Language` → español.

API (`SetLocale:api`): cabecera `X-Locale` → `Accept-Language` → idioma de la
cuenta dueña del token → español. La respuesta lleva `Content-Language`.

Solo se resuelven idiomas que un administrador dejó visibles. Se fija
`app()->setLocale()` y el idioma de Carbon, y se comparte con Inertia
(`App\Support\I18n\I18nProps`): `locale`, `locales` (cada idioma con la URL
de **esta** página en él) e `i18n` (`source`, `regional`, `version`).

Correos, notificaciones y push salen en el idioma de quien los recibe:
`User` implementa `HasLocalePreference` (`preferredLocale()` = `users.locale`),
que Laravel respeta también en notificaciones y correos en cola.

### Textos que escribe el servidor (PHP)

Todo lo que PHP le dice a un cliente va con `__()` / `trans_choice()`:
respuestas de la API, mensajes flash, errores de validación, excepciones de
dominio (`ApiException`), etiquetas de enums (`label()`), presentadores,
correos, notificaciones y push. El panel de administración, los logs, el
registro de actividad y los errores para desarrolladores se quedan en español.

- **A quien hace la petición** (respuesta, flash, excepción): `__()` a secas;
  `SetLocale` ya fijó el idioma. En la API manda `X-Locale` (va *antes* de
  `auth:sanctum` y del límite de peticiones, así que también traduce 401 y 429).
- **A otra persona** (notificación, push, correo armado en un comando o por
  un webhook): el texto se escribe en el idioma de **quien lo recibe**, nunca
  en el de quien lo provocó ni en el del *worker*:
  - `SendAthleteNotification::handle()` acepta el título y el mensaje como
    *closures* — `fn () => __('Tu pedido :number va en camino', ['number' => $n])`
    — y los evalúa en el idioma del destinatario (fila, evento en vivo y push
    llevan ese mismo texto). Una cadena simple es texto que escribió una
    persona (aviso del admin, campaña) y se envía tal cual.
  - En cualquier otro lugar: `in_locale_of($user, fn () => …)`
    (`App\Support\I18n\RecipientLocale`). Sin idioma guardado → el idioma por
    defecto. Dentro, Carbon también cambia: las fechas van con
    `isoFormat('LL')` / `translatedFormat()`, nunca con `locale('es')` fijo.
  - Correos de pedido: `OrderSummary::locale($order)` (idioma guardado en el
    pedido → cuenta del comprador → por defecto), fijado con
    `Mail::to(...)->locale()` porque el destinatario es solo una dirección.
- Quien se registra leyendo otro idioma lo conserva en su cuenta
  (`I18nServiceProvider`, evento `Registered`).
- Constantes y arreglos estáticos no pueden llamar `__()`: el texto vive en
  un método (`match` con un `__('…')` por caso). Nunca `__($variable)`.
- Validación: `lang/es/validation.php` y `lang/en/validation.php` cubren todas
  las reglas del framework; los nombres de campo van en `attributes`.

### URLs y SEO

- Las URLs en español **no cambian**. El inglés vive bajo `/en` y sirve
  **las mismas rutas**: `App\Http\Middleware\StripLocalePrefix` (middleware
  global, el más externo) quita el prefijo antes de enrutar y lo guarda en un
  atributo de la petición. No se registra ninguna ruta dos veces; los nombres
  de ruta y Wayfinder no se enteran.
- Una página tiene gemela `/en/…` solo si es pública: `GET`, grupo `web`, sin
  `auth`/`can`/`verified`/`signed` y fuera de `i18n.prefix.except`. `/en/admin`
  o `/en/dashboard` redirigen a la URL sin prefijo (las áreas privadas usan
  solo la preferencia).
- Quien eligió un idioma (cookie o cuenta) y abre una página pública sin
  prefijo es redirigido a su gemela, para que los enlaces compartidos lleven
  el idioma. Quien solo trae `Accept-Language` no es redirigido (los
  buscadores ven siempre el español en la URL española).
- `<html lang>` sale del idioma activo. `resources/views/i18n/head.blade.php`
  emite `hreflang` (uno por idioma + `x-default`) en páginas públicas.
- `sitemap.xml` lista las gemelas con `<xhtml:link rel="alternate">`.
- Helpers: `localized_url('en')` (página actual), `localized_url('en', $url)`,
  `localized_route('collections.index', [], 'en')`,
  `app(LocalizedUrls::class)->alternates()`.

Cambio de idioma: `POST /idioma` `{locale, url?}` guarda cookie + cuenta y
redirige a la misma página en ese idioma (recarga completa). En la API:
`PATCH /api/v1/me/locale`.

### Catálogo: tabla + archivos generados

`translation_strings` es la fuente de verdad (una fila por idioma + grupo +
texto): `source_text`, `translated_text`, `status`, `source_hash`,
`needs_review`, `obsolete_at`, `origins`.

| Grupo | Qué contiene | De dónde sale |
| --- | --- | --- |
| `ui` | Sitio web | `t()`, `$t()`, `tc()`, `<T>` en `resources/js` |
| `email` | Correos, notificaciones, mensajes del servidor | `__()`, `trans()`, `trans_choice()`, `@lang()` en `app`, `resources/views`, `routes` |
| `mobile` | App | `t()`, `tc()`, `<T>` en el repo móvil (`i18n.mobile_path`) |
| `content` | Textos con llave propia | `CatalogSync::upsertSource()` |

Estados: `missing` (pendiente) · `machine` (automática) · `reviewed` (la
editó una persona; la máquina no la vuelve a tocar) · `stale` (cambió el
original; se vuelve a traducir). Si cambia el original de una `reviewed`, se
conserva y solo se marca `needs_review`. Un texto que desaparece del código
se marca obsoleto (`obsolete_at`), no se borra.

Archivos **generados** por `i18n:sync` (no se editan a mano):

| Archivo | Uso |
| --- | --- |
| `lang/{locale}.json` | Catálogo JSON de Laravel (`__()`): `ui` + `email` |
| `lang/web/{locale}.json` | Lo que descarga el navegador (`GET /lang/{locale}.json?v=`) |
| `lang/mobile/{locale}.json` | Lo que descarga la app (`GET /api/v1/i18n/{locale}`) |
| `lang/mobile/es.json` | Lista de textos de la app (para servidores sin el repo móvil) |
| `{móvil}/src/i18n/locales/{locale}.json` | Catálogo incluido en la app |

`lang/es.json` (textos del framework) no se toca.

### Sincronizar

```bash
php artisan i18n:sync                 # extrae, traduce lo que falta, genera archivos
php artisan i18n:sync --locale=en     # solo un idioma
php artisan i18n:sync --dry-run       # qué cambiaría, sin guardar ni traducir
php artisan i18n:sync --no-translate  # extrae y regenera sin llamar al traductor
php artisan i18n:status               # idiomas, cobertura y traductor configurado
```

Flujo recomendado: en la máquina de desarrollo (tiene los dos repos) se
ejecuta `i18n:sync` y se suben los archivos generados. En producción,
`php artisan i18n:sync --no-translate` tras cada despliegue importa a la base
las traducciones que vienen en los archivos y regenera con las correcciones
hechas en el panel; el botón del panel hace la sincronización completa.
Un mismo texto usado en web y app se traduce una sola vez.

### Traductor automático

Contrato `App\Contracts\I18n\Translator::translateBatch()`; controlador
elegido con `I18N_TRANSLATOR`:

- **`anthropic`** — Claude, con el SDK oficial de PHP (`anthropic-ai/sdk`).
  Modelo `I18N_ANTHROPIC_MODEL` (por defecto `claude-opus-5-5`; alternativas
  más baratas: `claude-sonnet-5-5`, `claude-haiku-5-5`). El glosario de marca
  y las reglas van en un *system prompt* estable y **cacheado** (prompt
  caching); el idioma destino viaja en el mensaje para que todos los idiomas
  reutilicen la misma caché. Respuesta en JSON estricto (structured
  outputs). Se revisa `stop_reason`: un rechazo se salta (el texto queda
  pendiente), `max_tokens` parte el lote en dos. Con los modelos que lo
  admiten se activa el *fallback* del servidor ante rechazos.
- **`deepl`** — API HTTP de DeepL; marcadores y marca protegidos con `<x>`.
- **`null`** — no traduce (por defecto, y siempre que falte la llave).

Toda traducción se **valida** antes de guardarse
(`App\Services\I18n\TranslationValidator`): no vacía, mismos marcadores,
mismas etiquetas HTML, mismos saltos de línea y formas de plural, términos de
marca intactos y longitud razonable. Si falla se pide una vez más; si vuelve
a fallar queda pendiente.

**Costo.** Cada sincronización registra los tokens usados (entrada, salida,
caché) en `i18n_sync_runs` y en el log (`i18n: sync translator usage`); el
panel muestra el costo estimado con los precios de `i18n.pricing` (precios
de lista al 2026-11-03: confirmar en la página del proveedor). Solo se pagan
textos **nuevos o cambiados**: la primera pasada traduce todo y las
siguientes cuestan centavos. Se envían lotes de `I18N_BATCH_SIZE` (40)
textos; `I18N_ANTHROPIC_EFFORT=low` es suficiente para textos de interfaz.

### Contenido dinámico (lo que se captura en el panel)

Tabla `model_translations` (modelo, id, idioma, campo, valor, estado,
`source_hash`). Qué se traduce lo decide el mapa `i18n.models`:
`Product` (name, tagline, description, seo_*), `ProductVariant` (name),
`Collection` (name, tagline, description, tag_discipline_line,
tag_back_line, seo_*), `ProductCategory`, `PhotoExpansionPack`,
`ProductContentSection` (title), `CompanyMilestone`.

- Al crear o editar un registro, `I18nServiceProvider` encola
  `App\Jobs\I18n\TranslateModelFields` (solo si hay traductor).
- Lo existente: `php artisan i18n:translate-content {--model=} {--locale=*} {--force}`.
- Una traducción editada por una persona no se reemplaza; si cambia el
  original queda `needs_review`.

Lectura (el pequeño helper que cada recurso o prop puede adoptar):

```php
tr($product, 'name')                                   // idioma activo, español si no hay
app(ContentTranslations::class)->preload($products);   // una consulta para toda la lista
[...$data, ...app(ContentTranslations::class)->for($product)]
$category->tr('name')                                  // con el trait HasTranslations
```

### Web (`resources/js/i18n`)

- `initializeI18n()` (en `app.ts`, antes de montar) lee el idioma que
  renderizó el servidor y descarga `/lang/{locale}.json?v={version}` solo si
  no es español; nunca bloquea más que el *timeout* de la descarga.
- `installI18n` (`withApp`) da `$t` / `$tc` a todas las plantillas.
- `useI18n()` → `t`, `tc`, `locale`, `locales`, `switchLocale(code)`,
  `localizePath(path)`.
- `<LocaleSwitcher />` (`components/i18n`): menú accesible con los idiomas
  visibles; no se muestra si solo hay uno.

### App (`src/i18n`)

- Mismo contrato `t()` / `tc()` / `useI18n()`.
- Idioma: el elegido en la app → idioma del teléfono (`expo-localization`) →
  español.
- Catálogos incluidos en la app + actualización por API
  (`GET /api/v1/i18n`, `GET /api/v1/i18n/{locale}?v=` con ETag), guardados en
  AsyncStorage: textos e idiomas nuevos llegan sin publicar versión.
- El cliente HTTP envía `Accept-Language` y `X-Locale`.
- `<LanguagePicker />` (`components/i18n/language-picker.tsx`), montado en
  Configuración después de "Preferencias". Cambia la app al momento y guarda
  la elección en la cuenta (`PATCH /me/locale`) si hay sesión.
- Arranque: `initializeI18n()` en `src/app/_layout.tsx`; la pantalla de
  inicio (splash) se queda hasta que hay fuentes + sesión + idioma. Nunca
  lanza: si algo falla, la app abre en español.

**Cómo se actualiza la pantalla al cambiar de idioma** (la app se compila con
React Compiler, que guarda en caché cualquier `t('…')` sin entradas
reactivas):

- **Dentro de un componente o hook** el traductor sale siempre del hook:
  `const { t, tc } = useI18n()` (también `useT()` y `useLocale()`). El hook
  entrega un `t` **nuevo** cuando cambia el idioma o su catálogo, así que
  todo lo que lo usa se vuelve a pintar. `regional` da la etiqueta para
  `Intl` (`es-MX`, `en-US`).
- **Fuera de React** (mapas de errores, esquemas de zod, servicios, sesión
  NFC) se importa el `t` directo y se llama **en el momento de usarlo**,
  nunca al cargar el módulo. Una tabla de textos a nivel de módulo
  (`const COPY = { paid: 'Pago confirmado' }`) se congelaría en español:
  se escribe como función (`paymentPhaseCopy(phase)`, `failureCopy(…)`,
  `timeBucketLabel(…)`) o se construye al pintar (`buildTabs(t)`). En zod:
  `.min(1, { error: () => t('Ingresa tu correo.') })`.
- **Red de seguridad:** `<LocaleBoundary>` (`components/i18n/locale-boundary.tsx`)
  es el `screenLayout` de los navegadores: al cambiar de idioma **vuelve a
  montar cada pantalla** (no el navegador: se conserva dónde estás y el
  historial), así se refresca también lo que llega por funciones auxiliares
  (fechas, dinero, textos de error). Lo que vive fuera de una pantalla
  (barra de pestañas, mini reproductor, avisos) usa el hook de principio a
  fin.
- Fechas y números: `getRegionalLocale()` / `dateFormatter()` de
  `utils/dates.ts`; nada de `'es-MX'` fijo. La moneda sigue siendo la que
  manda la API (MXN) y los precios de la tienda de apps salen del SDK.
- Pruebas (Jest): corren en español (`jest.setup.js` simula AsyncStorage y
  el idioma del teléfono), por eso las que comprueban textos en español no
  cambian. `src/i18n/reactivity.test.tsx` cubre el cambio en caliente.

**Textos nativos de iOS** (permisos de cámara, fotos, ubicación, NFC y el
nombre de la app) no pasan por el catálogo: los escribe el sistema antes de
que corra JavaScript. Viven en `locales/es.json` y `locales/en.json` del
repo móvil (`expo.locales` en `app.json`, más
`ios.infoPlist.CFBundleAllowMixedLocalizations`). Si se cambia un texto de
permiso en `app.json` / `app.config.ts` hay que cambiarlo también ahí, en
los dos idiomas, y **requieren una compilación nativa nueva**. En Android
los diálogos de permiso son del sistema y ya salen en el idioma del
teléfono.

## Panel: Idiomas y traducciones

Ruta **`/admin/idiomas`** (Configuración → Idiomas), permiso **`i18n.manage`**
(admin y super_admin, migración `2026_11_12_500400`).

- Idiomas con su cobertura y el interruptor **visible al público** (oculto =
  el sitio y la app dejan de ofrecerlo; `/en/…` redirige al español).
- Buscar y editar cualquier texto; filtros por dónde aparece y por estado
  (automática, desactualizada, pendiente, revisada, por revisar). Guardar
  marca el texto como **revisado** y regenera los archivos al momento.
- Pestaña **Contenido del catálogo**: lo mismo para productos, colecciones…
- **Sincronizar y traducir ahora** (en cola) y las últimas corridas con sus
  tokens y costo estimado.

### Flujo de revisión

1. Se agregan o cambian textos en el código → `i18n:sync` (o el botón).
2. La máquina traduce lo nuevo; queda como *Automática*.
3. Una persona filtra por *Automática* / *Por revisar*, corrige lo necesario
   y guarda → *Revisada*.
4. Si más adelante cambia el texto original de una *Revisada*, aparece en
   *Por revisar* con su traducción intacta.

## Variables de entorno

| Variable | Para qué |
| --- | --- |
| `I18N_TRANSLATOR` | `anthropic` \| `deepl` \| `null` (por defecto `null`) |
| `ANTHROPIC_API_KEY` | Llave de la API de Claude |
| `I18N_ANTHROPIC_MODEL` | Modelo (por defecto `claude-opus-5-5`) |
| `I18N_ANTHROPIC_EFFORT` | `low` (defecto) \| `medium` \| `high` |
| `DEEPL_API_KEY` / `DEEPL_API_URL` | Llave de DeepL (`:fx` = plan gratuito) |
| `I18N_BATCH_SIZE` | Textos por solicitud (40) |
| `APP_SUPPORTED_LOCALES` | Opcional: limita/ordena los idiomas (`es,en`) |
| `I18N_MOBILE_PATH` | Carpeta `src` del proyecto móvil |
| `I18N_DETECT_BROWSER` | Leer `Accept-Language` (en pruebas va en `false`) |
| `I18N_REDIRECT_TO_PREFIX` | Redirigir a la gemela `/en` a quien eligió idioma |

## Pruebas

`tests/Feature/I18n/` (Pest): orden de resolución, gemela `/en` + hreflang,
cambio de idioma, sincronización con traductor falso (nuevos, obsoletos,
desactualizados, protección de revisados, validación), contenido dinámico,
endpoints de catálogo, panel y el controlador de Claude sin red.
App: `src/i18n/i18n.test.ts` (Jest).
