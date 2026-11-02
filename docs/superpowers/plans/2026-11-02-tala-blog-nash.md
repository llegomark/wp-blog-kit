# Tala Blog 2.0.0 (NASH "Drafting Paper") Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give Tala Blog NASH Reviewer's look (paper, cobalt, Inter, bands and rails, cobalt footer slab with
the site-title wordmark, ruled post rows, NASH article page, closing call to action, Light/Dark switch) as
version 2.0.0, without losing an ad position, speed or accessibility.

**Architecture:** Same theme, same slug. Tokens move into theme.json (palette with dark values in
`settings.custom.color.dark`, Inter `fontFace`, NASH type scale); `assets/theme.css` (inlined, as now) is
rewritten section by section; templates are restructured into bands; new template parts
(`call-to-action`, `footer-note`) ship empty; small PHP filters in `functions.php` do what blocks can't
(first category only, wordmark attributes, empty parts, footer menu columns); two Block Bindings sources
give the copyright year and reading time. One inline head script applies the stored Light/Dark choice.

**Tech Stack:** WordPress 7.1 block theme (theme.json v3, patterns, template parts, Block Bindings API),
PHP 8.5, PHPUnit on WordPress's test framework, Playwright e2e, WPCS, PHPStan level 8, Stylelint,
ESLint, Theme Unit Test.

**Spec:** `docs/superpowers/specs/2026-11-02-tala-blog-nash-design.md` (read it with this plan; NASH
sources: `~/Documents/github/tanstack-start-nash-reviewer/src/styles.css`,
`~/Documents/github/astro-nash-reviewer-blog/src/styles/{nash.css,global.css}`).

## Global Constraints

- Every value in the spec's tables (colours light/dark, type roles, radii, rail 60rem, rail padding
  `clamp(1rem, 0.25rem + 3vw, 2.5rem)`, measure-72) is copied exactly; NASH's sources break ties.
- Inter only (v4.1 files from NASH's `public/fonts/inter-v4.1/`, with `OFL.txt`); no font services, no other
  family; no build step; no jQuery; CSS inlined from `assets/theme.css`.
- Generic theme: no site names, NASH links or DepEd wording anywhere in `tala-blog/`.
- Works without Tala SEO / Tala Ads / Tala Guard; without a menu, header and footer use the page list.
- Headings never bold (300–450; 500 only on small labels); nothing below 13 px; 44 px targets; 2 px cobalt
  focus ring; no gradients on surfaces, no glows, no blur, no sticky header, no pills.
- Hover styles only in `@media (hover: hover) and (pointer: fine)`; all motion in
  `prefers-reduced-motion: no-preference`.
- Light/Dark switch: two states, key `localStorage['tala-theme']`, `<html data-theme="light|dark">`;
  no choice = `prefers-color-scheme`.
- Text domain `tala-blog`, prefix `tala_blog_`; every user-visible string translatable.
- After each task: `composer fix && composer check`, `npm run lint`, `composer test`, `npm run test:e2e`
  green; commits signed, ending with the `Co-Authored-By: Claude …` trailer, pushed (no release until Task 9).

## Review Focus

1. A long site title (30+ characters) in the footer wordmark on a 320 px screen: no horizontal scroll, the
   whole title visible → Task 4 test `wordmark fits any title at 320 px`.
2. A menu nested three levels deep shown as footer columns: third-level links listed under their parent, not
   lost or overlapping → Task 4 test `test_footer_menu_columns` (third-level case).
3. Storage blocked (Safari private mode, `localStorage` throws): no script error, the page follows the
   device, the switch still toggles for the visit → Task 2 e2e `switch works when storage is blocked`.
4. A post with no category, no excerpt and an author without a name (Theme Unit Test content): the title
   band prints no empty eyebrow, lead or byline element → Task 6 test `test_title_band_without_optional_parts`.
5. Dark chosen with the switch while the device is light: Gravity Forms, the PDF viewer toolbar and the
   comment form follow the switch, not the device → Task 8 e2e `embedded styles follow the switch`.

---

### Task 1: Tokens and Inter

**Files:**
- Modify: `tala-blog/theme.json` (palette, `settings.custom.color.dark`, font families + `fontFace`, font
  sizes, layout `contentSize: "45rem"`, `wideSize: "60rem"`, element/block styles to the new tokens)
- Create: `tala-blog/assets/fonts/{InterVariable-latin.woff2,InterVariable-latin-ext.woff2,InterVariable-Italic-latin.woff2,InterVariable-Italic-latin-ext.woff2,InterDisplay-LightItalic-latin.woff2,OFL.txt}`
- Modify: `tala-blog/functions.php` (new `tala_blog_font_preloads()`; `tala_blog_theme_color()` keeps working)
- Modify: `tala-blog/assets/theme.css` (top section: dark tokens, Inter fallback faces)
- Delete: `tala-blog/styles/colors/*.json`, `tala-blog/styles/typography/*.json`
- Modify: `AGENTS.md` (font rule)
- Test: `tests/phpunit/tala-blog/test-theme.php`

**Interfaces:**
- Produces: palette slugs `base surface contrast heading muted accent on-accent accent-deep wash border rule slab on-slab`
  (CSS `--wp--preset--color--<slug>`); dark values at `--wp--custom--color--dark--<slug>`; font family slug
  `inter` (`--wp--preset--font-family--inter`) and `inter-display` (Light Italic display cut); font size slugs
  `x-small` 0.8125rem, `small` 0.9375rem, `medium` 1.0625rem, `large` 1.1875rem, `x-large` fluid title
  `clamp(1.5rem, 1.3rem + 0.9vw, 2rem)`, `xx-large` fluid display `clamp(2.25rem, 1.4rem + 3.6vw, 3.75rem)`.
- Produces: `tala_blog_font_preloads(): void` on `wp_head` priority 1.

- [ ] **Step 1: Write the failing tests** (replace the obsolete preset tests)

```php
public function test_colour_presets(): void {
	$palette = array_column( wp_get_global_settings( array( 'color', 'palette', 'theme' ) ), 'color', 'slug' );
	$this->assertSame( array( 'base' => '#f4f4f2', 'surface' => '#fafafa', 'contrast' => '#393d45', 'heading' => '#000000', 'muted' => '#585f6c', 'accent' => '#1348dc', 'on-accent' => '#ffffff', 'accent-deep' => '#053794', 'wash' => '#ebebec', 'border' => '#dadde2', 'rule' => '#cbcfd4', 'slab' => '#1348dc', 'on-slab' => '#ffffff' ), $palette );
	$dark = wp_get_global_settings( array( 'custom', 'color', 'dark' ) );
	$this->assertSame( array( 'base' => '#16181d', 'surface' => '#1c1f26', 'contrast' => '#d3d6dc', 'heading' => '#f2f3f5', 'muted' => '#a3aab7', 'accent' => '#7d9bff', 'on-accent' => '#0b1433', 'accent-deep' => '#4f73e6', 'wash' => '#232730', 'border' => '#2e323b', 'rule' => '#2c3038', 'slab' => '#1640b8', 'on-slab' => '#ffffff' ), $dark );
}
public function test_style_variations_are_offered(): void { // rename: test_no_style_variations
	$this->assertSame( array(), WP_Theme_JSON_Resolver::get_style_variations() );
}
public function test_inter_font(): void {
	$families = array_column( wp_get_global_settings( array( 'typography', 'fontFamilies', 'theme' ) ), null, 'slug' );
	$this->assertStringStartsWith( '"Inter", "Inter Fallback", "Inter Fallback Roboto"', $families['inter']['fontFamily'] );
	$this->assertFileExists( get_theme_file_path( 'assets/fonts/OFL.txt' ) );
	$head = get_echo( 'wp_head' );
	$this->assertSame( 1, substr_count( $head, 'InterVariable-latin.woff2" as="font" type="font/woff2" crossorigin' ) );
	$this->assertSame( 1, substr_count( $head, 'InterDisplay-LightItalic-latin.woff2" as="font" type="font/woff2" crossorigin' ) );
	$this->assertLessThan( strpos( $head, '<style' ), strpos( $head, 'rel="preload"' ), 'Preloads before styles.' );
}
public function test_theme_colour(): void { // update expectations
	$head = get_echo( 'tala_blog_theme_color' );
	$this->assertStringContainsString( 'content="#f4f4f2" media="(prefers-color-scheme: light)"', $head );
	$this->assertStringContainsString( 'content="#16181d" media="(prefers-color-scheme: dark)"', $head );
}
```

Update `test_typography_presets` to the six size slugs above and `test_dark_colours_follow_a_colour_preset` /
`test_damaged_palette_is_harmless` to the new slugs (same behaviour, new names).

- [ ] **Step 2: Run them to see them fail**

Run: `composer test -- --filter 'test_colour_presets|test_no_style_variations|test_inter_font|test_theme_colour|test_typography_presets'`
Expected: FAIL (old palette, variations present, no preloads).

- [ ] **Step 3: Implement**

theme.json values from the spec; `fontFace` entries with `src` `file:./assets/fonts/…`, `fontDisplay: swap`,
`unicodeRange` for the latin-ext files (copy NASH's ranges from `src/styles.css`). `tala_blog_font_preloads()`
prints the two `<link rel="preload" href="…" as="font" type="font/woff2" crossorigin>` with
`get_theme_file_uri()`. theme.css top: the Inter Fallback / Inter Fallback Roboto / italic / display-italic
`@font-face` blocks with NASH's metric overrides; dark tokens applied under
`@media (prefers-color-scheme: dark) { :root:not([data-theme="light"]) { … } }` and `:root[data-theme="dark"] { … }`,
`color-scheme` set the same way (`:root[data-theme="light"] { color-scheme: light }`). Delete the variations.
AGENTS.md line 57: "No build step, no web fonts except Inter (self-hosted in the theme; maintainer's decision,
2026-11-02), no jQuery."

- [ ] **Step 4: Run the tests to see them pass; run all checks**

Run: `composer test -- --filter test-theme && composer fix && composer check && npm run lint`
Expected: PASS, no findings.

- [ ] **Step 5: Commit** — "Tala Blog: NASH tokens and Inter"

---

### Task 2: Light/Dark switch

**Files:**
- Modify: `tala-blog/functions.php` (`tala_blog_theme_switch_script(): void`, `wp_head` priority 0)
- Modify: `tala-blog/patterns/header.php` (switch button, `core/html`)
- Modify: `tala-blog/assets/theme.css` (switch button, hidden without JS)
- Test: `tests/phpunit/tala-blog/test-theme.php`, Create: `tests/e2e/theme-switch.spec.mjs`

**Interfaces:**
- Produces: `<button type="button" class="tala-theme-switch" aria-pressed="false|true" aria-label="Switch to dark theme|Switch to light theme">` with two inline SVG icons (sun, moon; `aria-hidden="true"`); `<html class="tala-js">` once the head script ran.

- [ ] **Step 1: Failing tests**

PHPUnit `test_theme_switch`: `wp_head` output contains one `<script id="tala-blog-theme-switch-js">` (via
`wp_print_inline_script_tag`) printed before any `<style`/`<link rel="stylesheet"`; it reads
`localStorage.getItem("tala-theme")` inside `try`, sets `document.documentElement.dataset.theme` only to
`"light"`/`"dark"`, adds class `tala-js`; the header pattern contains `class="tala-theme-switch"` with
`aria-pressed` and both translatable labels.

e2e `theme-switch.spec.mjs` (colorScheme `light` context):
- `switches to dark and back, and remembers it`: click → `html[data-theme="dark"]`, body background
  `rgb(22, 24, 29)`, `aria-pressed="true"`, label "Switch to light theme"; reload → still dark; click → light,
  background `rgb(244, 244, 242)`.
- `follows the device until a choice is made`: colorScheme `dark` context, no storage → background
  `rgb(22, 24, 29)`, no `data-theme` attribute.
- `switch works when storage is blocked`: `addInitScript` making `localStorage.getItem/setItem` throw → no
  `pageerror`, click still toggles for the visit.
- `no flash`: on a dark choice, the first paint (`page.screenshot` right after `domcontentloaded` with
  storage preset) is dark.
- `hidden without JavaScript`: `javaScriptEnabled: false` → the button is not visible.

- [ ] **Step 2: Run, see them fail** — `composer test -- --filter test_theme_switch; npx playwright test tests/e2e/theme-switch.spec.mjs` → FAIL.

- [ ] **Step 3: Implement**

Head script (inline, under 1 KB): apply stored choice; a `click` listener on `document` delegating to
`.tala-theme-switch` toggles `data-theme`, stores it (`try`), updates `aria-pressed`, the label (labels passed
from PHP with `wp_json_encode` of `__()` strings) and every `meta[name="theme-color"]` content (`#f4f4f2` /
`#16181d`, read from the existing metas' values); transitions disabled for one frame while switching (class
`tala-switching` on `<html>` → `* { transition: none !important }`). CSS: `html:not(.tala-js) .tala-theme-switch { display: none }`;
44 px outline icon button per spec; icon shown = the other theme.

- [ ] **Step 4: Run, see them pass; all checks** (as Task 1 Step 4 plus `npx playwright test tests/e2e/theme-switch.spec.mjs`).

- [ ] **Step 5: Commit** — "Tala Blog: Light/Dark switch"

---

### Task 3: Frame: bands, rail, header, menu sheet

**Files:**
- Modify: `tala-blog/templates/*.html` (every template: header part, `main` built from band groups, footer part)
- Modify: `tala-blog/patterns/header.php`, `site-brand.php`, `navigation-overlay.php`, `search.php`
- Modify: `tala-blog/assets/theme.css` (frame sections: bands, rails, nodes, graph paper, header, overlay sheet)
- Test: `tests/phpunit/tala-blog/test-theme.php` (`test_header`, `test_templates_and_parts`), `tests/e2e/layout.spec.mjs`, `tests/e2e/menu.spec.mjs`, `tests/e2e/a11y.spec.mjs`

**Interfaces:**
- Produces CSS classes used by later tasks: `.tala-band` (full-width group, bottom rule), `.tala-rail`
  (constrained inner group: `inline-size: min(100% - 1.5rem, 60rem)`, side rules, padding var
  `--tala-rail-pad`), `.tala-band--paper` (graph paper), `.tala-measure` (45rem text column), type role
  classes `.tala-display`, `.tala-title`, `.tala-subtitle`, `.tala-lead`, `.tala-eyebrow`, `.tala-meta`.
- Header pattern order: brand, Navigation (ariaLabel "Primary"), separator, Search (`buttonPosition: "button-only"`, `buttonUseIcon: true`), switch.

- [ ] **Step 1: Failing tests**

`test_header`: header has brand, `nav[aria-label="Primary"]`, a Search block with `wp-block-search__button-only`,
the switch, in that order. e2e layout: at 1280 px the rail's content box is 60rem (960 px) wide and centred;
header band ≥ 64 px tall and `position: static`; rail side rules visible (`border-inline` 1 px); graph paper
only on `.tala-band--paper`. menu: overlay opens as a sheet (`max-inline-size` 24rem from 40rem width, full
width at 390 px), links ≥ 48 px with hairlines; existing focus/escape/no-shift tests keep passing.

- [ ] **Step 2: Run, see them fail.**

- [ ] **Step 3: Implement** templates as `header part → main.tala-main > band groups → (Task 7 part) → footer part`;
drop the in-page `tala-blog/search` pattern from the tops of templates (search now lives in the header; the
home band and the 404 panel keep a search field);
CSS per spec §2 (diamond nodes as `.tala-band > .tala-rail::before/::after`, 7 px rotated squares, rule border,
surface fill; outer rules from 86rem at rule 55%). Overlay restyled as NASH's sheet, keeping the current
fade-in and focus behaviour.

- [ ] **Step 4: Run, see them pass; all checks; `node dev/unit-test-check.mjs` on `dev/unit-test-site.sh --serve`.**

- [ ] **Step 5: Commit** — "Tala Blog: bands, rails, header and menu sheet"

---

### Task 4: Footer slab, columns, wordmark, footer note

**Files:**
- Modify: `tala-blog/patterns/footer.php`, `footer-site.php`; Delete: `parts/footer-without-search.html`, `patterns/footer-without-search.php`
- Create: `tala-blog/parts/footer-note.html` (empty), theme.json `templateParts` entry `{ "name": "footer-note", "title": "Footer note", "area": "uncategorized" }`
- Modify: `tala-blog/functions.php`: `tala_blog_register_bindings(): void` (`init`) registering source
  `tala-blog/year`; `tala_blog_wordmark( string $html, array $block ): string` (`render_block_core/site-title`);
  `tala_blog_empty_part( string $html, array $block ): string` (`render_block_core/template-part`)
- Modify: `tala-blog/assets/theme.css` (footer section)
- Test: `tests/phpunit/tala-blog/test-theme.php`, `tests/e2e/layout.spec.mjs`

**Interfaces:**
- Produces: binding source `tala-blog/year` (`get_value_callback` returns `wp_date( 'Y' )`, the year in the site's timezone), used by a paragraph `© <year> <site title>`; `tala_blog_empty_part()` returns `''` when the part's inner HTML is whitespace only (Task 7 reuses it).
- Footer markup: `footer > .tala-slab > .tala-rail > .tala-cells` (brand cell: site-title `isLink:false`, site-tagline, template-part `footer-note`; Navigation `{"overlayMenu":"never","className":"tala-footer-menu","ariaLabel":"Footer"}`); bottom bar; `p.wp-block-site-title.tala-wordmark` (site-title block with `className: tala-wordmark`, `isLink: false`, `level: 0`).

- [ ] **Step 1: Failing tests**

- `test_footer_menu_columns`: with a `wp_navigation` holding "Grades" (submenu: Grade 1, Grade 2 (submenu: Grade 2 Math)) and "About" (no submenu): footer HTML has the Navigation with class `tala-footer-menu`, both submenus rendered open (no `wp-block-navigation__submenu-icon` button), "Grade 2 Math" present; without a menu, the page list renders.
- `test_wordmark`: footer site-title with `tala-wordmark` carries `aria-hidden="true"` and `style="--tala-chars:14"` for "DepEd Tambayan" (length of the title, spaces counted), no link.
- `test_year_binding`: rendering `<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"tala-blog/year"}}}} --><p></p><!-- /wp:paragraph -->` prints `wp_date( 'Y' )`.
- `test_empty_footer_note_prints_nothing`: footer HTML has no `wp-block-template-part` wrapper for the empty part; with content saved in a `wp_template_part` post (slug `footer-note`, theme `tala-blog`), its text shows.
- e2e `wordmark fits any title at 320 px`: set blogname to 40 characters → no horizontal scroll at 320 px, wordmark box within the viewport; at 1280 px "DEPED TAMBAYAN"-length title spans ≥ 90% of the rail.
- e2e footer contrast: links on slab ≥ 4.5:1 (axe), footer columns 2-up at 390 px.

- [ ] **Step 2: Run, see them fail.**

- [ ] **Step 3: Implement** per spec §2 Footer (cells grid with 1 px gaps on `rule`-at-white-24%, link
underline white 45% → solid on hover, uppercase bottom bar, hatch band, wordmark `text-transform: uppercase;
font: 300 calc(100cqi / var(--tala-chars) * 1.7)/0.8 var(--wp--preset--font-family--inter);
-webkit-text-stroke: 2px color-mix(in srgb, #fff 45%, var(--wp--preset--color--slab)); color: var(--wp--preset--color--slab)`,
band `overflow: hidden` clipping the letters' bottoms, `container-type: inline-size` on the band). Footer menu
CSS shows submenus static and open (`.tala-footer-menu .wp-block-navigation__submenu-container { position: static; opacity: 1; visibility: visible; … }`),
top-level items as columns.

- [ ] **Step 4: Run, see them pass; all checks.**

- [ ] **Step 5: Commit** — "Tala Blog: footer slab, menu columns and wordmark"

---

### Task 5: Lists: header band, ruled rows, chips

**Files:**
- Modify: `tala-blog/patterns/post-list.php`, `templates/{home,index,archive,search,author}.html`
- Modify: `tala-blog/functions.php`: remove `tala_blog_list_thumb_sizes()`; add `tala_blog_first_term( string $html, array $block ): string` (`render_block_core/post-terms`, only for `className` containing `tala-first-term`: keeps the first `<a>`, drops the rest and separators)
- Modify: `tala-blog/assets/theme.css` (lists section)
- Test: `tests/phpunit/tala-blog/test-theme.php`, `tests/e2e/layout.spec.mjs`

**Interfaces:**
- Consumes: band/rail/type classes (Task 3).
- Produces: `tala_blog_first_term()` (Task 6 uses it for the eyebrow); row markup `.tala-row` > `.tala-row__meta` (post-date + post-terms `tala-first-term`) + `.tala-row__text` (post-title h2 link, post-excerpt 40 words).

- [ ] **Step 1: Failing tests**

- `test_list_rows`: home list HTML has no `<img`, no `wp-block-post-featured-image`; each row has a `time` and only the first category link of a post with three categories. Remove `test_list_thumbnail_sizes`.
- `test_home_header_band`: home template's first band is `.tala-band--paper` with site-title as `h1.tala-display`, tagline as lead, a Search block, a `core/categories` list (`showOnlyTopLevel`) with class `tala-chips`.
- e2e: at 1280 px rows are a `9rem 1fr` grid; at 390 px stacked; title link hover underline cobalt; `test_page_numbers_are_a_choice` unchanged.

- [ ] **Step 2: Run, see them fail.** **Step 3: Implement** (spec §3 lists). **Step 4: Run, pass; all checks.**

- [ ] **Step 5: Commit** — "Tala Blog: NASH post rows and list header band"

---

### Task 6: Article and page

**Files:**
- Modify: `tala-blog/patterns/{article,page,post-terms,post-footer,comments,hidden-404}.php`, `templates/{single,page,404}.html`
- Modify: `tala-blog/functions.php`: binding source `tala-blog/reading-time` in `tala_blog_register_bindings()`; `tala_blog_reading_minutes( WP_Post|int $post ): int`; `tala_blog_contents_box()` markup → `<details class="tala-contents">`
- Modify: `tala-blog/assets/theme.css` (article, prose, contents, post footer, comments, 404)
- Test: `tests/phpunit/tala-blog/test-theme.php`, `tests/e2e/semantics.spec.mjs`, `tests/e2e/structured-content.spec.mjs`

**Interfaces:**
- Consumes: `tala_blog_first_term()` (Task 5), band/type classes (Task 3), year binding registration function (Task 4).
- Produces: `tala_blog_reading_minutes()` = `max( 1, (int) ceil( word_count / 220 ) )`, word count of `wp_strip_all_tags( do_blocks( post_content ) )` via `str_word_count`-free whitespace split (`preg_split( '/\s+/u', …, -1, PREG_SPLIT_NO_EMPTY )`); binding label "%d min read" (`_n()`).

- [ ] **Step 1: Failing tests**

- `test_reading_minutes`: 0 words → 1; 220 → 1; 221 → 2; 660 → 3; Filipino text with "ñ" counts words.
- `test_title_band`: single post HTML: band `.tala-band--paper` containing breadcrumbs (with Tala SEO only), eyebrow with the first category only, `h1.tala-display`, excerpt lead, "By {author}", Published/Updated dates and "N min read".
- `test_title_band_without_optional_parts`: no category, no excerpt, author with empty display name → no empty `.tala-eyebrow`, `.tala-lead` or byline element.
- `test_table_of_contents` (update): contents box is `<details class="tala-contents">` with `<summary>` "On this page" and "N sections", closed by default, ordered list of links; existing when/where rules unchanged.
- `test_related_articles` / `test_post_navigation_is_a_named_landmark` (update): no thumbnails; cells markup.
- e2e: article h2 has a rule above; blockquote 2 px cobalt left rule on wash; tables scroll inside their box at 390 px; 404 panel shows "404" and a search field; axe clean light and dark.

- [ ] **Step 2: Run, see them fail.** **Step 3: Implement** (spec §3 single, pages, 404, comments). **Step 4: Run, pass; all checks; Theme Unit Test.**

- [ ] **Step 5: Commit** — "Tala Blog: NASH article, page and 404"

---

### Task 7: Closing call to action

**Files:**
- Create: `tala-blog/parts/call-to-action.html` (empty); theme.json `templateParts` `{ "name": "call-to-action", "title": "Call to action", "area": "uncategorized" }`
- Create: `tala-blog/patterns/call-to-action.php` (Title "Closing call to action", Categories `call-to-action`, Block Types `core/template-part/uncategorized`, neutral copy: eyebrow "Next step", heading "Ready for the next step?", lead "Tell readers where to go next.", button "Get started" → `#`)
- Modify: `templates/{home,index,archive,search,author,single,page}.html` (part before the footer)
- Modify: `tala-blog/assets/theme.css` (CTA band and card with the 6 px offset shadow)
- Test: `tests/phpunit/tala-blog/test-theme.php`, `tests/e2e/standalone.spec.mjs`

**Interfaces:** Consumes `tala_blog_empty_part()` (Task 4).

- [ ] **Step 1: Failing tests** — `test_call_to_action_part`: empty part prints nothing on a single post; a saved `wp_template_part` (slug `call-to-action`) prints once, after the content and before `<footer`; the pattern registers with the title and contains `.tala-band--paper`, `h2.tala-display`, one `wp-block-button`. e2e standalone: no CTA markup without content.
- [ ] **Step 2: fail. Step 3: implement. Step 4: pass, all checks. Step 5: Commit** — "Tala Blog: closing call-to-action part"

---

### Task 8: Forms, plugins, classic content, print, editor

**Files:**
- Modify: `tala-blog/assets/{gravity-forms-orbital.css,gravity-forms-2-5.css,pdf-embedder.css}`, theme.css (classic content, gallery, author box, print)
- Test: `tests/phpunit/tala-blog/test-theme.php` (`test_gravity_forms_styles_follow_its_stylesheets` unchanged), Create: `tests/e2e/embedded-styles.spec.mjs`

- [ ] **Step 1: Failing test** — e2e `embedded styles follow the switch`: device light, switch dark → comment form fields, a Gravity Forms field (seeded form markup with GF's classes) and the PDF toolbar fixture use dark tokens (background `rgb(28, 31, 38)` surface, text `rgb(211, 214, 220)`); light → light tokens. Static: the three CSS files use `--wp--preset--color--*` only (grep for hex in them → none except fallbacks).
- [ ] **Step 2: fail. Step 3: implement** (dark rules keyed on the same selectors as Task 1, controls 4 px radius, borders `border`, focus ring). **Step 4: pass; all checks; Theme Unit Test by eye (handbook checklist).**
- [ ] **Step 5: Commit** — "Tala Blog: forms, PDF viewer and classic content in the new look"

---

### Task 9: Version, docs, screenshots, quality gates, release

**Files:**
- Modify: `tala-blog/style.css` (`Version: 2.0.0`, description), `tala-blog/readme.txt` (changelog 2.0.0, Inter/OFL credit under Resources/Copyright), `tala-blog/screenshot.png` (1200×900, regenerated), README.md / AGENTS.md mentions of the old look
- Test: Lighthouse, side-rail check, `build.sh`

- [ ] **Step 1:** `node dev/screenshots.mjs` (theme screenshot + readme shots) and look at them light and dark, phone and desktop.
- [ ] **Step 2:** Lighthouse (mobile, PSI settings) on the local article with ads off: Performance ≥ 90, Accessibility/Best Practices/SEO = 100; record numbers in the commit message. CLS from the theme = 0 (e2e layout test).
- [ ] **Step 3:** All checks + `node dev/unit-test-check.mjs` + `wp-content/debug.log` empty.
- [ ] **Step 4:** `./build.sh`; commit "Tala Blog 2.0.0: NASH Reviewer's look" (body: what changed, the decisions, the numbers); push; wait for Checks + release.yml; verify the release (sha256, attestation, local build match).
- [ ] **Step 5:** Install on both sites (scp → `wp-site <site> theme install … --force` → rm); create per-site `wp_template_part` posts (theme `tala-blog`): `call-to-action` with the NASH promotion (spec §2 copy, `utm_source=teacherph|depedtambayan`) and `footer-note` "Independent resource site. Not affiliated with the Department of Education (DepEd)."; full refresh both sites (nginx clear-all → Cloudflare purge_everything → warmer).
- [ ] **Step 6:** Verify live: in-article ad, Innity span/box, Auto ads, side rails at 1366/1440/1536/1920 (if missing where 1.13 had them, narrow the rail in a 2.0.1); switch; footer columns and wordmark; Lighthouse; screenshots to the maintainer.
