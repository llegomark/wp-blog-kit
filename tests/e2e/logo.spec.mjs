/**
 * The site logo beside the site title, in the header and in the phone menu.
 */
import { test, expect } from '@playwright/test';
import { demoPages, PHONE, wp, openMenu } from './utils.mjs';

const pages = demoPages();
// The logo's attachment ID, 0 for none (`wp option get` fails when the option doesn't exist).
const siteLogo = () => Number( wp( 'eval', 'echo (int) get_option( "site_logo" );' ) );
let before;

test.use( PHONE );

test.beforeAll( () => {
	before = siteLogo();
} );

test.afterAll( () => {
	wp( 'eval', `${ before } ? update_option( 'site_logo', ${ before } ) : delete_option( 'site_logo' );` );
} );

test.describe( 'without a logo', () => {
	test.beforeAll( () => {
		wp( 'option', 'delete', 'site_logo' );
	} );

	test( 'there is no logo element', async ( { page } ) => {
		await page.goto( pages.home );
		await expect( page.locator( '.wp-block-site-logo' ) ).toHaveCount( 0 );
	} );
} );

test.describe( 'with a logo', () => {
	test.beforeAll( () => {
		const image = wp( 'post', 'list', '--post_type=attachment', '--post_mime_type=image', '--posts_per_page=1', '--field=ID' );
		wp( 'option', 'update', 'site_logo', image );
	} );

	test( 'it sits beside the title in the header and the menu: not a link, silent, with its size set', async ( { page } ) => {
		await page.goto( pages.home );
		const logos = page.locator( '.tala-logo img' );
		await expect( logos ).toHaveCount( 2 );
		for ( const logo of await logos.all() ) {
			await expect( logo ).toHaveAttribute( 'alt', '' );
			await expect( logo ).toHaveAttribute( 'width', '28' );
			await expect( logo ).toHaveAttribute( 'height', /^\d+$/ );
			await expect( logo.locator( 'xpath=ancestor::a' ), 'Not a link' ).toHaveCount( 0 );
		}
	} );

	test( 'the menu\'s logo and title sit exactly over the header\'s', async ( { page } ) => {
		await page.goto( pages.article );
		// The menu sits inside the header's Navigation block: the header's own logo and title come first.
		const where = ( selector ) => page.locator( selector ).first().evaluate( ( el ) => JSON.stringify( el.getBoundingClientRect() ) );
		const header = [ await where( '.tala-header .tala-logo img' ), await where( '.tala-header .wp-block-site-title' ) ];
		await openMenu( page );
		expect( [ await where( '.tala-overlay .tala-logo img' ), await where( '.tala-overlay .wp-block-site-title' ) ] ).toEqual( header );
	} );
} );

/**
 * A one-word title (it can't wrap) beside the logo: it keeps its room on a 360 px phone, where the
 * header's buttons move closer, below that, where the logo gives way, and beside a long menu on a
 * small desktop, which wraps instead.
 */
test.describe( 'with a logo and a one-word title', () => {
	let name;

	test.beforeAll( () => {
		name = wp( 'option', 'get', 'blogname' );
		wp( 'option', 'update', 'blogname', 'Teacherph' );
		const image = wp( 'post', 'list', '--post_type=attachment', '--post_mime_type=image', '--posts_per_page=1', '--field=ID' );
		wp( 'option', 'update', 'site_logo', image );
	} );

	test.afterAll( () => {
		wp( 'option', 'update', 'blogname', name );
	} );

	/**
	 * How far the title's letters run past the brand (0 or less: they fit), whether the header's
	 * logo shows, and the gap between the header's buttons.
	 *
	 * @param {import('@playwright/test').Page} page Page.
	 * @return {Promise<{over: number, logo: boolean, gap: string, scroll: number}>} Fit.
	 */
	const fit = ( page ) =>
		page.evaluate( () => {
			const brand = document.querySelector( '.tala-header .tala-brand' );
			const letters = document.createRange();
			letters.selectNodeContents( brand.querySelector( '.wp-block-site-title a' ) );
			return {
				over: Math.round( letters.getBoundingClientRect().right - brand.getBoundingClientRect().right ),
				logo: 'none' !== getComputedStyle( brand.querySelector( '.tala-logo' ) ).display,
				gap: getComputedStyle( brand.parentElement ).columnGap,
				scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth,
			};
		} );

	for ( const [ width, logo, gap ] of [ [ 412, true, '8px' ], [ 360, true, '4px' ], [ 340, false, '4px' ] ] ) {
		test( `${ width } px phone: the title fits, the logo ${ logo ? 'shows' : 'gives way' }, buttons ${ gap } apart`, async ( { page } ) => {
			await page.setViewportSize( { width, height: 800 } );
			await page.goto( pages.home );
			const row = await fit( page );
			expect( row.over, 'The title inside the brand' ).toBeLessThanOrEqual( 0 );
			expect( row.logo ).toBe( logo );
			expect( row.gap ).toBe( gap );
			expect( row.scroll, 'No sideways scrolling' ).toBe( 0 );
			// The menu's top bar keeps the header's logo and title exactly.
			const where = ( selector ) => page.locator( selector ).first().evaluate( ( el ) => JSON.stringify( el.getBoundingClientRect() ) );
			const header = await where( '.tala-header .wp-block-site-title' );
			await openMenu( page );
			expect( await where( '.tala-overlay .wp-block-site-title' ) ).toBe( header );
		} );
	}

	test( 'small desktop: beside a long menu the title keeps its room and the menu wraps', async ( { page } ) => {
		await page.setViewportSize( { width: 860, height: 800 } );
		await page.goto( pages.home );
		const row = await page.evaluate( () => {
			const head = document.querySelector( '.tala-header' );
			// A menu wider than the room beside the title, like a site's five top-level links.
			const menu = head.querySelector( '.wp-block-navigation__responsive-container-content > ul' );
			const items = [ ...menu.children ];
			for ( let i = 0; menu.scrollWidth < 700 && i < 16; i++ ) {
				menu.append( items[ i % items.length ].cloneNode( true ) );
			}
			const brand = head.querySelector( '.tala-brand' );
			const letters = document.createRange();
			letters.selectNodeContents( brand.querySelector( '.wp-block-site-title a' ) );
			return {
				over: Math.round( letters.getBoundingClientRect().right - brand.getBoundingClientRect().right ),
				clear: Math.round( head.querySelector( 'nav.wp-block-navigation' ).getBoundingClientRect().left - brand.getBoundingClientRect().right ),
				rows: new Set( [ ...menu.children ].map( ( li ) => Math.round( li.getBoundingClientRect().top ) ) ).size,
				scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth,
			};
		} );
		expect( row.over, 'The title inside the brand' ).toBeLessThanOrEqual( 0 );
		expect( row.clear, 'The menu clear of the brand' ).toBeGreaterThanOrEqual( 0 );
		expect( row.rows, 'The menu wraps instead' ).toBeGreaterThan( 1 );
		expect( row.scroll, 'No sideways scrolling' ).toBe( 0 );
	} );
} );
