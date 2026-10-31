=== Tala Ads ===
Contributors: edventuredev
Tags: adsense, ads, google tag manager, consent, advertising
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.5
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AdSense Auto ads, Google Tag Manager and Google's consent message, each loaded once and set up with IDs alone. Works with any theme.

== Description ==

Tala Ads puts a site's ad and tracking code in one place, outside the theme, so switching themes never takes the ads with it.

* **AdSense Auto ads.** Enter the publisher ID and the AdSense code goes on every page, once. Google places the ads: you choose the formats (in-page, anchor, vignette, multiplex) and how many in AdSense. Error pages and searches that found nothing get no ads, as AdSense doesn't allow ads on pages without the site's own content.
* **Google Tag Manager.** Enter the container ID: the code goes as high in the head as WordPress allows, and the fallback for browsers without JavaScript right after the opening body tag. The container then loads Google Analytics and the other tags.
* **Google's consent message.** One switch loads the message set up under Privacy & messaging in AdSense, which Google needs to show personalized ads to visitors from the European Economic Area, the UK and Switzerland, and its ad blocking recovery message when that is on.
* **Innity.** An optional 300 × 250 box after the text of each post and page, with its height kept free while the ad loads so the page doesn't move. Innity's universal tags (full-screen phone formats) aren't offered: they shift the page as it loads.
* **IDs only, never code.** Each field keeps just the ID it asks for, even when the whole code is pasted, so nothing typed into the settings can put a script on the site.
* **ads.txt check.** Settings → Ads says whether the site's ads.txt lists the publisher ID, without which AdSense may hold back ads.
* **Any theme.** Every script goes through WordPress's script loader, which keeps each one to a single copy per page, and the box is added after the post's content. A theme only needs to call `wp_body_open()`, as WordPress asks themes to.

Developers can keep ads off a page with the `tala_ads_show` filter (return false), and choose which post types get the Innity box with `tala_ads_post_types` (posts and pages by default).

== Installation ==

1. Download the zip of the latest version from https://github.com/llegomark/wp-blog-kit/releases (under Assets), then upload it in Plugins → Add New Plugin → Upload Plugin, or run `wp plugin install tala-ads-<version>.zip --activate`.
2. Open Settings → Ads and enter your AdSense publisher ID, your Tag Manager container ID, or both.
3. Remove the same code from wherever it was before (the theme, a code snippets plugin, header and footer settings), so it doesn't load twice.
4. In AdSense, turn on Auto ads for the site under Ads → By site.

== Frequently Asked Questions ==

= Where do the ads appear? =

Where AdSense's Auto ads put them. Google picks the places on each page; in AdSense, under Ads → By site, you choose the formats, the number of ads, and pages or parts of pages that should have none.

= Can I paste the code AdSense or Tag Manager gave me? =

Yes. Tala Ads takes the ID out of it and keeps only that. The settings never store code.

= Does it work with page caching? =

Yes. The code is the same for every visitor, so cached pages carry it. Google's scripts choose the ads in the visitor's browser.

= Why no ads on 404 pages? =

AdSense's policies don't allow Google ads on pages without the site's own content, such as error pages and empty search results.

== Screenshots ==

1. Settings → Ads: the AdSense publisher ID with the ads.txt check, the consent message, the Tag Manager container ID and the Innity box.

== Changelog ==

= 1.0.0 =
* First release: AdSense Auto ads, Google Tag Manager, Google's consent message and an Innity box after posts and pages, each loaded once, set up with IDs only.

== Upgrade Notice ==

= 1.0.0 =
First release. Remove the same ad and Tag Manager code from the theme or other plugins when you turn this on, so nothing loads twice.
