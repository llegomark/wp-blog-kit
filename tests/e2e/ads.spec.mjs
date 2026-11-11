/**
 * Tala Ads in the browser, with every ad service stood in for: the page stays as wide as the
 * window when Google leaves an Auto ads place empty, the plugin's own units keep their space so
 * the text doesn't jump when an ad arrives, ad frames stay see-through in dark mode, and with an ad
 * blocker pages have no script errors and search still works.
 *
 * The settings hold made-up IDs, and no request leaves this machine: the ad services' addresses are
 * answered here (or refused, as an ad blocker would), and so is everything else that isn't the
 * local site. The site's own settings are put back afterwards.
 */
import { readdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { test, expect } from '@playwright/test';
import { wp, demoPages, PHONE, DESKTOP } from './utils.mjs';

const pages = demoPages();
const PUBLISHER = 'ca-pub-1234567890123456';
const INNITY_ZONE = '103011';

/** The ad and tracking services Tala Ads loads from, as ad blockers know them. */
const AD_HOSTS = [
	'pagead2.googlesyndication.com',
	'tpc.googlesyndication.com',
	'googleads.g.doubleclick.net',
	'www.googletagmanager.com',
	'fundingchoicesmessages.google.com',
	'cdn.innity.net',
];

/**
 * Stands in for AdSense's script. `window.kitUnfilledAutoAd()` adds an Auto ads place as Google
 * leaves it when it has no ad: an empty frame as wide as Google made it, in a place shrunk to no
 * height. `window.kitFillUnits()` puts an ad of the usual size into the plugin's own units.
 */
const ADSENSE_STUB = `( () => {
	window.adsbygoogle = window.adsbygoogle || [];
	window.kitAdsenseLoaded = true;
	window.kitUnfilledAutoAd = ( width ) => {
		const text = document.querySelector( '.wp-block-post-content, .entry-content' ) || document.body;
		const place = document.createElement( 'div' );
		place.className = 'google-auto-placed';
		place.style.cssText = 'width: 100%; height: auto; clear: both; text-align: center;';
		place.innerHTML =
			'<ins class="adsbygoogle adsbygoogle-noablate" data-ad-status="unfilled" style="display: block; margin: 0px auto; background-color: transparent; height: 0px;">' +
			'<div style="border: none; height: 0px; width: ' + width + 'px; margin: 0px; padding: 0px; position: relative; visibility: visible; background-color: transparent; display: inline-block;">' +
			'<iframe width="' + width + '" height="0" style="left: 0; position: absolute; top: 0; border: 0; width: ' + width + 'px; height: 0px;"></iframe>' +
			'</div></ins>';
		text.insertBefore( place, text.children[ 1 ] || null );
	};
	window.kitFillUnits = () => {
		document.querySelectorAll( '.tala-ads-in-article ins.adsbygoogle' ).forEach( ( unit ) => {
			unit.style.height = '280px';
			unit.innerHTML = '<iframe width="300" height="280" style="border: 0; width: 100%; height: 280px;"></iframe>';
		} );
		document.querySelectorAll( '.tala-ads-innity > div' ).forEach( ( box ) => {
			box.innerHTML = '<div style="width: 300px; height: 250px;"></div>';
		} );
	};
} )();`;

/** Stands in for Google's search script: draws the field Google's own script draws. */
const SEARCH_STUB = `( () => {
	const draw = () => document.querySelectorAll( '.gcse-search' ).forEach( ( box ) => {
		box.innerHTML = '<form class="gsc-search-box"><input class="gsc-input" type="text" name="search"></form>';
	} );
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', draw );
	} else {
		draw();
	}
} )();`;

/**
 * Answers every request that would leave this machine. The ad services get a stand-in, or are
 * refused when `blocker` is on, along with every script an ad blocker's path rules catch
 * (EasyList's generic "-ads/assets/"). Google's search script, which ad blockers leave alone, gets
 * its stand-in. Anything else outside the local site is refused.
 *
 * @param {import('@playwright/test').Page} page    Page.
 * @param {{blocker?: boolean}}             options Whether an ad blocker is on.
 * @return {Promise<string[]>} The addresses asked for outside the local site, as they come in.
 */
async function standIn( page, { blocker = false } = {} ) {
	const asked = [];
	await page.route(
		( url ) => '127.0.0.1' !== url.hostname,
		( route ) => {
			const url = new URL( route.request().url() );
			asked.push( url.href );
			if ( AD_HOSTS.includes( url.hostname ) ) {
				if ( blocker ) {
					return route.abort( 'blockedbyclient' );
				}
				const body = 'pagead2.googlesyndication.com' === url.hostname ? ADSENSE_STUB : '';
				return route.fulfill( { contentType: 'text/javascript', body } );
			}
			if ( 'cse.google.com' === url.hostname ) {
				return route.fulfill( { contentType: 'text/javascript', body: SEARCH_STUB } );
			}
			return route.abort();
		},
	);
	if ( blocker ) {
		await page.route( /-ads\/assets\/[^?]*\.js/, ( route ) => route.abort( 'blockedbyclient' ) );
	}
	return asked;
}

let before = '';
let resultsPage = '';

test.beforeAll( () => {
	before = wp( 'option', 'get', 'tala_ads', '--format=json' );
	resultsPage = wp(
		'post',
		'create',
		'--post_type=page',
		'--post_status=publish',
		'--post_title=Search results',
		'--post_name=kit-search-results',
		'--meta_input={"_tala_seo_description":"Search this site."}',
		'--post_content=<!-- wp:search {"label":"Search","placeholder":"Search the guides"} /-->',
		'--porcelain',
	);
	wp(
		'option',
		'update',
		'tala_ads',
		JSON.stringify( {
			adsense: PUBLISHER,
			in_article: '3203298627',
			multiplex: '2403531897',
			consent: true,
			gtm: 'GTM-ABC1234',
			innity_key: '0123456789abcdef0123456789abcdef',
			innity_zone: INNITY_ZONE,
			innity_desktop: '103008',
			innity_mobile: '103009',
			cse: '0123456789abcdef0',
			search_page: Number( resultsPage ),
		} ),
		'--format=json',
	);
} );

test.afterAll( () => {
	wp( 'option', 'update', 'tala_ads', before || '[]', '--format=json' );
	wp( 'post', 'delete', resultsPage, '--force' );
} );

for ( const [ name, device ] of Object.entries( { phone: PHONE, desktop: DESKTOP } ) ) {
	test.describe( `${ name }`, () => {
		test.use( device );

		for ( const theme of [ 'tala-blog', 'twentytwentyfive' ] ) {
			test( `an empty Auto ads place wider than the window doesn't make the page scroll sideways (${ theme })`, async ( { page } ) => {
				await standIn( page );
				wp( 'theme', 'activate', theme );
				try {
					await page.goto( pages.article );
					await page.waitForFunction( () => window.kitAdsenseLoaded );
					await page.evaluate( () => window.kitUnfilledAutoAd( 1600 ) );
					const measured = await page.evaluate( () => {
						const frame = document.querySelector( '.google-auto-placed iframe' ).getBoundingClientRect();
						window.scrollTo( 400, 0 );
						return {
							frameWidth: frame.width,
							frameSticksOut: frame.right > window.innerWidth,
							scrolledTo: window.scrollX,
							pageWidth: document.documentElement.scrollWidth,
							windowWidth: document.documentElement.clientWidth,
							// A sideways scrollbar would take its height from the window.
							scrollbarHeight: window.innerHeight - document.documentElement.clientHeight,
						};
					} );
					expect( measured.frameWidth, 'Google\'s frame keeps the width Google gave it' ).toBe( 1600 );
					expect( measured.frameSticksOut, 'The frame does reach past the window\'s edge' ).toBe( true );
					expect( measured.scrolledTo, 'The page can\'t be scrolled sideways' ).toBe( 0 );
					expect( measured.pageWidth, 'The page is as wide as the window' ).toBe( measured.windowWidth );
					expect( measured.scrollbarHeight, 'No sideways scrollbar' ).toBe( 0 );
				} finally {
					wp( 'theme', 'activate', 'tala-blog' );
				}
			} );
		}

		test( 'the In-article unit and the Innity box keep their space, so the text doesn\'t move when an ad arrives', async ( { page } ) => {
			await standIn( page );
			await page.goto( pages.article );
			await page.waitForFunction( () => window.kitAdsenseLoaded );
			const tops = () =>
				page.evaluate( () => {
					const below = ( selector ) => {
						const wrapper = document.querySelector( selector );
						return Math.round( wrapper.nextElementSibling.getBoundingClientRect().top + window.scrollY );
					};
					return {
						inArticleHeight: document.querySelector( '.tala-ads-in-article' ).getBoundingClientRect().height,
						innityHeight: document.querySelector( '.tala-ads-innity' ).getBoundingClientRect().height,
						afterInArticle: below( '.tala-ads-in-article' ),
						afterInnity: below( '.tala-ads-innity' ),
					};
				} );
			const empty = await tops();
			expect( empty.inArticleHeight, 'The In-article unit\'s space is kept before the ad' ).toBe( 280 );
			expect( empty.innityHeight, 'And the Innity box\'s' ).toBe( 250 );

			await page.evaluate( () => window.kitFillUnits() );
			await expect( page.locator( '.tala-ads-in-article iframe' ) ).toBeVisible();
			expect( await tops() ).toEqual( empty );
		} );

		test( 'the page asks AdSense once for each of its own units, after the article and before Innity', async ( { page } ) => {
			await standIn( page );
			await page.goto( pages.article );
			const order = await page.evaluate( () =>
				[ ...document.querySelectorAll( '.tala-ads-in-article, .tala-ads-multiplex, .tala-ads-innity, #innity-in-post' ) ].map(
					( element ) => element.className || element.id,
				),
			);
			expect( order ).toEqual( [ 'tala-ads-in-article', 'tala-ads-multiplex', 'tala-ads-innity', 'innity-in-post' ] );
			await page.waitForFunction( () => window.kitAdsenseLoaded );
			expect( await page.evaluate( () => window.adsbygoogle.length ), 'One request per unit, waiting in AdSense\'s queue' ).toBe( 2 );
		} );
	} );
}

test( 'everything hidden from screen readers on an article is kept out of ad intents, the theme\'s contents list included', async ( { page } ) => {
	await standIn( page );
	// Tala Blog adds its "On this page" contents, with hidden numbers and icons, to the post's
	// content after Tala Ads has read that block. The demo articles have two sections: enough here.
	wp( 'theme', 'mod', 'set', 'tala_blog_contents_sections', '2' );
	try {
		await page.goto( pages.article );
		await expect( page.locator( '.tala-contents .tala-contents-num[aria-hidden="true"]' ).first(), 'The article has a contents list' ).toBeAttached();
		const unmarked = await page
			.locator( '.wp-site-blocks [aria-hidden="true"]' )
			.evaluateAll( ( elements ) =>
				elements.filter( ( element ) => ! element.classList.contains( 'google-anno-skip' ) ).map( ( element ) => element.outerHTML.slice( 0, 120 ) ),
			);
		expect( unmarked ).toEqual( [] );
	} finally {
		wp( 'theme', 'mod', 'remove', 'tala_blog_contents_sections' );
	}
} );

test( 'ad frames stay see-through on a dark page', async ( { page } ) => {
	await standIn( page );
	await page.emulateMedia( { colorScheme: 'dark' } );
	await page.goto( pages.article );
	await page.waitForFunction( () => window.kitAdsenseLoaded );
	await page.evaluate( () => {
		document.documentElement.dataset.theme = 'dark';
		window.kitFillUnits();
		window.kitUnfilledAutoAd( 300 );
	} );
	const schemes = await page.evaluate( () => ( {
		page: window.getComputedStyle( document.documentElement ).colorScheme,
		unit: window.getComputedStyle( document.querySelector( '.tala-ads-in-article iframe' ) ).colorScheme,
		autoAd: window.getComputedStyle( document.querySelector( '.google-auto-placed iframe' ) ).colorScheme,
	} ) );
	expect( schemes.page, 'The page is dark' ).toMatch( /dark/ );
	// A frame declared light keeps its transparent background on a dark page; one that follows the
	// page's dark scheme is painted white until an ad arrives, and stays a white box without one.
	expect( schemes.unit ).toBe( 'light' );
	expect( schemes.autoAd ).toBe( 'light' );
} );

test( 'with an ad blocker, pages have no script errors and search still gets its hint', async ( { page } ) => {
	const asked = await standIn( page, { blocker: true } );
	const errors = [];
	page.on( 'pageerror', ( error ) => errors.push( error.message ) );

	for ( const path of [ pages.home, pages.article, pages.page ] ) {
		const response = await page.goto( path );
		expect( response.status(), path ).toBe( 200 );
		await page.waitForLoadState( 'load' );
	}
	expect( asked.some( ( url ) => url.includes( 'adsbygoogle.js' ) ), 'The pages did ask for AdSense, and were refused' ).toBe( true );
	expect( await page.evaluate( () => window.kitAdsenseLoaded ), 'AdSense never ran' ).toBeUndefined();

	// A search from any search form lands on the results page, where Google's field gets the hint.
	await page.goto( '/?s=reading' );
	await expect( page ).toHaveURL( /\/kit-search-results\/\?q=reading$/ );
	await expect( page.locator( '.tala-ads-google-search input.gsc-input' ) ).toHaveAttribute( 'placeholder', 'Search the guides' );
	expect( errors ).toEqual( [] );
} );

test( 'no script of the plugin sits where ad blockers\' path rules block scripts', () => {
	const root = fileURLToPath( new URL( '../../', import.meta.url ) );
	const scripts = readdirSync( `${ root }tala-ads`, { recursive: true } )
		.map( ( file ) => `tala-ads/${ file }` )
		.filter( ( file ) => file.endsWith( '.js' ) );
	expect( scripts ).toContain( 'tala-ads/js/google-search.js' );
	// EasyList: "-ads/assets/$script". The plugin's own scripts are no ads, so none may match.
	expect( scripts.filter( ( file ) => /-ads\/assets\//.test( file ) ) ).toEqual( [] );
} );
