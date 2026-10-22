=== Tala SEO ===
Tags: seo, schema, json-ld, sitemap, breadcrumbs
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lean SEO for blogs: meta descriptions, canonicals, JSON-LD, breadcrumbs and a publish check that blocks posts without a description or featured image.

== Description ==

Tala SEO covers what a blog needs to follow Google Search Central's guidelines, without upsells or licences.

* **Publish check.** A post can't be published or scheduled without a meta description, a featured image, and alternative text on that image (pages need a description). Enforced on the server, so it holds for the block editor, Quick Edit, the REST API and WP-CLI. Already-published posts stay editable.
* **SEO panel** in the block editor: search result preview, SEO title, meta description, "minor edit" switch (keeps the updated date for typo fixes), hide from search engines, canonical URL, and a checklist when you press Publish.
* **Head tags:** `<title>` as "Title | Site name" (the name only once when a page is named like the site), meta description, self-referencing canonical on every indexable page (paginated archives included), noindex for search results, 404s, date archives and tag pages with too few posts, Open Graph and Twitter card tags.
* **Structured data (JSON-LD):** WebSite and Organization (with description) on the home page, BlogPosting on posts (image in 16:9, 4:3 and 1:1), ProfilePage on author pages, BreadcrumbList matching the visible Breadcrumbs block.
* **Sitemap:** WordPress's own `/wp-sitemap.xml` with accurate `lastmod`, without noindexed posts or small tag pages. It exists only while search engines may index the site.
* **robots.txt:** points to the sitemap; blocks nothing else, and AI crawlers are welcome.
* **Redirects:** a page, category or tag whose address changes (new slug or parent) sends visitors and search engines from the old address to the new one (301). WordPress does this for posts only. Children move with their parent.
* **Hidden-site warning:** while "Discourage search engines" is on, every admin screen and the pre-publish checklist say so.
* **Authors:** a real author photo (no Gravatar request) and social profile links for the author markup.
* **Settings → SEO:** site owner, official profiles, home description (also the Organization description), default share image, the minimum number of posts for tag pages to be indexed (2 by default), and a setup checklist.
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

= 1.1.0 =
* New: 301 redirects for pages, categories and tags whose address changed (new slug or new parent), including their children and paginated archives.
* New: tag pages with fewer posts than a minimum (Settings → SEO, 2 by default) get noindex and stay out of the sitemap.
* New: the Organization (or Person) markup on the home page carries the home description.
* Fix: the "search engines are blocked" warning now shows on every admin screen and in the pre-publish checklist, not only on Settings → SEO.
* Fix: the sitemap follows Settings → Reading; a site hidden from search engines no longer publishes one.
* Fix: no contradictory "nofollow, follow" robots directives on noindexed pages of a hidden site.
* Fix: no "Name | Name" title on a page named like the site, such as an author named after the brand.

= 1.0.0 =
* First release.
