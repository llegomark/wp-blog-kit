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
 * Reflow with the text at 200% (the browser's text size, as the root font size): on a 360 and a 320
 * px phone no page scrolls sideways, and nothing sticks out of the window: the in-page search form
 * (home, search results, the 404 page) and the comment form's button wrap.
 */
test.describe( 'text at 200%', () => {
	for ( const width of [ 360, 320 ] ) {
		test( `${ width } px: nothing wider than the window`, async ( { page } ) => {
			await page.setViewportSize( { width, height: 800 } );
			for ( const path of [ pages.home, pages.search, pages.notFound, pages.withComments ] ) {
				await page.goto( path );
				// At the usual text size the search field and its button share a row.
				const row = await page.evaluate( () => {
					const [ field, button ] = [ 'main .wp-block-search__input', 'main .wp-block-search__button' ].map( ( s ) => document.querySelector( s )?.getBoundingClientRect() );
					return ! field || Math.abs( field.top - button.top ) < 1;
				} );
				expect( row, `${ path }: field and button on one row at 100%` ).toBe( true );
				await page.addStyleTag( { content: 'html { font-size: 200% !important; }' } );
				const overflow = await page.evaluate( () => {
					const edge = document.documentElement.clientWidth;
					// Boxes past the window's edge, outside boxes that scroll or clip on their own (a wide table).
					const clipped = ( el ) => {
						for ( let up = el.parentElement; up && up !== document.body; up = up.parentElement ) {
							if ( 'visible' !== getComputedStyle( up ).overflowX ) {
								return true;
							}
						}
						return false;
					};
					const out = [ ...document.querySelectorAll( 'main *, footer form *' ) ]
						.filter( ( el ) => el.getClientRects().length && el.getBoundingClientRect().right > edge + 0.5 && ! clipped( el ) )
						.map( ( el ) => `${ el.tagName.toLowerCase() }.${ [ ...el.classList ].join( '.' ) } +${ Math.round( el.getBoundingClientRect().right - edge ) }` );
					// The comment form's button: each word of its label fits inside its padding.
					const submit = document.querySelector( 'main #commentform input[type="submit"]' );
					const tight = [];
					if ( submit ) {
						const style = getComputedStyle( submit );
						const text = document.createElement( 'canvas' ).getContext( '2d' );
						text.font = `${ style.fontWeight } ${ style.fontSize } ${ style.fontFamily }`;
						const room = submit.clientWidth - parseFloat( style.paddingLeft ) - parseFloat( style.paddingRight );
						tight.push( ...submit.value.split( /\s+/ ).filter( ( word ) => text.measureText( word ).width > room ) );
					}
					return { scroll: document.documentElement.scrollWidth - edge, out, tight, form: !! document.querySelector( 'main .wp-block-search__input, main #commentform' ) };
				} );
				expect( overflow.form, `${ path }: has its form` ).toBe( true );
				expect( overflow.out, `${ path }: past the window` ).toEqual( [] );
				expect( overflow.scroll, `${ path }: sideways scrolling` ).toBe( 0 );
				expect( overflow.tight, `${ path }: words of the comment button broken` ).toEqual( [] );
			}
		} );
	}
} );

/**
 * The author box: the photo beside the name and biography on a desktop, above them on a phone,
 * laid out by wrapping, without making the text column a size container (which would wrap every
 * ad slot in it too).
 */
test.describe( 'the author box', () => {
	for ( const [ width, beside ] of [ [ DESKTOP.viewport.width, true ], [ 320, false ] ] ) {
		test( `${ width } px: the photo ${ beside ? 'beside' : 'above' } the name; the text column is no size container`, async ( { page } ) => {
			await page.setViewportSize( { width, height: 900 } );
			await page.goto( pages.article );
			const box = await page.evaluate( () => {
				const rect = ( s ) => document.querySelector( `main .wp-block-post-author ${ s }` ).getBoundingClientRect();
				const card = document.querySelector( 'main .wp-block-post-author' ).getBoundingClientRect();
				const [ photo, content ] = [ rect( '.wp-block-post-author__avatar' ), rect( '.wp-block-post-author__content' ) ];
				return {
					containers: [ ...document.querySelectorAll( 'main *' ) ].filter( ( el ) => 'normal' !== getComputedStyle( el ).containerType && el.contains( document.querySelector( 'main .wp-block-post-content' ) ) ).map( ( el ) => el.className ),
					besideTop: Math.abs( content.top - photo.top ) < 1 && content.left >= photo.right,
					below: content.top >= photo.bottom,
					inside: content.right <= card.right && photo.right <= card.right,
				};
			} );
			expect( box.containers, 'No size container around the article\'s text' ).toEqual( [] );
			expect( box.inside ).toBe( true );
			expect( beside ? box.besideTop : box.below ).toBe( true );
		} );
	}
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

	test( 'a short title stops at a third of the band, so no letter is cut off', async ( { page } ) => {
		await page.setViewportSize( DESKTOP.viewport );
		wp( 'option', 'update', 'blogname', 'Me' );
		await page.goto( pages.article );
		const short = await wordmark( page );
		expect( short.letters.left ).toBeGreaterThanOrEqual( short.band.left );
		expect( short.letters.right ).toBeLessThanOrEqual( short.band.right );
		wp( 'option', 'update', 'blogname', title );
	} );

	/**
	 * The footer's cells: the brand cell, each column of the menu (its heading, box, and whether
	 * it shows), the third-level link and its parent, the rail.
	 *
	 * @param {import('@playwright/test').Page} page Page.
	 * @return {Promise<Object>} Boxes.
	 */
	const cells = ( page ) =>
		page.evaluate( () => {
			const box = ( el ) => {
				const r = el.getBoundingClientRect();
				return { left: r.left, right: r.right, top: r.top, bottom: r.bottom, width: r.width };
			};
			const columns = [ ...document.querySelectorAll( 'footer .tala-footer-menu .wp-block-navigation__container > li' ) ];
			const reading = document.querySelector( 'footer a[href="/?s=reading"]' );
			const teaching = document.querySelector( 'footer a[href="/tag/teaching/"]' );
			return {
				rail: document.querySelector( 'footer .tala-rail' ).clientWidth,
				brand: box( document.querySelector( 'footer .tala-footer-brand' ) ),
				columns: columns.map( ( li ) => ( { ...box( li ), heading: li.querySelector( ':scope > a' )?.textContent ?? '' } ) ),
				third: reading && {
					visible: reading.checkVisibility( { visibilityProperty: true, opacityProperty: true } ),
					link: box( reading ),
					parent: box( teaching ),
					column: box( reading.closest( '.wp-block-navigation__container > li' ) ),
				},
				slab: getComputedStyle( document.querySelector( 'footer .tala-slab' ) ).backgroundColor,
				underline: getComputedStyle( document.querySelector( 'footer .tala-footer-menu li li a' ) ).textDecorationLine,
			};
		} );

	/**
	 * Every column on one row, each 1fr, and the brand cell 2fr beside them (one or two columns).
	 *
	 * @param {Object} footer What cells() measured.
	 * @param {number} count  Columns expected.
	 */
	const beside = ( footer, count ) => {
		expect( footer.columns, 'Columns' ).toHaveLength( count );
		for ( const column of footer.columns ) {
			expect( column.top, `${ column.heading || 'last column' }: on the brand cell's row` ).toBeCloseTo( footer.brand.top, 0 );
			expect( column.width ).toBeCloseTo( footer.columns[ 0 ].width, 0 );
		}
		expect( footer.brand.width / footer.columns[ 0 ].width, 'The brand cell is 2fr' ).toBeCloseTo( 2, 1 );
		// The cells fill the rail: brand, columns and their 1 px hairlines.
		expect( footer.brand.width + ( count * footer.columns[ 0 ].width ) + count ).toBeCloseTo( footer.rail, 0 );
	};

	/**
	 * Three columns or more: the brand cell on a row of its own across the rail, the columns on one
	 * row below it, sharing the whole rail equally.
	 *
	 * @param {Object} footer What cells() measured.
	 * @param {number} count  Columns expected.
	 */
	const stacked = ( footer, count ) => {
		expect( footer.columns, 'Columns' ).toHaveLength( count );
		expect( footer.brand.width, 'The brand cell spans the rail' ).toBeCloseTo( footer.rail, 0 );
		for ( const column of footer.columns ) {
			expect( column.top, `${ column.heading || 'last column' }: below the brand cell` ).toBeGreaterThanOrEqual( footer.brand.bottom );
			expect( column.top, 'One row' ).toBeCloseTo( footer.columns[ 0 ].top, 0 );
			expect( column.width ).toBeCloseTo( footer.columns[ 0 ].width, 0 );
		}
		// The columns fill the rail: columns and the 1 px hairlines between them.
		expect( ( count * footer.columns[ 0 ].width ) + count - 1 ).toBeCloseTo( footer.rail, 0 );
	};

	/**
	 * Every word of the menu (headings, links, third-level links) against its column: on one line
	 * (no break inside a word) and inside the column's content box.
	 *
	 * @param {import('@playwright/test').Page} page Page.
	 * @return {Promise<Object>} Words checked, and those that don't fit.
	 */
	const words = ( page ) =>
		page.evaluate( () => {
			const misfits = [];
			let checked = 0;
			for ( const cell of document.querySelectorAll( 'footer .tala-footer-menu .wp-block-navigation__container > li' ) ) {
				const style = getComputedStyle( cell );
				const outer = cell.getBoundingClientRect();
				const left = outer.left + parseFloat( style.borderLeftWidth ) + parseFloat( style.paddingLeft );
				const right = outer.right - parseFloat( style.borderRightWidth ) - parseFloat( style.paddingRight );
				const walker = document.createTreeWalker( cell, NodeFilter.SHOW_TEXT );
				for ( let node = walker.nextNode(); node; node = walker.nextNode() ) {
					for ( const word of node.data.matchAll( /\S+/g ) ) {
						const range = document.createRange();
						range.setStart( node, word.index );
						range.setEnd( node, word.index + word[ 0 ].length );
						const lines = [ ...range.getClientRects() ].filter( ( r ) => r.width > 0 );
						++checked;
						if ( 1 !== lines.length || lines[ 0 ].left < left - 0.5 || lines[ 0 ].right > right + 0.5 ) {
							misfits.push( { word: word[ 0 ], lines: lines.length, width: Math.round( range.getBoundingClientRect().width ), room: Math.round( right - left ) } );
						}
					}
				}
			}
			return { checked, misfits };
		} );

	/**
	 * A menu link or submenu in block markup.
	 *
	 * @param {string}   label    Label.
	 * @param {string[]} children Its submenu's markup; none for a link.
	 * @return {string} Markup.
	 */
	const item = ( label, children = [] ) => {
		const attrs = JSON.stringify( { label, url: `/?s=${ encodeURIComponent( label ) }`, kind: 'custom' } );
		return children.length ? `<!-- wp:navigation-submenu ${ attrs } -->${ children.join( '' ) }<!-- /wp:navigation-submenu -->` : `<!-- wp:navigation-link ${ attrs } /-->`;
	};

	/**
	 * Publishes a menu (the newest is the one the footer uses) and returns its ID.
	 *
	 * @param {string}   name  Title.
	 * @param {string[]} items Markup of its items.
	 * @return {string} ID.
	 */
	const publishMenu = ( name, items ) => wp( 'post', 'create', '--post_type=wp_navigation', '--post_status=publish', `--post_title=${ name }`, `--post_content=${ items.join( '' ) }`, '--porcelain' );

	test( 'desktop: one or two columns beside the brand cell, 2fr to each column\'s 1fr; three or more on a row of their own below it', async ( { page } ) => {
		// Four columns (a newer menu is the one used): three with submenus, then the gathered items.
		const four = publishMenu( 'Four columns', [
			item( 'Guides', [ item( 'Classroom guides' ) ] ),
			item( 'Topics', [ item( 'Teaching' ) ] ),
			item( 'About', [ item( 'Contact' ) ] ),
			item( 'Hello' ),
		] );
		try {
			await page.setViewportSize( DESKTOP.viewport );
			await page.goto( pages.article );
			stacked( await cells( page ), 4 );
		} finally {
			wp( 'post', 'delete', four, '--force' );
		}
		// Two columns: one submenu and the gathered items, beside the brand cell.
		const two = publishMenu( 'Two columns', [ item( 'Guides', [ item( 'Classroom guides' ) ] ), item( 'Hello' ) ] );
		try {
			for ( const width of [ 820, DESKTOP.viewport.width ] ) {
				await page.setViewportSize( { width, height: 900 } );
				await page.goto( pages.article );
				beside( await cells( page ), 2 );
			}
		} finally {
			wp( 'post', 'delete', two, '--force' );
		}
		// Three columns (Guides, About, the gathered items) at 820 px: 48rem of footer and more.
		await page.setViewportSize( { width: 820, height: 900 } );
		await page.goto( pages.article );
		const three = await cells( page );
		stacked( three, 3 );
		expect( three.columns.map( ( c ) => c.heading ) ).toEqual( [ 'Guides', 'About', '' ] );
	} );

	// Menus shaped like two real sites' (their labels as test data): five columns with short
	// columns and long one-word labels, and three columns with a third level and long words in it.
	const shapes = {
		'five columns': [
			item( 'Grades', [ 'Kindergarten', ...Array.from( { length: 12 }, ( _, i ) => `Grade ${ i + 1 }` ) ].map( ( label ) => item( label ) ) ),
			item( 'Issuances', [ item( 'DepEd Orders' ), item( 'DepEd Memoranda' ) ] ),
			item( 'Resources', [ 'Self-Learning Modules', 'Homeroom Guidance Modules', 'DepEd Online Application', 'Position Description Form', 'Principals’ Test', 'Directory' ].map( ( label ) => item( label ) ) ),
			item( 'About', [ item( 'Homework Help' ), item( 'Suggest a Resource' ), item( 'Facebook Group' ) ] ),
			item( 'Search' ),
		],
		'three columns with a third level': [
			item( 'DepEd Resources', [
				item( 'MELCs', [ item( 'Most Essential Learning Competencies (MELCs)' ) ] ),
				item( 'Issuances', [ item( 'DepEd Order' ), item( 'DepEd Memoranda' ) ] ),
				item( 'Scholarships' ),
				item( 'DepEd Hiring Process' ),
				item( 'Examinations', [ item( 'Principals’ Test' ), item( 'Licensure Examination for Teachers' ), item( 'Schools Division Superintendents Examination' ) ] ),
				item( 'Buhay Guro' ),
				item( 'Teaching & Education' ),
			] ),
			item( 'About', [ 'TeacherPH Team', 'Our Story', 'Contribute', 'Our Services', 'Advertise With Us', 'Feedback Form', 'Contact Us', 'Suggest a Resource', 'Facebook Group' ].map( ( label ) => item( label ) ) ),
			item( 'Search' ),
		],
	};
	for ( const [ shape, items ] of Object.entries( shapes ) ) {
		test( `desktop: no word breaks inside its column, ${ shape }`, async ( { page } ) => {
			const id = publishMenu( `Footer ${ shape }`, items );
			try {
				// 800 px: under 48rem of footer, so two up; 810 px: about the narrowest row of columns (48rem of footer).
				for ( const width of [ 800, 810, DESKTOP.viewport.width ] ) {
					await page.setViewportSize( { width, height: 900 } );
					await page.goto( pages.article );
					const found = await words( page );
					expect( found.checked, `${ width } px: words checked` ).toBeGreaterThan( 20 );
					expect( found.misfits, `${ width } px: words broken or wider than their column` ).toEqual( [] );
					expect( await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth ), 'No sideways scrolling' ).toBe( 0 );
				}
				// From 48rem of footer: the brand cell on its own row, the columns sharing the rail.
				for ( const width of [ 810, DESKTOP.viewport.width ] ) {
					await page.setViewportSize( { width, height: 900 } );
					await page.goto( pages.article );
					stacked( await cells( page ), items.length );
				}
			} finally {
				wp( 'post', 'delete', id, '--force' );
			}
		} );
	}

	for ( const width of [ 320, 390 ] ) {
		test.describe( `phone, ${ width } px`, () => {
			test.use( { viewport: { width, height: 844 }, deviceScaleFactor: 3, isMobile: true, hasTouch: true } );

			test( 'menu columns two up under the brand cell, the third level under its parent; links on the slab pass contrast', async ( { page } ) => {
				for ( const colorScheme of [ 'light', 'dark' ] ) {
					await page.emulateMedia( { colorScheme } );
					await page.goto( pages.article );
					const footer = await cells( page );
					expect( footer.slab, colorScheme ).toBe( 'light' === colorScheme ? 'rgb(19, 72, 220)' : 'rgb(22, 64, 184)' );
					expect( footer.brand.width, 'The brand cell spans the rail' ).toBeCloseTo( footer.rail, 0 );
					expect( footer.columns.map( ( c ) => c.heading ) ).toEqual( [ 'Guides', 'About', '' ] );
					const [ first, second, last ] = footer.columns;
					expect( second.top, 'Two up' ).toBeCloseTo( first.top, 0 );
					expect( second.left ).toBeGreaterThan( first.left );
					expect( second.width ).toBeCloseTo( first.width, 0 );
					expect( last.top, 'The odd column on a row of its own' ).toBeGreaterThan( first.top );
					expect( last.width ).toBeCloseTo( footer.rail, 0 );
					// The third level shows, under its parent, indented, inside the parent's column.
					expect( footer.third.visible, 'The third level shows' ).toBe( true );
					expect( footer.third.link.top ).toBeGreaterThanOrEqual( footer.third.parent.bottom );
					expect( footer.third.link.left ).toBeGreaterThan( footer.third.parent.left );
					expect( footer.third.link.right ).toBeLessThanOrEqual( footer.third.column.right );
					expect( footer.third.link.bottom ).toBeLessThanOrEqual( footer.third.column.bottom );
					expect( [ footer.third.column.left, footer.third.column.top ], 'In the Guides column' ).toEqual( [ first.left, first.top ] );
					expect( footer.underline ).toBe( 'underline' );
					expect( await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth ), 'No sideways scrolling' ).toBe( 0 );
					expect( await axeViolations( page ), colorScheme ).toEqual( [] );
					// The focus ring shows on the slab: white, not cobalt on cobalt.
					const link = page.locator( 'footer .tala-footer-menu a' ).first();
					await link.focus();
					expect( await link.evaluate( ( a ) => [ a.matches( ':focus-visible' ), getComputedStyle( a ).outlineColor, getComputedStyle( a ).outlineWidth ] ) ).toEqual( [ true, 'rgb(255, 255, 255)', '2px' ] );
				}
			} );
		} );
	}
} );

/**
 * Article lists (home, archives, search results), as NASH's: ruled rows without pictures. From
 * 44rem of list the date and first category sit in a 9rem column beside the title and summary;
 * narrower, they stack. The rules run from side rule to side rule. "Previous page" and "Next page"
 * are outline buttons with arrows.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @return {Promise<Object>} What the list measures.
 */
const rows = ( page ) =>
	page.evaluate( () => {
		const all = [ ...document.querySelectorAll( 'main .tala-row' ) ];
		const row = all[ 0 ];
		const box = ( el ) => el.getBoundingClientRect();
		const rail = row.closest( '.tala-rail' );
		const railStyle = getComputedStyle( rail );
		const title = row.querySelector( '.wp-block-post-title a' );
		const s = getComputedStyle( title );
		return {
			count: all.length,
			images: document.querySelectorAll( 'main .tala-rows img, main .tala-rows .wp-block-post-featured-image' ).length,
			columns: getComputedStyle( row ).gridTemplateColumns,
			meta: { top: box( row.querySelector( '.tala-row__meta' ) ).top, bottom: box( row.querySelector( '.tala-row__meta' ) ).bottom, left: box( row.querySelector( '.tala-row__meta' ) ).left },
			text: { top: box( row.querySelector( '.tala-row__text' ) ).top, left: box( row.querySelector( '.tala-row__text' ) ).left },
			terms: row.querySelectorAll( '.tala-first-term a' ).length,
			time: !! row.querySelector( '.tala-row__meta time' ),
			// Each row is ruled from side rule to side rule.
			span: [ Math.round( box( row ).left ), Math.round( box( row ).width ) ],
			inner: [ Math.round( box( rail ).left + parseFloat( railStyle.borderLeftWidth ) ), Math.round( rail.clientWidth ) ],
			rules: all.slice( 1 ).map( ( r ) => getComputedStyle( r.closest( '.wp-block-post' ) ) ).map( ( style ) => `${ style.borderTopWidth } ${ style.borderTopStyle }` ),
			title: [ s.color, s.textDecorationLine ].join( ' ' ),
			excerpt: getComputedStyle( row.querySelector( '.wp-block-post-excerpt' ) ).fontSize,
		};
	} );

test.describe( 'article lists, desktop', () => {
	test.use( DESKTOP );

	test( 'ruled rows, a 9rem column for the date and category beside the title; no pictures', async ( { page } ) => {
		for ( const path of [ pages.home, pages.category, pages.author, pages.search ] ) {
			await page.goto( path );
			const list = await rows( page );
			expect( list.count, path ).toBeGreaterThan( 0 );
			expect( list.images, `${ path }: no thumbnails` ).toBe( 0 );
			const tracks = list.columns.split( ' ' );
			expect( tracks, path ).toHaveLength( 2 );
			expect( tracks[ 0 ], `${ path }: 9rem` ).toBe( '144px' );
			expect( list.text.top, `${ path }: side by side` ).toBeCloseTo( list.meta.top, 0 );
			expect( list.text.left ).toBeGreaterThan( list.meta.left + 144 );
			expect( [ list.time, list.terms ], `${ path }: a date and one category` ).toEqual( [ true, 1 ] );
			expect( list.span, `${ path }: rules span the rail` ).toEqual( list.inner );
			expect( list.rules.every( ( rule ) => '1px solid' === rule ), path ).toBe( true );
			expect( list.excerpt ).toBe( '15px' );
		}
	} );

	test( 'title bands draw no rules inside them (controls and chips have their own boxes)', async ( { page } ) => {
		for ( const path of [ pages.home, pages.category, pages.tag, pages.author, pages.date, pages.search ] ) {
			await page.goto( path );
			const ruled = await page.evaluate( () =>
				[ ...document.querySelectorAll( 'main .tala-intro *' ) ]
					.filter( ( el ) => ! el.matches( 'input, button, .tala-chips a' ) )
					.filter( ( el ) => [ 'Top', 'Right', 'Bottom', 'Left' ].some( ( side ) => {
						const s = getComputedStyle( el );
						return parseFloat( s[ `border${ side }Width` ] ) > 0 && 'none' !== s[ `border${ side }Style` ];
					} ) )
					.map( ( el ) => `${ el.tagName.toLowerCase() }.${ [ ...el.classList ].join( '.' ) }` ),
			);
			expect( ruled, path ).toEqual( [] );
		}
	} );

	test( 'no articles found: one rule under the title band, not two', async ( { page } ) => {
		await page.goto( pages.searchNone );
		const edges = await page.evaluate( () => {
			const band = document.querySelector( 'main .tala-band--paper' );
			const none = document.querySelector( 'main .tala-rows .wp-block-query-no-results' );
			return {
				band: getComputedStyle( band ).borderBottomWidth,
				none: getComputedStyle( none ).borderTopWidth,
				touching: Math.abs( none.getBoundingClientRect().top - band.getBoundingClientRect().bottom ) < 1,
			};
		} );
		expect( edges ).toEqual( { band: '1px', none: '0px', touching: true } );
	} );

	test( 'the home page\'s topics: a navigation landmark named "Browse by topic", 44 px square-cornered chips', async ( { page } ) => {
		await page.goto( pages.home );
		const topics = page.getByRole( 'navigation', { name: 'Browse by topic' } );
		await expect( topics ).toBeVisible();
		const chips = await topics.locator( '.tala-chips a' ).evaluateAll( ( links ) =>
			links.map( ( a ) => [ a.getBoundingClientRect().height >= 44, getComputedStyle( a ).borderTopLeftRadius ] ),
		);
		expect( chips.length ).toBeGreaterThan( 0 );
		expect( new Set( chips.map( String ) ) ).toEqual( new Set( [ 'true,2px' ] ) );
	} );

	test( 'the home page\'s display title wraps at NASH\'s measure-20 of display type (0.594em a "ch")', async ( { page } ) => {
		await page.goto( pages.home );
		const measure = await page.evaluate( () => parseFloat( getComputedStyle( document.querySelector( 'main .tala-intro > .tala-display' ) ).maxWidth ) );
		// 60 px display type at 1280 px.
		expect( measure ).toBeCloseTo( 20 * 0.594 * 60, 0 );
	} );

	test( 'the title link: heading ink, a cobalt underline on hover', async ( { page } ) => {
		await page.goto( pages.home );
		expect( ( await rows( page ) ).title ).toBe( 'rgb(0, 0, 0) none' );
		await page.locator( 'main .tala-row .wp-block-post-title a' ).first().hover();
		expect( ( await rows( page ) ).title ).toBe( 'rgb(19, 72, 220) underline' );
	} );

	test( '"Previous page" and "Next page": outline buttons with arrows', async ( { page } ) => {
		const button = ( name ) =>
			page.getByRole( 'link', { name } ).evaluate( ( a ) => {
				const s = getComputedStyle( a );
				return {
					text: a.textContent.replace( /\s+/g, ' ' ).trim(),
					border: `${ s.borderTopWidth } ${ s.borderTopStyle }`,
					height: a.getBoundingClientRect().height >= 44,
					radius: s.borderTopLeftRadius,
				};
			} );
		await page.goto( pages.home );
		expect( await button( 'Next page' ) ).toEqual( { text: 'Next page→', border: '1px solid', height: true, radius: '4px' } );
		await page.goto( pages.page2 );
		expect( await button( 'Previous page' ) ).toEqual( { text: '←Previous page', border: '1px solid', height: true, radius: '4px' } );
	} );
} );

test.describe( 'article lists, phone', () => {
	test.use( { viewport: { width: 390, height: 844 } } );

	test( 'the date and category stack above the title', async ( { page } ) => {
		await page.goto( pages.home );
		const list = await rows( page );
		expect( list.columns.split( ' ' ), 'One column' ).toHaveLength( 1 );
		expect( list.text.top ).toBeGreaterThanOrEqual( list.meta.bottom );
		expect( list.span, 'Rules span the rail' ).toEqual( list.inner );
		expect( await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth ) ).toBe( 0 );
	} );
} );
