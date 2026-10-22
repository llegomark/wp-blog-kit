=== Tala SEO ===
Tags: seo, schema, json-ld, sitemap, breadcrumbs
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lean SEO for blogs: meta descriptions, canonicals, JSON-LD, breadcrumbs and a publish check that blocks posts without a description or featured image.

== Description ==

Tala SEO covers what a blog needs to follow Google Search Central's guidelines, without upsells or licences.

* **Publish check.** A post can't be published or scheduled without a meta description, a featured image, and alternative text on that image (pages need a description). Enforced on the server, so it holds for the block editor, Quick Edit, the REST API and WP-CLI. Already-published posts stay editable.
* **SEO panel** in the block editor: search result preview, SEO title and meta description with length meters, primary category, "minor edit" switch (keeps the updated date for typo fixes), hide from search engines, canonical URL, redirect, and a checklist when you press Publish.
* **Length meters** measure titles and descriptions in pixels, the way Google cuts them (a "W" takes more room than an "i"). The preview shortens them where Google would.
* **SEO column** in the Posts, Pages, Categories and Tags lists: what each item has and still needs at a glance. Every status pairs a symbol with a word, so it never depends on colour.
* **Primary category:** choose which category leads the breadcrumbs (and the address, when the permalink contains %category%) for posts filed under several.
* **Categories, tags and authors** get their own SEO title, meta description and "hide from search engines" switch.
* **Head tags:** `<title>` as "Title | Site name" (the name only once when a page is named like the site), meta description, self-referencing canonical on every indexable page (paginated archives included), noindex for search results, 404s, date archives, empty categories and tag pages with too few posts, Open Graph and Twitter card tags, and a `fediverse:creator` tag that credits the author when an article is shared on Mastodon.
* **Feeds and sitemaps** send `X-Robots-Tag: noindex, follow`, so they never show up in search results themselves (search engines still read them).
* **Structured data (JSON-LD):** WebSite and Organization (with description) on the home page, BlogPosting on posts (image in 16:9, 4:3 and 1:1), ProfilePage on author pages, BreadcrumbList matching the visible Breadcrumbs block.
* **Sitemap:** WordPress's own `/wp-sitemap.xml` with accurate `lastmod`, without noindexed or redirected posts, hidden or empty categories and tags, small tag pages, or hidden author pages. It exists only while search engines may index the site.
* **robots.txt:** points to the sitemap; blocks nothing else, and AI crawlers are welcome.
* **Redirects:** a page, category or tag whose address changes (new slug or parent) sends visitors and search engines from the old address to the new one (301). WordPress does this for posts only. Children move with their parent. A post can also send its visitors to another address on purpose (301), for example after two guides are merged.
* **Permalinks:** the setup checklist switches a site with plain URLs to post-name URLs in one click, and Settings → Permalinks warns before a structure change moves every published post. The plugin never changes the permalinks on its own.
* **Hidden-site warning:** while "Discourage search engines" is on, every admin screen and the pre-publish checklist say so.
* **Authors:** a real author photo (no Gravatar request), social profile links (TikTok and Mastodon included) for the author markup, and a Fediverse handle.
* **Settings → SEO:** site owner, official profiles, home description (also the Organization description), default share image, the minimum number of posts for tag pages to be indexed (2 by default), and a setup checklist.
* **WP-CLI:** `wp tala-seo audit` lists published posts that are missing something; `wp tala-seo import` copies data from Yoast SEO, Rank Math, The SEO Framework or SEOPress.

== Installation ==

1. Upload the plugin zip in Plugins → Add New → Upload Plugin, or run `wp plugin install tala-seo.zip --activate`.
2. Open Settings → SEO and work through the setup checklist.
3. On a site that already has images, run `wp media regenerate --only-missing` once to create the article image crops.

== Frequently Asked Questions ==

= Why can't I publish? =

The pre-publish panel lists what is missing: usually the meta description (SEO panel in the post sidebar), the featured image, or the featured image's alternative text.

= How do agents and scripts publish? =

Create a draft, set the featured image with `wp media import <file> --post_id=<id> --featured_image --alt="…"`, set `_tala_seo_description` with `wp post meta update`, then update the post status to publish.

= I'm switching from another SEO plugin. What carries over? =

Run `wp tala-seo import --dry-run` to see what would be copied, then `wp tala-seo import`. Until then, titles, descriptions and noindex from Yoast SEO, Rank Math, The SEO Framework or SEOPress still apply to the pages themselves, but the sitemap and the author settings only know Tala SEO's own values.

= Why no FAQ or HowTo markup? =

Google retired those rich results. The plugin only outputs structured data Google still uses.

== Changelog ==

= 1.3.0 =
* New: an SEO column in the Posts, Pages, Categories and Tags lists. Each status pairs a symbol with a word (colour only helps), and screen readers hear a full sentence.
* New: length meters for the SEO title and meta description, measured in pixels like Google's results. The search preview now shortens long titles and descriptions where Google would.
* New: primary category for posts in several categories. It leads the breadcrumbs, and the address too when the permalink contains %category%; addresses don't change until one is chosen. Primary categories set in Yoast SEO, Rank Math, The SEO Framework or SEOPress are honoured.
* New: SEO title, meta description and "hide from search engines" for categories, tags (and other public taxonomies) and author pages. Hidden ones leave the sitemap.
* New: empty categories and tags get noindex and leave the sitemap.
* New: feeds and sitemaps send `X-Robots-Tag: noindex, follow`.
* New: a per-post redirect (301) in the SEO panel, for merged or retired articles. Redirected posts leave the sitemap; previews still show the post.
* New: `wp tala-seo import` also reads The SEO Framework and SEOPress, and now copies primary categories, permanent redirects, category and tag fields, author-page fields and Fediverse handles.
* New: a Fediverse handle on user profiles adds `fediverse:creator` to the author's articles; TikTok and Mastodon profile links join the author's sameAs links, and the Mastodon link gets rel="me" on the author page.

= 1.2.0 =
* New: a "Switch to post-name URLs" button in the setup checklist for sites still on plain URLs (?p=123). It appears only where the web server can serve readable URLs; old ?p= links keep working.
* New: Settings → Permalinks warns, on a site with published posts, that another structure (or category and tag base) gives every post a new address; on a site with plain URLs it recommends "Post name".

= 1.1.1 =
* Plugin header: author and author URI.

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
