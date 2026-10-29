# Public experience — Finisher Legacy Tag (updated 2026-10-29)

The public site sells and explains the **Finisher Legacy Tag**: a
factory-finished collectible tag with strap, designed per **collection**,
with an **NFC chip** that opens its unique URL (`/t/{code}`). **The tag has
no QR** — not on the product, the packaging, prints, the activation flow or
marketing copy. If a phone can't read NFC, the fallback is typing the tag's
code at `/activar`. Three layers drive every page: **objeto** (premium
physical memory) → **historia** (bring the phone close and it opens) →
**permanencia** (the story keeps growing).

Concept line: **"El recuerdo físico de tu logro. Tu historia vive dentro."**
Domain: `docs/architecture/commerce-os.md`.

## Visual direction

- **LIGHT only.** Every public page is warm paper (`bg-background` /
  `bg-fl-paper` #F8F7F3), white cards and `bg-fl-cream` bands with warm
  ink (#171714). **No black or graphite backgrounds** anywhere; the
  navbar is the same light bar on every page (no transparent-over-dark
  mode). Gold (#C9A45C) is an accent only — eyebrows, 1px rules, focus
  rings, the tag's grommet; gold *text* uses `text-fl-gold-ink` (#85662B)
  for contrast.
- Classic, refined: Fraunces display (`font-serif`, `fl-display`) +
  Instrument Sans UI. Compact pill buttons (`h-11`/`h-12`, `px-6`,
  `text-sm`); secondary actions are text links with a gold underline.
- Radii and shadows come from the token scale only: `rounded-control`,
  `rounded-card`, `rounded-media`, `rounded-hero`, pills
  `rounded-full`; `shadow-fl-micro/soft/float/overlay`.
- Micro-interactions are quiet: hover lift (`fl-hover-lift` or
  `hover:-translate-y-0.5` + `shadow-fl-float`), image zoom
  (`fl-hover-zoom`), gold focus rings (`focus-visible:ring-fl-gold/40`).
  All transitions/animations are off under `prefers-reduced-motion`.
- Only existing `fl-*` tokens; the only per-record color is a
  collection's `accent_color`, validated server-side as a hex
  (`PublicCollections::safeColor`) and used only for artwork accents.

## Page map

| URL | Page | Controller | Data |
|---|---|---|---|
| `/` | `pages/Home.vue` | `HomeController@index` | `collections` (published, ≤6), `tagProducts` (active `legacy_tag`, ≤3) |
| `/colecciones` | `pages/collections/Index.vue` | `Store\CollectionController@index` | published collections |
| `/colecciones/{slug}` | `pages/collections/Show.vue` | `Store\CollectionController@show` | collection + its active products (price via `ResolveProductPrice`, availability via `IsVariantAvailableForCheckout`) + 3 other collections. **404** if unpublished (`status != active` or `launch_at` in the future). |
| `/tienda/{slug}` | `pages/store/Show.vue` → `components/store/TagProductDetail.vue` when `type = legacy_tag` | `Store\ProductController@show` | adds `collection` (only if published), `specs` (material, strap_type, dimensions, instructions), `seo_*`; tag variants priced by `ResolveProductPrice`, `in_stock` by `IsVariantAvailableForCheckout`. Other product types: unchanged layout. |
| `/tienda` | `pages/store/Index.vue` | `Store\ProductController@index` | `featured` = first `legacy_tag` (historical plate only if no tag) |
| `/como-funciona` | `pages/HowItWorks.vue` | route `inertia` | static (7 moments with a pinned visual, how it opens — NFC, typed code as fallback — and FAQ) |
| `/activar` | `pages/activate/Index.vue` | `LegacyTagsActivationController@index` | manual code entry (fallback for phones without NFC) next to the `NfcTapVisual` illustration |
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

### Home (`pages/Home.vue`, light, 2026-10-29)

Hero with the **interactive 3D tag** (`components/three/LegacyTag3D.vue`,
three.js loaded lazily, poster first; front and back) → `HomeSteps`
(cómo funciona in 3 steps) → `TagFaces` (frente y reverso) →
`CollectionsShowcase` (real collections, fallback to tag products, honest
empty state) → `StoryPreview` (example story page, labelled "Ejemplo") →
`CommunityTeaser` → `HomeFaq` → `HomeFinalCta`. All sections are light;
the 3D/SVG tag wears the first published collection or a neutral
"Tu colección". The Home and `components/home/**`, `components/three/**`
and `components/tag/**` are owned by the tag work stream — check their
file headers for the current behaviour.

### Shared

| Component | Role |
|---|---|
| `components/tag/TagVisualizer.vue` | Illustrative SVG of the tag (front: collection artwork, name, subtitle, year, accent lines, NFC mark; back: NFC hint — **no QR**). Props: `name`, `subtitle`, `year`, `accent`, `face`, `tilt`, `label`. |
| `components/public/NfcTapVisual.vue` | SVG illustration of the one gesture: phone close to the tag → NFC waves → the story opens on screen. Used by Cómo funciona ("Acercas") and `/activar`. Unique SVG ids per instance; waves stop under reduced motion. |
| `components/collections/CollectionCard.vue` | Collection card (cover or drawn tag), real "desde" price. |
| `components/collections/TagProductTile.vue` | Product tile (photo or drawn tag) with resolved price. |
| `components/store/TagProductDetail.vue` | Tag product page: gallery/3D viewer, purchase card (existing cart endpoint), Qué incluye, specs only if filled, Cómo funciona, Qué pasa después de comprarlo. |
| `composables/useInView.ts` | One-shot in-view flag with reduced-motion + fallback. |
| `app/Queries/Store/PublicCollections.php` | Read side of public collections (cards, detail, sitemap). |

## Asset inventory

| Path | Type | Resolution | Aspect | Used for |
|---|---|---|---|---|
| `media/home/hero/finisher-hero-poster.webp` | image | 1280×720 | 16:9 | Hero poster ≥md |
| `media/home/hero/finisher-hero-poster-mobile.webp` | image | 750×422 | 16:9 | Hero poster on phones |
| `media/home/story/medal-closeup-{640,667}.webp` | image | 640×960 / 667×1000 | 2:3 | Block A portrait, Cómo funciona "Regresas" |
| `media/home/story/race-effort-{640,1000}.webp` | image | 640×427 / 1000×667 | 3:2 | Block A secondary frame |
| `media/home/story/finish-emotion-{640,1000}.webp` | image | 640×427 / 1000×667 | 3:2 | Phone screen in TapDemo (example story) |
| `media/home/story/training-dawn-{640,1000}.webp` | image | 640×640 / 1000×1000 | 1:1 | Example story photo (Block D, Cómo funciona) |
| `media/home/story/training-dawn.{mp4,webm}` + `-poster.webp` | video + poster | poster 1280×720 | 16:9 | not used (available for a future story chapter) |
| `media/home/story/*.jpeg` | originals | same as webp | — | not referenced (sources) |
| `media/home/final/legacy-final-poster-{640,1000}.webp` | image | 640×427 / 1000×667 | 3:2 | Block H background |
| `images/brand/og-finisher-legacy.jpg` | image | 1200×630 | 1.91:1 | OG image (Home) |
| `images/brand/logo/logo-mark*.png` | image | 1400×671 | ~2.1:1 | Navbar/footer wordmark  |
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
