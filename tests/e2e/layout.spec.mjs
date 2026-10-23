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
