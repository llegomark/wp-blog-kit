=== Tala Blog ===
Contributors: edventuredev
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A minimal, fast blog theme for long reads on phones: near-black text on a near-white page, and an automatic dark mode.

== Description ==

Tala Blog is a block theme for blogs that are mostly read on phones.

* A quiet reading page: near-black text on a near-white page, a 640 px column, fine dotted link underlines and hairline dividers. Dark mode is the same look inverted, chosen automatically from the reader's device setting.
* System fonts only: nothing to download, and the theme's CSS is inlined, so a page needs no stylesheet requests.
* A header with the site logo (when the site has one) beside the site title, and the site's menu, or its pages until there is one. On phones it folds behind a menu button and opens full screen in the page's colours, light or dark.
* Article pages: breadcrumbs, "Published" and "Updated" dates, featured image, "Filed under" categories and tags, an author box with biography, links to the previous and next articles, and comments with their dates.
* Article lists (home page, archives, search results): title, summary, date and a thumbnail of the featured image, with numbered pages.
* Accessible: one H1 per page, uniquely named landmarks, one search form per page, a skip link, visible keyboard focus, text contrast of at least 6:1 and form fields of at least 3:1 in both colour schemes, and long words that wrap instead of widening the page when zoomed.
* Prints just the article, with its dates and author, in black on white.
* Translation-ready: every text in the templates comes from translatable patterns (text domain tala-blog), and translated block settings are escaped as JSON, so no translation can break a block.

* Older posts look right too: images aligned by the classic editor, [caption] and [gallery], fixed-width video embeds, plain tables and quotes, password forms, untitled posts, and titles with very long words, checked with WordPress's Theme Unit Test on phones and desktops, left to right and right to left.
* A pinned (sticky) post is marked "Pinned" at the top of the home page, the article author's comments are marked "Author", and a post that has stopped taking comments says so under them.

Pairs with the Tala SEO plugin, which provides the breadcrumbs block and the structured data. Without the plugin, the breadcrumbs are simply left out, and the theme works on its own.

== Frequently Asked Questions ==

= How many articles does a page show? =

As many as Settings → Reading → "Blog pages show at most" says (10 by default). Older articles follow on numbered pages.

= How do I change the header menu? =

In Appearance → Editor → Navigation. Until you make a menu, the header lists the site's pages (child pages left out), and WordPress saves that list as a menu called "Navigation" that you can edit. On phones the same menu opens from the menu button; its full-screen view is the "Menu overlay" template part in Appearance → Editor → Patterns.

= How do I add a logo? =

In Appearance → Editor → Patterns → Header, select the logo placeholder beside the site title and choose an image. It appears 28 px wide in the header and in the phone menu. A square or round image works best. The logo isn't a link and screen readers skip it, because the site title beside it already names the site and links to the home page.

== Changelog ==

= 1.6.0 =
* New: classic content from the classic editor looks right: images aligned left, right or centred with text around them, captions and [gallery] grids, video players and wide images that fit a phone, and plain tables and quotes styled like their blocks.
* New: "Pinned" marks the sticky post at the top of the home page; "Author" marks the comments of the article's author; "Comments are closed." appears under the comments of a post that no longer takes them.
* New: a post without a title gets its date as the heading, linked in article lists, so it can still be opened.
* Fixed: the breadcrumbs come from a pattern that is empty without the Tala SEO plugin, so the Site Editor no longer reports a missing block when the plugin is off.
* Fixed: a very long word in the next or previous article's title wraps instead of widening the page on phones.
* Changed: "Leave a Reply" is a level-two heading, like the comments' title, so headings don't skip a level on articles with no comments yet. The password field of a protected post is a full-size field.

= 1.5.1 =
* Theme header and readme: Edventure is the author and copyright holder; the readme names the WordPress.org contributor.

= 1.5.0 =
* New: the site logo, when the site has one, beside the site title in the header and in the phone menu. Both use one pattern ("Logo and site title"), so the menu's top bar still lines up exactly with the header.
* Fixed: the phone menu no longer seems to shake as it opens. It fades in without WordPress's slide-up, which showed a second, moving copy of the site title and menu button for a moment.

= 1.4.2 =
* Fixed: pages no longer jump sideways when going from a long page to a short one (a 404, a search with few results, a short page). Short pages keep the same room for the scrollbar as long ones.
* Fixed: on short pages the footer sits at the bottom of the window instead of halfway up it.

= 1.4.1 =
* Fixed: search results and 404 pages show one search form, at the top, instead of a second one in the footer that repeated the search words. They use the new "Footer without search" template part; the site title and tagline row is one pattern shared by both footers. Search or 404 templates customized in the Site Editor keep the old footer until their customizations are cleared.

= 1.4.0 =
* Changed: requires PHP 8.5 or newer, the version the servers run. The theme's PHP uses current language features (array_find(), arrow functions); nothing changes in how it looks or works.

= 1.3.0 =
* New: the header shows the site's menu (a Navigation block named "Primary"), or the site's pages until there is one, as a single list for screen readers. On phones it folds behind a menu button and opens full screen in the page's colours, light or dark, with the site title and a close button. That screen is the "Menu overlay" template part.
* Changed: requires WordPress 7.0 or newer, and the theme carries no code for older versions. A header customized in the Site Editor before this update keeps its old page list; clear its customizations (Appearance → Editor → Patterns → Header) to get the new menu.

= 1.2.0 =
* Changed: a minimalist look. Light mode is near-black text (#3f3f3f) on a near-white page (#fcfcfc), with black for emphasis; dark mode is the same, inverted (#e3e3e3 on #121212). The pad-paper margin line and the chalkboard colours are gone, and the palette colours are named by their role.
* Changed: a 640 px reading column, line height 1.6, semibold headings on a calmer scale, fine dotted link underlines in the text colour, quotes and code in soft boxes, and table header rows and stripes that suit both colour schemes.
* New: the two search forms on search results and 404 pages are named differently ("Articles", "Site"), and the header menu is named "Primary".
* New: form fields keep at least 3:1 contrast; long words and web addresses wrap instead of widening the page; fewer lonely last words in headings and paragraphs; links to a section leave room above it; a short crossfade between pages, unless the reader prefers less motion.
* Fixed: translated text in block settings is escaped as JSON, so a translation with a quote or backslash can't break a block.

= 1.1.2 =
* Changed: the stylesheet follows WordPress's CSS coding standards, now checked by Stylelint. Nothing changes in how the theme looks.

= 1.1.1 =
* Faster article lists on phones: thumbnails tell the browser their real width (5.5rem, 8rem on wider screens), so it downloads the 300 px image instead of the 768 or 1024 px one.

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

Tala Blog WordPress Theme, (C) 2026 Edventure.
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

Screenshot (screenshot.png): the theme showing the kit's own demo content and a generated demo image, (C) 2026 Edventure, GPLv2 or later.
