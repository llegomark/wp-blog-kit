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
