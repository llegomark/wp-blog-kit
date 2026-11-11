/**
 * The HTML says what each part of an article page is: the article is one article element, its
 * title band (title, byline and dates) its header and its topics (categories and tags) its footer;
 * "Read next" (related articles, then the older and newer ones) is a navigation landmark of its own
 * name, after the topics and before the author box; each comment is an article of its own. "On this
 * page" (the table of contents) is a disclosure after the opening paragraphs, closed until opened,
 * with a navigation landmark of links that jump to their sections. The article's text has NASH's
 * typography, and the 404 page is a status panel. Landmark names and heading order are axe's
 * (a11y.spec.mjs).
 */
import { test, expect } from '@playwright/test';
import { axeViolations, demoPages, wp, PHONE } from './utils.mjs';

const pages = demoPages();

test( 'an article: its title band is its header, its topics its footer', async ( { page } ) => {
	await page.goto( pages.article );
	const article = page.locator( 'main > article' );
	await expect( article ).toHaveCount( 1 );
	const header = article.locator( ':scope > header.tala-band--paper' );
	await expect( header.locator( 'h1.tala-display' ) ).toHaveCount( 1 );
	await expect( header.locator( '.tala-eyebrow' ) ).toHaveText( 'Classroom guides' );
	await expect( header.getByText( 'By Maria Santos' ) ).toBeVisible();
	await expect( header.getByRole( 'link', { name: 'Maria Santos' } ) ).toHaveAttribute( 'rel', 'author' );
	await expect( header.locator( 'time' ).first() ).toBeVisible();
	await expect( header.locator( '.tala-reading-time' ) ).toHaveText( /^\d+ min read$/ );
	const footer = article.locator( 'footer.tala-terms' );
	await expect( footer ).toContainText( 'Topics' );
	// Chips: square (2px), 44px tall.
	const chip = footer.getByRole( 'link', { name: 'Classroom guides' } );
	expect( await chip.evaluate( ( a ) => [ getComputedStyle( a ).borderRadius, a.getBoundingClientRect().height ] ) ).toEqual( [ '2px', 44 ] );
} );

test( '"Read next" is a navigation landmark after the topics, before the author box', async ( { page } ) => {
	await page.goto( pages.article );
	const nav = page.getByRole( 'navigation', { name: 'Read next' } );
	await expect( nav.getByRole( 'heading', { level: 2, name: 'Read next' } ) ).toBeVisible();
	await expect( nav.locator( '.tala-related .tala-row h3.tala-subtitle a' ).first() ).toBeVisible();
	await expect( nav.locator( 'img' ), 'No pictures' ).toHaveCount( 0 );
	// One 1px rule above the rows and one below: nothing inside the list draws a second.
	const rules = await nav.locator( '.wp-block-query.tala-related' ).evaluate( ( list ) =>
		[ list, ...list.querySelectorAll( '*' ) ].map( ( el ) => getComputedStyle( el ) ).reduce( ( sum, s ) => [ sum[ 0 ] + parseFloat( s.borderTopWidth ), sum[ 1 ] + parseFloat( s.borderBottomWidth ) ], [ 0, 0 ] ),
	);
	const rows = await nav.locator( '.tala-related .wp-block-post' ).count();
	expect( rules, 'Top rules: the list\'s and one between each pair of rows; bottom rule: the list\'s' ).toEqual( [ rows, 1 ] );
	await expect( nav.getByRole( 'link', { name: /Older article/ } ) ).toBeVisible();
	await expect( page.locator( 'main .tala-measure > footer.tala-terms + nav.tala-post-nav + .wp-block-post-author' ) ).toHaveCount( 1 );
	// Named by its heading, once.
	expect( await nav.evaluate( ( el ) => [ el.getAttribute( 'aria-labelledby' ) === el.querySelector( 'h2' ).id, el.hasAttribute( 'aria-label' ) ] ) ).toEqual( [ true, false ] );

	// The newest article has only an older one: its cell takes the whole row.
	await page.goto( pages.withComments );
	const cells = page.locator( '.tala-post-nav__links' );
	const older = cells.locator( '.post-navigation-link-previous' );
	expect( await older.evaluate( ( el ) => Math.round( el.getBoundingClientRect().width ) ) ).toBe( await cells.evaluate( ( el ) => el.clientWidth ) );

	await page.goto( pages.page );
	await expect( page.getByRole( 'navigation', { name: 'Read next' } ) ).toHaveCount( 0 );
} );

test( 'each comment is an article', async ( { page } ) => {
	await page.goto( pages.withComments );
	const comments = page.locator( '.wp-block-comment-template li' );
	const count = await comments.count();
	expect( count ).toBeGreaterThan( 0 );
	await expect( page.locator( '.wp-block-comment-template li > article' ) ).toHaveCount( count );
} );

test( '"On this page": after the opening, closed until opened, its links jump to their sections', async ( { page } ) => {
	// The demo articles have two sections (and a subsection): two are enough here.
	wp( 'theme', 'mod', 'set', 'tala_blog_contents_sections', '2' );
	try {
		await page.goto( pages.article );
		const box = page.locator( 'details.tala-contents' );
		await expect( box ).toHaveCount( 1 );
		await expect( box ).not.toHaveAttribute( 'open' );
		await expect( box.locator( 'summary' ) ).toHaveText( 'On this page 2 sections' );
		expect( await box.evaluate( ( el ) => el.previousElementSibling?.tagName ), 'After the opening paragraph' ).toBe( 'P' );
		const contents = page.getByRole( 'navigation', { name: 'On this page' } );
		await expect( contents, 'Hidden while closed' ).toHaveCount( 0 );
		await box.locator( 'summary' ).click();
		await expect( contents ).toBeVisible();
		const first = contents.getByRole( 'link' ).first();
		await expect( contents.locator( 'ol ol a' ) ).toHaveCount( 1 );
		const target = ( await first.getAttribute( 'href' ) ).slice( 1 );
		const heading = page.locator( `h2[id="${ target }"]` );
		await expect( first, 'Named by the heading alone, without its number' ).toHaveAccessibleName( await heading.textContent() );
		await expect( first.locator( '.tala-contents-num' ) ).toHaveText( '01' );
		await first.click();
		await expect( page ).toHaveURL( new RegExp( `#${ target }$` ) );
		await expect( heading ).toBeInViewport();
		expect( await axeViolations( page ) ).toEqual( [] );
	} finally {
		wp( 'theme', 'mod', 'remove', 'tala_blog_contents_sections' );
	}
	await page.goto( pages.article );
	await expect( page.locator( 'details.tala-contents' ), 'Three sections by default' ).toHaveCount( 0 );
} );

test( 'the article\'s text: h2 under a rule, blockquote on a cobalt wash with a 2px cobalt rule, cobalt links', async ( { page } ) => {
	await page.goto( pages.article );
	const text = page.locator( '.wp-block-post-content' );
	const style = ( locator, props ) => locator.evaluate( ( el, names ) => names.map( ( name ) => getComputedStyle( el )[ name ] ).join( ' | ' ), props );
	const h2 = text.locator( 'h2' ).first();
	expect( await style( h2, [ 'borderTopWidth', 'borderTopStyle', 'borderTopColor', 'paddingTop', 'marginTop', 'fontWeight' ] ) ).toBe( '1px | solid | rgb(203, 207, 212) | 28px | 40px | 400' );
	const h3 = text.locator( 'h3' ).first();
	expect( await style( h3, [ 'fontSize', 'fontWeight' ] ) ).toBe( '19px | 450' );
	const quote = text.locator( 'blockquote' ).first();
	expect( await quote.evaluate( ( el ) => el.getBoundingClientRect().width === el.parentElement.getBoundingClientRect().width ), 'The width of the text' ).toBe( true );
	// The wash is the accent at 7%, in light and dark, and the quote's text keeps 4.5:1 on it.
	expect( await style( quote, [ 'borderLeftWidth', 'borderLeftStyle', 'borderLeftColor', 'backgroundColor', 'borderTopLeftRadius' ] ) ).toBe( '2px | solid | rgb(19, 72, 220) | color(srgb 0.0745098 0.282353 0.862745 / 0.07) | 0px' );
	for ( const colorScheme of [ 'light', 'dark' ] ) {
		await page.emulateMedia( { colorScheme } );
		const wash = await quote.evaluate( ( el ) => {
			// The wash painted on the page's paper, read back as a pixel; the quote's text colour likewise.
			const paint = ( ...colours ) => {
				const canvas = document.createElement( 'canvas' ).getContext( '2d' );
				for ( const colour of colours ) {
					canvas.fillStyle = colour;
					canvas.fillRect( 0, 0, 1, 1 );
				}
				return [ ...canvas.getImageData( 0, 0, 1, 1 ).data ].slice( 0, 3 );
			};
			const luminance = ( rgb ) => {
				const [ r, g, b ] = rgb.map( ( v ) => ( v / 255 <= 0.04045 ? v / 255 / 12.92 : ( ( ( v / 255 ) + 0.055 ) / 1.055 ) ** 2.4 ) );
				return ( 0.2126 * r ) + ( 0.7152 * g ) + ( 0.0722 * b );
			};
			const accent = getComputedStyle( document.body ).getPropertyValue( '--wp--preset--color--accent' ).trim();
			const background = paint( getComputedStyle( document.body ).backgroundColor, getComputedStyle( el ).backgroundColor );
			const expected = paint( getComputedStyle( document.body ).backgroundColor, `color-mix(in srgb, ${ accent } 7%, transparent)` );
			const [ hi, lo ] = [ luminance( background ), luminance( paint( getComputedStyle( el.querySelector( 'p' ) ?? el ).color ) ) ].sort( ( a, b ) => b - a );
			return { background, expected, contrast: ( hi + 0.05 ) / ( lo + 0.05 ) };
		} );
		expect( wash.background, `${ colorScheme }: the accent at 7%` ).toEqual( wash.expected );
		expect( wash.contrast, `${ colorScheme }: text on the wash` ).toBeGreaterThanOrEqual( 4.5 );
	}
	await page.emulateMedia( { colorScheme: 'light' } );
	expect( await style( text.locator( 'ul' ).first(), [ 'listStyleType' ] ) ).toBe( 'square' );
	expect( await style( text.locator( 'table' ).first(), [ 'fontVariantNumeric' ] ) ).toBe( 'tabular-nums' );
	expect( await style( text.locator( '.wp-block-table' ).first(), [ 'overflowX', 'borderTopWidth', 'borderTopStyle' ] ) ).toBe( 'auto | 1px | solid' );
} );

// The Theme Unit Test's "Title With Special Characters": its title, a run of symbols without a
// space, and its classic table of characters, ten to a row, which is wider than a phone's column.
test.describe( 'special characters (the Theme Unit Test\'s post)', () => {
	test.use( { viewport: { width: 390, height: 844 }, deviceScaleFactor: 3, isMobile: true, hasTouch: true } );
	let id;

	test.beforeAll( () => {
		const cover = wp( 'post', 'list', '--post_type=attachment', '--s=Cover illustration', '--field=ID', '--posts_per_page=1' );
		// As agents publish (Tala SEO's readme): a draft, its description and featured image, then publish.
		const cell = ( c ) => `<td>${ c.replace( '&', '&amp;' ).replace( '<', '&lt;' ).replace( '>', '&gt;' ) }</td>`;
		const rows = [ '!"#$%&\'()*', '+,-./01234', '56789:;>=<' ].map( ( row ) => `<tr>${ [ ...row ].map( cell ).join( '' ) }</tr>` );
		const title = 'Title With Special Characters ~`!@#$%^&*()-_=+{}[]/\\;:\'"?,.>';
		id = wp( 'post', 'create', `--post_title=${ title }`, '--meta_input={"_tala_seo_description":"Symbols."}', `--post_content=<table><tbody>${ rows.join( '' ) }</tbody></table>`, '--porcelain' );
		wp( 'post', 'meta', 'set', id, '_thumbnail_id', cover );
		wp( 'post', 'update', id, '--post_status=publish' );
	} );

	test.afterAll( () => {
		wp( 'post', 'delete', id, '--force' );
	} );

	test( 'at 390 px the title breaks and the classic table scrolls in its own box: the page doesn\'t scroll sideways', async ( { page } ) => {
		await page.goto( `/?p=${ id }` );
		await expect( page.locator( 'h1' ) ).toContainText( '~`!@#$%^&*()' );
		const wide = await page.evaluate( () => {
			const width = document.documentElement.clientWidth;
			return {
				scroll: document.documentElement.scrollWidth - width,
				// Everything stays in the window, but what the table's box clips as it scrolls.
				over: [ ...document.querySelectorAll( 'main *' ) ]
					.filter( ( el ) => ! el.parentElement.closest( '.tala-table-scroll' ) && el.getBoundingClientRect().right > width + 0.5 )
					.map( ( el ) => `${ el.tagName }.${ el.className }` ),
				tableScrolls: ( ( box ) => box.scrollWidth > box.clientWidth )( document.querySelector( '.wp-block-post-content .tala-table-scroll' ) ),
			};
		} );
		expect( wide ).toEqual( { scroll: 0, over: [], tableScrolls: true } );
	} );
} );

test.describe( 'the 404 page', () => {
	test.use( PHONE );

	for ( const colorScheme of [ 'light', 'dark' ] ) {
		test( `a graph-paper status panel: "404", the heading, a search field and the way home (${ colorScheme })`, async ( { page } ) => {
			await page.emulateMedia( { colorScheme } );
			await page.goto( pages.notFound );
			const panel = page.locator( 'main .tala-band--paper .tala-status' );
			const code = panel.locator( '.tala-status-code' );
			await expect( code ).toHaveText( '404' );
			await expect( code ).toHaveAttribute( 'aria-hidden', 'true' );
			expect( await code.evaluate( ( el ) => [ getComputedStyle( el ).fontStyle, getComputedStyle( el ).fontWeight ] ) ).toEqual( [ 'italic', '300' ] );
			await expect( panel.getByRole( 'heading', { level: 1, name: 'Page not found' } ) ).toBeVisible();
			await expect( panel.getByRole( 'searchbox' ) ).toBeVisible();
			await expect( panel.getByRole( 'link', { name: 'Back to the home page' } ) ).toHaveAttribute( 'href', /\/$/ );
			expect( await axeViolations( page ) ).toEqual( [] );
		} );
	}
} );

// A site fills the Call to action part from the theme's "Closing call to action" pattern: it closes
// the page between main and the footer, a region landmark of its own name, so axe finds nothing.
test.describe( 'the call to action, filled from the pattern', () => {
	test.use( PHONE );
	let part;

	test.beforeAll( () => {
		const content = wp( 'eval', 'echo WP_Block_Patterns_Registry::get_instance()->get_registered( "tala-blog/call-to-action" )["content"];' );
		part = wp( 'post', 'create', '--post_type=wp_template_part', '--post_status=publish', '--post_name=call-to-action', '--post_title=Call to action', `--post_content=${ content }`, '--porcelain' );
		wp( 'post', 'term', 'set', part, 'wp_theme', 'tala-blog' );
	} );

	test.afterAll( () => {
		wp( 'post', 'delete', part, '--force' );
	} );

	for ( const colorScheme of [ 'light', 'dark' ] ) {
		test( `a named region between main and the footer, and axe finds nothing (${ colorScheme })`, async ( { page } ) => {
			await page.emulateMedia( { colorScheme } );
			for ( const name of [ 'article', 'home' ] ) {
				await page.goto( pages[ name ] );
				const region = page.getByRole( 'region', { name: 'Call to action' } );
				await expect( region, name ).toHaveCount( 1 );
				await expect( region.locator( '.tala-cta' ), name ).toHaveCount( 1 );
				await expect( region.getByRole( 'heading', { level: 2, name: 'Ready for the next step?' } ), name ).toBeVisible();
				expect(
					await page.evaluate( () => {
						const after = document.querySelector( '.wp-site-blocks > main' ).nextElementSibling;
						return [ !! after.querySelector( 'section.tala-band' ), after.nextElementSibling?.tagName ];
					} ),
					name,
				).toEqual( [ true, 'FOOTER' ] );
				expect( await axeViolations( page ), name ).toEqual( [] );
			}
		} );
	}
} );

for ( const colorScheme of [ 'light', 'dark' ] ) {
	test.describe( `article, phone, ${ colorScheme }`, () => {
		test.use( { ...PHONE, colorScheme } );

		test( 'axe finds nothing in the article, its open contents and its footer', async ( { page } ) => {
			wp( 'theme', 'mod', 'set', 'tala_blog_contents_sections', '2' );
			try {
				await page.goto( pages.article );
				await page.locator( 'details.tala-contents summary' ).click();
				expect( await axeViolations( page ) ).toEqual( [] );
			} finally {
				wp( 'theme', 'mod', 'remove', 'tala_blog_contents_sections' );
			}
		} );
	} );
}

// A site fills the Before Read next part from the theme's "Call to action before Read next" pattern: a card at
// the end of the post's text, after its topics and before Read next, a complementary landmark of its own name
// (not the closing call to action's), so axe finds nothing.
test.describe( 'the call to action before Read next, filled from the pattern', () => {
	test.use( PHONE );
	let part;

	test.beforeAll( () => {
		const content = wp( 'eval', 'echo WP_Block_Patterns_Registry::get_instance()->get_registered( "tala-blog/before-read-next" )["content"];' );
		part = wp( 'post', 'create', '--post_type=wp_template_part', '--post_status=publish', '--post_name=before-read-next', '--post_title=Before Read next', `--post_content=${ content }`, '--porcelain' );
		wp( 'post', 'term', 'set', part, 'wp_theme', 'tala-blog' );
	} );

	test.afterAll( () => {
		wp( 'post', 'delete', part, '--force' );
	} );

	for ( const colorScheme of [ 'light', 'dark' ] ) {
		test( `in the article, after the topics and before Read next, and axe finds nothing (${ colorScheme })`, async ( { page } ) => {
			await page.emulateMedia( { colorScheme } );
			await page.goto( pages.article );
			const card = page.getByRole( 'complementary', { name: 'Recommended' } );
			await expect( card ).toHaveCount( 1 );
			await expect( card.getByRole( 'heading', { level: 2, name: 'Worth a look next' } ) ).toBeVisible();
			await expect( card.getByRole( 'link', { name: 'Take a look' } ) ).toBeVisible();
			expect(
				await page.evaluate( () => {
					const aside = document.querySelector( 'aside.tala-cta-card' );
					const next = document.querySelector( 'nav.tala-post-nav' );
					return [
						!! aside.closest( 'article' ),
						aside.compareDocumentPosition( next ) === Node.DOCUMENT_POSITION_FOLLOWING,
						document.documentElement.scrollWidth <= window.innerWidth,
					];
				} ),
			).toEqual( [ true, true, true ] );
			expect( await axeViolations( page ) ).toEqual( [] );
		} );
	}

	test( 'pages have no Read next and no card', async ( { page } ) => {
		await page.goto( pages.page );
		await expect( page.locator( 'aside.tala-cta-card' ) ).toHaveCount( 0 );
	} );
} );
