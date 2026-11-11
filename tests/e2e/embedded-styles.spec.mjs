/**
 * What plugins and older content bring into the page follows the Light/Dark switch, not only the
 * device: the comment form, Gravity Forms (Orbital and 2.5 themes) and PDF Embedder's viewer take
 * the theme's colour presets, light or dark as the reader chose, with field edges and error text
 * that stay readable. Printing drops the frame's rules and prints wide tables whole.
 *
 * Gravity Forms and PDF Embedder aren't installed on the test site (Gravity Forms is a paid plugin),
 * so their markup is added to a real page with the classes they write, with the theme's stylesheets
 * for them (tala-blog/assets/*.css) and a copy of the plugins' own rules for the same elements.
 */
import { readFileSync } from 'node:fs';
import { test, expect } from '@playwright/test';
import { demoPages, wp } from './utils.mjs';

const pages = demoPages();

const STYLESHEETS = [ 'gravity-forms-orbital.css', 'gravity-forms-2-5.css', 'pdf-embedder.css' ];
const css = ( file ) => readFileSync( new URL( `../../tala-blog/assets/${ file }`, import.meta.url ), 'utf8' );

/** The theme's colours: surface (fields, bars), body ink, the field edge (muted), the danger red. */
const tokens = {
	light: { surface: 'rgb(250, 250, 250)', contrast: 'rgb(57, 61, 69)', edge: 'rgb(88, 95, 108)', danger: 'rgb(197, 59, 43)', icons: 'brightness(0)' },
	dark: { surface: 'rgb(28, 31, 38)', contrast: 'rgb(211, 214, 220)', edge: 'rgb(163, 170, 183)', danger: 'rgb(240, 122, 104)', icons: 'none' },
};

/*
 * Gravity Forms 3.2.1's own rules for these elements, as its stylesheets write them (the selectors
 * and custom properties of gravity-forms-theme-framework.min.css for Orbital, theme.min.css for the
 * 2.5 theme), and the style element Gravity Forms prints inside each Orbital form with the form's
 * block settings (#gform_wrapper_{id}[data-form-index].gform-theme, an ID's weight). They come after
 * the theme's rules here, so the theme's win by weight, not by order.
 */
const NOT_DISABLED = ':where(:not(.gform-theme__disable):not(.gform-theme__disable *):not(.gform-theme__disable-framework):not(.gform-theme__disable-framework *))';
const GRAVITY_FORMS = `
.gform-theme--api,.gform-theme--framework{--gf-color-in-ctrl:#fff;--gf-color-in-ctrl-rgb:255,255,255;--gf-color-in-ctrl-contrast:#112337;--gf-color-in-ctrl-dark-lighter:#686e77;--gf-color-danger:#c02b0a;--gf-color-danger-rgb:192,43,10}
.gform-theme--api,.gform-theme--framework{--gf-ctrl-bg-color:var(--gf-color-in-ctrl);--gf-ctrl-border-color:var(--gf-color-in-ctrl-dark-lighter);--gf-ctrl-color:var(--gf-color-in-ctrl-contrast);--gf-ctrl-label-color-req:var(--gf-color-danger)}
.gform-theme--framework input[type]:where(:not(.gform-text-input-reset):not([type=hidden]))${ NOT_DISABLED }{--gf-local-bg-color:var(--gf-ctrl-bg-color);--gf-local-border-color:var(--gf-ctrl-border-color);--gf-local-color:var(--gf-ctrl-color);background-color:var(--gf-local-bg-color);border:1px solid var(--gf-local-border-color);color:var(--gf-local-color)}
.gform-theme--framework .gform-field-label>.gfield_required${ NOT_DISABLED }{color:var(--gf-ctrl-label-color-req)}
.gform_wrapper.gravity-theme input[type=text]{font-size:15px;margin-bottom:0;margin-top:0;padding:8px}
.gform_wrapper.gravity-theme .gfield_required{color:#c02b0a;display:inline;font-size:13.008px}
.gform_wrapper.gravity-theme .gfield_validation_message,.gform_wrapper.gravity-theme .validation_message{background:#fff9f9;border:1px solid #c02b0a;font-size:14.992px;margin-top:8px;padding:12.992px 24px}
`;

/* PDF Embedder's rules (its pdfemb.css), before the theme's: functions.php attaches the theme's right after them. */
const PDF_EMBEDDER = `
.pdfemb-viewer{border:1px solid black;background-color:lightGrey}
.pdfemb-viewer .pdfemb-toolbar{background-color:grey}
.pdfemb-viewer .pdfemb-toolbar div{color:lightgray;font-size:12px}
.pdfemb-viewer .pdfemb-toolbar button,.pdfemb-viewer .pdfemb-toolbar button[disabled]{background-color:hsla(0,0%,0%,0.12);color:hsla(0,0%,100%,0.8)}
.pdfemb-viewer .pdfemb-toolbar button.pdfemb-prev::before{content:"^"}
.pdfemb-loadingmsg{text-align:center;padding:5px;background-color:white}
div.pdfemb-errormsg{padding:5px;text-align:center;background-color:#faa}
`;

const FIXTURE = `
<div class="gform_wrapper gform-theme gform-theme--foundation gform-theme--framework gform-theme--orbital" id="gform_wrapper_1" data-form-index="0">
	<style>#gform_wrapper_1[data-form-index="0"].gform-theme{--gf-color-in-ctrl:#fff;--gf-color-in-ctrl-contrast:#112337;--gf-ctrl-border-color:#686e77;--gf-ctrl-label-color-primary:#112337;--gf-ctrl-label-color-secondary:#112337}</style>
	<div class="gfield"><label class="gfield_label gform-field-label" for="input_1_1">Name<span class="gfield_required">*</span></label>
	<div class="ginput_container"><input type="text" id="input_1_1"></div></div>
</div>
<div class="gform_wrapper gravity-theme" id="gform_wrapper_2">
	<div class="gfield gfield--type-text gfield_error"><label class="gfield_label gform-field-label" for="input_2_1">Name<span class="gfield_required">*</span></label>
	<div class="ginput_container"><input type="text" id="input_2_1"></div>
	<div class="gfield_description validation_message gfield_validation_message">This field is required.</div></div>
</div>
<div class="pdfemb-viewer" style="height: 200px">
	<div class="pdfemb-toolbar pdfemb-toolbar-fixed pdfemb-toolbar-top">
		<button class="pdfemb-prev" title="Previous page" type="button"></button>
		<div class="pdfemb-page-area">Page <span class="pdfemb-page-num">1</span> / <span class="pdfemb-page-count">3</span></div>
	</div>
</div>
<div class="pdfemb-loadingmsg">Loading…</div>
<div class="pdfemb-errormsg">The PDF could not be loaded.</div>`;

/**
 * Opens the article with comments and adds the plugins' markup and rules.
 *
 * @param {import('@playwright/test').Page} page Page.
 */
async function openWithPlugins( page ) {
	await page.goto( pages.withComments );
	await page.addStyleTag( { content: PDF_EMBEDDER + STYLESHEETS.map( css ).join( '\n' ) + GRAVITY_FORMS } );
	await page.locator( '.wp-block-post-comments-form' ).evaluate( ( form, html ) => form.insertAdjacentHTML( 'beforebegin', html ), FIXTURE );
}

/**
 * The colours of each embedded element, as rgb(), and the contrast of edges and text against what
 * they sit on.
 *
 * @param {import('@playwright/test').Page} page Page.
 */
const colours = ( page ) =>
	page.evaluate( () => {
		// Any CSS colour (color-mix() computes to color(srgb …)) as rgb(), through a canvas pixel.
		const canvas = document.createElement( 'canvas' ).getContext( '2d', { willReadFrequently: true } );
		const rgb = ( colour ) => {
			canvas.clearRect( 0, 0, 1, 1 );
			canvas.fillStyle = colour;
			canvas.fillRect( 0, 0, 1, 1 );
			const [ r, g, b ] = canvas.getImageData( 0, 0, 1, 1 ).data;
			return [ r, g, b ];
		};
		const luminance = ( colour ) => {
			const [ r, g, b ] = rgb( colour ).map( ( v ) => {
				const c = v / 255;
				return c <= 0.03928 ? c / 12.92 : ( ( c + 0.055 ) / 1.055 ) ** 2.4;
			} );
			return ( 0.2126 * r ) + ( 0.7152 * g ) + ( 0.0722 * b );
		};
		const ratio = ( a, b ) => {
			const [ hi, lo ] = [ luminance( a ), luminance( b ) ].sort( ( x, y ) => y - x );
			return Math.round( ( ( hi + 0.05 ) / ( lo + 0.05 ) ) * 100 ) / 100;
		};
		const string = ( colour ) => `rgb(${ rgb( colour ).join( ', ' ) })`;
		const page_ = getComputedStyle( document.body ).backgroundColor;
		const style = ( selector, pseudo ) => getComputedStyle( document.querySelector( selector ), pseudo );
		const field = ( selector ) => {
			const s = style( selector );
			return {
				background: string( s.backgroundColor ),
				text: string( s.color ),
				edge: string( s.borderTopColor ),
				edgeContrast: Math.min( ratio( s.borderTopColor, s.backgroundColor ), ratio( s.borderTopColor, page_ ) ) >= 3,
			};
		};
		const box = ( selector ) => {
			const s = style( selector );
			return { background: string( s.backgroundColor ), text: string( s.color ), textContrast: ratio( s.color, s.backgroundColor ) >= 4.5 };
		};
		const message = style( '#gform_wrapper_2 .gfield_validation_message' );
		return {
			comment: field( '.wp-block-post-comments-form textarea' ),
			orbital: field( '#input_1_1' ),
			legacy: field( '#input_2_1' ),
			danger: [ '#gform_wrapper_1 .gfield_required', '#gform_wrapper_2 .gfield_required' ].map( ( s ) => string( style( s ).color ) ),
			message: { text: string( message.color ), textContrast: ratio( message.color, message.backgroundColor ) >= 4.5 },
			toolbar: { background: string( style( '.pdfemb-toolbar' ).backgroundColor ), text: string( style( '.pdfemb-page-area' ).color ) },
			icons: style( '.pdfemb-prev', '::before' ).filter,
			loading: box( '.pdfemb-loadingmsg' ),
			error: box( '.pdfemb-errormsg' ),
		};
	} );

/**
 * What each theme should show.
 *
 * @param {'light'|'dark'} theme Theme.
 */
function expected( theme ) {
	const t = tokens[ theme ];
	const field = { background: t.surface, text: t.contrast, edge: t.edge, edgeContrast: true };
	return {
		comment: field,
		orbital: field,
		legacy: field,
		danger: [ t.danger, t.danger ],
		message: { text: t.danger, textContrast: true },
		toolbar: { background: t.surface, text: t.contrast },
		icons: t.icons,
		loading: { background: t.surface, text: t.contrast, textContrast: true },
		error: { background: expect.not.stringMatching( /^rgb\(255, 170, 170\)$/ ), text: t.contrast, textContrast: true },
	};
}

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
		await openWithPlugins( page );
		expect( await colours( page ), 'The device\'s light theme' ).toEqual( expected( 'light' ) );

		const button = page.locator( '.tala-theme-switch' );
		await button.click();
		await expect( page.locator( 'html' ) ).toHaveAttribute( 'data-theme', 'dark' );
		expect( await colours( page ), 'Dark, chosen with the switch' ).toEqual( expected( 'dark' ) );

		// Back to the device's own theme: the page follows the device again.
		await button.click();
		await expect( page.locator( 'html' ) ).not.toHaveAttribute( 'data-theme' );
		expect( await colours( page ), 'Light again' ).toEqual( expected( 'light' ) );

		// The device turns dark, and the page with it; there, Light is a choice, and wins.
		await page.emulateMedia( { colorScheme: 'dark' } );
		expect( await colours( page ), 'Dark, with the device' ).toEqual( expected( 'dark' ) );
		await button.click();
		await expect( page.locator( 'html' ) ).toHaveAttribute( 'data-theme', 'light' );
		expect( await colours( page ), 'Light, chosen on a dark device' ).toEqual( expected( 'light' ) );
	} );
} );

test.describe( 'dark device', () => {
	test.use( { colorScheme: 'dark' } );

	test( 'Light chosen with the switch wins over the device for the embedded styles too', async ( { page } ) => {
		await openWithPlugins( page );
		expect( await colours( page ), 'The device\'s dark theme' ).toEqual( expected( 'dark' ) );

		await page.locator( '.tala-theme-switch' ).click();
		await expect( page.locator( 'html' ) ).toHaveAttribute( 'data-theme', 'light' );
		expect( await colours( page ), 'Light, chosen with the switch' ).toEqual( expected( 'light' ) );
		// The select arrow Orbital draws is the light theme's muted grey, not the dark one.
		const arrow = await page.locator( '#gform_wrapper_1' ).evaluate( ( el ) => getComputedStyle( el ).getPropertyValue( '--gf-icon-ctrl-select' ) );
		expect( arrow ).toContain( '%23585f6c' );
	} );
} );

test.describe( 'print', () => {
	// Wide enough for the fainter outer rules (from 86rem), so their absence on paper means something.
	test.use( { viewport: { width: 1440, height: 900 } } );
	let classic;

	test.beforeAll( () => {
		// A classic table (the theme wraps it in a .tala-table-scroll box) wider than the page.
		const cells = Array.from( { length: 14 }, ( _, i ) => `<td>Column number ${ i + 1 }</td>` ).join( '' );
		classic = wp( 'post', 'create', '--post_type=page', '--post_status=publish', '--post_title=Wide classic table', '--meta_input={"_tala_seo_description":"A wide table."}', `--post_content=<table><tbody><tr>${ cells }</tr></tbody></table>`, '--porcelain' );
	} );

	test.afterAll( () => {
		wp( 'post', 'delete', classic, '--force' );
	} );

	test( 'a printed article has no frame: no side rules, band rules or nodes, and wide tables aren\'t cut off', async ( { page } ) => {
		const frame = () =>
			page.evaluate( () => {
				const rail = document.querySelector( 'main .tala-band > .tala-rail' );
				const s = getComputedStyle( rail );
				return {
					band: getComputedStyle( rail.parentElement ).borderBottomWidth,
					sides: [ s.borderLeftWidth, s.borderRightWidth, s.paddingLeft, s.paddingRight ],
					nodes: [ '::before', '::after' ].map( ( pseudo ) => getComputedStyle( rail, pseudo ).content ),
					outer: getComputedStyle( rail.parentElement, '::before' ).content,
					table: getComputedStyle( document.querySelector( '.wp-block-post-content :is(.wp-block-table, .tala-table-scroll)' ) ).overflowX,
				};
			} );
		const paper = { band: '0px', sides: [ '0px', '0px', '0px', '0px' ], nodes: [ 'none', 'none' ], outer: 'none', table: 'visible' };

		await page.goto( pages.article );
		await page.emulateMedia( { media: 'print' } );
		expect( await frame(), 'A Table block' ).toEqual( paper );

		await page.goto( `/?page_id=${ classic }` );
		await expect( page.locator( '.wp-block-post-content .tala-table-scroll' ) ).toHaveCount( 1 );
		expect( await frame(), 'A classic table' ).toEqual( paper );
	} );
} );
