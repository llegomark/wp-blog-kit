=== Tala Blog ===
Contributors: edventuredev
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A fast blog theme for long reads on phones, in a "drafting paper" look: dark ink on warm paper, one cobalt accent, Inter, and a Light/Dark switch.

== Description ==

Tala Blog is a block theme for blogs that are mostly read on phones.

* A "drafting paper" look: dark ink on a warm paper colour, one cobalt accent used sparingly, and every section a full-width band ruled underneath, with a centred 70rem rail whose side rules meet the bands at small diamond nodes. Graph paper shows only behind the title bands, the call to action and the 404 panel, never behind text.
* Inter, self-hosted: the variable Latin font (and its Latin Extended and italic subsets only for the characters that need them) ships with the theme and is preloaded, with metric-matched fallbacks so the page barely moves when it arrives. No font service is called, and the theme's CSS is inlined, so a page needs no stylesheet requests.
* A Light/Dark switch in the header: until the reader chooses, the page follows their device; their choice is kept in the browser and applied on every page before it paints, so it never flashes. It is a toggle button named "Dark theme", pressed in dark mode. Without JavaScript the page follows the device and the switch is hidden.
* A header with the site logo (when the site has one) beside the site title, the site's menu (or its pages until there is one), a search button that opens into a field, and the switch. On narrow screens the menu folds behind a button and opens as a side sheet.
* A cobalt footer: the site title and tagline, a "Footer note" template part under them, a menu as columns (the header's, or one chosen for the footer; one column per item with a submenu, headed by its title, the other items in a last column), the copyright line, and the site title as a giant outlined wordmark (hidden from screen readers).
* Article lists (home page, archives, search results) as ruled rows without pictures: the date and first category on the left, the title and summary on the right, then "Previous page" and "Next page". The home page opens with the site title, a search field and the top-level categories as chips. Numbered pages are a choice in Appearance → Customize → Article lists, off by default.
* Article pages: a title band with the breadcrumbs, the first category, the title in large italic display type, the excerpt, the author, the "Published" and "Updated" dates and the reading time (220 words a minute); then the text in a 72-character column, with "On this page" after the opening paragraphs on articles with three sections or more (a closed box listing each section and its subsections, linked to readable anchors; Appearance → Customize → Table of contents turns it off or sets the number of sections; the class tala-no-contents keeps a heading out of it); then the categories and tags as chips, "Read next" (up to three recent articles from the same categories, then the older and newer articles), the author box with biography, and comments with their dates.
* A "Call to action" template part after the content of every post, page and list, empty until it is filled in the Site Editor; the "Closing call to action" pattern gives a ready band (heading, lead, one button) to fill it with. An empty part prints nothing.
* Accessible: one H1 per page, uniquely named landmarks, a skip link, visible keyboard focus, targets of at least 44 px, text contrast of at least 4.5:1 (body text far more) and form field edges of at least 3:1 in both colour schemes, and long words that wrap instead of widening the page when zoomed.
* Forms and embeds follow the switch: Gravity Forms (Orbital and the 2.5 theme), the comment form, the search fields and PDF Embedder's viewer take the theme's controls in light and dark.
* Prints just the article, with its dates and author, in black on white.
* Translation-ready: every text in the templates comes from translatable patterns (text domain tala-blog), and translated block settings are escaped as JSON, so no translation can break a block.

* Older posts look right too: images aligned by the classic editor, [caption] and [gallery], fixed-width video embeds, plain tables and quotes, password forms, untitled posts, and titles with very long words, checked with WordPress's Theme Unit Test on phones and desktops, left to right and right to left.
* A pinned (sticky) post is marked "Pinned" at the top of the home page, the article author's comments are marked "Author", and a post that has stopped taking comments says so under them.

Pairs with the Tala SEO plugin, which provides the breadcrumbs block and the structured data. Without the plugin, the breadcrumbs are simply left out, and the theme works on its own.

== Installation ==

1. Download the zip of the latest version from https://github.com/llegomark/wp-blog-kit/releases (under Assets).
2. In Appearance → Themes → Add New Theme → Upload Theme, choose the zip, install it and activate it.
3. Optional: the Tala SEO plugin, from the same page, adds breadcrumbs and structured data.
4. Optional: fill the "Call to action" and "Footer note" template parts in Appearance → Editor → Patterns.

== Frequently Asked Questions ==

= How many articles does a page show? =

As many as Settings → Reading → "Blog pages show at most" says (10 by default). Older articles follow on the next pages.

= How do I change the header menu and the footer columns? =

Make the menus in Appearance → Editor → Navigation, then pick which one the header and which one the footer show in Appearance → Customize → Header and footer menus ("Automatic" leaves it to WordPress, its newest menu, but never gives the header the footer's menu). In the header, a submenu's title is a button that opens it; its page can be one of its items. In the footer, each top-level item with a submenu becomes a column headed by its title (a label, not a link), and the items without a submenu share a last column. Until you make a menu, both list the site's pages (child pages left out), and WordPress saves that list as a menu called "Navigation" that you can edit. On narrow screens the header menu opens as a side sheet, the "Menu overlay" template part in Appearance → Editor → Patterns.

= How do I add a closing call to action or a note in the footer? =

In Appearance → Editor → Patterns, open the "Call to action" template part and insert the "Closing call to action" pattern (or any blocks), then write your own text and link; it shows after the content of every post, page and list. The "Footer note" part sits under the tagline in the footer, for a short line such as a disclaimer. Both are saved in the site's database, so theme updates leave them as they are; empty, they print nothing.

= How do I add a logo? =

In Appearance → Editor → Patterns → Header, select the logo placeholder beside the site title and choose an image. It appears 28 px wide in the header and in the menu sheet. A square or round image works best. The logo isn't a link and screen readers skip it, because the site title beside it already names the site and links to the home page.

= Can I change the colours or the font? =

The theme has one look on purpose: version 2 has no colour or serif presets. Appearance → Editor → Styles still changes colours and fonts for a site, as with any block theme. A preset picked in version 1 stays saved and overrides the new colours or Inter until the site's styles are reset in Appearance → Editor → Styles.

= Does it work with Gravity Forms? =

Yes. Forms take the theme's colours, font and controls, in light and dark mode and with the Light/Dark switch, with either of Gravity Forms' form themes (Orbital, or the Gravity Forms 2.5 theme). The rules load only on pages with a form, right after Gravity Forms' own stylesheet.

With Orbital, the theme's colours replace the colours set in a form block's style settings, so every form matches the page. Other form plugins that leave their fields to the browser follow the page's light or dark mode as well.

== Changelog ==

= 2.0.4 =
* Changed: the page's rail is 70rem wide (was 60rem), so the header, the lists, the footer and the call to action line up with the design system's other apps. The text column keeps its width.

= 2.0.3 =
* New: Appearance → Customize → Header and footer menus: pick which menu the header shows and which one the footer shows, so the footer can hold other links (such as legal pages). "Automatic" leaves it to WordPress, its newest menu, but the header never takes the footer's menu.
* Changed: in the header, a submenu's title is a button that opens its submenu on click or tap, with its arrow inside, instead of a link to a page. In the footer's columns and the phone menu, a submenu's title is a label over its items, not a link.
* Changed: the site title shows in capitals in the header and the phone menu's bar. The site's name itself stays as written, for search results, the browser tab and link previews.

= 2.0.2 =
* Fixed: an article or page whose text repeats its featured image in an Image block (often as its first block) no longer shows that image on its own page, as the templates already leave the featured image out. Its other images stay, and the featured image is still used in link previews and structured data.
* Fixed: an article without an excerpt of its own no longer has its text run a second time to make one for the title band, only to leave it out; what plugins add to the text (such as ads) now runs once.

= 2.0.1 =
* Fixed: the header menu keeps its row when the site title grows wider (a long name, or a link an ad network adds inside it); the title wraps instead.

= 2.0.0 =
* New look, "drafting paper": dark ink on warm paper (#393d45 on #f4f4f2; #d3d6dc on #16181d in dark mode), black headings, one cobalt accent (#1348dc, #7d9bff in dark mode), hairline rules and borders, square cards and 4 px controls with a deep-cobalt lip on buttons.
* New: Inter, self-hosted in the theme (Inter 4.1 under the SIL Open Font License, credited below): the variable Latin font and the display italic are preloaded; the Latin Extended and italic subsets load only for the characters they cover. Metric-matched fallbacks keep the swap from moving the page. Headings are light to regular weight; titles in large italic display type.
* New: a Light/Dark switch in the header. It follows the device until the reader chooses, keeps the choice in the browser and applies it before the page paints. A toggle button with one name, "Dark theme", pressed in dark mode; hidden without JavaScript.
* New: every section is a full-width band ruled underneath, on a centred 60rem rail with side rules and diamond nodes; graph paper behind the title bands, the call to action and the 404 panel only.
* New: the footer is a cobalt slab: the site title and tagline, the "Footer note" part, the site's menu as columns (one per item with a submenu, the rest in a last column), the copyright line and tagline, and the site title as a giant outlined wordmark. The footer search field is gone; search is in the header, a button that opens into a field.
* New: the header menu opens as a side sheet on narrow screens; the home page opens with the site title, a search field and the top-level categories as chips.
* Changed: article lists are ruled rows without thumbnails (date and first category, then title and summary), with "Previous page" and "Next page" buttons.
* New: articles open with a title band: breadcrumbs, the first category, the title, the excerpt, the author, the dates and the reading time. "In this article" becomes "On this page", a closed box at every width. "Keep reading" becomes "Read next"; topics are chips; the author box is a card.
* New: empty "Call to action" and "Footer note" template parts, filled per site in the Site Editor, and a "Closing call to action" pattern for the first. Empty, they print nothing.
* Changed: Gravity Forms, the comment form, PDF Embedder's viewer and print follow the new look and the Light/Dark switch; form field edges keep 3:1 contrast.
* Removed: the Sepia, Blue and Green colour presets and the Serif and Serif headings typography presets. One look on every site.

= 1.13.0 =
* Faster articles with a PDF: PDF Embedder's scripts (PDF.js, its viewer and the jQuery they need, about 140 KB, 490 KB unpacked) now load deferred instead of blocking the page from its head, so the article's text shows before them. Lighthouse estimated they held a phone's first paint back by up to 2.2 seconds. The viewer starts when the page is ready, as before; a script that needs jQuery at once (a Gravity Forms form) keeps it blocking.
* PDF Embedder's toolbar is readable: its light grey text now sits on the theme's dark grey (7:1, was 2.6:1).
* The site title's link is at least 24 px tall, a touch target of the size WCAG 2.5.8 asks for.

= 1.12.0 =
* New: Gravity Forms support, in light and dark mode. The theme now styles Gravity Forms forms with the page's colours, font and field style.
* Orbital forms: labels, fields, descriptions, buttons and focus rings use the theme's colour presets through Gravity Forms' CSS API, so they change with dark mode and the colour variations. Previously their dark labels disappeared on the dark page.
* Gravity Forms 2.5 theme forms: fields and buttons look like the theme's own.
* Both form themes: field text is 16 px, so phones don't zoom in when a field is tapped. In dark mode, the red "Required" marks and error messages are a lighter red with 7:1 contrast.
* Loaded only on pages with a form, right after Gravity Forms' own stylesheet.

= 1.11.0 =
* New: "In this article", a table of contents on articles with three sections or more. It sits after the opening paragraphs, before the first section, as a navigation landmark folded into one line ("In this article · 5 sections") that opens into the sections, each with its subsections. The sections are the article's highest heading level (H2, or H3 or H4 in older articles), the subsections the level below; headings in FAQ answers, quotations, figures, tables and forms are left out, and so are headings given the class tala-no-contents (on the heading or a group around it).
* Each entry links to its heading with the heading's own words. A heading keeps its anchor, so links to it keep working; one without gets a readable anchor from its words ("#plan-the-week", "#plan-the-week-2" for a second one), as does one whose anchor the article uses twice or that the classic editor left (mce_…). The box adds no heading of its own, and it isn't printed.
* Appearance → Customize → Table of contents: on or off (on by default), and the number of sections an article needs (2 to 10, 3 by default).
* Read with WordPress's HTML API; an article whose HTML it can't read keeps its text as it is, without the box.

= 1.10.0 =
* New: the "Published" and "Updated" dates of an article show the time too ("Published November 1, 2026 at 8:00 am"), in the site's date and time formats (Settings → General). A date format chosen in the block itself is kept as it is; dates in article lists stay without the time. "Updated" still shows only when the article changed on a later day.
* New: articles, pages, archives and author pages have the search form at the top, above the breadcrumbs, so a reader can search from wherever they are. It is the page's one search form: those pages use the footer without search, and the home page keeps the search form in its footer.

= 1.9.0 =
* Changed: articles and pages no longer show the featured image above their text. It still shows as the thumbnail in article lists and "Keep reading", and Tala SEO still uses it for link previews and the article's markup.
* Changed: article lists link to newer and older articles without numbered pages, so the number of pages isn't on show. Appearance → Customize → Article lists → "Show numbered pages" brings them back.

= 1.8.1 =
* Fixed: a palette entry saved in the site's styles that isn't a colour (which a multisite administrator's styles can hold, saved as written) no longer stops every page of the site with an error; the theme colour and the dark colours just leave it out.

= 1.8.0 =
* New: "Keep reading" right after the article: up to three recent articles from the same categories (title, date and thumbnail, like the article list), then the older and newer articles as two cards that are tap targets in full, with arrows. The author box follows it. The links were small text below the author box before, and were labelled "Previous" and "Next", which read backwards; they now say "Older article" and "Newer article", as the article lists do.
* Changed: a Group block that is a navigation landmark is left out when it holds no link, whatever text it has (it kept text before): "Keep reading" disappears on a site's only article, heading and all. Single templates customized in the Site Editor keep their old markup until their customizations are cleared.

= 1.7.0 =
* New: colour presets Sepia, Blue and Green, each with its own dark mode, and typography presets Serif and Serif headings (Appearance → Editor → Styles → Browse styles). Every preset keeps text contrast at 6:1 or more in light and dark mode and uses system fonts only.
* New: the article's title and dates are its header and its categories and tags its footer; the links to the previous and next articles are a navigation landmark named "Previous and next articles" (none when there is no other article); each comment is an article.
* New: the Site Editor's List View names the theme's groups ("Article", "Title and dates", "Comment", "Article list" and so on) instead of "Group".
* Changed: the dark-mode colours are in settings.custom.color.dark in theme.json; custom CSS that used the --wp--custom--dark--* variables needs the --wp--custom--color--dark--* names. Single and page templates customized in the Site Editor keep their old markup until their customizations are cleared.


= 1.6.2 =
* The "Published", "Updated", "Pinned" and "Author" labels are inserted as written, whatever their translation holds.
* A Page List in the menu is unwrapped only when its HTML is the list itself.
* Pattern files do nothing when requested directly.

= 1.6.1 =
* Readme: installing from the GitHub releases.

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

== Upgrade Notice ==

= 2.0.0 =
A new look. A 1.x colour or typography preset you picked stays saved and overrides the new colours or Inter: reset Appearance → Editor → Styles. The Call to action and Footer note parts are empty until filled. Templates or parts customized in the Site Editor keep their old markup until cleared.

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

Inter (assets/fonts/*.woff2), version 4.1
Copyright (c) 2016 The Inter Project Authors (https://github.com/rsms/inter)
License: SIL Open Font License, Version 1.1 (assets/fonts/OFL.txt, https://openfontlicense.org)
Source: https://github.com/rsms/inter, subset to Latin, Latin Extended and the display italic's Latin characters.

The theme bundles no other third-party fonts, images or scripts. Its few icons (menu, search, the switch's sun and moon, chevrons, arrows) are basic line shapes written into its markup and CSS.

Screenshot (screenshot.png): the theme showing the kit's own demo content, (C) 2026 Edventure, GPLv2 or later.
