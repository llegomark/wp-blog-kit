/**
 * What plugins and older content bring into the page follows the Light/Dark switch, not only the
 * device: the comment form, Gravity Forms (Orbital and 2.5 themes) and PDF Embedder's toolbar take
 * the theme's colour presets, light or dark as the reader chose. Printing drops the frame's rules.
 *
 * Gravity Forms and PDF Embedder aren't installed on the test site, so their markup is added to a
 * real page with the classes they write, with the theme's stylesheets for them
 * (tala-blog/assets/*.css) as functions.php attaches them, after a stand-in for the plugin's own rules.
 */
import { readFileSync } from 'node:fs';
import { test, expect } from '@playwright/test';
import { demoPages } from './utils.mjs';

const pages = demoPages();

const STYLESHEETS = [ 'gravity-forms-orbital.css', 'gravity-forms-2-5.css', 'pdf-embedder.css' ];
const css = ( file ) => readFileSync( new URL( `../../tala-blog/assets/${ file }`, import.meta.url ), 'utf8' );

/** Surface (background) and body ink (text) of each theme. */
const tokens = {
	light: { surface: 'rgb(250, 250, 250)', contrast: 'rgb(57, 61, 69)', danger: 'rgb(197, 59, 43)' },
	dark: { surface: 'rgb(28, 31, 38)', contrast: 'rgb(211, 214, 220)', danger: 'rgb(240, 122, 104)' },
};

/*
 * The plugins' own rules for the same elements, which the theme's must outrank: PDF Embedder's
 * toolbar (its pdfemb.css), and the chain Orbital uses to colour a field from its CSS API.
 */
const STAND_IN = `
.pdfemb-viewer .pdfemb-toolbar { background-color: grey; }
.pdfemb-viewer .pdfemb-toolbar div { color: lightgray; font-size: 12px; }
.pdfemb-viewer .pdfemb-toolbar button { background-color: hsla(0, 0%, 0%, 0.12); color: hsla(0, 0%, 100%, 0.8); }
.gform-theme--framework { --gf-color-in-ctrl: #fff; --gf-color-in-ctrl-contrast: #112337; --gf-color-danger: #c02b0a; --gf-ctrl-bg-color: var(--gf-color-in-ctrl); --gf-ctrl-color: var(--gf-color-in-ctrl-contrast); }
.gform-theme--framework input[type="text"] { background-color: var(--gf-ctrl-bg-color); color: var(--gf-ctrl-color); }
.gform-theme--framework .gfield_required { color: var(--gf-color-danger); }
`;

const FIXTURE = `
<div class="gform_wrapper gform-theme gform-theme--foundation gform-theme--framework gform-theme--orbital" id="gform_wrapper_1" data-form-index="0">
	<div class="gfield"><label class="gfield_label" for="input_1_1">Name<span class="gfield_required">*</span></label>
	<div class="ginput_container"><input type="text" id="input_1_1"></div></div>
</div>
<div class="gform_wrapper gravity-theme" id="gform_wrapper_2">
	<div class="gfield gfield--type-text"><label class="gfield_label" for="input_2_1">Name<span class="gfield_required">*</span></label>
	<div class="ginput_container"><input type="text" id="input_2_1"></div></div>
</div>
<div class="pdfemb-viewer" style="height: 200px">
	<div class="pdfemb-toolbar pdfemb-toolbar-fixed pdfemb-toolbar-top">
		<button class="pdfemb-prev" title="Previous page" type="button"></button>
		<div class="pdfemb-page-area">Page <span class="pdfemb-page-num">1</span> / <span class="pdfemb-page-count">3</span></div>
	</div>
</div>`;

/**
 * Background and text colour of each embedded element.
 *
 * @param {import('@playwright/test').Page} page Page.
 */
const colours = ( page ) =>
	page.evaluate( () => {
		const style = ( selector ) => {
			const s = getComputedStyle( document.querySelector( selector ) );
			return { background: s.backgroundColor, text: s.color };
		};
		return {
			comment: style( '.wp-block-post-comments-form textarea' ),
			orbital: style( '#input_1_1' ),
			legacy: style( '#input_2_1' ),
			toolbar: { background: style( '.pdfemb-toolbar' ).background, text: style( '.pdfemb-page-area' ).text },
			danger: [ '#gform_wrapper_1 .gfield_required', '#gform_wrapper_2 .gfield_required' ].map( ( s ) => style( s ).text ),
		};
	} );

test( 'the embedded stylesheets use the theme\'s colour presets, not colours of their own', () => {
	for ( const file of STYLESHEETS ) {
		const code = css( file )
			.replace( /\/\*[\s\S]*?\*\//g, '' ) // Comments.
			.replace( /var\(\s*--[\w-]+\s*,\s*#[0-9a-f]{3,8}\s*\)/gi, '' ); // Fallbacks.
		expect( code.match( /#[0-9a-f]{3,8}\b/gi ), file ).toBeNull();
		expect( code, `${ file } uses the presets` ).toContain( 'var(--wp--preset--color--' );
		// Rules for a dark device also need the switch: Dark on a light device, Light on a dark one.
		const count = ( pattern ) => [ ...code.matchAll( pattern ) ].length;
		const device = count( /@media \(prefers-color-scheme: dark\)/g );
		expect( count( /@media \(prefers-color-scheme: dark\) \{\s*:root:not\(\[data-theme="light"\]\)/g ), `${ file }: Light chosen wins` ).toBe( device );
		expect( count( /:root\[data-theme="dark"\]/g ), `${ file }: Dark chosen too` ).toBeGreaterThanOrEqual( device );
	}
} );

test.describe( 'light device', () => {
	test.use( { colorScheme: 'light' } );

	test( 'embedded styles follow the switch', async ( { page } ) => {
		await page.goto( pages.withComments );
		await page.addStyleTag( { content: STAND_IN + STYLESHEETS.map( css ).join( '\n' ) } );
		await page.locator( '.wp-block-post-comments-form' ).evaluate( ( form, html ) => form.insertAdjacentHTML( 'beforebegin', html ), FIXTURE );

		const expected = ( theme ) => {
			const field = { background: tokens[ theme ].surface, text: tokens[ theme ].contrast };
			return { comment: field, orbital: field, legacy: field, toolbar: field, danger: [ tokens[ theme ].danger, tokens[ theme ].danger ] };
		};
		expect( await colours( page ), 'The device\'s light theme' ).toEqual( expected( 'light' ) );

		const button = page.locator( '.tala-theme-switch' );
		await button.click();
		await expect( page.locator( 'html' ) ).toHaveAttribute( 'data-theme', 'dark' );
		expect( await colours( page ), 'Dark, chosen with the switch' ).toEqual( expected( 'dark' ) );

		await button.click();
		await expect( page.locator( 'html' ) ).toHaveAttribute( 'data-theme', 'light' );
		expect( await colours( page ), 'Light again' ).toEqual( expected( 'light' ) );
	} );
} );

test.describe( 'dark device', () => {
	test.use( { colorScheme: 'dark' } );

	test( 'Light chosen with the switch wins over the device for the embedded styles too', async ( { page } ) => {
		await page.goto( pages.withComments );
		await page.addStyleTag( { content: STAND_IN + STYLESHEETS.map( css ).join( '\n' ) } );
		await page.locator( '.wp-block-post-comments-form' ).evaluate( ( form, html ) => form.insertAdjacentHTML( 'beforebegin', html ), FIXTURE );
		expect( ( await colours( page ) ).orbital.background ).toBe( tokens.dark.surface );

		await page.locator( '.tala-theme-switch' ).click();
		await expect( page.locator( 'html' ) ).toHaveAttribute( 'data-theme', 'light' );
		const { orbital, legacy, toolbar, danger } = await colours( page );
		expect( [ orbital.background, legacy.background, toolbar.background ] ).toEqual( Array( 3 ).fill( tokens.light.surface ) );
		expect( danger ).toEqual( [ tokens.light.danger, tokens.light.danger ] );
		// The select arrow Orbital draws is the light theme's muted grey, not the dark one.
		const arrow = await page.locator( '#gform_wrapper_1' ).evaluate( ( el ) => getComputedStyle( el ).getPropertyValue( '--gf-icon-ctrl-select' ) );
		expect( arrow ).toContain( '%23585f6c' );
	} );
} );

test.describe( 'print', () => {
	// Wide enough for the fainter outer rules (from 86rem), so their absence on paper means something.
	test.use( { viewport: { width: 1440, height: 900 } } );

	test( 'a printed article has no frame: no side rules, band rules or nodes, and wide tables aren\'t cut off', async ( { page } ) => {
		await page.goto( pages.article );
		await page.emulateMedia( { media: 'print' } );
		const frame = await page.evaluate( () => {
			const rail = document.querySelector( 'main .tala-band > .tala-rail' );
			const s = getComputedStyle( rail );
			return {
				band: getComputedStyle( rail.parentElement ).borderBottomWidth,
				sides: [ s.borderLeftWidth, s.borderRightWidth, s.paddingLeft, s.paddingRight ],
				nodes: [ '::before', '::after' ].map( ( pseudo ) => getComputedStyle( rail, pseudo ).content ),
				outer: getComputedStyle( rail.parentElement, '::before' ).content,
				table: getComputedStyle( document.querySelector( '.wp-block-post-content .wp-block-table' ) ).overflowX,
			};
		} );
		expect( frame ).toEqual( { band: '0px', sides: [ '0px', '0px', '0px', '0px' ], nodes: [ 'none', 'none' ], outer: 'none', table: 'visible' } );
	} );
} );
