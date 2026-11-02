/**
 * Accessibility: axe finds nothing on any kind of page, on a phone or a desktop, in light or dark
 * mode, nor in the open phone menu. The page takes the device's colours until it names a theme
 * (data-theme on <html>, which the Light/Dark switch sets), and axe finds nothing in either theme
 * on a device of either kind; on paper it is black on white.
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

/** The page background (paper) of each theme, as the browser reports it. */
const paper = { light: 'rgb(244, 244, 242)', dark: 'rgb(22, 24, 29)' };

for ( const colorScheme of [ 'light', 'dark' ] ) {
	test.describe( `${ colorScheme } device`, () => {
		test.use( { ...PHONE, colorScheme } );

		test( 'the page follows the device until it names a theme, then that theme', async ( { page } ) => {
			const background = () => page.evaluate( () => getComputedStyle( document.body ).backgroundColor );
			await page.goto( pages.article );
			expect( await background() ).toBe( paper[ colorScheme ] );
			for ( const theme of [ 'light', 'dark' ] ) {
				await page.evaluate( ( name ) => ( document.documentElement.dataset.theme = name ), theme );
				expect( await background(), theme ).toBe( paper[ theme ] );
				expect( await page.evaluate( () => getComputedStyle( document.documentElement ).colorScheme ), theme ).toBe( theme );
				expect( await axeViolations( page ), theme ).toEqual( [] );
			}
		} );

		test( 'a page prints black on white in either theme', async ( { page } ) => {
			await page.goto( pages.article );
			await page.emulateMedia( { media: 'print' } );
			for ( const theme of [ 'light', 'dark' ] ) {
				await page.evaluate( ( name ) => ( document.documentElement.dataset.theme = name ), theme );
				expect(
					await page.evaluate( () => [ document.body, document.querySelector( 'h1' ) ].map( ( el ) => getComputedStyle( el ).backgroundColor + ' ' + getComputedStyle( el ).color ) ),
					theme,
				).toEqual( [ 'rgb(255, 255, 255) rgb(0, 0, 0)', 'rgba(0, 0, 0, 0) rgb(0, 0, 0)' ] );
			}
		} );
	} );
}
