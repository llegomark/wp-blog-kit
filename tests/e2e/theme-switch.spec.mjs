/**
 * The Light/Dark switch in the header: the page follows the device until the reader chooses the
 * other theme, the choice is kept for every page (localStorage "tala-theme") and applied before the
 * first paint, switching back to the device's own theme undoes it (nothing stays stored, and the
 * page follows the device again), a choice made or undone in another tab or before going Back shows
 * on the open page, the switch still works for the visit when storage is blocked, and without
 * JavaScript it isn't shown (the Site Editor, which doesn't run the theme's script, shows it). The
 * page's color-scheme meta says which schemes it has now.
 */
import { test, expect } from '@playwright/test';
import { demoPages, logIn } from './utils.mjs';

const pages = demoPages();

/** The page background (paper) and the browser's theme colour of each theme. */
const paper = { light: 'rgb(244, 244, 242)', dark: 'rgb(22, 24, 29)' };
const themeColor = { light: '#f4f4f2', dark: '#16181d' };

/**
 * The page's background colour, as the browser computes it.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @return {Promise<string>} Colour.
 */
const background = ( page ) => page.evaluate( () => getComputedStyle( document.body ).backgroundColor );

/**
 * The content of every theme-color meta.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @return {Promise<string[]>} Colours.
 */
const themeColors = ( page ) => page.locator( 'meta[name="theme-color"]' ).evaluateAll( ( metas ) => metas.map( ( meta ) => meta.content ) );

/**
 * The color schemes the page declares to the browser, and the choice in storage.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @return {Promise<Array<string|null>>} The color-scheme meta's content, the stored choice.
 */
const declared = ( page ) => page.evaluate( () => [ document.querySelector( 'meta[name="color-scheme"]' ).content, window.localStorage.getItem( 'tala-theme' ) ] );

/**
 * Starts every page of the test with this choice stored, as an earlier visit would have left it.
 *
 * @param {import('@playwright/test').Page} page   Page.
 * @param {string}                          choice Stored value.
 */
const storeChoice = ( page, choice ) =>
	page.addInitScript( ( value ) => {
		try {
			window.localStorage.setItem( 'tala-theme', value );
		} catch {}
	}, choice );

test.describe( 'light device', () => {
	test.use( { colorScheme: 'light' } );

	test( 'switches to dark and remembers it; switching back follows the device again', async ( { page } ) => {
		await page.goto( pages.article );
		const button = page.locator( '.tala-theme-switch' );
		await expect( button ).toHaveAttribute( 'aria-pressed', 'false' );
		await expect( button ).toHaveAccessibleName( 'Dark theme' );
		expect( await declared( page ), 'Both schemes, nothing stored' ).toEqual( [ 'light dark', null ] );

		await button.click();
		await expect( page.locator( 'html' ) ).toHaveAttribute( 'data-theme', 'dark' );
		expect( await background( page ) ).toBe( paper.dark );
		await expect( button ).toHaveAttribute( 'aria-pressed', 'true' );
		await expect( button ).toHaveAccessibleName( 'Dark theme' );
		expect( await themeColors( page ), 'The browser\'s colour follows' ).toEqual( [ themeColor.dark, themeColor.dark ] );
		expect( await declared( page ) ).toEqual( [ 'dark', 'dark' ] );

		await page.reload();
		await expect( page.locator( 'html' ) ).toHaveAttribute( 'data-theme', 'dark' );
		expect( await background( page ) ).toBe( paper.dark );
		await expect( button ).toHaveAttribute( 'aria-pressed', 'true' );
		await expect( button ).toHaveAccessibleName( 'Dark theme' );
		expect( await themeColors( page ) ).toEqual( [ themeColor.dark, themeColor.dark ] );
		expect( await declared( page ), 'Declared before the first paint' ).toEqual( [ 'dark', 'dark' ] );

		await page.goto( pages.home );
		await expect( page.locator( 'html' ), 'On every page' ).toHaveAttribute( 'data-theme', 'dark' );

		// Back to the device's own theme: the choice is undone, not replaced by "light".
		await button.click();
		await expect( page.locator( 'html' ) ).not.toHaveAttribute( 'data-theme' );
		expect( await background( page ) ).toBe( paper.light );
		await expect( button ).toHaveAttribute( 'aria-pressed', 'false' );
		await expect( button ).toHaveAccessibleName( 'Dark theme' );
		expect( await themeColors( page ), 'Each of the browser\'s colours is its own again' ).toEqual( [ themeColor.light, themeColor.dark ] );
		expect( await declared( page ) ).toEqual( [ 'light dark', null ] );

		// So the page turns dark with the device (at night, say).
		await page.emulateMedia( { colorScheme: 'dark' } );
		expect( await background( page ) ).toBe( paper.dark );
		await expect( button ).toHaveAttribute( 'aria-pressed', 'true' );
	} );

	test( 'a choice holds while the device changes, until the reader switches back', async ( { page } ) => {
		await page.goto( pages.article );
		const button = page.locator( '.tala-theme-switch' );
		await button.click();
		expect( await declared( page ) ).toEqual( [ 'dark', 'dark' ] );
		// The device turns dark too (the choice now matches it, and is kept), then light again.
		for ( const colorScheme of [ 'dark', 'light' ] ) {
			await page.emulateMedia( { colorScheme } );
			await expect( page.locator( 'html' ) ).toHaveAttribute( 'data-theme', 'dark' );
			expect( await background( page ) ).toBe( paper.dark );
			expect( await declared( page ), `Kept on a ${ colorScheme } device` ).toEqual( [ 'dark', 'dark' ] );
		}
		// On a dark device, the other theme is Light, and that is a choice.
		await page.emulateMedia( { colorScheme: 'dark' } );
		await button.click();
		await expect( page.locator( 'html' ) ).toHaveAttribute( 'data-theme', 'light' );
		expect( await background( page ) ).toBe( paper.light );
		expect( await declared( page ) ).toEqual( [ 'light', 'light' ] );
		expect( await themeColors( page ) ).toEqual( [ themeColor.light, themeColor.light ] );
		await button.click();
		await expect( page.locator( 'html' ) ).not.toHaveAttribute( 'data-theme' );
		expect( await background( page ) ).toBe( paper.dark );
		expect( await declared( page ) ).toEqual( [ 'light dark', null ] );
	} );

	test( 'shows the theme it switches to: a moon on a light page, a sun on a dark one', async ( { page } ) => {
		await page.goto( pages.article );
		const button = page.locator( '.tala-theme-switch' );
		await expect( button.locator( '.tala-theme-switch__moon' ) ).toBeVisible();
		await expect( button.locator( '.tala-theme-switch__sun' ) ).toBeHidden();
		await button.click();
		await expect( button.locator( '.tala-theme-switch__sun' ) ).toBeVisible();
		await expect( button.locator( '.tala-theme-switch__moon' ) ).toBeHidden();
		expect( await button.evaluate( ( el ) => [ el.offsetWidth, el.offsetHeight ] ), 'A 44 px target' ).toEqual( [ 44, 44 ] );
	} );

	test( 'works from the keyboard, with transitions off while switching', async ( { page } ) => {
		await page.emulateMedia( { reducedMotion: 'no-preference' } );
		await page.goto( pages.article );
		/**
		 * The CSS transitions running now (the switch's own colours have one).
		 *
		 * @return {Promise<string[]>} Transitioned properties.
		 */
		const transitions = () => page.evaluate( () => document.getAnimations().filter( ( a ) => a instanceof window.CSSTransition ).map( ( a ) => a.transitionProperty ) );
		await page.locator( '.tala-theme-switch' ).focus();
		await page.keyboard.press( 'Enter' );
		expect( await transitions() ).toEqual( [] );
		await expect( page.locator( 'html' ) ).toHaveAttribute( 'data-theme', 'dark' );
		await page.keyboard.press( 'Space' );
		expect( await transitions() ).toEqual( [] );
		await expect( page.locator( 'html' ) ).not.toHaveAttribute( 'data-theme' );
		expect( await background( page ) ).toBe( paper.light );
		await expect( page.locator( 'html' ) ).not.toHaveClass( /tala-switching/ );
	} );

	test( 'a choice made in another tab holds on the open page', async ( { page, context } ) => {
		await page.goto( pages.article );
		await expect( page.locator( 'html' ) ).not.toHaveAttribute( 'data-theme' );
		const other = await context.newPage();
		await other.goto( pages.home );
		await other.locator( '.tala-theme-switch' ).click();
		await expect( page.locator( 'html' ) ).toHaveAttribute( 'data-theme', 'dark' );
		await expect( page.locator( '.tala-theme-switch' ) ).toHaveAttribute( 'aria-pressed', 'true' );
		expect( await background( page ) ).toBe( paper.dark );
		expect( await themeColors( page ) ).toEqual( [ themeColor.dark, themeColor.dark ] );
		// Another key changing (an ad script's, say) changes nothing: once the page has heard of it,
		// the theme is the same.
		await page.evaluate( () => {
			window.heard = [];
			window.addEventListener( 'storage', ( event ) => window.heard.push( event.key ) );
		} );
		await other.evaluate( () => window.localStorage.setItem( 'something-else', 'light' ) );
		await expect.poll( () => page.evaluate( () => window.heard ) ).toContain( 'something-else' );
		await expect( page.locator( 'html' ) ).toHaveAttribute( 'data-theme', 'dark' );
		// Undone in the other tab: this page follows the device again.
		await other.locator( '.tala-theme-switch' ).click();
		await expect( page.locator( 'html' ) ).not.toHaveAttribute( 'data-theme' );
		await expect( page.locator( '.tala-theme-switch' ) ).toHaveAttribute( 'aria-pressed', 'false' );
		expect( await background( page ) ).toBe( paper.light );
		expect( await themeColors( page ) ).toEqual( [ themeColor.light, themeColor.dark ] );
		expect( await declared( page ) ).toEqual( [ 'light dark', null ] );
		await other.close();
	} );

	test( 'a page back from the back/forward cache shows the choice made since', async ( { page } ) => {
		await page.goto( pages.article );
		// As when the reader chose Dark on another page (on this site, or another app on the same
		// origin that keeps the same key), then went Back: the browser restores this page as it was,
		// and fires pageshow with persisted set.
		await page.evaluate( () => {
			window.localStorage.setItem( 'tala-theme', 'dark' );
			window.dispatchEvent( new PageTransitionEvent( 'pageshow', { persisted: true } ) );
		} );
		await expect( page.locator( 'html' ) ).toHaveAttribute( 'data-theme', 'dark' );
		await expect( page.locator( '.tala-theme-switch' ) ).toHaveAttribute( 'aria-pressed', 'true' );
		await expect( page.locator( 'html' ) ).not.toHaveClass( /tala-switching/ );
		expect( await background( page ) ).toBe( paper.dark );
		// A first load's pageshow (persisted false) reads nothing again.
		await page.evaluate( () => {
			window.localStorage.setItem( 'tala-theme', 'light' );
			window.dispatchEvent( new PageTransitionEvent( 'pageshow', { persisted: false } ) );
		} );
		await expect( page.locator( 'html' ) ).toHaveAttribute( 'data-theme', 'dark' );
	} );

	test( 'switch works when storage is blocked', async ( { page } ) => {
		const errors = [];
		page.on( 'pageerror', ( error ) => errors.push( error.message ) );
		// As in Safari's private windows and with site data blocked: storage throws.
		await page.addInitScript( () => {
			const blocked = () => {
				throw new DOMException( 'Storage is blocked', 'SecurityError' );
			};
			window.Storage.prototype.getItem = blocked;
			window.Storage.prototype.setItem = blocked;
			window.Storage.prototype.removeItem = blocked;
		} );
		await page.goto( pages.article );
		expect( await background( page ), 'The page follows the device' ).toBe( paper.light );
		await expect( page.locator( 'html' ) ).not.toHaveAttribute( 'data-theme' );

		const button = page.locator( '.tala-theme-switch' );
		await button.click();
		await expect( page.locator( 'html' ) ).toHaveAttribute( 'data-theme', 'dark' );
		expect( await background( page ) ).toBe( paper.dark );
		await expect( button ).toHaveAttribute( 'aria-pressed', 'true' );
		// A page back from the back/forward cache can't read storage either: the visit's choice stays.
		await page.evaluate( () => window.dispatchEvent( new PageTransitionEvent( 'pageshow', { persisted: true } ) ) );
		await expect( page.locator( 'html' ) ).toHaveAttribute( 'data-theme', 'dark' );
		await button.click();
		await expect( page.locator( 'html' ) ).not.toHaveAttribute( 'data-theme' );
		expect( await background( page ) ).toBe( paper.light );
		await expect( button ).toHaveAttribute( 'aria-pressed', 'false' );
		await expect( button ).toHaveAccessibleName( 'Dark theme' );
		expect( errors ).toEqual( [] );
	} );

	test( 'no flash', async ( { page } ) => {
		await storeChoice( page, 'dark' );
		// What <html> had when the parser created <body>: nothing of the page can be painted before.
		await page.addInitScript( () => {
			new window.MutationObserver( ( records, observer ) => {
				if ( document.body ) {
					window.talaThemeAtBody = `${ document.documentElement.dataset.theme } ${ document.documentElement.className }`;
					observer.disconnect();
				}
			} ).observe( document, { childList: true, subtree: true } );
		} );
		await page.goto( pages.article, { waitUntil: 'domcontentloaded' } );
		const shot = ( await page.screenshot() ).toString( 'base64' );
		expect( await page.evaluate( () => window.talaThemeAtBody ) ).toMatch( /^dark .*\btala-js\b/ );

		// The first paint's background, beside the content (read with the browser's own PNG decoder).
		const pixel = await page.evaluate( async ( data ) => {
			const image = new window.Image();
			image.src = `data:image/png;base64,${ data }`;
			await image.decode();
			const canvas = document.createElement( 'canvas' );
			canvas.width = image.width;
			canvas.height = image.height;
			const context = canvas.getContext( '2d' );
			context.drawImage( image, 0, 0 );
			return [ ...context.getImageData( 4, Math.floor( image.height / 2 ), 1, 1 ).data.slice( 0, 3 ) ];
		}, shot );
		expect( `rgb(${ pixel.join( ', ' ) })` ).toBe( paper.dark );
	} );
} );

test.describe( 'dark device', () => {
	test.use( { colorScheme: 'dark' } );

	test( 'follows the device until a choice is made', async ( { page } ) => {
		await page.goto( pages.article );
		expect( await background( page ) ).toBe( paper.dark );
		await expect( page.locator( 'html' ) ).not.toHaveAttribute( 'data-theme' );
		const button = page.locator( '.tala-theme-switch' );
		await expect( button ).toHaveAttribute( 'aria-pressed', 'true' );
		await expect( button ).toHaveAccessibleName( 'Dark theme' );
		expect( await themeColors( page ), 'The browser picks its colour by the device' ).toEqual( [ themeColor.light, themeColor.dark ] );
	} );

	test( 'a stored value that is not a choice changes nothing', async ( { page } ) => {
		await storeChoice( page, 'sepia' );
		await page.goto( pages.article );
		await expect( page.locator( 'html' ) ).not.toHaveAttribute( 'data-theme' );
		expect( await background( page ) ).toBe( paper.dark );
		await expect( page.locator( 'meta[name="color-scheme"]' ) ).toHaveAttribute( 'content', 'light dark' );
	} );
} );

test.describe( 'without JavaScript', () => {
	test.use( { javaScriptEnabled: false } );

	test( 'hidden without JavaScript', async ( { page } ) => {
		await page.goto( pages.article );
		await expect( page.locator( '.tala-theme-switch' ) ).toHaveCount( 1 );
		await expect( page.locator( '.tala-theme-switch' ) ).toBeHidden();
	} );
} );

test( 'the Site Editor shows the switch in the header', async ( { page } ) => {
	await logIn( page );
	await page.goto( '/wp-admin/site-editor.php?p=%2Fwp_template_part%2Ftala-blog%2F%2Fheader&canvas=edit' );
	await expect( page.frameLocator( 'iframe[name="editor-canvas"]' ).locator( '.tala-header .tala-theme-switch' ) ).toBeVisible( { timeout: 30_000 } );
} );
