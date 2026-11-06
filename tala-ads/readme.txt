=== Tala Ads ===
Contributors: edventuredev
Tags: adsense, ads, google tag manager, google search, ad blocking
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.5
Stable tag: 1.9.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AdSense Auto ads and units, Tag Manager, Google's consent and ad blocking recovery messages, and Google search, set up with IDs. Works with any theme.

== Description ==

Tala Ads puts a site's ad and tracking code in one place, outside the theme, so switching themes never takes the ads with it.

* **AdSense Auto ads.** Enter the publisher ID and the AdSense code goes on every page, once, at the top of the head, before the page's styles, so the browser starts loading it first. Just before it, the browser is told to connect to AdSense's servers early: the one the code comes from and the one the ads load from, which the browser would otherwise only learn of once the code has run. Nothing else of Tala Ads goes ahead of the page. Google places the Auto ads: you choose the formats (in-page, anchor, vignette, side rail) and how many in AdSense. Nothing Google places, and none of the units below, is ever hidden, filled or not. Error pages and searches that found nothing get no ads, as AdSense doesn't allow ads on pages without the site's own content. An ad frame waiting for its ad, or left empty, stays see-through in dark mode instead of showing a white box, and an empty frame Google leaves past the window's edge gives the page no sideways scrollbar. Apart from the frames' color scheme, Tala Ads never styles Google's own elements: no size, position, clipping or visibility. The site header, the site title, tagline and logo wherever they appear, and text hidden from screen readers (aria-hidden="true"), such as a theme's decorative wordmark, get Google's google-anno-skip class, so ad intents don't turn them into links.
* **AdSense ad units, beside Auto ads.** Optional, from each unit's ID (or its pasted code):
  * An In-article ad after the second paragraph of each post, where Google suggests, with its space kept free while it loads so the text doesn't jump. Auto ads keep their distance from it.
  * A Multiplex ad right after the text of each post, before any Innity ad.
* **Google Tag Manager.** Enter the container ID: the code goes in the head with the page's other scripts, and the fallback for browsers without JavaScript right after the opening body tag. The container then loads Google Analytics and the other tags.
* **Google's consent and ad blocking recovery messages.** One switch loads the messages set up under Privacy & messaging in AdSense, which come from one Google tag: the consent message, which Google needs to show personalized ads to visitors from the European Economic Area, the UK and Switzerland, and the ad blocking recovery message.
* **Error protection.** Google's optional error protection code, which asks readers to allow ads when an ad blocker stops the recovery message itself, goes on pages with ads. It is the one piece of code the settings keep: only in the shape Google gives it, for the site's own publisher ID, naming only Google's addresses, and only from people WordPress lets add scripts.
* **Google search.** With a Programmable Search Engine ID and a results page, Google answers every search on the site: WordPress's search forms send their searches to that page, where its Search block shows Google's search box (with the block's placeholder, or "Type here to search", as its hint) and results in the theme's look: its colors (light and dark), font, links, form field and button, from the theme.json presets block themes define. Results for a search get noindex, as Google asks for internal search results.
* **Innity.** Each part optional, from the zone IDs in Innity's zone tags, and always below the AdSense ads:
  * A 300 × 250 box after the text of each post and page, below the AdSense ads, centered, with its height kept free while the ad loads so the page doesn't move. The box is never hidden.
  * Universal tags, one for wide screens and one for phones. Innity picks the format. Its in-read ad goes after the text and the box, below the AdSense ads, instead of after the second paragraph, where Innity would put it. The phones' tag also brings full-screen formats, which cover the page on top of AdSense's vignette and anchor ads; leave it empty to keep them off.
  * Innity's tags read no consent signal of their own and set an identifier cookie. With Google's messages on, Innity waits for Google's consent data and stays off where the GDPR applies. It also stays off searches and Google's results page, whose addresses carry what readers typed. The Universal tag loads once the page has.
  * Innity's in-image ads, laid over the lower part of a large image, stay off: the site's images carry data-innity-allow-in-image="false", which Innity's image finder and its ads skip. On a scanned memo or an infographic they would cover its text.
* **IDs, not code.** Each ID field keeps just the ID it asks for, even when the whole code is pasted, so nothing typed into those fields can put a script on the site.
* **ads.txt check.** Settings → Ads says whether the site's ads.txt lists the publisher ID, without which AdSense may hold back ads.
* **Any theme.** Every script goes through WordPress's script loader, which keeps each one to a single copy per page, and the boxes are added to the post's content. A theme only needs to call `wp_body_open()`, as WordPress asks themes to.

Developers can keep ads off a page with the `tala_ads_show` filter (return false), choose which post types get the Innity box and in-read place with `tala_ads_post_types` (posts and pages by default). Return true from `tala_ads_innity_in_image` to allow Innity's in-image ads. `tala_ads_unit_post_types` chooses which post types get the In-article and Multiplex ads (posts by default), and `tala_ads_in_article_paragraphs` (2) how many paragraphs come before the In-article ad.

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

1. Settings → Ads: the AdSense publisher ID with the ads.txt check, Google's messages and error protection, the Tag Manager container ID, Google search and Innity's box, Universal tags and channels.

== Changelog ==

= 1.9.6 =
* Fixed: ad intents no longer turn the site's name into a link. The site header (the logo, site name, menu and search inside it) and the Site Title, Site Tagline and Site Logo blocks wherever they appear, such as the footer's copyright line, get google-anno-skip, Google's own class for keeping ad intents out of part of a page. In the header, the link pushed the name onto two lines inside a big pill. The article's text stays open to ad intents.

= 1.9.5 =
* Fixed: a page with an unfilled Auto ads place is no longer wider than the window. 1.9.1's clip on the root element removed the scrollbar but left the page wider (2145 px in a 1890 px window on a busy page), which scripts could scroll and phones such as iPhone Safari may let a finger pan. A block theme's page wrapper, .wp-site-blocks, now clips too, so Google's empty frames are cut where they start. Google's own elements still get no rule of their own.

= 1.9.4 =
* Fixed: ad intents no longer turn decorative text into links. Text hidden from screen readers (aria-hidden="true"), such as Tala Blog's giant footer wordmark, gets google-anno-skip, Google's own class for keeping ad intents out of part of a page. On the wordmark, the link took its giant size and was cut off by the band. Everything else stays open to ad intents, and nothing of Google's is styled.

= 1.9.3 =
* Fixed: in dark mode, Google's ad frames no longer show as white boxes. The rule that declares them light pages (color-scheme), removed in 1.9.2, is back: an unfilled place's empty frame, which can lie over the text, and a frame waiting for its ad stay see-through. It sets only the frames' color scheme, nothing about their size, position or visibility.

= 1.9.2 =
* Removed: the rule that declared ad frames light (color-scheme), so in dark mode they stayed see-through instead of white while waiting for their ad. Tala Ads now has no rule on Google's own elements at all; in dark mode a frame may show white until its ad paints.

= 1.9.1 =
* Fixed: no sideways scrollbar from an Auto ads place Google leaves unfilled. Google shrinks such a place to nothing but keeps its empty frame, as wide as before, centered on it, so the frame stuck out past the window's edge. The page now clips at the window's edge (overflow-x: clip on the root element): only empty space off the screen is cut. Tala Ads never sizes, moves, clips or hides Google's own elements.

= 1.9.0 =
* New: AdSense ad units beside Auto ads, each optional, from its ID or pasted code under Settings → Ads: an In-article ad after the second paragraph of each post, and a Multiplex ad right after the text. Auto ads alone left the middle and end of the article to other ads. Neither unit is ever hidden, filled or not.
* Changed: Innity goes below the AdSense ads. Its box and its in-read place follow the post's text and the Multiplex ad, instead of the sixth and fourth paragraphs, as before the site moved to Tala Ads. The `tala_ads_innity_paragraphs` and `tala_ads_innity_in_read_paragraphs` filters are gone.

= 1.8.2 =
* New: on pages with ads, the browser connects early to AdSense's servers: preconnect to pagead2.googlesyndication.com (in the anonymous CORS mode the AdSense tag loads in) and to googleads.g.doubleclick.net, where the ads load, and a DNS lookup for tpc.googlesyndication.com, which serves the ads' files. The DNS lookups WordPress adds for Google's messages and Innity stay.

= 1.8.1 =
* Changed: only the AdSense tag comes first in the page's head, so the ads load first. Tag Manager, Google's messages and error protection print with the page's other head scripts, after its styles, as before 1.8.0.

= 1.8.0 =
* Removed: the In-article ad unit. Google's Auto ads place every AdSense ad; Tala Ads adds none of its own.
* Removed: hiding the places Auto ads leave without an ad. Nothing Google places is hidden.
* Removed: closing the Innity box when no ad comes. The box is never hidden.
* Changed: Google's tags (Tag Manager, the consent message, AdSense) come first in the page's head, before its styles, so the browser starts loading AdSense sooner with a theme that inlines its CSS.

= 1.7.1 =
* Changed: Google's search results on the results page show no thumbnails beside each result, like the theme's lists of articles; the title and summary take the full width.

= 1.7.0 =
* New: with a Universal tag set, Innity's in-image ads stay off the site's images, which carry data-innity-allow-in-image="false": those in posts and pages and those printed from the media library, such as featured images. Innity's image finder and its in-image ads both skip them. The `tala_ads_innity_in_image` filter allows them again.

= 1.6.0 =
* Changed: with Google's messages on, Innity waits for Google's consent data and doesn't load where the GDPR applies; the box then closes. Innity's tags read no consent signal of their own, and set an identifier cookie (iUUID) that they share with partners.
* Changed: the Universal tag loads once the page has loaded and the browser is idle (at most 2 seconds later), so it doesn't compete with the page. Its in-read ad draws only when scrolled to.
* Changed: no Innity on searches or Google's results page. Innity's tags send the page's full address, which there carries what readers typed.
* Changed: the box closes as soon as Innity's blank ad hides its frame, instead of a few seconds later, and an error in Innity's code no longer reaches the page.

= 1.5.1 =
* Fixed: Innity's in-read ads now open in the place Tala Ads keeps for them, after the fourth paragraph. They look only for a span with the id or class innity-in-post, which the place now is; without one, they found the article themselves and opened after its second paragraph, next to the In-article ad.

= 1.5.0 =
* New: Innity's Universal tags, one zone for wide screens and one for phones, each optional. The browser picks the zone for its screen, as the cached page is the same for every device. Innity's in-read ad gets its own place after the fourth paragraph, two below the In-article ad, instead of after the second, on top of it.
* Changed: the Innity box goes after the sixth paragraph, where readers still are, instead of after the whole article. It is centered, and closes when Innity sends no ad (a zone whose alternate ad is "Blank Ad") or none arrives, as behind an ad blocker.
* Changed: Innity's zones no longer pass the "%%CLICK_URL_UNESC%%" click macro. Only Google Ad Manager fills it in; Innity's tag for direct placement has none.
* New: the zone and channel fields also take Innity's standard zone tag (new innity_adZone), and the channels field takes a pasted zone tag.

= 1.4.1 =
* Fixed: no more "AdSense head tag doesn't support data-wp-strategy attribute" warning in the browser console on every page. AdSense reads each data- attribute on its script tag as a setting and warns about those it doesn't know; WordPress adds data-wp-strategy to scripts that load asynchronously. The note is now left off AdSense's tag, which still loads asynchronously.

= 1.4.0 =
* New: an AdSense In-article ad unit, after the second paragraph of each post. Enter the unit's ID (or paste its code: only the ID is kept) under Settings → Ads. Only the article's own paragraphs count; its space is kept free while it loads, so the text doesn't jump, and closes if Google leaves it unfilled. The ad comes before the Innity box, which still follows the article's text.

= 1.3.0 =
* Fixed: in dark mode an ad frame no longer shows as a white box while it waits for its ad (or for Google to mark it unfilled). Browsers paint the frame of a light page white on a dark page; ad frames are now declared light, so they stay see-through as on a light page, and an ad paints its own background.
* New: Google's search box shows a hint until one types: the placeholder of the Search block it replaces ("Type here to search" when the block has none, or when Google's results follow the page's text).
* Changed: the results page shows one search box, Google's. Other Search blocks on that page, such as a theme's search form at the top or in the footer, are left out there; on every other page they stay and send their searches to it.

= 1.2.0 =
* Fixed: a place Auto ads leave without an ad no longer shows. Google marks such slots "unfilled", collapsed but with their ad frame still visible (a white box over the text), or "unfill-optimized", a blank gap; both are hidden now, as Google recommends for unfilled slots.

= 1.1.0 =
* New: Google's error protection code for the ad blocking recovery message (Settings → Ads → Error protection), kept only in Google's shape, for the site's publisher ID, and changeable only by people who may add scripts.
* New: Google search. With a Programmable Search Engine ID and a results page, WordPress's searches go to that page, where its Search block shows Google's search box and results in the theme's colors, font, links and form styles, light and dark; results for a search get noindex.
* The messages switch is now named for what it loads: Google's consent and ad blocking recovery messages.

= 1.0.0 =
* First release: AdSense Auto ads and ad units, Google Tag Manager, Google's consent message and an Innity box after posts and pages, each loaded once, set up with IDs only.

== Upgrade Notice ==

= 1.9.0 =
Optional In-article and Multiplex AdSense units beside Auto ads (Settings → Ads), never hidden. Innity moves below the AdSense ads, after the post's text.

= 1.8.0 =
Auto ads place every AdSense ad: the In-article unit is gone and nothing Google places is hidden. AdSense loads first in the head. Delete the In-article unit in AdSense if unused.

= 1.6.0 =
Innity now waits for Google's consent data and stays off where the GDPR applies, skips searches, and loads its Universal tag after the page.

= 1.5.0 =
The Innity box moves to after the sixth paragraph and closes when no ad comes; set its zone's alternate ad to Blank Ad in Innity. Optional Universal tags (wide screens, phones) under Settings → Ads.

= 1.4.0 =
Optional AdSense In-article ad after each post's second paragraph: enter the ad unit's ID under Settings → Ads to turn it on.

= 1.3.0 =
No white box in dark mode while an ad loads; a hint in Google's search box (the Search block's placeholder); one search box on the results page.

= 1.2.0 =
Hides the empty places Auto ads leave without an ad: no more white boxes over the text or blank gaps.

= 1.1.0 =
New: Google's error protection code for the ad blocking recovery message, and Google search in place of WordPress's search. Remove any other copy of Google's search script so it doesn't load twice.

= 1.0.0 =
First release. Remove the same ad and Tag Manager code from the theme or other plugins when you turn this on, so nothing loads twice.
