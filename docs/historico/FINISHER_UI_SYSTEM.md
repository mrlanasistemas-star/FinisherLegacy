# Finisher Legacy — UI System (Premium Visual Round)

> **Operative guide.** This is the single source of truth for the public-web visual
> round. Every implementation agent reads this before touching a component and
> follows it. It is not aspirational theory — it encodes concrete tokens, class
> names, file locations and decisions. When this doc and a component disagree,
> fix the component.

---

## 0. Direction lock (non-negotiable)

**LIGHT, warm, editorial.** Warm-white / cream canvas · graphite + near-black as
structure and contrast · warm **metallic** gold as the *earned* accent · editorial
sports photography · lots of air · magazine composition.

Target feeling: an international tech-sports brand. **NOT** a Laravel template,
a SaaS dashboard, a generic store grid, or a landing-page-builder page.

**NEVER regress** to the previous predominantly-dark design: no black backgrounds
everywhere, no gold text everywhere, no dashboard-card walls. (Brief §127.)

**The one rule above the rules:** the page is a *magazine*, not a card wall.
Every ~100vh should present a *new kind* of visual idea, not another instance of
the last one.

**Real data only (Brief §97).** No invented athletes, events, likes, reviews or
stats. Illustrative UI must be labelled `Ejemplo` / `Vista previa` (the repo
already does this via `.hw-sample`, "Ejemplo" chips, LegacyProfilePreview). Design
to the props the controllers actually send — see §13.

---

## 1. Color tokens

All defined in `resources/css/app.css` `:root`. Use the **semantic** tokens for
surfaces; use `fl-*` only for ink/accent/photo work.

| Token | Value | Use |
|---|---|---|
| `--background` | `#f8f7f3` | page canvas (warm paper) |
| `--card` | `#ffffff` | raised surfaces |
| `--muted` / `--secondary` | `#f1eee7` | quiet fills, chip rest |
| `--accent` (`--fl-cream`) | `#f4eedf` | warm tint blocks |
| `--foreground` (`--fl-black`) | `#171714` | primary ink |
| `--fl-graphite` | `#2a2925` | dark contrast sections, secondary ink |
| `--muted-foreground` (`--fl-stone`) | `#6b675e` | captions, meta |
| `--border` (`--fl-line`) | `#e7e3da` | hairlines |
| `--fl-gold` | `#c9a45c` | fills, rules, active strokes (sparingly) |
| `--fl-gold-ink` | `#85662b` | gold **text** that passes contrast on paper |
| `--fl-gold-soft` / `--fl-gold-dim` | `#e8d6ad` / `#a8843f` | gradient stops for metal sheen |

**Rules**
- Default surfaces are paper/white/cream. Graphite/black is a *contrast block*,
  not a background (NFC moment, 3D product, footer, one storytelling section,
  media overlays). If a screen is >~15% dark, pull back.
- **Never** introduce a new saturated brand color. Discipline keeps gold precious.
- Replace stray raw palette colors (`text-emerald-600` in Contact,
  `text-red-500` in Preregister) with tokens (`--destructive`, a success token).

---

## 2. Gold — make it metal, not casino

1. Gold is a **hairline-and-accent material**, never a large fill. Appears as:
   1px rules, active-state strokes, small badges, icon micro-highlights, the
   avatar achievement ring, the active segmented-tab indicator.
2. For *metal* moments (medal, Legacy Plate sheen) use the gradient
   `--fl-gold-soft → --fl-gold → --fl-gold-dim`, not flat bright yellow.
   See `legacy-line` / `fl-shine` / `fl-plate-sweep` already in `app.css`.
3. **Gold means achievement** — finisher, medal, verified, premium, earned. Do
   not spend it on generic CTAs. The primary button is **graphite/near-black**,
   not gold (retire the repeated `bg-fl-gold text-fl-black` CTA — see §8).
4. Gold **text** only for single words / tiny eyebrows, and only via
   `--fl-gold-ink` on light, or `--fl-gold`/`--fl-gold-soft` on the dark section.
5. Physicality from light (a slow single sheen sweep), never neon glow or
   looping shimmer.

---

## 3. Typography

Two families, **strict roles**:
- **Fraunces** (`--font-serif`, `.fl-display`) = display / editorial headlines,
  pull quotes, numbers-as-statement. Weights 300–500. Never body copy.
- **Instrument Sans** (`--font-sans`) = everything functional: body, UI, labels,
  buttons, metadata, feed text, stat numbers (tabular). Never headlines.

**Fluid scale (use `clamp()` everywhere).** Target ramp — implement as helper
classes in `app.css` (`.fl-display` exists; add the rest in B0):

| Role | clamp() | line-height |
|---|---|---|
| Display | `clamp(2.75rem, 6vw, 5.75rem)` | 1.02–1.06 |
| H1 | `clamp(2rem, 4vw, 3.5rem)` | 1.08 |
| H2 | `clamp(1.5rem, 2.6vw, 2.5rem)` | 1.12 |
| H3 | `clamp(1.25rem, 1.8vw, 1.6rem)` | 1.2 |
| Body-lg | `clamp(1.0625rem, 1.1vw, 1.1875rem)` | 1.6 |
| Body | `1rem` | 1.6 |
| Caption | `0.8125rem` | 1.4 |

- Eyebrow = `.fl-eyebrow` (uppercase, +0.22em tracking, `--fl-gold-ink`). It is the
  editorial-sports signature — keep using it, centrally.
- Display tracking slightly negative (`-0.02em`, already in `.fl-display`).
- **Reading measure capped at 60–75ch** even inside wide sections
  (`max-w-[68ch]` / `max-w-prose`). Paragraphs never run full-bleed.
- **Spanish-first:** pressure-test headlines in Spanish (≈15–20% longer than
  English); honor ¿ ¡ and accents; `text-wrap: balance` is already on h1–h3.
- Numbers: add `.legacy-numeric` (already exists) on times/pace/distance/stats.

---

## 4. Radius system (tokenize — replaces ~19 ad-hoc radii)

Add to `app.css` `@theme` so utilities exist; use **only** these:

| Utility | Value | Use |
|---|---|---|
| `rounded-full` | 9999px | pills, chips, avatars, icon buttons |
| `rounded-control` | `0.625rem` (`--radius`) | buttons, inputs, selects, small controls |
| `rounded-card` | `1rem` | standard cards, post cards, product cards |
| `rounded-media` | `1.25rem` | media frames, galleries, cover images |
| `rounded-hero` | `1.75rem` | large editorial / hero blocks, dark CTA block |

- **Fix the mismatch:** `.fl-card` currently hardcodes `0.75rem`; retune it to
  `var(--radius-card)` so the shared card and utilities agree.
- Delete arbitrary `rounded-[20px]/[24px]/[1.5rem]/[1.75rem]/[2rem]/[22px]…`
  and map each to the nearest token above (big editorial card → `rounded-hero`).
- `rounded-xl`/`rounded-2xl`/`rounded-3xl` → migrate to `card`/`media`/`hero`.
  (Don't mass-rewrite admin in this round; public surfaces first — Brief §108.)

---

## 5. Shadow system (max 3 elevations + control micro — replaces ~25 ad-hoc)

Add to `app.css` `@theme`; use **only** these. Warm-tinted, never gray.

| Utility | Value | Use |
|---|---|---|
| (control) `shadow-fl-micro` | `0 1px 2px rgb(23 23 20 / 0.04)` | inputs, selects, resting chips |
| `shadow-fl-soft` | `0 2px 10px -4px rgb(23 23 20 / 0.08)` | resting cards |
| `shadow-fl-float` | `0 18px 40px -28px rgb(23 23 20 / 0.32)` | hover lift, floating layers |
| `shadow-fl-overlay` | `0 32px 64px -24px rgb(23 23 20 / 0.40)` | modals, sheets, hero product layer |

- Replace every one-off `shadow-[...]` with one of these; normalize stray
  `rgba()` shadows to the `rgb(... / ...)` form.
- `.fl-hover-lift` already lands on a float-class shadow — keep it; it becomes the
  canonical card hover.

---

## 6. Spacing & rhythm (break the monotony)

Section vertical rhythm — add three classes, **alternate them**, never run the
same one down a whole page (Brief §87; desktop 80–144, mobile 48–80):

| Class | padding | feel |
|---|---|---|
| `.fl-section-sm` | `py-12 sm:py-16` | tight / adjacent-to-prev |
| `.fl-section` | `py-16 sm:py-24` | standard |
| `.fl-section-lg` | `py-24 sm:py-32 lg:py-36` | spacious / chapter opener |

- At least one section per long page is a **whitespace statement**: ~70% empty
  warm-white, a single Fraunces line, huge margins.
- Chapter the page by **container width changes** (see §7) and by the cream/
  graphite flip — not by putting a hairline border on every section. Reduce the
  292 `border border-border`: prefer whitespace / background-step / `legacy-line`
  divider over boxing.

---

## 7. Layout & the module vocabulary (anti–"card card card")

`.fl-container` (`max-w-[80rem]`, responsive gutters) stays the default. Vary width
deliberately to signal chapters:
- **Full-bleed** (100vw) — hero media, one immersive section, mobile feed/media.
- **Contained** (`fl-container`, ~1280px) — most sections.
- **Reading measure** (`max-w-[68ch]`/`max-w-2xl`) — statements, article text.

**Module types** — compose pages by alternating these; never twice in a row:
1. Full-bleed media statement
2. Whitespace statement (big Fraunces line, air)
3. Contained card cluster (**2–3 cards max**, real air)
4. Horizontal rail (products / events / athletes — edge-bleed, peek next item)
5. Split editorial (image one side, text other — **alternate** the side each use)
6. Sticky-storytelling (pinned media, text steps scroll past — no scroll-hijack)

- **Grids are for the store index/catalog only.** The homepage gets rails +
  whitespace, not a 3–4 col grid.
- Section headers are editorial: `.fl-eyebrow` + `.fl-display` (+ optional gold
  hairline). "Ver todo" is a **quiet underlined text link**, not a SaaS button.
  Use the shared `SectionHeading.vue` — Home sections currently roll their own;
  standardize on it.

---

## 8. Components & interaction states

**Buttons** (`components/ui/button`): primary = solid **graphite/near-black**
pill/control; secondary = outline (`border-foreground/15`); tertiary = quiet
underlined text link. **Retire** the hand-typed `bg-fl-gold text-fl-black` CTA
scattered across CTASection/About/Error/Preregister/photographers — if a gold
button is truly needed, make it a single `variant`, used rarely.

**Cards** = `.fl-card` (white, hairline, `shadow-fl-soft`, `rounded-card`) +
`.fl-hover-lift` on interactive ones. Product cards must be **stable**: drop the
pointer-follow 3D tilt in `ProductCard.vue`; hover = gentle lift + optional second
-image crossfade only.

**Inputs/selects**: `rounded-control`, `shadow-fl-micro`, gold focus ring
(already in `app.css`). Errors via the shared `InputError` component (not inline
`text-red-500`).

**Interaction states (all interactive elements):**
- `hover`: lift `translateY(-2px…-4px)` + `shadow-fl-float`, or tint shift. Images
  scale ≤1.04. No big jumps.
- `focus-visible`: `.fl-focus-glow` (gold ring). Never remove focus outlines.
- `active`: `scale-95` on taps.
- `disabled`: reduced opacity, no shadow.
- Min touch target **44px** (`min-h-11`), everywhere tappable.

---

## 9. Motion

Tokens exist in `app.css` (`--legacy-duration-*`, `--legacy-ease`). Use them.
- **Reveals:** `motion/Reveal.vue` + `motion/StaggerGroup.vue` are the canonical,
  fail-open, reduced-motion-aware primitives. **Standardize on them** — stop
  hand-rolling per-section IntersectionObserver/keyframes. Reveal = opacity
  0.01→1 + translateY **12–24px** (not 80px), 450–700ms, stagger 60–100ms, once.
- **Entrances** choreographed, not simultaneous: media first, then headline lines,
  then CTA/floating layer. Total < ~1.2s. One easing (`--legacy-ease`).
- **Sticky storytelling**, never scroll-hijack. Scroll drives ≤2 hero moments
  (plate sheen / slight rotate), `transform`/`opacity` only.
- **Micro-interactions** 150–250ms (reaction pop, follow check — already exist).
- Animate **only** `transform` & `opacity`. Never `top/left/width/height`. Use
  `IntersectionObserver`, not per-frame scroll handlers. Hold ~60fps on mid phone.
- `prefers-reduced-motion` is a first-class path: content at final state, no
  parallax, no sheen loops, sticky degrades to stacked. (All existing components
  already guard this — keep it.)

---

## 10. Media & images

- Fixed `aspect-ratio` on every media box (no CLS). `object-position` tuned where
  crops matter. `<picture>`/`srcset`/`sizes` for art-directed crops (landscape
  desktop, portrait mobile for heroes).
- `loading="lazy"` + `decoding="async"` below the fold; `fetchpriority="high"`
  only for the above-fold hero. `width`/`height` always set.
- Gradients only for **legibility** (scrim the text quadrant), not gray overlays
  everywhere — let photos breathe. No expensive CSS noise; reuse existing light
  textures only.
- **Adopt the orphaned hero video** (`media/HomeHeroMedia.vue`) and story sequence
  (`media/StoryMediaSequence.vue`) — they already have video→poster→CSS fallbacks,
  offscreen-pause, reduced-motion handling. Video: muted, `playsinline`, poster,
  no audio autoplay. One active video at a time (intersection-gated).
- 3D (`@google/model-viewer`) stays **lazy / on-interaction only** (already is via
  async `Product3DViewer` + in-`onMounted` import). Never load GLB on Home/lists.

---

## 11. Dark contrast sections

Permitted and encouraged as the *exception*: NFC moment, 3D product frame, footer,
one storytelling band, the closing CTA, media overlays. On dark (`--fl-graphite`/
`--fl-black`): invert type to paper, gold hairlines/words pop here (gold's best
stage), keep contrast WCAG-safe. One dark band per page max (plus footer).

---

## 12. Responsive — recompose, don't shrink

Three structural tiers; decide composition per module, fluid `clamp()` between:
- **Phone** `<640` · **Tablet** `~640–1024` · **Desktop** `>1024`.
- QA breakpoints (Brief §54/§94): 320, 360, 375, 390, 430, 480, 640, 768, 820,
  1024, 1280, 1366, 1440, 1600, 1920, 2560. No hardcoding to 1440.

Rules:
- **Grids → horizontal rails on mobile** (products/categories/medals/athletes):
  edge-bleed, scroll-snap, hidden scrollbar, peek of next card, 44px targets.
- **Split editorial** → stacks vertically on mobile, image full-bleed on top.
- **Feed & media go edge-to-edge on phone** (0 side margin); contained on desktop.
  No card-in-card, no 16px border around everything on mobile. Post = full-width
  container, header/caption padded 16px, media 100vw, footer padded 16px.
- Heroes re-crop per tier (portrait on phone). Use `svh`/`dvh`, not exact `100vh`.
- Right rails **hidden on tablet** (768–1024) — feed centers; no squeezed sidebar.
- Honor `safe-area-inset-*` for any fixed bottom UI (`--fl-bottom-bar` already
  used by BackToTop/cart bars).

---

## 13. Per-surface decisions (real data; file-anchored)

### Home (`pages/Home.vue` + `components/public/home/*`)
- Hero: switch `StoryHero` to a **full-bleed cinematic** archetype using
  `HomeHeroMedia`; ONE floating product layer, gold as a hairline/one word; copy
  "Tu historia. / Tu legado." + 3 CTAs (Descubrir / Eventos / Tienda tertiary).
- Collapse the **NFC/phone/plate motif to ONE signature moment** (it currently
  appears in Hero + LegacyJourney + NfcMoment). Keep the NfcMoment reveal; thin
  the others.
- WhatIsIt: this is the **"Finisher Legacy conecta tus…" section the owner
  explicitly called ugly** (a flat statement + a static breakdown of carreras/
  productos/etc). It must become a genuinely **interactive ecosystem**, not a
  feature-card grid and not a static list. Concrete spec:
  - Extract a dedicated `LegacyEcosystem.vue` component.
  - **Desktop:** a center medallion (FINISHER LEGACY / logomark, thin metallic
    gold ring) with 6 satellite nodes — Carreras · Legacy Plate · Fotos · NFC ·
    Comunidad · Apoyo. **Gold connector lines draw** from center to each node as
    the section enters view (SVG `stroke-dashoffset` animation, staggered via
    IntersectionObserver), then nodes fade/scale in (Reveal/Stagger). **Hover a
    node:** it lifts, its connector brightens (gold glow), its short description
    reveals. Subtle **cursor parallax** on the nodes (≤8%, pointer-only). Optional
    scroll-progress drives a faint rotation/line-draw so moving the page feels
    alive. Use the `legacy-line` motif + `--fl-gold*` gradient.
  - **Mobile:** NO cramped diagram — a vertical "connected" flow or horizontal
    snap-rail of node cards linked by a progressive vertical gold line (like the
    Legacy timeline fill); tap a node to expand its copy; scroll-snap + peek next.
  - **Reduced motion:** lines appear already drawn, nodes at final state, no
    parallax. `transform`/`opacity` only; hold 60fps on mid-range phones.
  - This section is the showcase of the owner's "fluid/beautiful/modern/
    interactive" directive — give it real craft; it is the product explained
    visually before any text (§130).
- Replace flat plate SVG with the **photoreal raster render** (`MEDIA.plate.*`,
  already in repo, unused on Home) in hero/store moments.
- Vary section rhythm (§6); use `Reveal`/`StaggerGroup` consistently; feed the
  Eventos band through `SectionHeading`; stop discarding 3 of 6 community posts;
  use the unused `legacyPlateSlug` to surface the plate CTA.

### Community (`pages/community/*`, `components/community/*`)
- Compact app-style header (shrink the `fl-display` hero). **Sticky segmented/
  pill tabs** — reuse the pill style `Connections.vue` already has (consistency).
- Composer: collapsed one-line pill → **modal (desktop) / bottom-sheet (mobile)**.
  Photos-only (video pipeline not accepted; don't add video affordances).
- **Rebuild `PostMediaGrid`** with exact layouts: 1 full (cap 4:5/16:9) · 2 equal
  cols · 3 = large-left + 2 stacked · 4 = 2×2 · 5+ = 2×2 with gold `+N` tile.
  Fixed 2–4px gutter, uniform radius, add a lightbox; media edge-to-edge on phone.
- Post card **media-dominant**, less chrome; drop card border on phones.
- `Show.vue`: **split detail** (media left / caption+actions+comments right) when
  media present; text posts stay centered reading measure. **Borderless comments**
  (avatar + bubble/indent, hover actions, spacing not per-item boxes).
- Keep one "Celebrar" reaction (brand identity) with its gold pop.
- Enrich `PostGearHighlight` (product image, link to the product), suggestions
  (real reason/mutuals only — never invent). Delete deprecated `PostCard.vue`
  shim; align `PostPreview` to `reactions.total`.
- Backend contracts (`CommunityPost` type, `MomentResource`, `FeedQuery`, Social
  Actions, `Inertia::scroll` infinite feed) are **stable — do not change**.

### Public profile (`pages/profile/Show.vue`, `components/profile/*`)
- **Full-bleed/panoramic cover** with avatar overlapping it; gold achievement ring
  on the avatar. Currently a contained rounded card.
- **Sticky tabs** (currently static). Real tabs shipped: `publicaciones · historia
  · logros · fotos`. **Do not invent "Equipo"/"Legado"** — no data. Match the
  feed's tab language.
- Stats are real keys `medals · events · posts` (+ followers/following/fotos).
  Make a clean **inline stat row** with hairline dividers, not a 6-into-3 wrapped
  grid, not boxed KPI tiles.
- Media grid → **editorial/deterministic masonry-safe** grid (not rigid
  `grid-cols-3` squares). Pass the richer `TimelineEntry` fields (`imageUrl`,
  `position`, `hasLegacyPlate`, `photoCount`) that `Show.vue` currently drops.
- "Historia" timeline → adopt the **animated line-fill + scroll-lit dots** that
  currently live only in the unused demo `public/LegacyTimeline.vue`, applied to
  the real `LegacyTimelineList` with real data. This is the brand differentiator.

### Store (`pages/store/*`, `components/shared|store/*`)
- `Index.vue` is already editorial — keep; just brand the title (not "Tienda").
- **`Catalog.vue` gets an editorial hero** (it's the real "H1 + plain grid"
  offender). Replace `FancySelect` Tipo/Orden with **chip/segmented** controls
  (chips already exist for categories/sizes/NFC).
- **Stabilize `ProductCard`** (drop pointer tilt). Keep 2-col mobile; stable
  aspect ratio; gentle lift + second-image crossfade.
- Product detail already strong: gallery-led split, sticky buy panel, lazy 3D,
  mobile swipe. Add desktop swipe indicators; ensure 3D poster always resolves.
- Consolidate on `store/VariantPicker.vue`; drop redundant `shared/VariantSelector`
  (keep its exported type).

### Events / HowItWorks / About / Contact / Photos / Photographers
- Events `Index`: lift the admin-like header to an editorial hero; featured event
  larger, rest compact; align radii/empty-state. Events `Show`: **use `SeoHead`**
  (fold JSON-LD into its slot) instead of the inline meta block.
- Events `Preregister`: normalize to `fl-container`/`fl-card`, shared `InputError`,
  default `Button`.
- HowItWorks: it's the strongest editorial page but a **style island** (`.hw-*`,
  6× `rounded-[2rem]`). Keep its quality; migrate its local radii/shadows to the
  tokens so other pages can match it. Make it the 5-chapter visual experience
  (Corre · Conecta · Guarda · Comparte · Revive) with NFC shown visually.
- About: break the 7× identical `py-16 sm:py-24` + border-on-every-section
  monotony with whitespace/scale.
- Photos: swap the hand-rolled lightbox for the shared `Dialog`; replace hardcoded
  hrefs with Wayfinder helpers. Photographers: same href fix.
- Errors/404 already branded — keep; swap the gold button for the standard variant.

### Navbar / shell (`components/public/PublicNavbar.vue`, `PublicFooter.vue`)
- Keep the fixed 64px navbar + `pt-16` compensation and the 5 links. Keep the
  `scrolled` transition; refine toward transparent-at-top → white-glass + hairline
  + ultralight shadow on scroll. Mobile = the existing shadcn `Sheet` (keep,
  refine). Replace hardcoded `/fotografos` etc. with Wayfinder helpers.
- This file is **cross-cutting** (every page) — change it carefully and last among
  implementation tasks; QA all surfaces after.

---

## 14. Accessibility (WCAG-sane, Brief §92/§93)

- Contrast: body/ink on paper passes AA; gold **text** only via `--fl-gold-ink` on
  light. Check gold-on-dark too.
- Keyboard: everything operable; visible `focus-visible` (`.fl-focus-glow`); dialogs
  & sheets trap focus (Reka handles it — keep). Menus keyboard-navigable.
- `aria-label` on icon-only buttons; reaction/counts readable by SR; composer errors
  associated with inputs; media `alt` (author/event context); dialog labelled.
- `prefers-reduced-motion` fully usable (see §9).

---

## 15. Performance budget (Brief §100)

- No giant new dependency (existing stack is enough: Vue, Tailwind v4, Reka,
  VueUse, Lucide, tw-animate, model-viewer). Justify any `npm install` first.
- LCP = optimized hero (poster + lazy video). CLS ≈ 0 (reserve media dimensions).
- Feed media lazy; one active video; 3D lazy; below-fold images lazy.

---

## 16. Working agreement for this round

- **Tokens first** (B0): add radius/shadow/spacing/type helpers to `app.css` and
  fix `.fl-card`, *before* component work, so everything references them.
- Edit the existing Home/Community/Profile components — **don't** rewrite `Home.vue`
  into 2000 lines or rebuild social from scratch (Brief §113/§114).
- Extract a component only if used 2+ times or it owns a clear domain
  (`PostMediaGrid`, `ProfileHero`, `NfcDemo`, `EventFeatureCard`) — no
  `GenericHero`/`PrettySection` (Brief §112).
- **Do not run** lint/format/types/build/ssr until the very end (user instruction;
  Brief §124). Backend tests only if props/controllers change (they shouldn't).
- Admin is out of scope except shared primitives that improve safely (Brief §108).

### Tells to avoid → achieve
| SaaS/template (avoid) | Premium tech-sports (achieve) |
|---|---|
| Uniform card grid everywhere | Alternating module types, varied widths |
| Boxed KPI stat tiles | Inline stat rows, hairline dividers |
| Permanently-open composer + toolbar | Collapsed one-line composer pill |
| Bright yellow gold fills/glows | Desaturated warm gold hairlines; metal gradient on medals only |
| Centered logo-soup hero | One art-directed hero archetype per surface |
| Dense catalog on homepage | Editorial rails + whitespace statements |
| Everything animates at once | One choreographed reveal per section, one easing |
| Layouts shrink on mobile | Layouts recompose; grids → rails |
