# Working on this repository (for AI agents and people)

A WordPress block theme (`tala-blog/`) and SEO plugin (`tala-seo/`) for minimalist, fast blogs.
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
npm run lint                      # Stylelint, ESLint, theme.json/block.json schemas, patterns, block markup
```

Then test on the local site (http://127.0.0.1:8080):
- pages render with no PHP notices, and `wp-content/debug.log` stays clean
- templates still validate in the editor
- for the plugin: the publish check and the JSON-LD output (BlogPosting, BreadcrumbList,
  ProfilePage, WebSite and Organization on the home page only)
- for visible changes: Lighthouse on a mobile article page (Accessibility, Best Practices, SEO)

## Rules that come from decisions, not taste

- SEO output follows Google Search Central. Never add FAQPage, HowTo or SearchAction markup
  (Google retired them), and never use a placeholder author image.
- Don't block AI crawlers (robots.txt or anything else).
- No build step, no web fonts, no jQuery. Keep the theme's CSS inlined (`assets/theme.css`). Node and npm
  are for the linters only; nothing they install ships in the theme or plugin.
- Fix lint findings in the code. Only switch a rule off in `stylelint.config.mjs` or `eslint.config.mjs`,
  with a comment saying why, when it can't apply here; never with disable comments in `assets/theme.css`,
  which is inlined into every page.
- Keep `Update URI: false` in the theme and plugin headers. The plugin is private.
- Use the text domains `tala-seo` and `tala-blog`, and the prefixes `tala_seo_` and `tala_blog_`.
- When you change the plugin or theme version, bump `Version:`, `Stable tag:` (in readme.txt)
  and the changelog, then run `./build.sh`.

## Commits

The pre-commit hook checks the staged files (`lint-staged.config.mjs`). GitHub re-runs WPCS, PHPStan,
Stylelint, ESLint, the theme validator and Plugin Check on every push and pull request
(`.github/workflows/checks.yml`). Pushing doesn't replace running the checks first; it catches what
slipped through. If a run fails, fix it in a new commit.


Commit to `main` only after `composer check` passes. Commits are signed by the maintainer's key.
When an AI agent contributed, end the message with a `Co-Authored-By:` trailer.
