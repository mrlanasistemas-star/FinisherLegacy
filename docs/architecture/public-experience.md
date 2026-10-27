# Public experience — Finisher Legacy Tag (2026-10-27)

The public site sells and explains the **Finisher Legacy Tag**: a
factory-finished collectible tag with strap, designed per **collection**,
with NFC + unique URL + backup QR + printed code. Three layers drive every
page: **objeto** (premium physical memory) → **historia** (tap / scan opens
it) → **permanencia** (the story keeps growing).

Concept line: **"El recuerdo físico de tu logro. Tu historia vive dentro."**
Domain: `docs/architecture/commerce-os.md`.

## Visual direction

- Marketing pages (Home, Colecciones, Cómo funciona, tag product hero):
  dark cinematic chapters (`bg-fl-black` / `bg-fl-graphite`, white ink)
  alternating with warm paper (`bg-fl-paper`, `bg-fl-cream`). Gold is an
  accent only: eyebrows, 1px rules, the tag's grommet, **one** gold CTA per
  page (final CTA).
- Classic, refined: Fraunces display (`font-serif`) + Instrument Sans UI.
  Compact buttons (pill, `h-11` = 44px, `px-6`, `text-sm`), secondary
  actions are text links with a gold underline
  (`underline decoration-fl-gold underline-offset-[6px]`), never fat
  full-width buttons.
- Only existing `fl-*` tokens; the only per-record color is a
  collection's `accent_color`, validated server-side as a hex
  (`PublicCollections::safeColor`) and used only for artwork accents.
- Motion tells the story and plays once (IntersectionObserver via
  `useInView`, bounded fallback so content never stays hidden):
  tag appears → callouts; phone approaches → NFC pulse → story opens;
  the 6-step gold line fills. All of it is off under
  `prefers-reduced-motion`. No scroll hijacking, no loops.
- The public navbar turns transparent with light ink over dark heroes
  (`DARK_HERO_PAGES` in `PublicNavbar.vue`, plus `store/Show` for
  `legacy_tag` products) and back to paper once scrolled.

## Page map

| URL | Page | Controller | Data |
|---|---|---|---|
| `/` | `pages/Home.vue` | `HomeController@index` | `collections` (published, ≤6), `tagProducts` (active `legacy_tag`, ≤3) |
| `/colecciones` | `pages/collections/Index.vue` | `Store\CollectionController@index` | published collections |
| `/colecciones/{slug}` | `pages/collections/Show.vue` | `Store\CollectionController@show` | collection + its active products (price via `ResolveProductPrice`, availability via `IsVariantAvailableForCheckout`) + 3 other collections. **404** if unpublished (`status != active` or `launch_at` in the future). |
| `/tienda/{slug}` | `pages/store/Show.vue` → `components/store/TagProductDetail.vue` when `type = legacy_tag` | `Store\ProductController@show` | adds `collection` (only if published), `specs` (material, strap_type, dimensions, instructions), `seo_*`; tag variants priced by `ResolveProductPrice`, `in_stock` by `IsVariantAvailableForCheckout`. Other product types: unchanged layout. |
| `/tienda` | `pages/store/Index.vue` | `Store\ProductController@index` | `featured` = first `legacy_tag` (historical plate only if no tag) |
| `/como-funciona` | `pages/HowItWorks.vue` | route `inertia` | static (7 moments, 3 ways to open, FAQ) |
| `/sitemap.xml` | — | `SitemapController` | home, colecciones, cómo funciona, tienda, eventos, nosotros, contacto, published collections, active products, published events. **Never** profiles, stories, `/t/…`, recipient links. |

`public/sitemap.xml` (static, hard-coded domain) was removed so the
dynamic route answers; `public/robots.txt` keeps `Sitemap: /sitemap.xml`
and now also disallows `/t/`, `/activar`, `/mi-legacy`, `/legacy/para/`.
Note: robots.txt is static, so the Sitemap line is relative; set it to the
absolute production URL at deploy time.

`/activar` and `/mi-legacy` belong to the activation stream; public pages
link to them through `resources/js/config/public-links.ts` (Wayfinder
`activate.index` / `my-legacy.index`).

### Navigation

Header: Inicio · Colecciones · Cómo funciona · Mi Legacy · Activar.
Contextual CTA: guest → "Explorar colecciones"; signed in → "Mi Legacy".
Footer: Tu tag (Colecciones, Cómo funciona, Activar, Mi Legacy) · Explora
(Tienda, Eventos, Comunidad, Fotos) · Finisher Legacy (Nosotros, Ayuda y
contacto, Fotógrafos) · Legal.

## Components

| Component | Role |
|---|---|
| `components/tag/TagVisualizer.vue` | **Illustrative SVG render** of the tag: portrait body with thickness + rim light, punched eyelet with metal grommet, woven strap through it, grain + single sheen, collection artwork (name, subtitle, year, accent contour lines, NFC mark) on the front; illustrative non-scannable QR, masked code and NFC hint on the back. Props: `name`, `subtitle`, `year`, `accent`, `face`, `tilt`, `label`. Mouse-only 3D tilt, off for reduced motion. |
| `components/home/HomeHero.vue` | Hero: real start-line video (≥md, no reduced motion; pauses off-screen) over poster `<picture>`; concept headline; CTAs. |
| `components/home/FinishStory.vue` | A — "Tu carrera termina. Tu historia no." |
| `components/home/TagObject.vue` | B — "Un recuerdo que puedes tocar." front/back toggle + part callouts (no numbers). |
| `components/home/TapDemo.vue` | C — wordless demo tag → NFC/QR → story (example profile). |
| `components/home/StoryPreview.vue` | D — example Legacy: photo, facts, dedication, audio waveform, certificate (drawings, labelled "Ejemplo"). |
| `components/home/CollectionsShowcase.vue` | E — real collections; fallback to tag products; honest empty state. |
| `components/home/RecipientsBlock.vue` | F — recipient albums (familia, pareja, amigos, entrenador). |
| `components/home/LegacySequence.vue` | G — Compites → Consigues tu tag → Lo activas → Agregas tu historia → La compartes → Regresas a ella. |
| `components/home/HomeFinalCta.vue` | H — emotional close, gold commercial CTA + activation link. |
| `components/collections/CollectionCard.vue` | Collection card (cover or drawn tag), real "desde" price. |
| `components/collections/TagProductTile.vue` | Product tile (photo or drawn tag) with resolved price. |
| `components/store/TagProductDetail.vue` | Tag product page: gallery/visualizer, purchase card (existing cart endpoint), Qué incluye, specs only if filled, Cómo funciona, Qué pasa después de comprarlo. |
| `composables/useInView.ts` | One-shot in-view flag with reduced-motion + fallback. |
| `app/Queries/Store/PublicCollections.php` | Read side of public collections (cards, detail, sitemap). |

The previous Home components under `components/public/home/*` (plate era)
are no longer used by the Home; they were left in place for the lead to
remove once nothing else references them.

## Asset inventory

| Path | Type | Resolution | Aspect | Used for |
|---|---|---|---|---|
| `media/home/hero/finisher-hero-desktop.mp4` | video (4.0 MB) | — | 16:9 | Home hero loop, ≥md only |
| `media/home/hero/finisher-hero-desktop.webm` | video (2.4 MB) | — | 16:9 | same, preferred source |
| `media/home/hero/finisher-hero-poster.webp` | image | 1280×720 | 16:9 | Hero poster ≥md |
| `media/home/hero/finisher-hero-poster-mobile.webp` | image | 750×422 | 16:9 | Hero poster on phones |
| `media/home/story/medal-closeup-{640,667}.webp` | image | 640×960 / 667×1000 | 2:3 | Block A portrait, Cómo funciona "Regresas" |
| `media/home/story/race-effort-{640,1000}.webp` | image | 640×427 / 1000×667 | 3:2 | Block A secondary frame |
| `media/home/story/finish-emotion-{640,1000}.webp` | image | 640×427 / 1000×667 | 3:2 | Phone screen in TapDemo (example story) |
| `media/home/story/training-dawn-{640,1000}.webp` | image | 640×640 / 1000×1000 | 1:1 | Example story photo (Block D, Cómo funciona) |
| `media/home/story/training-dawn.{mp4,webm}` + `-poster.webp` | video + poster | poster 1280×720 | 16:9 | not used (available for a future story chapter) |
| `media/home/story/*.jpeg` | originals | same as webp | — | not referenced (sources) |
| `media/home/scan/scan-phone.png` | image, white bg | 1080×1080 | 1:1 | Cómo funciona "Acercas" on paper (`mix-blend-multiply`); unsuitable on dark |
| `media/home/final/legacy-final-poster-{640,1000}.webp` | image | 640×427 / 1000×667 | 3:2 | Block H background |
| `images/brand/og-finisher-legacy.jpg` | image | 1200×630 | 1.91:1 | OG image (Home) |
| `images/brand/logo/logo-mark*.png` | image | 1400×671 | ~2.1:1 | Navbar/footer wordmark (white on dark heroes) |
| `media/brand/plate/*` | renders | various | — | **OLD product (Legacy Plate)** — not used on new public pages; still served for historical plate products |
| `media/products/concepts/nfc-keychain-*.webp` | concept render | 480×600…1200×1500 | 4:5 | not used (concept, not the tag) |

### Pending

- **Real tag photography / 3D render** — every tag on the site is the
  illustrative `TagVisualizer` and is captioned "Render ilustrativo".
  When photos exist, upload them as `ProductMedia` (product page uses them
  automatically) and as collection `cover_path` / `hero_path`.
- Tag specs (material, correa, medidas, instrucciones) appear only when
  filled in admin; no numbers are hard-coded.
- Absolute `Sitemap:` URL in `robots.txt` at deploy time.
- Mobile sticky purchase bar for the tag product page (generic products
  keep theirs).
