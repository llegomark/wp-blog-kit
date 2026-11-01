=== Tala SEO ===
Contributors: edventuredev
Tags: seo, schema, json-ld, sitemap, breadcrumbs
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.5
Stable tag: 1.17.0
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
* **Head tags:** wrapped in a comment that names the plugin, its version and https://edventure.com.ph, so the page source shows what Tala SEO wrote. `<title>` as "Title | Site name", with the separator you choose (the name only once when a page is named like the site), meta description, self-referencing canonical on every indexable page (paginated archives included), noindex for search results, 404s, date archives, empty categories and tag pages with too few posts, Open Graph and Twitter card tags with a 1200 × 630 share image (the 1.91:1 shape of link previews on Facebook, LinkedIn, X and messaging apps), and a `fediverse:creator` tag that credits the author when an article is shared on Mastodon.
* **Feeds and sitemaps** send `X-Robots-Tag: noindex, follow`, so they never show up in search results themselves (search engines still read them).
* **Structured data (JSON-LD):** WebSite and Organization on the home page (its kind, such as an educational organization, with description, legal name, postal address, telephone, email and a customer support contact point when set), BlogPosting on posts (image in 16:9, 4:3 and 1:1), ProfilePage on author pages, BreadcrumbList matching the visible Breadcrumbs block, on pages that show it.
* **Videos:** each YouTube video in an Embed block is described as a VideoObject (title, thumbnail, player address, the caption as its description). The title and thumbnail are fetched from YouTube once, when the post is saved. Google also needs the date the video was published, which YouTube doesn't share this way: enter it in the SEO panel, where each video gets a date field.
* **Event block:** when, where, tickets and organizer, shown as a card and described as an Event the way Google's event results ask: start and end with the UTC offset (or the date alone for an all-day event), the venue's address, status (cancelled, postponed, rescheduled), price and ticket page, and the featured image. One per post, as Google supports pages about a single event.
* **Course block:** a course's description, provider (the site unless you name another), level, format (online, in person or both), time, instructor and cost with an enrollment link, shown as a card and described as a Course for Google's course list, a carousel in English searches. A post with one course is its detail page; a category (or other list of posts) whose posts are all courses, three or more, becomes their summary page; a post with several courses lists them itself. Google uses the name, description and provider; the other details were its Course info result, retired in 2025, and stay for other search engines and AI assistants.
* **FAQ and How-to:** two Group blocks in the inserter, made of core blocks: an FAQ with a Details block (opens on click) for each question, and a how-to guide with a heading, an introduction and a numbered list of steps (a post's only how-to guide may leave the heading out: it is then named after the post). They are described as FAQPage and HowTo. Google no longer shows these as rich results; the markup is valid schema.org that other search engines and AI assistants can read, and it only repeats what the page shows. Without the plugin they are ordinary blocks.
* **Sitemap:** WordPress's own `/wp-sitemap.xml` with accurate `lastmod`, where the sitemap addresses of Yoast SEO and Rank Math (`sitemap_index.xml`, `post-sitemap.xml` and the like) now lead, without noindexed or redirected posts, hidden or empty categories and tags, small tag pages, or hidden author pages. It exists only while search engines may index the site.
* **robots.txt:** points to the sitemap; blocks nothing else, and AI crawlers are welcome.
* **Redirects:** a page, category or tag whose address changes (new slug or parent) sends visitors and search engines from the old address to the new one (301). WordPress does this for posts only. Children move with their parent. A post can also send its visitors to another address on purpose (301), for example after two guides are merged.
* **Attachment pages:** the setup checklist turns off the thin page WordPress made for every uploaded image on sites older than WordPress 6.4, in one click; their addresses then lead to the files (301), as with Yoast SEO and Rank Math.
* **Permalinks:** the setup checklist switches a site with plain URLs to post-name URLs in one click, and Settings → Permalinks warns before a structure change moves every published post. The plugin never changes the permalinks on its own.
* **Hidden-site warning:** while "Discourage search engines" is on, every admin screen and the pre-publish checklist say so.
* **Authors:** a real author photo (no Gravatar request), social profile links (TikTok and Mastodon included) for the author markup, and a Fediverse handle.
* **Settings → SEO:** the title separator (vertical bar, hyphen, dashes, colon, middle dot or bullet), site owner, official profiles (the organization's sameAs), its kind, legal name, address, telephone and email, home description (also the Organization description), default share image, the minimum number of posts for tag pages to be indexed (2 by default), Google Search Console, Bing Webmaster Tools and Meta (Facebook) verification codes, and a setup checklist.
* **WP-CLI:** `wp tala-seo audit` lists published posts that are missing something; `wp tala-seo import` copies data from Yoast SEO, Rank Math, The SEO Framework or SEOPress; `wp tala-seo describe` writes the missing meta descriptions from each post's opening text, once, for you to edit.

== Installation ==

1. Download the zip of the latest version from https://github.com/llegomark/wp-blog-kit/releases (under Assets), then upload it in Plugins → Add New Plugin → Upload Plugin, or run `wp plugin install tala-seo-<version>.zip --activate`.
2. Open Settings → SEO and work through the setup checklist.
3. On a site that already has images, run `wp media regenerate --only-missing` once to create the image crops (again after updating to 1.6.0, for the share crop). Without it, Tala SEO makes an older image's share crop in the background the first time a page with that image is served.

== Frequently Asked Questions ==

= Why can't I publish? =

The pre-publish panel lists what is missing: usually the meta description (SEO panel in the post sidebar), the featured image, or the featured image's alternative text.

= How do agents and scripts publish? =

Create a draft, set the featured image with `wp media import <file> --post_id=<id> --featured_image --alt="…"`, set `_tala_seo_description` with `wp post meta update`, then update the post status to publish.

= I'm switching from another SEO plugin. What carries over? =

Run `wp tala-seo import --dry-run` to see what would be copied, then `wp tala-seo import`. It copies each post's, category's, tag's and author's SEO title, description, canonical, noindex, primary category and permanent redirect, fills in titles made with the other plugin's variables (%%title%% %%sep%% %%sitename%%) and takes off the site name it added. From Yoast SEO or Rank Math it also copies the title separator, home page description, site owner, official profiles, default share image and verification codes, and the organization's kind, address, telephone and email from Yoast Local SEO or Rank Math's local SEO, turns off attachment pages if the plugin redirected them, and hides the category, tag and author pages it hid as a rule. With settings from both, add `--from=yoast` or `--from=rank-math`. Rank Math wrote descriptions on the fly for posts without one; `wp tala-seo describe` writes them once, from the opening text, for you to edit. Until the import, titles, descriptions and noindex from Yoast SEO, Rank Math, The SEO Framework or SEOPress still apply to the pages themselves and keep hidden posts, categories and tags out of the sitemap, but the author settings only know Tala SEO's own values.

= What size should featured images be? =

At least 1200 × 675 px. Tala SEO crops each one to 1200 × 630 for link previews (Facebook, LinkedIn, X, WhatsApp and the like show that 1.91:1 shape) and to 16:9, 4:3 and 1:1 for Google's article results. Keep the subject away from the edges so every crop shows it. The default share image in Settings → SEO is shown as it is, so make that one 1200 × 630.

= Does Tala SEO need the Tala Blog theme? =

No. It works with any theme that follows WordPress's standards: titles, descriptions, canonicals, share images, structured data, the sitemap and the blocks don't depend on the theme. Tala Blog only places the Breadcrumbs block in its templates for you. With another block theme, add the block to your templates in Appearance → Editor (for example above the post title); with a classic theme, put it in a block widget area or in the content. Breadcrumb structured data is output only on pages that show the block, as Google asks that structured data describe what readers see.

= Does Google still show FAQ and HowTo results? =

No. Google stopped showing HowTo results in 2023 and FAQ results in May 2026. The FAQ and How-to blocks still describe their content as FAQPage and HowTo: valid schema.org that other search engines and AI assistants can read. The questions and steps on the page matter most, for readers and for every search engine. Structured data for content that isn't on the page is against Google's guidelines, so the markup comes only from what the blocks show (and nothing from a password-protected post until its content is shown).

= How do my courses get into Google's course list? =

Give each course its own post with one Course block, with a description, and file the course posts in a category of their own, for example "Courses". With three or more courses there, the category page tells Google they belong together. The list only counts when every post on that page is a course. Google shows the course list in English searches.

= What counts as a course? =

Google's definition: lessons or modules on a subject, with a clear learning outcome, led by an instructor. A one-off event (use the Event block) or a single short video isn't a course. Keep prices and promotions out of the course name.

= Why doesn't the Event block show online events? =

Google's event results only cover events at a physical place that the public can book. An online-only event can still be described in the post; it just doesn't get Event markup without a street address or town.

= Where does the video's publish date come from? =

From you: YouTube's oEmbed (what WordPress uses to embed videos) gives the title and thumbnail, but not the date. Open the SEO panel, find the video under "Videos" and enter the date shown under it on YouTube. Until then the video has no VideoObject markup, since Google requires the date.

== Screenshots ==

1. The SEO panel in the block editor: a preview of the Google result, and the SEO title and meta description with length meters measured in pixels, as Google cuts them.
2. The publish check: a post without a meta description or a featured image can't be published, and the panel says what is missing.
3. Settings → SEO, starting with the setup checklist.
4. The SEO column in the list of posts: what each one has and what it still needs.
5. The Event and Course blocks on a phone.

== Changelog ==

= 1.17.0 =
* New: the organization's details (Settings → SEO → Address and contact): its kind (organization, educational organization, NGO, news media, online business, corporation), legal name, postal address, telephone and email, in its markup on the home page, with the telephone and email also as its customer support contact point. Only what is filled in is used, and none of it for a person.
* The Course block's provider, when it is the site, takes the organization's kind.
* Import: the organization's kind, address, telephone and email come over from Yoast Local SEO and from Rank Math's local SEO settings.

= 1.16.1 =
* A post's only How-to group may go without a heading of its own: the guide is then named after the post, whose title is the page's heading, as Yoast SEO's and Rank Math's how-to blocks were. With several groups, each still needs its heading.

= 1.16.0 =
* New: the title separator is a setting (Settings → SEO → Titles): vertical bar, hyphen, en or em dash, colon, middle dot or bullet. A hyphen stays a hyphen; WordPress would make " - " an en dash.
* New: `wp tala-seo describe` writes a meta description for each published post without one, from its opening paragraphs, cut after a whole sentence; an introduction three or more posts share is passed over.
* New: the setup checklist turns off attachment pages in one click; their addresses lead to the files (301).
* New: Yoast SEO's and Rank Math's sitemap addresses (sitemap_index.xml, post-sitemap.xml and the like) lead to WordPress's sitemap.
* Import: titles and descriptions made with the other plugin's variables are filled in, and the site name it added is taken off; from Yoast SEO or Rank Math, the site-wide settings are copied too, attachment pages are turned off if it redirected them, and the category, tag and author pages it hid as a rule stay hidden. `--from` chooses when both left settings.

= 1.15.1 =
* Security: the publish check counts only text as a meta description. WordPress saves a post before it rejects meta of another type (a number, a list), so a publish checked against such a value went live with no description stored. A description made of spaces, no-break spaces included, is empty too.
* Security: a Breadcrumbs block inside a block hidden with block visibility adds no BreadcrumbList, as the hidden block itself already didn't.
* Security: `wp tala-seo import` applies the same trust gate to SEOPress redirects as to The SEO Framework's: a redirect goes live only if the post's author could set one on the published post.

= 1.15.0 =
* The page source shows what the plugin wrote: its meta tags and JSON-LD sit between a comment naming Tala SEO, its version and https://edventure.com.ph, and a closing comment. The "tala_seo_head_comment" filter leaves both out.

= 1.14.0 =
* A static front page and the page that lists the posts (Settings → Reading) now use their own SEO title, meta description and "hide from search engines" switch, as the SEO panel on those pages shows. Without a description of their own they use the home page description.
* The sitemap leaves out posts hidden by a setting from Yoast SEO, Rank Math, The SEO Framework or SEOPress, which already got noindex on the page itself.
* Fixed: a damaged "official profiles" setting can't raise a PHP warning, and the image pickers in Settings → SEO and user profiles reuse one media window instead of adding one per click.

= 1.13.0 =
* Security: the category, tag, author and photo forms each carry a check tied to the term or user they edit, and a category's form saves to that category only.
* Security: who may edit the SEO fields is checked for the user asked about, not whoever is logged in.
* Security: a canonical address left by another SEO plugin is used only when it is a real address, and "hide from search engines" and "minor edit" are saved as yes or no however they are written.
* Author pages: members who never published get no profile markup and no "rel=me" link. The photo picker is offered to people who can upload files; a photo someone else chose stays when the profile is saved.
* Breadcrumbs leave out parent pages that aren't public, and a hidden Breadcrumbs block adds no breadcrumb markup.
* `wp tala-seo import` also lists the canonical addresses it copies; `wp tala-seo audit` prints plain one-line titles, and in CSV no title starts a spreadsheet formula.
* Fixed: an address a page or category uses again is no longer kept as another's old address; the share crop is made once even when queued twice; block settings of the wrong type no longer fill the error log.
* YouTube requests take a small answer and follow no redirect; FAQ questions and video captions are read in time that grows only with the length of the content.

= 1.12.0 =
* Security: the meta description a post list shows in place of an excerpt is escaped, so markup written into it shows as text.
* Security: an author photo can only be an image the person saving the profile may see, and shows only while that image is public. On multisite, each site keeps its own photo.
* Security: a password-protected post's excerpt is no longer used as its description, and the YouTube video dates of a post are readable over the REST API only by people who may edit it.
* Security: blocks hidden with block visibility are left out of the structured data, as they are out of the page.
* Security: an autosave can no longer publish a post that the publish check would stop.
* Security: `wp tala-seo import` copies a redirect kept in a plain custom field only when the post's author may edit published posts, and lists every redirect it copies or leaves out.
* Performance: looking up old addresses on 404 pages, the post and tag sitemaps, and fetching YouTube video details on save no longer grow with the size of the request or the number of posts, tags or videos.

= 1.11.1 =
* Readme: installing from the GitHub releases.

= 1.11.0 =
* Changed: breadcrumb structured data (BreadcrumbList) is output only on pages that show the Breadcrumbs block, so with a theme that doesn't show breadcrumbs there is no markup for a trail readers can't see. With a block theme it is in the page's structured data as before; a trail shown later in the page (a classic theme's content or widgets) gets its own script at the end of the page.
* Changed: the setup checklist describes the tagline for any theme.

= 1.10.2 =
* Fixed: in the Breadcrumbs block, a title with a very long word wraps instead of widening the page on phones.

= 1.10.1 =
* Changed: Edventure is the plugin's author.
* Readme: contributors, screenshots and upgrade notices, as WordPress.org's readme validator suggests.

= 1.10.0 =
* New: the Course block, a card with a course's description, provider, level, format, time, instructor and cost, described as a Course for Google's course list. A post with several courses gets an ItemList of them, each at its card's anchor; a list of posts that are all courses (three or more) gets the ItemList of a summary page.
* Changed: the Event and Course block previews in the editor use the post title as it is being edited.

= 1.9.0 =
* New: YouTube videos in Embed blocks are described as VideoObject. Title and thumbnail come from YouTube's oEmbed when the post is saved (never while a page is served); the publish date, which Google requires, is entered per video in the SEO panel, and the pre-publish checklist mentions videos without one.
* New: the Event block, a card with when, where, tickets and organizer, described as an Event following Google's event guidelines (UTC offset or date-only for all-day events, a place with an address, one event per post).
* New: FAQ and How-to in the block inserter, Group blocks of core blocks (Details, numbered list) described as FAQPage and HowTo for search engines and AI assistants. Google no longer shows these rich results; the markup only repeats what the page shows.

= 1.8.1 =
* Fixed: parent categories whose posts are all in subcategories are in the sitemap, as their pages may be indexed.
* Fixed: WebSite and Organization markup is on the home page itself, not on its page 2 and later.
* Fixed: plain words in an address field (redirect, canonical, official profiles, author profile links) are refused instead of being turned into an address like "http://not%20a%20link".
* Fixed: a post saved through the REST API inside PHP (a plugin's rest_do_request(), WP-CLI's REST commands) is no longer kept as a draft when it has everything it needs.
* Fixed: no PHP warning from the SEO column when Quick Edit saves a post.
* Changed: the breadcrumb trail and the sitemap's list of categories and tags are worked out once per page request (main query) rather than once per PHP process.

= 1.8.0 =
* Changed: requires PHP 8.5 or newer, the version the servers run. The code uses current language features: array_find() and array_first(), first-class callables, typed properties and class constants, and a never return type for the permalink switch.
* Fixed: values from another SEO plugin that are only spaces, or not text at all, are skipped for posts as they already were for categories, tags and authors.

= 1.7.0 =
* Changed: requires WordPress 7.0 or newer. The SEO panel and the pre-publish checklist use only the current editor API (`wp.editor`), without the fallback for older versions.

= 1.6.1 =
* Changed: the editor and admin scripts and styles follow WordPress's JavaScript and CSS coding standards, now checked by ESLint and Stylelint. Nothing changes in how they work.
* Fixed: the SEO panel no longer calls `getMedia`, which WordPress 6.9 deprecated (a warning in the browser console).

= 1.6.0 =
* New: shared links use a 1200 × 630 (1.91:1) crop of the featured image, the shape Facebook, LinkedIn, X and messaging apps show, instead of the 16:9 crop they had to trim. Google's structured data keeps its 16:9, 4:3 and 1:1 crops.
* New: images uploaded before this version get the share crop in the background (WP-Cron), once per image; until then the 16:9 crop is shared. `wp media regenerate --only-missing` makes them all at once.
* Changed: Settings → SEO now asks for a 1200 × 630 default share image.

= 1.5.0 =
* New: Meta (Facebook) domain verification in Settings → SEO → Site verification (formerly Search engine verification), for sites that run Facebook or Instagram ads, use the Meta Pixel or edit their link previews. Paste the code or the whole meta tag from Meta Business Suite.

= 1.4.0 =
* New: Settings → SEO → Search engine verification. Paste the code from Google Search Console or Bing Webmaster Tools (or the whole meta tag they show) and the verification tag is added to the home page. A value that isn't a valid code is refused, and the code saved before stays.

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

== Upgrade Notice ==

= 1.17.0 =
New Address and contact settings for the organization's markup (kind, address, telephone, email). Coming from Yoast Local SEO or Rank Math? `wp tala-seo import` copies them.

= 1.16.0 =
Title separator setting, attachment pages switch, and a fuller import from Yoast SEO and Rank Math. Coming from one? Run `wp tala-seo import --dry-run`, then `wp tala-seo import`, and `wp tala-seo describe` for posts without a description.

= 1.13.0 =
Hardening. Members who never published lose the profile markup on their empty author page. Subscribers and contributors no longer see a photo picker they couldn't use.

= 1.12.0 =
Security fixes; update soon. On multisite, authors choose their photo again on each site. An author photo attached to an unpublished post shows once that post is published.

= 1.11.0 =
Breadcrumb structured data now appears only where the Breadcrumbs block shows the trail. Using a theme other than Tala Blog? Add the Breadcrumbs block to your templates in Appearance → Editor.

= 1.10.1 =
Readme and author details only. Nothing changes on your site.

= 1.10.0 =
New Course block for Google's course list. Give each course its own post and file them in a category of their own, three or more, so its page lists them.

= 1.9.0 =
New Event block, FAQ and How-to groups, and markup for YouTube videos. Enter each video's YouTube publish date in the SEO panel: videos without one get no video markup.

= 1.8.0 =
Requires PHP 8.5. Check your server's PHP version before updating.

= 1.7.0 =
Requires WordPress 7.0.

= 1.6.0 =
Run `wp media regenerate --only-missing` once, to make the new 1200 × 630 share crop of existing images.
