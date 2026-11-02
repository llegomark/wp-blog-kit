# Tala Blog 2.0.0: NASH Reviewer's look ("Drafting Paper")

Date: 2026-11-02. Status: approved in conversation. This spec is the written record, for review before planning.

## Intent

The maintainer finds Tala Blog "a dead theme": grey, plain, no footer links, no light/dark switch. Every
site on the theme (teacherph.com, depedtambayan.net, and any future one) should look like the
maintainer's NASH Reviewer (nash.com.ph). Ads, revenue, speed, accessibility and every improvement made
so far stay. Success means:

- The pages read as the same family as nash.com.ph: same paper, ink, cobalt, Inter type, rules, bands,
  footer slab and wordmark.
- AdSense (Auto ads, the in-article unit) and Innity (box, Universal tags, in-read) earn as before:
  no position is lost, side-rail ads still fit on common laptop widths.
- Lighthouse on a mobile article without ads: Performance ≥ 90, Accessibility, Best Practices and SEO 100;
  the theme itself causes no layout shift.
- The repo's checks pass, including WordPress's Theme Unit Test, and the theme still works without the
  plugins.

## Decisions (the maintainer's)

| Question | Decision |
|---|---|
| How close to NASH | "What I want is what NASH looks like." Follow NASH's design system closely. |
| Colour | NASH's cobalt, the same on every site ("same for all WordPress sites"). |
| Font | Inter, self-hosted like NASH. Overrides the repo rule "no web fonts" for Inter only. |
| Post lists | NASH-style ruled rows, no thumbnails. |
| Footer links | Columns built from each site's header menu, plus an independence note in the brand column. |
| Footer wordmark | The site's title in giant capitals, outlined, like NASH's "NASH": DEPED TAMBAYAN, TEACHERPH. |
| Closing call to action | A band at the end of each page promoting NASH Reviewer. |
| Light/dark switch | A header switch with Light and Dark only (no "System" choice). |
| Approach | Redesign Tala Blog in place, released as 2.0.0 (not a new theme, not a style variation). |

## Source of truth

NASH's tokens and components: `~/Documents/github/tanstack-start-nash-reviewer/src/styles.css` (tokens;
the YAML front matter of its DESIGN.md is out of date) and the blog port
`~/Documents/github/astro-nash-reviewer-blog/src/styles/{nash.css,global.css}` (article prose, contents,
chips, rows, footer). Where this spec gives a value, it is NASH's; where it differs, the reason is given.

## 1. Foundations

### Colours

Theme presets (theme.json palette, slugs kept stable where Tala Blog already has them) with NASH's light
and dark values. Dark values go through the existing `tala_blog_preset_dark_colors()` filter path.

| Role (slug) | Light | Dark |
|---|---|---|
| base (paper) | `#f4f4f2` | `#16181d` |
| surface | `#fafafa` | `#1c1f26` |
| contrast (body ink) | `#393d45` | `#d3d6dc` |
| heading | `#000000` | `#f2f3f5` |
| muted (meta) | `#585f6c` | `#a3aab7` |
| accent (cobalt) | `#1348dc` | `#7d9bff` |
| on-accent | `#ffffff` | `#0b1433` |
| accent-deep (button lip) | `#053794` | `#4f73e6` |
| wash-muted (hover, inline code) | `#ebebec` | `#232730` |
| border (controls, cards) | `#dadde2` | `#2e323b` |
| rule (lines, rails) | `#cbcfd4` | `#2c3038` |
| slab (footer) | `#1348dc` | `#1640b8` |
| on-slab | `#ffffff` | `#ffffff` |

Derived in CSS: wash = cobalt at 7% (callouts, blockquotes); gridline = muted at 7% (graph paper).
`theme-color` meta follows paper (`#f4f4f2` / `#16181d`). Cobalt stays scarce above the footer (NASH: at
most two accents per viewport).

The style variations in `styles/colors` and `styles/typography` are removed: one identity on every site.

### Light/dark switch

- A 44 px outline icon button in the header (sun in dark mode, moon in light mode), `aria-label`
  "Switch to dark theme" / "Switch to light theme", `aria-pressed` reflecting dark.
- Two states only. Before a reader chooses, the page follows `prefers-color-scheme`; a click sets
  `data-theme="light"` or `"dark"` on `<html>` and stores it (`localStorage`, key `tala-theme`). The
  stored choice applies on every page.
- A tiny inline script in `<head>` (before CSS paints) applies the stored choice, so there is no flash.
  No jQuery, no dependency; without JavaScript the page follows the device and the button is hidden.
- CSS: dark tokens apply under `@media (prefers-color-scheme: dark) { :root:not([data-theme="light"]) … }`
  and under `:root[data-theme="dark"]`; `color-scheme` follows. Ad frames keep `color-scheme: light`
  (Tala Ads), so they stay see-through.
- Theme switching runs with transitions off (as NASH).

### Inter, self-hosted

- Files from NASH's `public/fonts/inter-v4.1/` (Inter v4.1, SIL OFL; `OFL.txt` ships with them) in
  `tala-blog/assets/fonts/`: `InterVariable-latin.woff2` (89 KB), `InterDisplay-LightItalic-latin.woff2`
  (42 KB), and the latin-ext and italic subsets loaded only for the characters they cover
  (`unicode-range`), as NASH does.
- Declared through theme.json `fontFace` (so the editor shows Inter too), `font-display: swap`.
- The two Latin files are preloaded (`<link rel="preload" as="font" type="font/woff2" crossorigin>`).
- Fallback faces with NASH's metric overrides, which download nothing: "Inter Fallback" (`local()` Arial,
  Arimo, Liberation Sans; size-adjust 106.43%, ascent 91.02%, descent 22.66%, line gap 0) and the Roboto
  versions for Android, plus the italic and display-italic variants. The swap barely moves the page.
- Stack: `"Inter", "Inter Fallback", "Inter Fallback Roboto", ui-sans-serif, system-ui, -apple-system,
  "Segoe UI", sans-serif`. Inter for headings, body and code (NASH has no mono face).
- AGENTS.md: "no web fonts" becomes "no web fonts except Inter, self-hosted in the theme (decision
  2026-11-02)". Still no font services, no other families.

### Type

Body 1.0625rem / 1.65; small 0.9375rem; meta and eyebrow 0.8125rem; nothing below 13 px. No root
font-size. `font-synthesis: none`; `text-wrap: balance` on h1–h4; tabular numbers on `time` and tables.

| Role | Use | Spec |
|---|---|---|
| display | home header title, post title, page title, call to action, 404 | cobalt, `clamp(2.25rem, 1.4rem + 3.6vw, 3.75rem)`, weight 300 italic (Inter Display), -0.035em, line height 1.08 |
| title | section headings, archive titles | heading ink, `clamp(1.5rem, 1.3rem + 0.9vw, 2rem)`, 400, -0.025em, 1.2 |
| subtitle | row and card titles | heading ink, 1.125rem, 450, -0.012em, 1.35 |
| lead | summaries under titles | `clamp(1.0625rem, 1rem + 0.3vw, 1.1875rem)`, 1.65 |
| eyebrow | category label, section labels | muted, 0.8125rem, 500, uppercase, 0.08em |
| meta | dates, reading time, breadcrumbs | muted, 0.8125rem, tabular |

Headings are never bold (300–450; 500 at most on small labels). theme.json font sizes map to this scale.

### Shape, depth, motion, focus

- Radius: controls 4 px, chips and badges 2 px, cards and panels 0 (square). No pills.
- Depth is hairlines: 1 px `rule` for structure, 1 px `border` for controls and cards, 2 px cobalt for
  active indicators. Primary buttons have NASH's 2 px inset lip (`accent-deep`); outline buttons a faint lip.
  One raised element at a time may carry the flat 6 px cobalt-10% offset shadow (the call-to-action card).
  No gradients on surfaces or buttons, no glows, no blur.
- Focus: `:focus-visible` 2 px cobalt outline, offset 2 px.
- Targets at least 44 px (buttons, menu links, chips, the switch).
- Motion: 150 ms colour, background and border transitions; hover styles only under
  `(hover: hover) and (pointer: fine)`; everything that moves sits inside
  `prefers-reduced-motion: no-preference`. Nothing animates on scroll.

## 2. Page frame

### Bands and rail

- Every section is a full-width band with a 1 px bottom rule. Inside it, a centred rail with 1 px side rules
  and NASH's 7 px diamond nodes where the rail meets a band's top rule.
- Rail width 60rem, not NASH's 70rem: AdSense side-rail ads need free space beside the content, and
  side rails earned about 12% of depedtambayan's October AdSense income. At 1366 px a 70rem rail leaves
  about 120 px per side; 60rem leaves about 200 px. Measured before release at 1366, 1440, 1536 and
  1920 px. Inner padding `clamp(1rem, 0.25rem + 3vw, 2.5rem)` everywhere, so edges line up.
- The article text column is `measure-72` (about 45em of 17 px text), centred in the rail.
- Graph paper (16 px grid of `gridline`) only behind the home header band, post and page title bands,
  the call to action and the 404 panel; never behind dense text.

### Header

- One band, at least 4rem tall, not sticky (NASH forbids it). Brand on the left: the site logo when one is
  set (Site Logo block), then the site title at 1.1875rem, 400, heading ink.
- Menu (the site's Navigation block): NASH ghost links (15 px, 500, heading ink, 44 px tall, hover on
  `wash-muted`), a vertical separator, Search (the Search block as a button that opens into a field, styled
  as NASH's outline button), the light/dark switch.
- Narrow screens (the Navigation block's overlay): a 44 px outline menu button opens the overlay styled as
  NASH's side sheet: full width on phones, 24rem from wider screens, paper background, links 17 px with
  hairlines between them, current link in cobalt.

### Footer slab

- Full-bleed cobalt (`slab`), white text, rules at white 24%. Bottom safe-area padding.
- A hairline grid of cells (NASH `.cells`):
  - Brand cell: site title (1.3125rem), tagline (Settings → General) as the blurb, and a "Footer note"
    template part below it in small text, which the theme ships empty (an empty part prints nothing). On
    teacherph and depedtambayan it is filled in the Site Editor with "Independent resource site. Not
    affiliated with the Department of Education (DepEd)." A separate part keeps the footer itself
    uncustomized, so later theme updates still reach it.
  - Link columns: the site's header menu (Navigation block without a fixed menu, which uses the site's
    menu), shown with every submenu open: each top-level item with a submenu becomes a column with its
    label as the heading (h2-sized 1.1875rem, 400) and its links below (15 px, 40 px tall); top-level
    items without a submenu gather in a last column. Links underlined at white 45%, solid white on hover.
  - Phone: two columns (brand cell spanning both); from 48rem of footer width the brand cell takes 2fr
    and the columns share the rest when the menu has one or two columns; with three or more, the brand
    cell takes a row of its own and the columns share the whole rail below it (final review M1: at 2fr
    beside five columns, words like "Kindergarten" broke mid-word).
- Bottom bar: rule above, 0.75rem uppercase, 0.12em tracking, 80% opacity: "© {year} {site title}" left,
  the tagline right.
- Wordmark band (`aria-hidden="true"`): NASH's -45° hatch, the site title in capitals in Inter 300, outlined
  (fill = slab, stroke = white at 45%), sized so the whole title spans the rail (font size from the title's
  length, via container units), bottoms of the letters clipped by the band, as on NASH.
- The footer search field goes; search lives in the header.

### Closing call to action

- A template part "Call to action" placed after the content on posts, pages and the home and archive
  lists, before the footer. The theme ships it empty: an empty part prints nothing.
- A theme pattern "Closing call to action" gives the NASH band: graph-paper band, eyebrow, display h2,
  lead, one primary button and a short meta line, in a surface card with the offset shadow.
- On teacherph and depedtambayan the part is filled in the Site Editor (saved in each site's database,
  editable there) with NASH Reviewer's promotion: draft copy "Aiming to lead a school?" / "Practise for the
  NASH with timed, situational questions written against the PPSSH and scored by depth of thinking. Free
  to start." / button "Start practicing free" to
  `https://nash.com.ph/?utm_source=<site>&utm_medium=referral&utm_campaign=closing-cta`.
- One call to action per page (NASH's rule): nothing similar higher on the page.

## 3. Templates

- Home and archives (category, tag, author, date, search results): a graph-paper header band (eyebrow,
  display title, the description or tagline as lead; on the home page also a search field and chips for the
  top-level categories), then NASH rows: from 44rem of width, grid `9rem 1fr`; left column the date and the
  first category (meta), right column the title (subtitle, title link: heading ink, cobalt underline on
  hover) and the excerpt (0.9375rem, about 68 characters per line); a rule under each row. No thumbnails.
  Pagination: "Previous page" / "Next page" outline buttons with arrows, as NASH.
- Single post:
  - Title band (graph paper): breadcrumbs in meta size, the first category as eyebrow, display title,
    excerpt as lead, "By {author}" and "Published · Updated · N min read" (new: reading time from the post's
    word count at 220 words a minute, rounded up, at least 1).
  - Body band: the text column with NASH's article styles: h2 with a rule above and 2.5rem of space,
    h3 1.1875rem / 450, links cobalt with a 1 px underline (2 px on hover), square bullets, blockquote
    with a 2 px cobalt rule on wash, tables in a bordered scroll box, `figcaption` muted.
  - The theme's "In this article" contents box becomes NASH's closed disclosure ("On this page", "N
    sections", chevron) at every width: no sticky sidebar, which would take the side rails' space.
  - After the text: topics as chips, "Read next" (the related query) as cells, comments, the call to action.
  - The PDF viewer keeps Tala Blog 1.13's deferral and contrast fix, restyled to the frame.
- Pages: title band with the display title, then the text column.
- 404: graph-paper status panel: huge italic "404", title, lead, search and a home button.
- Comments, search form, Gravity Forms (1.12's Orbital and 2.5 styles) and the Theme Unit Test blocks:
  restyled with the same tokens, light and dark.

## 4. Ads, speed, accessibility

- Tala Ads is not changed. Every position stays: AdSense Auto ads, the in-article unit after paragraph 2,
  Innity in-read after paragraph 4 and the box after paragraph 6 all sit in the text column. Auto ads'
  in-page slots get the text column's width. Ad frames stay `color-scheme: light`. No ad is ever hidden.
- Side rails: checked at 1366, 1440, 1536 and 1920 px on an article; if they no longer appear where they
  did on 1.13, the rail narrows until they do.
- CSS stays inlined (`assets/theme.css`); the switch script is inline and under 1 KB; Inter is the only
  added download (about 130 KB, preloaded, cached a year by nginx's static rules).
- Accessibility: NASH's contrast (muted 5.8:1 on paper, 7.6:1 dark; checked with axe), one h1, named
  `<nav>`s ("Primary", each footer column's label, "Pages"), skip link, 44 px targets, reflow at 200% text
  on a 390 px screen, the wordmark hidden from screen readers.

## 5. Generic theme

Tala Blog stays a theme for any site (AGENTS.md): no site names, NASH links or DepEd wording in the theme.
Site-specific content (the call-to-action and footer-note parts) is filled per site in the Site Editor. Without a menu the header and footer use the page list, as now. Without the plugins everything
renders.

## 6. Testing

- `composer check`, `npm run lint`, `composer test`, `npm run test:e2e` (tests updated for the new presets,
  landmarks, footer columns, switch, rows without thumbnails), the Theme Unit Test site
  (`node dev/unit-test-check.mjs` plus the handbook checklist by eye), `wp-content/debug.log` clean.
- New tests: the switch (stored choice applied before paint, two states, `aria-pressed`, works without
  JavaScript), the footer columns from a menu with submenus and without a menu, the wordmark (title in
  capitals, `aria-hidden`), the empty call-to-action part printing nothing, Inter preloads and font faces.
- Lighthouse (mobile article, ads blocked) against the targets above; side-rail check at four widths with
  ads; screenshots of home, article, page, 404, light and dark, phone and desktop, for the maintainer.

## 7. Rollout

1. Release Tala Blog 2.0.0 through the usual path (checks, immutable release, attestation, local build
   match).
2. Install on both sites; fill the call-to-action part per site in the Site Editor; full refresh (nginx,
   then Cloudflare, then the warmer) on both sites, as after every theme or plugin update.
3. Verify live: ads in place (in-article, Innity, Auto ads, side rails), the switch, footer columns and
   wordmark, Lighthouse; send the maintainer screenshots.
4. Rollback, if needed: reinstall Tala Blog 1.13.0 from its release and refresh.

## Out of scope

NASH's sign-up, pricing, dashboard, product window, charts, "blog bar" tabs and Pagefind search; share
buttons; author boxes beyond what the theme already has; changes to Tala Ads, Tala SEO or Tala Guard.
