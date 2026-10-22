=== Tala Blog ===
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A minimal, fast blog theme for long reads on phones, with a pad-paper look and an automatic dark mode.

== Description ==

Tala Blog is a block theme for blogs that are mostly read on phones.

* Pad-paper look in light mode (white sheet, ruled lines, red margin line) and a chalkboard look in dark mode, chosen automatically from the reader's device setting.
* System fonts only: nothing to download, and the theme's CSS is inlined, so a page needs no stylesheet requests.
* Article pages: breadcrumbs, "Published" and "Updated" dates, featured image, "Filed under" categories and tags, an author box with biography, links to the previous and next articles, and comments with their dates.
* Article lists (home page, archives, search results): title, summary, date and a thumbnail of the featured image, with numbered pages.
* Accessible: one H1 per page, labelled landmarks, a skip link, visible keyboard focus, and text contrast of at least 6:1 in both colour schemes.
* Prints just the article, with its dates and author, in black on white.
* Translation-ready: every text in the templates comes from translatable patterns (text domain tala-blog).

Pairs with the Tala SEO plugin, which provides the breadcrumbs block and the structured data. Without the plugin, the breadcrumbs are simply left out.

== Frequently Asked Questions ==

= How many articles does a page show? =

As many as Settings → Reading → "Blog pages show at most" says (10 by default). Older articles follow on numbered pages.

= How do I change the header menu? =

The header lists the site's pages. For a hand-made menu, replace the page list with a Navigation block in Appearance → Editor → Patterns → Header.

== Changelog ==

= 1.1.0 =
* New: thumbnails of the featured image in article lists, and numbered pages under them.
* New: "Filed under" categories at the end of an article, next to the tags.
* New: comments show their date (linked to the comment) and an edit link for editors.
* New: print styles that print only the article, with its dates and author.
* Accessibility: breadcrumb links are underlined in the text colour, so they stand apart from the current page; the header menu is a labelled navigation landmark.
* All template text (404 page, search form, author box, article navigation, categories and tags) moved into translatable patterns.
* The dark palette is defined once, in theme.json (settings.custom.dark), and used by both the CSS and the browser's theme colour.
* readme.txt with copyright and licence information.

= 1.0.1 =
* Theme header: author and author URI.

= 1.0.0 =
* First release.

== Copyright ==

Tala Blog WordPress Theme, (C) 2026 Mark Anthony Llego.
Tala Blog is distributed under the terms of the GNU GPL.

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

== Resources ==

The theme bundles no third-party fonts, images, scripts or icons.

Screenshot (screenshot.png): the theme showing the kit's own demo content and a generated demo image, (C) 2026 Mark Anthony Llego, GPLv2 or later.
