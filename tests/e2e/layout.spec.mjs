/**
 * Layout: nothing moves while a page loads, pages don't jump sideways between short and long ones,
 * and short pages keep their footer at the bottom of the window.
 */
import { test, expect } from '@playwright/test';
import { demoPages, PHONE, DESKTOP } from './utils.mjs';

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
