/**
 * The header menu: on phones a button opens it full screen (the "Menu overlay" template part),
 * with its top bar exactly over the header's, so nothing seems to move.
 */
import { test, expect } from '@playwright/test';
import { demoPages, PHONE, DESKTOP, wp } from './utils.mjs';

const pages = demoPages();

/**
 * Where an element is drawn: "left,top widthxheight".
 *
 * @param {import('@playwright/test').Page} page     Page.
 * @param {string}                          selector CSS selector.
 * @return {Promise<string>} Box.
 */
const box = ( page, selector ) =>
	page.locator( selector ).first().evaluate( ( el ) => {
		const r = el.getBoundingClientRect();
		return `${ r.left.toFixed( 1 ) },${ r.top.toFixed( 1 ) } ${ r.width.toFixed( 1 ) }x${ r.height.toFixed( 1 ) }`;
	} );

test.describe( 'phone', () => {
	test.use( PHONE );

	test( 'opens as a modal dialog; Escape and the close button close it and give focus back', async ( { page } ) => {
		await page.goto( pages.article );
		const open = page.getByRole( 'button', { name: 'Open menu' } );
		const dialog = page.getByRole( 'dialog', { name: 'Menu' } );

		await open.click();
		await expect( dialog ).toBeVisible();
		await expect( dialog ).toHaveAttribute( 'aria-modal', 'true' );
		await expect( dialog.locator( ':focus' ), 'Focus moves into the menu' ).toHaveCount( 1 );

		await page.keyboard.press( 'Escape' );
		await expect( dialog ).toBeHidden();
		await expect( open ).toBeFocused();

		await open.click();
		await page.getByRole( 'button', { name: 'Close', exact: true } ).click();
		await expect( dialog ).toBeHidden();
		await expect( open ).toBeFocused();
	} );

	test( 'its top bar stays exactly over the header on every frame while it fades in', async ( { page } ) => {
		await page.goto( pages.article );
		const header = {
			title: await box( page, '.tala-header .wp-block-site-title' ),
			button: await box( page, '.wp-block-navigation__responsive-container-open svg' ),
		};
		const frames = await page.evaluate( async () => {
			const seen = { title: new Set(), button: new Set(), opacity: new Set() };
			const at = ( selector ) => {
				const r = document.querySelector( selector ).getBoundingClientRect();
				return `${ r.left.toFixed( 1 ) },${ r.top.toFixed( 1 ) } ${ r.width.toFixed( 1 ) }x${ r.height.toFixed( 1 ) }`;
			};
			const start = performance.now();
			document.querySelector( '.wp-block-navigation__responsive-container-open' ).click();
			await new Promise( ( done ) => {
				const frame = () => {
					seen.title.add( at( '.tala-overlay .wp-block-site-title' ) );
					seen.button.add( at( '.wp-block-navigation-overlay-close svg' ) );
					seen.opacity.add( getComputedStyle( document.querySelector( '.wp-block-navigation__responsive-container' ) ).opacity );
					return performance.now() - start < 250 ? requestAnimationFrame( frame ) : done();
				};
				requestAnimationFrame( frame );
			} );
			return { title: [ ...seen.title ], button: [ ...seen.button ], fades: seen.opacity.size > 1 };
		} );
		expect( frames.title ).toEqual( [ header.title ] );
		expect( frames.button ).toEqual( [ header.button ] );
		expect( frames.fades, 'It still fades in' ).toBe( true );
	} );

	test( 'the close button is a 44 px target in the menu button\'s place', async ( { page } ) => {
		await page.goto( pages.home );
		await page.getByRole( 'button', { name: 'Open menu' } ).click();
		const close = page.getByRole( 'button', { name: 'Close', exact: true } );
		const size = await close.evaluate( ( el ) => [ el.offsetWidth, el.offsetHeight ] );
		expect( size ).toEqual( [ 44, 44 ] );
	} );
} );

test.describe( 'with a saved menu that has a submenu', () => {
	let menu;

	test.beforeAll( () => {
		const content =
			'<!-- wp:navigation-link {"label":"Guides","url":"/category/classroom-guides/","kind":"custom"} /-->' +
			'<!-- wp:navigation-submenu {"label":"About","url":"/about/","kind":"custom"} -->' +
			'<!-- wp:navigation-link {"label":"Contact","url":"/about/#contact","kind":"custom"} /-->' +
			'<!-- /wp:navigation-submenu -->' +
			'<!-- wp:navigation-link {"label":"Search","url":"/?s=","kind":"custom"} /-->';
		menu = wp( 'post', 'create', '--post_type=wp_navigation', '--post_status=publish', '--post_title=Test menu', `--post_content=${ content }`, '--porcelain' );
	} );

	test.afterAll( () => {
		wp( 'post', 'delete', menu, '--force' );
	} );

	test.describe( 'phone', () => {
		test.use( PHONE );

		test( 'submenus show in the menu, indented and spaced like the other items', async ( { page } ) => {
			await page.goto( pages.home );
			await page.getByRole( 'button', { name: 'Open menu' } ).click();
			const links = await page.locator( '.tala-overlay-menu a' ).evaluateAll( ( all ) =>
				all.map( ( a ) => ( { text: a.textContent, left: a.getBoundingClientRect().left, top: a.getBoundingClientRect().top } ) ),
			);
			expect( links.map( ( l ) => l.text ) ).toEqual( [ 'Guides', 'About', 'Contact', 'Search' ] );
			const gaps = links.slice( 1 ).map( ( l, i ) => Math.round( l.top - links[ i ].top ) );
			expect( Math.max( ...gaps ) - Math.min( ...gaps ), `Even spacing: ${ gaps }` ).toBeLessThanOrEqual( 1 );
			expect( links[ 2 ].left - links[ 1 ].left, 'Contact is indented' ).toBeGreaterThan( 10 );
		} );
	} );

	test.describe( 'desktop, dark mode', () => {
		test.use( { ...DESKTOP, colorScheme: 'dark' } );

		test( 'submenus drop down in the page\'s colours', async ( { page } ) => {
			await page.goto( pages.home );
			await page.getByRole( 'button', { name: 'About submenu' } ).click();
			const colours = await page.evaluate( () => {
				const sub = getComputedStyle( document.querySelector( '.tala-header .wp-block-navigation__responsive-container-content .wp-block-navigation__submenu-container' ) );
				return { background: sub.backgroundColor, page: getComputedStyle( document.body ).backgroundColor, visible: sub.visibility };
			} );
			expect( colours.visible ).toBe( 'visible' );
			expect( colours.background ).toBe( colours.page );
		} );
	} );
} );

test.describe( 'narrow desktop window', () => {
	test.use( { viewport: { width: 520, height: 900 } } );

	test( 'opening the menu moves nothing (the page keeps its scrollbar space)', async ( { page } ) => {
		await page.goto( pages.article );
		const before = await box( page, '.tala-header .wp-block-site-title' );
		await page.getByRole( 'button', { name: 'Open menu' } ).click();
		expect( await box( page, '.tala-header .wp-block-site-title' ) ).toBe( before );
		expect( await box( page, '.tala-overlay .wp-block-site-title' ) ).toBe( before );
	} );
} );
