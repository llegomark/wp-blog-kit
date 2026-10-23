/**
 * Accessibility: axe finds nothing on any kind of page, on a phone or a desktop, in light or dark
 * mode, nor in the open phone menu.
 */
import { test, expect } from '@playwright/test';
import { demoPages, PHONE, DESKTOP, axeViolations, openMenu } from './utils.mjs';

const pages = demoPages();

for ( const [ device, settings ] of Object.entries( { phone: PHONE, desktop: DESKTOP } ) ) {
	for ( const colorScheme of [ 'light', 'dark' ] ) {
		test.describe( `${ device }, ${ colorScheme }`, () => {
			test.use( { ...settings, colorScheme } );

			for ( const [ kind, path ] of Object.entries( pages ) ) {
				test( `${ kind }`, async ( { page } ) => {
					await page.goto( path );
					expect( await axeViolations( page ) ).toEqual( [] );
				} );
			}

			if ( 'phone' === device ) {
				test( 'open menu', async ( { page } ) => {
					await page.goto( pages.article );
					await openMenu( page );
					expect( await axeViolations( page ) ).toEqual( [] );
				} );
			}
		} );
	}
}
