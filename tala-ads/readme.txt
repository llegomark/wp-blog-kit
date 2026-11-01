=== Tala Ads ===
Contributors: edventuredev
Tags: adsense, ads, google tag manager, google search, ad blocking
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.5
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AdSense Auto ads, Google Tag Manager, Google's consent and ad blocking recovery messages, and Google search, set up with IDs. Works with any theme.

== Description ==

Tala Ads puts a site's ad and tracking code in one place, outside the theme, so switching themes never takes the ads with it.

* **AdSense Auto ads.** Enter the publisher ID and the AdSense code goes on every page, once. Google places the ads: you choose the formats (in-page, anchor, vignette, multiplex) and how many in AdSense. Error pages and searches that found nothing get no ads, as AdSense doesn't allow ads on pages without the site's own content. A place Google leaves without an ad is hidden, so no empty frame or blank gap shows in the text, and an ad frame waiting for its ad stays see-through in dark mode instead of showing a white box.
* **Google Tag Manager.** Enter the container ID: the code goes as high in the head as WordPress allows, and the fallback for browsers without JavaScript right after the opening body tag. The container then loads Google Analytics and the other tags.
* **Google's consent and ad blocking recovery messages.** One switch loads the messages set up under Privacy & messaging in AdSense, which come from one Google tag: the consent message, which Google needs to show personalized ads to visitors from the European Economic Area, the UK and Switzerland, and the ad blocking recovery message.
* **Error protection.** Google's optional error protection code, which asks readers to allow ads when an ad blocker stops the recovery message itself, goes on pages with ads. It is the one piece of code the settings keep: only in the shape Google gives it, for the site's own publisher ID, naming only Google's addresses, and only from people WordPress lets add scripts.
* **Google search.** With a Programmable Search Engine ID and a results page, Google answers every search on the site: WordPress's search forms send their searches to that page, where its Search block shows Google's search box (with the block's placeholder, or "Type here to search", as its hint) and results in the theme's look: its colours (light and dark), font, links, form field and button, from the theme.json presets block themes define. Results for a search get noindex, as Google asks for internal search results.
* **Innity.** An optional 300 × 250 box after the text of each post and page, with its height kept free while the ad loads so the page doesn't move. Innity's universal tags (full-screen phone formats) aren't offered: they shift the page as it loads.
* **IDs, not code.** Each ID field keeps just the ID it asks for, even when the whole code is pasted, so nothing typed into those fields can put a script on the site.
* **ads.txt check.** Settings → Ads says whether the site's ads.txt lists the publisher ID, without which AdSense may hold back ads.
* **Any theme.** Every script goes through WordPress's script loader, which keeps each one to a single copy per page, and the box is added after the post's content. A theme only needs to call `wp_body_open()`, as WordPress asks themes to.

Developers can keep ads off a page with the `tala_ads_show` filter (return false), and choose which post types get the Innity box with `tala_ads_post_types` (posts and pages by default).

== Installation ==

1. Download the zip of the latest version from https://github.com/llegomark/wp-blog-kit/releases (under Assets), then upload it in Plugins → Add New Plugin → Upload Plugin, or run `wp plugin install tala-ads-<version>.zip --activate`.
2. Open Settings → Ads and enter your AdSense publisher ID, your Tag Manager container ID, or both, and for Google search your engine ID and results page.
3. Remove the same code from wherever it was before (the theme, a code snippets plugin, header and footer settings), so it doesn't load twice.
4. In AdSense, turn on Auto ads for the site under Ads → By site.

== Frequently Asked Questions ==

= Where do the ads appear? =

Where AdSense's Auto ads put them. Google picks the places on each page; in AdSense, under Ads → By site, you choose the formats, the number of ads, and pages or parts of pages that should have none.

= Can I paste the code AdSense or Tag Manager gave me? =

Yes. Tala Ads takes the ID out of it and keeps only that. The settings never store code.

= Does it work with page caching? =

Yes. The code is the same for every visitor, so cached pages carry it. Google's scripts choose the ads in the visitor's browser.

= How does Google search replace WordPress's search? =

Every search form on the site still sends its search to WordPress (?s=…). With Google search set up, WordPress sends it on to the results page (?q=…), where Google's script reads it and shows Google's results in place of the page's Search block, or after the page's text when it has none. The theme's search forms need no change; on the results page itself they are left out, so Google's box is the only one there.

= Why does the error protection code need special care? =

It is code Google writes for each site and changes from time to time, so it can't be cut down to an ID. Tala Ads keeps it only when it has the shape of Google's code, names your publisher ID and only Google's servers, and contains nothing that could end its script or start another. Only people allowed to add scripts to the site (administrators, unless the site turns that off) can change it.

= Why no ads on 404 pages? =

AdSense's policies don't allow Google ads on pages without the site's own content, such as error pages and empty search results.

== Screenshots ==

1. Settings → Ads: the AdSense publisher ID with the ads.txt check, Google's messages and error protection, the Tag Manager container ID, Google search and the Innity box.

== Changelog ==

= 1.3.0 =
* Fixed: in dark mode an ad frame no longer shows as a white box while it waits for its ad (or for Google to mark it unfilled). Browsers paint the frame of a light page white on a dark page; ad frames are now declared light, so they stay see-through as on a light page, and an ad paints its own background.
* New: Google's search box shows a hint until one types: the placeholder of the Search block it replaces ("Type here to search" when the block has none, or when Google's results follow the page's text).
* Changed: the results page shows one search box, Google's. Other Search blocks on that page, such as a theme's search form at the top or in the footer, are left out there; on every other page they stay and send their searches to it.

= 1.2.0 =
* Fixed: a place Auto ads leave without an ad no longer shows. Google marks such slots "unfilled", collapsed but with their ad frame still visible (a white box over the text), or "unfill-optimized", a blank gap; both are hidden now, as Google recommends for unfilled slots.

= 1.1.0 =
* New: Google's error protection code for the ad blocking recovery message (Settings → Ads → Error protection), kept only in Google's shape, for the site's publisher ID, and changeable only by people who may add scripts.
* New: Google search. With a Programmable Search Engine ID and a results page, WordPress's searches go to that page, where its Search block shows Google's search box and results in the theme's colours, font, links and form styles, light and dark; results for a search get noindex.
* The messages switch is now named for what it loads: Google's consent and ad blocking recovery messages.

= 1.0.0 =
* First release: AdSense Auto ads, Google Tag Manager, Google's consent message and an Innity box after posts and pages, each loaded once, set up with IDs only.

== Upgrade Notice ==

= 1.3.0 =
No white box in dark mode while an ad loads; a hint in Google's search box (the Search block's placeholder); one search box on the results page.

= 1.2.0 =
Hides the empty places Auto ads leave without an ad: no more white boxes over the text or blank gaps.

= 1.1.0 =
New: Google's error protection code for the ad blocking recovery message, and Google search in place of WordPress's search. Remove any other copy of Google's search script so it doesn't load twice.

= 1.0.0 =
First release. Remove the same ad and Tag Manager code from the theme or other plugins when you turn this on, so nothing loads twice.
