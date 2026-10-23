# WordPress blog kit: Tala Blog theme + Tala SEO plugin

A minimal, fast blog setup for WordPress with the block editor (Gutenberg). No premium licences, nothing that expires.

| Folder | What it is |
|---|---|
| `tala-blog/` | Block theme. Minimalist: near-black text on a near-white page, the same inverted in automatic dark mode, a header with the site logo and a menu that opens full screen on phones, system fonts (no downloads), labelled Published/Updated dates, author box with bio under every article, breadcrumbs, "Filed under" categories and tags, article lists with thumbnails and numbered pages, dated comments, print styles. Translation-ready and accessible (WCAG 2.2 AA checks pass in both colour schemes). |
| `tala-seo/` | SEO plugin. Titles, meta descriptions, canonicals, robots rules, Open Graph, JSON-LD, sitemap and robots.txt tuning, redirects for moved pages and categories, and a publish check. |
| `dev/` | Demo content and a WordPress Playground blueprint for local testing (not deployed). |
| `build.sh` | Packages both as zips in `dist/`. |

Both need WordPress 7.0 or newer and PHP 8.5 or newer. They follow the current WordPress and PHP releases and carry no code for older versions.

## What Tala SEO does

- **Won't publish a post without** a meta description, a featured image, and alternative text on that image (pages need a description only). This is enforced on the server, so it holds for the editor, Quick Edit, the REST API and WP-CLI. Already-published posts stay editable.
- **SEO panel in the editor:** Google-style preview, SEO title and meta description with pixel-length meters (Google cuts by width, not characters), primary category, "minor edit" switch (keeps the Updated date for typo fixes), hide from search engines, canonical URL, redirect. When you press Publish, a checklist shows what's needed and what Google recommends.
- **SEO column** in the Posts, Pages, Categories and Tags lists: description, image, hidden, redirect and more at a glance. Symbols and words carry the meaning, so it doesn't rely on colour.
- **Categories, tags and author pages** get their own SEO title, meta description and "hide from search engines" switch.
- **In `<head>`:**
  - `<title>` as "Title | Site name"
  - meta description
  - self-referencing canonical (each page of an archive is its own canonical)
  - noindex for search results, 404s, date archives, empty categories and tag pages with fewer posts than the minimum in Settings → SEO (2 by default)
  - Open Graph and Twitter card tags with a 1200 × 630 (1.91:1) share image, the shape link previews use on Facebook, LinkedIn, X and messaging apps, and `fediverse:creator` for authors with a Fediverse handle
- **`X-Robots-Tag: noindex, follow`** on feeds and sitemaps, so they don't appear in search results themselves.
- **JSON-LD** (following Google Search Central, October 2026):
  - home page: WebSite and Organization (or Person), with the home description
  - posts: BlogPosting, with the image in 16:9, 4:3 and 1:1 crops and dates with timezone
  - author pages: ProfilePage
  - breadcrumbs: BreadcrumbList matching the visible trail
  - videos: VideoObject for each YouTube video in an Embed block. The title and thumbnail come from YouTube when the post is saved; the date the video was published, which Google requires and YouTube's oEmbed doesn't give, is entered in the SEO panel.
  - events: the **Event** block shows when, where, tickets and organizer as a card and describes the same as an Event (start with the UTC offset, or the date alone for all-day events; a place with an address; one event per post, as Google asks).
  - FAQ and How-to: Group blocks from the inserter, built from core blocks (Details for each question, a numbered list for the steps), described as FAQPage and HowTo. Google no longer shows these as rich results (HowTo since 2023, FAQ since May 2026); the markup is still valid schema.org for other search engines and AI assistants, and it only repeats what the page shows.
  - SearchAction is left out on purpose: Google retired the sitelinks search box.
- **Sitemap:** WordPress's own `/wp-sitemap.xml`, with accurate `lastmod`, without noindexed or redirected posts, hidden or empty categories and tags, small tag pages, hidden author pages or post formats. Like WordPress, it exists only while search engines may index the site.
- **robots.txt:** points to the sitemap and blocks nothing else. AI crawlers are welcome.
- **Redirects:** when a page, category or tag gets a new address (slug or parent), the old address answers with a 301 to the new one, children and paginated archives included. WordPress does this for posts only. A post can also redirect on purpose (SEO panel → Redirect to).
- **Permalinks:** a one-click switch from plain URLs (`?p=123`) to post-name URLs in the setup checklist, and a warning on Settings → Permalinks before a change gives every published post a new address. Nothing changes without the admin pressing the button.
- **Hidden-site warning:** while Settings → Reading discourages search engines, every admin screen and the pre-publish checklist say so.
- **Authors:** a real author photo (no Gravatar request), social profile links (TikTok and Mastodon included) that feed the Person markup, and a Fediverse handle.
- **Settings → SEO:** who runs the site, official profiles, home description, default share image, the minimum posts for an indexable tag page, and Google Search Console, Bing Webmaster Tools and Meta (Facebook) verification codes (for the HTML-tag method; a DNS record needs nothing), and a setup checklist (search engine visibility, site icon, logo, tagline, permalinks, author bios).
- **WP-CLI:**
  - `wp tala-seo audit` lists published posts that are missing something.
  - `wp tala-seo import` copies titles, descriptions, canonicals, noindex, primary categories and redirects from Yoast SEO, Rank Math, The SEO Framework or SEOPress, for posts, categories, tags and authors.
  - Post meta keys for agents: `_tala_seo_description`, `_tala_seo_title`, `_tala_seo_noindex`, `_tala_seo_canonical`, `_tala_seo_redirect`, `_tala_seo_primary_category`, `_tala_seo_video_dates` (YouTube video ID => `YYYY-MM-DD`).

## Using it on a new site

```bash
./build.sh                                   # makes dist/*.zip
wp plugin install dist/tala-seo-1.0.0.zip --activate
wp theme install dist/tala-blog-1.0.0.zip --activate
wp media regenerate --only-missing --yes     # only on sites with existing images: creates the share and Google crops
```

Then in wp-admin:
- Settings → General: site title, tagline and site icon (square PNG, at least 512 × 512).
- Users → Profile: biography and author photo.
- Settings → SEO: work through the checklist.

## Development

### Local WordPress on Arch Linux (no Docker)

```bash
sudo bash dev/setup-arch.sh   # once: PHP 8.5, Composer, MariaDB (socket login only, no network port)
composer install              # coding standards, PHPStan and the WordPress stubs
npm ci                        # Node 24+: Stylelint, ESLint and the theme validator, and the pre-commit hook
dev/site.sh                   # local site in ~/Sites/blogkit, theme and plugin symlinked, demo content
dev/serve.sh                  # http://127.0.0.1:8080 (admin login: ~/Sites/blogkit/.dev-credentials)
```

`dev/site.sh --reset` starts the site over. Query Monitor and Plugin Check are installed on it.

### Checks (run them all before every commit)

| Command | What it runs |
|---|---|
| `composer lint` | WordPress Coding Standards: WPCS 3.4 with WordPress-Core, WordPress-Docs and WordPress-Extra (`phpcs.xml.dist`) |
| `composer fix` | the automatic coding-standards fixes |
| `composer analyse` | PHPStan level 8 with the WordPress extension (`phpstan.neon.dist`), against WordPress 7.1's functions and classes |
| `composer plugin-check` | WordPress Plugin Check, including experimental checks, against the local site; exits 1 on any finding |
| `composer check` | all of the above |
| `npm run lint:css` | Stylelint with WordPress's CSS coding standards (`@wordpress/stylelint-config`, `stylelint.config.mjs`); `lint:css:fix` fixes what it can |
| `npm run lint:js` | ESLint with WordPress's JavaScript coding standards (`@wordpress/eslint-plugin`, `eslint.config.mjs`), text domains included; `lint:js:fix` fixes what it can |
| `npm run lint:theme` | `bin/validate-theme.mjs`: `theme.json` and `block.json` against WordPress's schemas for the "Requires at least" version, pattern headers, block markup in templates, parts and patterns, and no hard-coded text in templates and parts |
| `npm run lint` | the three above, plus `composer lint` |
| `composer test` | PHPUnit integration tests (`tests/phpunit/`) on WordPress's own test framework: Tala SEO's head tags, structured data (videos, events, FAQ and how-to included), sitemap, redirects, publish check, admin forms, import and share images, and Tala Blog's block filters, patterns and template parts |
| `npm run test:e2e` | browser tests (`tests/e2e/`, Playwright) on the local site: every kind of page, the phone menu frame by frame, layout shifts, the logo, axe in light and dark mode on phone and desktop, the block editor's validation of the theme, and Tala SEO's FAQ, How-to and Event blocks and video dates in the editor and on the page |

A pre-commit hook (husky and lint-staged, set up by `npm ci`) runs the linters on the staged files of each commit.

### Tests

The PHPUnit tests run on WordPress's test framework (`wp-phpunit`, PHPUnit 9.6, the version WordPress itself runs its tests on). Each test runs in a database transaction against a real WordPress with Tala SEO active and Tala Blog as the theme. `composer test` downloads WordPress into `.cache/wordpress` and creates the test database `wp_tests_blogkit` the first time; it uses MariaDB through its Unix socket as your user, as `dev/setup-arch.sh` sets up, and the `WP_TESTS_DB_*` variables in `tests/phpunit/wp-tests-config.php` select another server. The test database is emptied on every run: never point it at a site.

The browser tests need the local site (`dev/site.sh`); they start `dev/serve.sh` if it isn't running, and use the Google Chrome installed on the machine. They change settings (a menu, a logo) with WP-CLI and put them back.

A change in behaviour comes with a test that fails without it.

Without a local PHP, `dev/phpcs.sh` and `dev/plugin-check.sh` run the same checks in PHP compiled to WebAssembly and WordPress Playground. The PHPCS downloads are verified against the release key.

GitHub runs the same checks on every push to `main` and every pull request (`.github/workflows/checks.yml`): coding standards and PHPStan on PHP 8.5, Stylelint, ESLint and the theme validator on Node 24, the PHPUnit tests and the browser tests (with a MySQL 8.4 database), and Plugin Check through [WordPress/plugin-check-action](https://github.com/WordPress/plugin-check-action) against the latest WordPress. Every Monday it also checks the plugin against WordPress trunk, the release in development; a finding there is an early warning and doesn't fail the build. The actions are pinned to commit SHAs, and Dependabot proposes updates. Theme Check isn't part of CI; run `wp theme-check run tala-blog` on the local site.

`composer.json` loads the WordPress 7.1 stubs as `7.1.2 as 6.9.4`: the WP-CLI stubs still accept only WordPress 6 stubs in their latest release, though their next one won't (php-stubs/wp-cli-stubs#25). Drop the alias once that release is out.

The one deliberate exception is Plugin Check's `plugin_updater_detected`. It flags the `Update URI: false` header, which keeps this private plugin from ever being "updated" by an unrelated wordpress.org plugin with the same name.

### AI agents

`AGENTS.md` describes the workflow: WordPress agent skills first, then the checks, then tests on the local site. The skills come from [WordPress/agent-skills](https://github.com/WordPress/agent-skills) (GPL-2.0-or-later). They are installed in `.agents/skills/`, recorded in `skills-lock.json`, and Claude Code reads them through `.claude/skills/`. Update them with `npx skills update`.

### Test results (October 2026: WordPress 7.1, PHP 8.5.12)

- `composer test`: 73 PHPUnit tests pass. `npm run test:e2e`: 79 browser tests pass, axe included.
- Every page type renders without PHP notices, and every theme template passes the block editor's validator.
- The publish check holds in the editor, over REST, in Quick Edit and WP-CLI saves, and for scheduling.
- The WP-CLI commands `audit` and `import` (including `--dry-run`) work; Yoast titles built from `%%variables%%` are skipped.
- Lighthouse on a mobile article page: Accessibility 100, Best Practices 100, SEO 100.
- `composer check` passes: WPCS without findings, PHPStan level 8 without errors, Plugin Check 2.1 without findings.

**Not used on purpose:**
- a build step: the editor scripts are plain JavaScript using the `wp.*` globals (Node is only for the linters)
- web fonts and jQuery
