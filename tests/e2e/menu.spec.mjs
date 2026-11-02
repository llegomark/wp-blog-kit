/**
 * The header menu: on phones a button opens it full screen (the "Menu overlay" template part),
 * with its top bar exactly over the header's, so nothing seems to move.
 */
import { test, expect } from '@playwright/test';
import { demoPages, PHONE, DESKTOP, wp, openMenu } from './utils.mjs';

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
		const header = { title: await box( page, '.tala-header .wp-block-site-title' ) };
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
		expect( frames.button, 'The close button stays put' ).toHaveLength( 1 );
		expect( frames.fades, 'It still fades in' ).toBe( true );
	} );

	test( 'the close button is a 44 px target on the header row\'s centre line at the sheet\'s inline end', async ( { page } ) => {
		await page.goto( pages.home );
		const open = await box( page, '.wp-block-navigation__responsive-container-open' );
		expect( open, 'The menu button is a 44 px target' ).toMatch( / 44\.0x44\.0$/ );
		const row = await page.evaluate( () => {
			const r = document.querySelector( '.tala-header > .tala-rail' ).getBoundingClientRect();
			return r.top + ( r.height / 2 );
		} );
		await openMenu( page );
		const close = await page.evaluate( () => {
			const button = document.querySelector( '.wp-block-navigation-overlay-close' );
			const bar = document.querySelector( '.tala-overlay-bar' );
			const b = button.getBoundingClientRect();
			const s = getComputedStyle( bar );
			return {
				size: [ button.offsetWidth, button.offsetHeight ],
				middle: b.top + ( b.height / 2 ),
				gap: Math.round( ( bar.getBoundingClientRect().right - parseFloat( s.borderRightWidth ) - parseFloat( s.paddingRight ) - b.right ) * 10 ) / 10,
			};
		} );
		expect( close.size ).toEqual( [ 44, 44 ] );
		expect( close.middle ).toBeCloseTo( row, 1 );
		expect( close.gap, 'At the end of the bar, inside its padding' ).toBeCloseTo( 0, 1 );
	} );

	test( 'a sheet the width of the screen, links at least 48 px tall with hairlines', async ( { page } ) => {
		await page.setViewportSize( { width: 390, height: 844 } );
		await page.goto( pages.home );
		await openMenu( page );
		const sheet = await page.evaluate( () => {
			const overlay = document.querySelector( '.tala-overlay' ).getBoundingClientRect();
			const links = [ ...document.querySelectorAll( '.tala-overlay-menu a' ) ].map( ( a ) => {
				const s = getComputedStyle( a );
				return [ a.getBoundingClientRect().height >= 48, `${ s.borderBottomWidth } ${ s.borderBottomStyle }`, s.fontSize ];
			} );
			return { left: overlay.left, width: overlay.width, screen: document.documentElement.clientWidth, links };
		} );
		expect( sheet.left ).toBe( 0 );
		expect( sheet.width ).toBe( sheet.screen );
		expect( sheet.links.length ).toBeGreaterThan( 0 );
		for ( const link of sheet.links ) {
			expect( link ).toEqual( [ true, '1px solid', '17px' ] );
		}
	} );
} );

test.describe( 'tablet', () => {
	test.use( { viewport: { width: 720, height: 900 }, isMobile: true, hasTouch: true } );

	test( 'the menu opens as a 24rem sheet at the right edge, over a dimmed page', async ( { page } ) => {
		await page.goto( pages.article );
		await expect( page.locator( '.tala-header .wp-block-navigation__responsive-container-content > ul' ), 'The menu folds behind its button' ).toBeHidden();
		await openMenu( page );
		await expect( page.getByRole( 'dialog', { name: 'Menu' } ) ).toBeVisible();
		const sheet = await page.evaluate( () => {
			const container = document.querySelector( '.wp-block-navigation__responsive-container' );
			const overlay = document.querySelector( '.tala-overlay' );
			return {
				width: overlay.getBoundingClientRect().width,
				right: overlay.getBoundingClientRect().right,
				edge: container.getBoundingClientRect().right,
				rule: `${ getComputedStyle( overlay ).borderLeftWidth } ${ getComputedStyle( overlay ).borderLeftStyle }`,
				backdrop: getComputedStyle( container ).backgroundColor,
			};
		} );
		expect( sheet ).toMatchObject( { width: 384, right: sheet.edge, rule: '1px solid' } );
		expect( sheet.backdrop, 'The page behind is dimmed' ).not.toMatch( /^rgba\(0, 0, 0, 0\)$|^transparent$/ );
		await page.keyboard.press( 'Escape' );
		await expect( page.getByRole( 'dialog', { name: 'Menu' } ) ).toBeHidden();
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

test.describe( 'desktop header', () => {
	test.use( DESKTOP );

	test( 'brand, ghost links, a separator, search and the switch on one row of 44 px targets', async ( { page } ) => {
		await page.goto( pages.article );
		const row = await page.evaluate( () => {
			const at = ( el ) => {
				const r = el.getBoundingClientRect();
				return { left: r.left, right: r.right, middle: Math.round( r.top + ( r.height / 2 ) ), height: r.height, width: r.width };
			};
			const header = document.querySelector( '.tala-header' );
			const links = [ ...header.querySelectorAll( '.wp-block-navigation__responsive-container-content > ul > li > a' ) ];
			// The separator is drawn by the search form's ::before (no markup), at the start of the form.
			const form = header.querySelector( '.wp-block-search' );
			const line = getComputedStyle( form, '::before' );
			const formBox = at( form );
			const separator = { left: formBox.left + parseFloat( line.marginLeft ), right: formBox.left + parseFloat( line.marginLeft ) + parseFloat( line.width ), middle: formBox.middle, width: parseFloat( line.width ), height: parseFloat( line.height ) };
			const search = header.querySelector( '.wp-block-search__button' );
			const s = getComputedStyle( search );
			return {
				order: [ at( header.querySelector( '.wp-block-site-title' ) ), at( links[ 0 ] ), at( links.at( -1 ) ), separator, at( search ), at( header.querySelector( '.tala-theme-switch' ) ) ],
				links: links.map( ( a ) => [ at( a ).height >= 44, getComputedStyle( a ).fontSize, getComputedStyle( a ).fontWeight ] ),
				separator: [ separator.width, separator.height, line.content, header.querySelectorAll( '.tala-separator' ).length ],
				search: [ at( search ).width, at( search ).height, s.borderTopWidth, s.backgroundColor === getComputedStyle( document.body ).backgroundColor ],
				menuButton: getComputedStyle( header.querySelector( '.wp-block-navigation__responsive-container-open' ) ).display,
			};
		} );
		expect( row.menuButton, 'The menu is shown, not its button' ).toBe( 'none' );
		for ( let i = 1; i < row.order.length; i++ ) {
			expect( row.order[ i ].left, `item ${ i } after item ${ i - 1 }` ).toBeGreaterThanOrEqual( row.order[ i - 1 ].right );
			expect( Math.abs( row.order[ i ].middle - row.order[ 0 ].middle ), `item ${ i } on the row` ).toBeLessThanOrEqual( 1 );
		}
		for ( const link of row.links ) {
			expect( link ).toEqual( [ true, '15px', '500' ] );
		}
		expect( row.separator ).toEqual( [ 1, 20, '""', 0 ] );
		expect( row.search.slice( 0, 3 ) ).toEqual( [ 44, 44, '1px' ] );
	} );

	test( 'the search button opens a field over the header without moving anything', async ( { page } ) => {
		await page.goto( pages.article );
		const where = () =>
			page.evaluate( () =>
				[ '.tala-header .wp-block-site-title', '.tala-header .wp-block-search__button', '.tala-header .tala-theme-switch', 'main' ]
					.map( ( s ) => JSON.stringify( document.querySelector( s ).getBoundingClientRect() ) )
					.join( '\n' ),
			);
		const before = await where();
		await page.getByRole( 'button', { name: 'Expand search field' } ).click();
		const field = page.getByRole( 'searchbox', { name: 'Search' } );
		await expect( field ).toBeVisible();
		await expect( field ).toBeFocused();
		expect( await where() ).toBe( before );
		expect( ( await field.boundingBox() ).width ).toBeGreaterThanOrEqual( 240 );
		await field.fill( 'reading' );
		await page.keyboard.press( 'Enter' );
		await expect( page ).toHaveURL( /[?&]s=reading/ );
	} );
} );
