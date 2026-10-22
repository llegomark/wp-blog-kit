=== Tala SEO ===
Tags: seo, schema, json-ld, sitemap, breadcrumbs
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lean SEO for blogs: meta descriptions, canonicals, JSON-LD, breadcrumbs and a publish check that blocks posts without a description or featured image.

== Description ==

Tala SEO covers what a blog needs to follow Google Search Central's guidelines, without upsells or licences.

* **Publish check.** A post can't be published or scheduled without a meta description, a featured image, and alternative text on that image (pages need a description). Enforced on the server, so it holds for the block editor, Quick Edit, the REST API and WP-CLI. Already-published posts stay editable.
* **SEO panel** in the block editor: search result preview, SEO title, meta description, "minor edit" switch (keeps the updated date for typo fixes), hide from search engines, canonical URL, and a checklist when you press Publish.
* **Head tags:** `<title>` as "Title | Site name", meta description, self-referencing canonical on every indexable page (paginated archives included), noindex for search results, 404s and date archives, Open Graph and Twitter card tags.
* **Structured data (JSON-LD):** WebSite and Organization on the home page, BlogPosting on posts (image in 16:9, 4:3 and 1:1), ProfilePage on author pages, BreadcrumbList matching the visible Breadcrumbs block.
* **Sitemap:** WordPress's own `/wp-sitemap.xml` with accurate `lastmod`, without noindexed posts.
* **robots.txt:** points to the sitemap; blocks nothing else, and AI crawlers are welcome.
* **Authors:** a real author photo (no Gravatar request) and social profile links for the author markup.
* **Settings → SEO:** site owner, official profiles, home description, default share image and a setup checklist.
* **WP-CLI:** `wp tala-seo audit` lists published posts that are missing something; `wp tala-seo import` copies data from Yoast SEO or Rank Math.

== Installation ==

1. Upload the plugin zip in Plugins → Add New → Upload Plugin, or run `wp plugin install tala-seo.zip --activate`.
2. Open Settings → SEO and work through the setup checklist.
3. On a site that already has images, run `wp media regenerate --only-missing` once to create the article image crops.

== Frequently Asked Questions ==

= Why can't I publish? =

The pre-publish panel lists what is missing: usually the meta description (SEO panel in the post sidebar), the featured image, or the featured image's alternative text.

= How do agents and scripts publish? =

Create a draft, set the featured image with `wp media import <file> --post_id=<id> --featured_image --alt="…"`, set `_tala_seo_description` with `wp post meta update`, then update the post status to publish.

= Why no FAQ or HowTo markup? =

Google retired those rich results. The plugin only outputs structured data Google still uses.

== Changelog ==

= 1.0.0 =
* First release.
