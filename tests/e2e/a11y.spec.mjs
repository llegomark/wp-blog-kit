/**
 * Accessibility: axe finds nothing on any kind of page, on a phone or a desktop, in light or dark
 * mode, nor in the open phone menu; nor with each of the theme's colour presets, whose light and
 * dark colours also reach the page and the browser's theme colour.
 */
import { readdirSync, readFileSync } from 'node:fs';
import { test, expect } from '@playwright/test';
import { demoPages, PHONE, DESKTOP, axeViolations, openMenu, chooseStyleVariation, restoreStyles } from './utils.mjs';

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

const presetFolder = new URL( '../../tala-blog/styles/colors/', import.meta.url );

/**
 * A colour written #rrggbb as the browser reports it.
 *
 * @param {string} hex Colour.
 * @return {string} rgb(r, g, b).
 */
const rgb = ( hex ) => `rgb(${ [ 1, 3, 5 ].map( ( i ) => parseInt( hex.slice( i, i + 2 ), 16 ) ).join( ', ' ) })`;

for ( const file of readdirSync( presetFolder ).sort() ) {
	const preset = JSON.parse( readFileSync( new URL( file, presetFolder ), 'utf8' ) );
	const base = {
		light: preset.settings.color.palette.find( ( color ) => 'base' === color.slug ).color,
		dark: preset.settings.custom.color.dark.base,
	};

	test.describe( `colour preset ${ preset.title }`, () => {
		let saved;

		test.beforeAll( () => {
			saved = chooseStyleVariation( preset.title );
		} );

		test.afterAll( () => {
			restoreStyles( saved );
		} );

		for ( const colorScheme of [ 'light', 'dark' ] ) {
			test.describe( colorScheme, () => {
				test.use( { ...PHONE, colorScheme } );

				test( 'an article and the home page', async ( { page } ) => {
					for ( const path of [ pages.article, pages.home ] ) {
						await page.goto( path );
						expect( await axeViolations( page ), path ).toEqual( [] );
						expect( await page.evaluate( () => getComputedStyle( document.body ).backgroundColor ), path ).toBe( rgb( base[ colorScheme ] ) );
						await expect( page.locator( `meta[name="theme-color"][media="(prefers-color-scheme: ${ colorScheme })"]` ) ).toHaveAttribute( 'content', base[ colorScheme ] );
					}
				} );
			} );
		}
	} );
}
