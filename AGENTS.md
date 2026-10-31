# Working on this repository (for AI agents and people)

A WordPress block theme (`tala-blog/`), an SEO plugin (`tala-seo/`) and an ads plugin (`tala-ads/`) for
minimalist, fast blogs.
Everything must follow official WordPress practice, as checked by WordPress's own tools.

## Before changing code

1. **Read the WordPress agent skill for the task.** `wp-plugin-development` is installed in
   `.agents/skills/`. For other work, add the matching skill from WordPress/agent-skills first:
   - theme.json, templates and patterns: `wp-block-themes`, `wp-patterns`
   - blocks: `wp-block-development`
   - speed: `wp-performance`
   - static analysis: `wp-phpstan`

   Install one with `npx skills add WordPress/agent-skills --skill <name> --yes`.
2. Install the tools once: `composer install` and `npm ci` (Node 24 or newer; it also sets up the
   pre-commit hook).
3. Start the local site: `dev/serve.sh`. The site is created with `dev/site.sh`; see the README.

## After changing code: all of these must pass

```bash
composer fix && composer check    # WPCS, PHPStan level 8, Plugin Check (exits 1 on any finding)
npm run lint                      # Stylelint, ESLint, theme.json/block.json schemas, patterns, block markup, plugin readme
composer test                     # PHPUnit integration tests on WordPress's test framework
npm run test:e2e                  # browser tests on the local site: pages, menu, layout, logo, axe, editor
```

A change in behaviour comes with a test in `tests/phpunit/` or `tests/e2e/` that fails without it
(check that it does). The tests cover the pages, the JSON-LD (BlogPosting, BreadcrumbList,
ProfilePage, WebSite and Organization on the home page only, VideoObject, Event, Course and its
ItemList, FAQPage, HowTo),
the publish check, the editor's validation of the templates, the theme's landmarks and colour and
typography presets, and the plugin's blocks in the editor. Then, on the local site (http://127.0.0.1:8080):
- `wp-content/debug.log` stays clean
- for visible changes: Lighthouse on a mobile article page (Accessibility, Best Practices, SEO)
- for theme changes: WordPress's Theme Unit Test on `dev/unit-test-site.sh` (the theme alone, without
  Tala SEO): `node dev/unit-test-check.mjs` must pass, then the handbook's checklist by eye
  (https://make.wordpress.org/themes/handbook/review/theme-unit-test/)
- for visible changes to the SEO panel, the publish check, Settings → SEO, the SEO column, the
  plugin's blocks or Settings → Ads: `node dev/screenshots.mjs` takes the readmes' screenshots again

## Rules that come from decisions, not taste

- SEO output follows Google Search Central, and never uses a placeholder author image. Never add
  SearchAction markup (Google retired the sitelinks search box). The exceptions to "only what
  Google shows", by the maintainer's decision: FAQPage, HowTo and the Course info details (level,
  format, workload, instructor, price). Google retired those rich results, but the markup stays for
  other search engines and AI assistants, built only from content the page shows (the FAQ and
  How-to groups, the Course block's card). Don't add other retired types without asking.
- Don't block AI crawlers (robots.txt or anything else).
- Each works on its own: the theme without the plugins (no plugin blocks in templates, only through
  patterns that are empty without it) and each plugin with any theme (nothing that relies on Tala
  Blog's markup or wording). Ad and tracking code lives in Tala Ads, never in the theme.
  `tests/e2e/standalone.spec.mjs` checks both.
- No build step, no web fonts, no jQuery. Keep the theme's CSS inlined (`assets/theme.css`). Node and npm
  are for the linters only; nothing they install ships in the theme or plugin.
- Fix lint findings in the code. Only switch a rule off in `stylelint.config.mjs` or `eslint.config.mjs`,
  with a comment saying why, when it can't apply here; never with disable comments in `assets/theme.css`,
  which is inlined into every page.
- Keep `Update URI: false` in the theme and plugin headers. The plugins are private.
- Use the text domains `tala-seo`, `tala-ads` and `tala-blog`, and the prefixes `tala_seo_`, `tala_ads_` and
  `tala_blog_`.
- Tala Ads' settings hold IDs, never code: each field is cut down to the ID's shape on save and
  again on output, so nothing typed into wp-admin can put a script on the site. The one exception is
  Google's error protection code, which Google generates per site: it is kept only in Google's shape,
  for the site's publisher ID, naming only Google's servers, and only users with `unfiltered_html`
  can change it. Don't add other code fields.
- When you change the plugin or theme version, bump `Version:`, `Stable tag:` (in readme.txt)
  and the changelog, then run `./build.sh`. Once the push passes the checks, `release.yml` publishes
  the new version as a GitHub release (the zip, its SHA-256, a build attestation, the changelog entry
  as notes). Never move or reuse a release tag: a released version is fixed; release a new one. Add an upgrade notice (under 300 characters) when
  updating needs the site owner to do something or know something.
- The plugin readmes follow WordPress.org's readme standard, checked by `npm run lint:readme`.
  Contributors are WordPress.org usernames (`edventuredev`); there is no donate link, by decision.

## Repository protection (GitHub settings, not files)

Set after the LiteLLM supply-chain attack of October 2026 (a stolen token pushed unsigned workflow
commits to throwaway branches to read the secrets, then published malicious releases):

- Rulesets: every branch requires signed commits (so a stolen token alone can't push code or
  workflows); main can't be deleted or force-pushed; release tags can't be moved or deleted.
- Releases are immutable once published, with a build attestation; the deploy compares the zip
  with a local build before installing it.
- Actions: only GitHub's own actions and the allowlisted owners (`shivammathur/setup-php`,
  `WordPress/plugin-check-action`, `nick-fields/retry`) may run, every `uses:` must pin a full
  commit SHA (GitHub refuses the run otherwise), the default token is read-only, workflows can't
  approve pull requests, and a pull request from any outside contributor runs only after approval.
  Adding an action to a workflow means adding its owner to the allowlist in Settings → Actions.
- The repository holds no secrets, deploy keys or webhooks, and needs none: the release workflow
  uses its own token, and deploys run from the maintainer's machine. Keep it that way.
- Secret scanning with push protection is on; Dependabot keeps the pins and tools current, with a
  14-day cooldown on new releases.

## Commits

The pre-commit hook checks the staged files (`lint-staged.config.mjs`). GitHub re-runs WPCS, PHPStan,
Stylelint, ESLint, the theme validator, the PHPUnit and browser tests, and Plugin Check on every push
and pull request (`.github/workflows/checks.yml`). Pushing doesn't replace running the checks first; it catches what
slipped through. If a run fails, fix it in a new commit.


Commit to `main` only after `composer check` passes. Commits are signed by the maintainer's key.
When an AI agent contributed, end the message with a `Co-Authored-By:` trailer.
