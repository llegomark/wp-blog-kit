# WordPress blog kit: Tala Blog theme + Tala SEO plugin

A minimal, fast blog setup for WordPress with the block editor (Gutenberg). No premium licences, nothing that expires.

| Folder | What it is |
|---|---|
| `tala-blog/` | Block theme. Pad-paper look, automatic chalkboard dark mode, system fonts (no downloads), labelled Published/Updated dates, author box with bio under every article, breadcrumbs. |
| `tala-seo/` | SEO plugin. Titles, meta descriptions, canonicals, robots rules, Open Graph, JSON-LD, sitemap and robots.txt tuning, redirects for moved pages and categories, and a publish check. |
| `dev/` | Demo content and a WordPress Playground blueprint for local testing (not deployed). |
| `build.sh` | Packages both as zips in `dist/`. |

## What Tala SEO does

- **Won't publish a post without** a meta description, a featured image, and alternative text on that image (pages need a description only). This is enforced on the server, so it holds for the editor, Quick Edit, the REST API and WP-CLI. Already-published posts stay editable.
- **SEO panel in the editor:** Google-style preview, SEO title, meta description, "minor edit" switch (keeps the Updated date for typo fixes), hide from search engines, canonical URL. When you press Publish, a checklist shows what's needed and what Google recommends.
- **In `<head>`:**
  - `<title>` as "Title | Site name"
  - meta description
  - self-referencing canonical (each page of an archive is its own canonical)
  - noindex for search results, 404s, date archives and tag pages with fewer posts than the minimum in Settings → SEO (2 by default)
  - Open Graph and Twitter card tags
- **JSON-LD** (following Google Search Central, October 2026):
  - home page: WebSite and Organization (or Person), with the home description
  - posts: BlogPosting, with the image in 16:9, 4:3 and 1:1 crops and dates with timezone
  - author pages: ProfilePage
  - breadcrumbs: BreadcrumbList matching the visible trail
  - FAQ, HowTo and SearchAction are deliberately left out; Google retired those rich results.
- **Sitemap:** WordPress's own `/wp-sitemap.xml`, with accurate `lastmod`, without noindexed posts, small tag pages or post formats. Like WordPress, it exists only while search engines may index the site.
- **robots.txt:** points to the sitemap and blocks nothing else. AI crawlers are welcome.
- **Redirects:** when a page, category or tag gets a new address (slug or parent), the old address answers with a 301 to the new one, children and paginated archives included. WordPress does this for posts only.
- **Hidden-site warning:** while Settings → Reading discourages search engines, every admin screen and the pre-publish checklist say so.
- **Authors:** a real author photo (no Gravatar request) and social profile links, which feed the Person markup.
- **Settings → SEO:** who runs the site, official profiles, home description, default share image, the minimum posts for an indexable tag page, and a setup checklist (search engine visibility, site icon, logo, tagline, permalinks, author bios).
- **WP-CLI:**
  - `wp tala-seo audit` lists published posts that are missing something.
  - `wp tala-seo import` copies titles and descriptions from Yoast SEO or Rank Math.
  - Post meta keys for agents: `_tala_seo_description`, `_tala_seo_title`, `_tala_seo_noindex`, `_tala_seo_canonical`.

## Using it on a new site

```bash
./build.sh                                   # makes dist/*.zip
wp plugin install dist/tala-seo-1.0.0.zip --activate
wp theme install dist/tala-blog-1.0.0.zip --activate
wp media regenerate --only-missing --yes     # only on sites with existing images: creates the 1200 px crops
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
dev/site.sh                   # local site in ~/Sites/blogkit, theme and plugin symlinked, demo content
dev/serve.sh                  # http://127.0.0.1:8080 (admin login: ~/Sites/blogkit/.dev-credentials)
```

`dev/site.sh --reset` starts the site over. Query Monitor and Plugin Check are installed on it.

### Checks (run them all before every commit)

| Command | What it runs |
|---|---|
| `composer lint` | WordPress Coding Standards: WPCS 3.4 with WordPress-Core, WordPress-Docs and WordPress-Extra (`phpcs.xml.dist`) |
| `composer fix` | the automatic coding-standards fixes |
| `composer analyse` | PHPStan level 8 with the WordPress extension (`phpstan.neon.dist`) |
| `composer plugin-check` | WordPress Plugin Check, including experimental checks, against the local site; exits 1 on any finding |
| `composer check` | all of the above |

Without a local PHP, `dev/phpcs.sh` and `dev/plugin-check.sh` run the same checks in PHP compiled to WebAssembly and WordPress Playground. The PHPCS downloads are verified against the release key.

The one deliberate exception is Plugin Check's `plugin_updater_detected`. It flags the `Update URI: false` header, which keeps this private plugin from ever being "updated" by an unrelated wordpress.org plugin with the same name.

### AI agents

`AGENTS.md` describes the workflow: WordPress agent skills first, then the checks, then tests on the local site. The skills come from [WordPress/agent-skills](https://github.com/WordPress/agent-skills) (GPL-2.0-or-later). They are installed in `.agents/skills/`, recorded in `skills-lock.json`, and Claude Code reads them through `.claude/skills/`. Update them with `npx skills update`.

### Test results (October 2026: WordPress 7.1, PHP 8.5.12)

- Every page type renders without PHP notices, and every theme template passes the block editor's validator.
- The publish check holds in the editor, over REST, in Quick Edit and WP-CLI saves, and for scheduling.
- The WP-CLI commands `audit` and `import` (including `--dry-run`) work; Yoast titles built from `%%variables%%` are skipped.
- Lighthouse on a mobile article page: Accessibility 100, Best Practices 100, SEO 100.
- `composer check` passes: WPCS without findings, PHPStan level 8 without errors, Plugin Check 2.1 without findings.

**Not used on purpose:**
- a build step: the editor scripts are plain JavaScript using the `wp.*` globals
- web fonts and jQuery
- the Navigation block's automatic page list, which nests lists and fails accessibility checks
