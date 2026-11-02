/**
 * Layout: nothing moves while a page loads, pages don't jump sideways between short and long ones,
 * and short pages keep their footer at the bottom of the window.
 */
import { test, expect } from '@playwright/test';
import { demoPages, PHONE, DESKTOP, wp, axeViolations } from './utils.mjs';

const pages = demoPages();

// Records every layout shift from the first paint on.
const recordShifts = () => {
	window.talaShifts = [];
	new PerformanceObserver( ( list ) => window.talaShifts.push( ...list.getEntries().map( ( e ) => e.value ) ) ).observe( {
		type: 'layout-shift',
		buffered: true,
	} );
};

for ( const [ name, device ] of Object.entries( { phone: PHONE, desktop: DESKTOP } ) ) {
	test.describe( `${ name }`, () => {
		test.use( device );

		test( 'no layout shift on any page, also while scrolling to the end', async ( { page } ) => {
			await page.addInitScript( recordShifts );
			for ( const path of Object.values( pages ) ) {
				await page.goto( path );
				await page.evaluate( async () => {
					for ( let y = 0; y <= document.documentElement.scrollHeight; y += window.innerHeight / 2 ) {
						window.scrollTo( 0, y );
						await new Promise( ( resolve ) => setTimeout( resolve, 50 ) );
					}
				} );
				// Lazy images scrolled into view have loaded.
				await page.waitForFunction( () => [ ...document.images ].every( ( image ) => image.complete ) );
				expect( await page.evaluate( () => window.talaShifts ), path ).toEqual( [] );
			}
		} );

		test( 'short pages keep the footer at the bottom of the window', async ( { page } ) => {
			const boxes = [];
			for ( const path of [ pages.searchNone, pages.notFound, pages.page ] ) {
				await page.goto( path );
				boxes.push(
					await page.evaluate( ( where ) => ( {
						where,
						short: document.querySelector( '.wp-site-blocks' ).scrollHeight <= window.innerHeight,
						footerBottom: Math.round( document.querySelector( '.wp-site-blocks > footer' ).getBoundingClientRect().bottom ),
						pageHeight: document.documentElement.scrollHeight,
						window: window.innerHeight,
					} ), path ),
				);
			}
			const short = boxes.filter( ( box ) => box.short );
			expect( short.length, 'Short pages checked' ).toBeGreaterThan( 0 );
			// The footer ends at the bottom of the window, and the page doesn't scroll.
			expect( short.map( ( box ) => [ box.where, box.footerBottom, box.pageHeight ] ) ).toEqual(
				short.map( ( box ) => [ box.where, box.window, box.window ] ),
			);
		} );
	} );
}

test.describe( 'desktop', () => {
	test.use( DESKTOP );

	test( 'header and content sit in the same place on short and long pages', async ( { page } ) => {
		const positions = new Set();
		for ( const path of Object.values( pages ) ) {
			await page.goto( path );
			positions.add(
				await page.evaluate( () =>
					[ '.tala-header .wp-block-site-title', '.tala-header nav', 'main > *' ]
						.map( ( selector ) => document.querySelector( selector ).getBoundingClientRect().left )
						.join( ' ' ),
				),
			);
		}
		expect( [ ...positions ], 'One position for every page' ).toHaveLength( 1 );
	} );
} );

/**
 * The frame: every section is a full-width band ruled underneath; inside it a centred 60rem rail
 * with hairline side rules and a 7 px diamond where they meet the band's top rule. Bands stack with
 * no gap, so the rails run unbroken from the header to the footer. Graph paper only in paper bands.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @return {Promise<Object>} What the frame measures.
 */
const frame = ( page ) =>
	page.evaluate( () => {
		const px = ( value ) => Math.round( parseFloat( value ) * 10 ) / 10;
		const bands = [ ...document.querySelectorAll( '.tala-band' ) ].filter( ( band ) => ! band.closest( '.tala-overlay' ) );
		// The page's width: the window less the scrollbar's gutter (on short pages too, where Chrome's
		// clientWidth of <html> leaves out the empty gutter).
		const width = document.querySelector( '.wp-site-blocks' ).getBoundingClientRect().width;
		return {
			width,
			bands: bands.map( ( band ) => {
				const rail = band.querySelector( ':scope > .tala-rail' );
				const r = rail.getBoundingClientRect();
				const style = getComputedStyle( rail );
				const node = getComputedStyle( rail, '::before' );
				return {
					band: band.className.replace( /wp-block-group|wp-container-\S+|is-layout-\S+|wp-block-group-is-layout-\S+/g, '' ).trim(),
					fullWidth: Math.round( band.getBoundingClientRect().width ) === width,
					bottomRule: `${ getComputedStyle( band ).borderBottomWidth } ${ getComputedStyle( band ).borderBottomStyle }`,
					top: px( band.getBoundingClientRect().top + scrollY ),
					bottom: px( band.getBoundingClientRect().bottom + scrollY ),
					rail: { left: px( r.left ), width: px( r.width ) },
					sideRules: `${ style.borderLeftWidth } ${ style.borderLeftStyle } ${ style.borderRightWidth } ${ style.borderRightStyle }`,
					paper: style.backgroundImage.includes( 'linear-gradient' ),
					node: `${ node.width } ${ node.height } ${ node.rotate } ${ node.borderTopWidth }`,
				};
			} ),
			header: {
				height: document.querySelector( '.wp-site-blocks > header' ).getBoundingClientRect().height,
				position: [ '.wp-site-blocks > header', '.tala-header' ].map( ( s ) => getComputedStyle( document.querySelector( s ) ).position ),
			},
		};
	} );

test.describe( 'the frame, desktop', () => {
	test.use( DESKTOP );

	test( 'bands with a centred 60rem rail, side rules and diamond nodes, stacked without gaps', async ( { page } ) => {
		for ( const path of Object.values( pages ) ) {
			await page.goto( path );
			const { width, bands, header } = await frame( page );
			expect( bands.length, path ).toBeGreaterThanOrEqual( 2 );
			expect( header.height, `${ path }: a header at least 4rem tall` ).toBeGreaterThanOrEqual( 64 );
			// Not sticky or fixed: it scrolls away with the page (the band is only a positioning box for its rules).
			expect( header.position, `${ path }: not sticky` ).toEqual( [ 'static', 'relative' ] );
			for ( const band of bands ) {
				expect( band, path ).toMatchObject( {
					fullWidth: true,
					bottomRule: '1px solid',
					rail: { left: ( width - 960 ) / 2, width: 960 },
					sideRules: '1px solid 1px solid',
					node: '7px 7px 45deg 1px',
				} );
			}
			// Header band, then main's bands, one after another.
			for ( let i = 1; i < bands.length; i++ ) {
				expect( bands[ i ].top, `${ path }: ${ bands[ i ].band } follows ${ bands[ i - 1 ].band }` ).toBe( bands[ i - 1 ].bottom );
			}
		}
	} );

	test( 'graph paper only in paper bands', async ( { page } ) => {
		const seen = [];
		for ( const path of [ pages.home, pages.category, pages.notFound, pages.article ] ) {
			await page.goto( path );
			for ( const band of ( await frame( page ) ).bands ) {
				expect( band.paper, `${ path }: ${ band.band }` ).toBe( band.band.split( ' ' ).includes( 'tala-band--paper' ) );
				seen.push( band.paper );
			}
		}
		expect( seen, 'Some bands are paper' ).toContain( true );
		expect( seen, 'Most are not' ).toContain( false );
	} );

	test( 'type roles: display in Inter Display light italic, cobalt; title upright and regular', async ( { page } ) => {
		await page.goto( pages.home );
		const roles = await page.evaluate( () => {
			const main = document.querySelector( 'main' );
			const out = {};
			for ( const [ role, tag ] of [ [ 'display', 'h1' ], [ 'title', 'h2' ], [ 'subtitle', 'h3' ], [ 'lead', 'p' ], [ 'eyebrow', 'p' ], [ 'meta', 'p' ] ] ) {
				const el = document.createElement( tag );
				el.className = `tala-${ role }`;
				el.textContent = 'Role';
				main.append( el );
				const s = getComputedStyle( el );
				out[ role ] = [ s.fontFamily.split( ',' )[ 0 ], s.fontSize, s.fontWeight, s.fontStyle, s.letterSpacing, s.color, s.textTransform, s.fontVariantNumeric ].join( ' | ' );
			}
			const h1 = document.createElement( 'h1' );
			main.append( h1 );
			const s = getComputedStyle( h1 );
			out.h1 = [ s.fontSize, s.fontWeight, s.fontStyle, s.textWrap || s.textWrapStyle ].join( ' | ' );
			out.synthesis = getComputedStyle( document.documentElement ).fontSynthesis;
			out.time = getComputedStyle( document.querySelector( 'time' ) ).fontVariantNumeric;
			return out;
		} );
		// At 1280 px: display clamp(2.25rem, 1.4rem + 3.6vw, 3.75rem) = 60px, title clamp(1.5rem, 1.3rem + 0.9vw, 2rem) = 32px.
		expect( roles.display ).toBe( '"Inter Display" | 60px | 300 | italic | -2.1px | rgb(19, 72, 220) | none | normal' );
		expect( roles.title ).toBe( 'Inter | 32px | 400 | normal | -0.8px | rgb(0, 0, 0) | none | normal' );
		expect( roles.subtitle ).toBe( 'Inter | 18px | 450 | normal | -0.216px | rgb(0, 0, 0) | none | normal' );
		expect( roles.lead ).toMatch( /^Inter \| 19px \| 400 \| normal \| normal \| rgb\(57, 61, 69\)/ );
		expect( roles.eyebrow ).toBe( 'Inter | 13px | 500 | normal | 1.04px | rgb(88, 95, 108) | uppercase | normal' );
		expect( roles.meta ).toBe( 'Inter | 13px | 400 | normal | normal | rgb(88, 95, 108) | none | tabular-nums' );
		expect( roles.h1, 'A plain h1 is a title' ).toBe( '32px | 400 | normal | balance' );
		expect( roles.synthesis ).toBe( 'none' );
		expect( roles.time ).toBe( 'tabular-nums' );
	} );
} );

test.describe( 'the frame, wide desktop', () => {
	test.use( { viewport: { width: 1440, height: 900 } } );

	test( 'a fainter outer pair of rules from 86rem', async ( { page } ) => {
		await page.goto( pages.article );
		const outer = await page.evaluate( () => {
			const band = document.querySelector( 'main .tala-band' );
			const s = getComputedStyle( band, '::before' );
			const r = band.getBoundingClientRect();
			return { content: s.content, left: parseFloat( s.left ), right: parseFloat( s.right ), width: s.borderLeftWidth, band: r.width };
		} );
		// 50% - 30rem - 7rem from each side of the band.
		expect( outer ).toMatchObject( { content: '""', width: '1px' } );
		expect( outer.left ).toBeCloseTo( ( outer.band / 2 ) - 592, 0 );
		expect( outer.right ).toBeCloseTo( ( outer.band / 2 ) - 592, 0 );
	} );
} );

test.describe( 'the frame, phone', () => {
	test.use( PHONE );

	test( 'the rail leaves 0.75rem each side', async ( { page } ) => {
		await page.goto( pages.article );
		const { width, bands } = await frame( page );
		for ( const band of bands ) {
			expect( band.rail ).toEqual( { left: 12, width: width - 24 } );
		}
	} );
} );

test.describe( 'the text column, desktop', () => {
	test.use( DESKTOP );
	let post;

	test.beforeAll( () => {
		const content =
			'<!-- wp:paragraph --><p class="tala-test-before">Before the group.</p><!-- /wp:paragraph -->' +
			'<!-- wp:group {"className":"tala-test-group","layout":{"type":"constrained"}} --><div class="wp-block-group tala-test-group"><!-- wp:paragraph --><p>In a group, as the editor makes new ones.</p><!-- /wp:paragraph --></div><!-- /wp:group -->';
		// A page: the publish check asks posts for a featured image too. Its text sits in the same column.
		const meta = '--meta_input={"_tala_seo_description":"A Group in the text."}';
		post = wp( 'post', 'create', '--post_type=page', '--post_status=publish', '--post_title=Group test', meta, `--post_content=${ content }`, '--porcelain' );
	} );

	test.afterAll( () => {
		wp( 'post', 'delete', post, '--force' );
	} );

	test( 'NASH\'s measure-72, and a Group in the text lines up with it, as in the editor', async ( { page } ) => {
		await page.goto( new URL( wp( 'post', 'url', post ) ).pathname );
		const column = await page.evaluate( () => {
			const left = ( s ) => document.querySelector( s ).getBoundingClientRect().left;
			const group = document.querySelector( '.tala-test-group' );
			return {
				measure: document.querySelector( '.tala-measure' ).getBoundingClientRect().width,
				padding: getComputedStyle( group ).paddingLeft,
				text: left( '.tala-test-group p' ) - left( '.tala-test-before' ),
			};
		} );
		// 72 Inter "ch" (0.628em) of 17 px text, as NASH's measure-72.
		expect( column.measure ).toBeCloseTo( 72 * 0.628 * 17, 0 );
		expect( column.padding, 'No page padding added again' ).toBe( '0px' );
		// Its text starts where the column's does (within the 0.7 px between measure-72, 768.7 px, and
		// the 48rem content size the editor uses, which centres the Group's text in it).
		expect( Math.abs( column.text ) ).toBeLessThan( 1 );
	} );
} );

/**
 * The footer slab: the site's menu as columns of links on the cobalt slab, and the site title as a
 * giant outlined wordmark that spans the rail whatever its length, never widening the page.
 */
test.describe( 'the footer', () => {
	const title = wp( 'option', 'get', 'blogname' );
	let menu;

	test.beforeAll( () => {
		const content =
			'<!-- wp:navigation-submenu {"label":"Guides","url":"/category/classroom-guides/","kind":"custom"} -->' +
			'<!-- wp:navigation-link {"label":"Classroom guides","url":"/category/classroom-guides/","kind":"custom"} /-->' +
			'<!-- wp:navigation-submenu {"label":"Teaching","url":"/tag/teaching/","kind":"custom"} -->' +
			'<!-- wp:navigation-link {"label":"Reading","url":"/?s=reading","kind":"custom"} /-->' +
			'<!-- /wp:navigation-submenu -->' +
			'<!-- /wp:navigation-submenu -->' +
			'<!-- wp:navigation-submenu {"label":"About","url":"/about/","kind":"custom"} -->' +
			'<!-- wp:navigation-link {"label":"Contact","url":"/about/#contact","kind":"custom"} /-->' +
			'<!-- /wp:navigation-submenu -->' +
			'<!-- wp:navigation-link {"label":"Hello","url":"/hello-world/","kind":"custom"} /-->';
		menu = wp( 'post', 'create', '--post_type=wp_navigation', '--post_status=publish', '--post_title=Footer test menu', `--post_content=${ content }`, '--porcelain' );
	} );

	test.afterAll( () => {
		wp( 'post', 'delete', menu, '--force' );
		wp( 'option', 'update', 'blogname', title );
	} );

	/**
	 * Where the wordmark's letters are, against its band, the rail and the window.
	 *
	 * @param {import('@playwright/test').Page} page Page.
	 * @return {Promise<Object>} Boxes.
	 */
	const wordmark = ( page ) =>
		page.evaluate( () => {
			const mark = document.querySelector( 'footer .tala-wordmark' );
			const letters = document.createRange();
			letters.selectNodeContents( mark );
			const box = ( r ) => ( { left: r.left, right: r.right, width: r.width } );
			return {
				scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth,
				window: document.documentElement.clientWidth,
				mark: box( mark.getBoundingClientRect() ),
				letters: box( letters.getBoundingClientRect() ),
				band: box( mark.parentElement.getBoundingClientRect() ),
				rail: document.querySelector( 'footer .tala-rail' ).clientWidth,
				hidden: mark.getAttribute( 'aria-hidden' ),
				transform: getComputedStyle( mark ).textTransform,
			};
		} );

	test( 'wordmark fits any title at 320 px', async ( { page } ) => {
		await page.setViewportSize( { width: 320, height: 800 } );
		wp( 'option', 'update', 'blogname', 'Department of Education Teachers Network' );
		await page.goto( pages.article );
		const narrow = await wordmark( page );
		expect( narrow.scroll, 'No sideways scrolling' ).toBe( 0 );
		expect( narrow.mark.left ).toBeGreaterThanOrEqual( 0 );
		expect( narrow.mark.right ).toBeLessThanOrEqual( narrow.window );
		// The whole title shows: its letters sit inside the band.
		expect( narrow.letters.left ).toBeGreaterThanOrEqual( narrow.band.left );
		expect( narrow.letters.right ).toBeLessThanOrEqual( narrow.band.right );
		expect( narrow.hidden ).toBe( 'true' );
		expect( narrow.transform ).toBe( 'uppercase' );

		await page.setViewportSize( DESKTOP.viewport );
		wp( 'option', 'update', 'blogname', 'DepEd Tambayan' );
		await page.goto( pages.article );
		const wide = await wordmark( page );
		expect( wide.scroll ).toBe( 0 );
		expect( wide.letters.width, 'The title spans the rail' ).toBeGreaterThanOrEqual( 0.9 * wide.rail );
		expect( wide.letters.left ).toBeGreaterThanOrEqual( wide.band.left );
		expect( wide.letters.right ).toBeLessThanOrEqual( wide.band.right );
	} );

	test.describe( 'phone', () => {
		test.use( { viewport: { width: 390, height: 844 }, deviceScaleFactor: 3, isMobile: true, hasTouch: true } );

		test( 'menu columns two up under the brand cell; links on the slab pass contrast', async ( { page } ) => {
			for ( const colorScheme of [ 'light', 'dark' ] ) {
				await page.emulateMedia( { colorScheme } );
				await page.goto( pages.article );
				const cells = await page.evaluate( () => {
					const box = ( el ) => {
						const r = el.getBoundingClientRect();
						return { left: Math.round( r.left ), top: Math.round( r.top ), width: Math.round( r.width ) };
					};
					const columns = [ ...document.querySelectorAll( 'footer .tala-footer-menu .wp-block-navigation__container > li' ) ];
					return {
						rail: document.querySelector( 'footer .tala-rail' ).clientWidth,
						brand: box( document.querySelector( 'footer .tala-footer-brand' ) ),
						columns: columns.map( ( li ) => ( { ...box( li ), heading: li.querySelector( ':scope > a' )?.textContent ?? '' } ) ),
						reading: !! document.querySelector( 'footer .wp-block-navigation__submenu-container .wp-block-navigation__submenu-container a[href="/?s=reading"]' ),
						slab: getComputedStyle( document.querySelector( 'footer .tala-slab' ) ).backgroundColor,
						underline: getComputedStyle( document.querySelector( 'footer .tala-footer-menu li li a' ) ).textDecorationLine,
					};
				} );
				expect( cells.slab, colorScheme ).toBe( 'light' === colorScheme ? 'rgb(19, 72, 220)' : 'rgb(22, 64, 184)' );
				expect( cells.brand.width, 'The brand cell spans the rail' ).toBe( cells.rail );
				expect( cells.columns.map( ( c ) => c.heading ) ).toEqual( [ 'Guides', 'About', '' ] );
				const [ first, second, last ] = cells.columns;
				expect( second.top, 'Two up' ).toBe( first.top );
				expect( second.left ).toBeGreaterThan( first.left );
				expect( Math.abs( second.width - first.width ) ).toBeLessThanOrEqual( 1 );
				expect( last.top, 'The odd column on a row of its own' ).toBeGreaterThan( first.top );
				expect( last.width ).toBe( cells.rail );
				expect( cells.reading, 'The third level is listed' ).toBe( true );
				expect( cells.underline ).toBe( 'underline' );
				expect( await axeViolations( page ), colorScheme ).toEqual( [] );
				// The focus ring shows on the slab: white, not cobalt on cobalt.
				const link = page.locator( 'footer .tala-footer-menu a' ).first();
				await link.focus();
				expect( await link.evaluate( ( a ) => [ a.matches( ':focus-visible' ), getComputedStyle( a ).outlineColor, getComputedStyle( a ).outlineWidth ] ) ).toEqual( [ true, 'rgb(255, 255, 255)', '2px' ] );
			}
		} );
	} );
} );
