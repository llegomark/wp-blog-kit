/**
 * Each works on its own: Tala SEO with another theme (WordPress's default, Twenty Twenty-Five), and
 * Tala Blog without Tala SEO. The site is put back as it was afterwards.
 */
import { test, expect } from '@playwright/test';
import { wp, demoPages, axeViolations, PHONE } from './utils.mjs';

const pages = demoPages();

/**
 * What the page says about itself: title, description, canonical, structured data types, and PHP
 * messages on the page (the local site shows them).
 *
 * @param {import('@playwright/test').Page} page Page.
 * @return {Promise<Object>} Facts.
 */
const facts = ( page ) =>
	page.evaluate( () => ( {
		title: document.title,
		description: document.querySelector( 'meta[name="description"]' )?.content ?? null,
		canonical: !! document.querySelector( 'link[rel="canonical"]' ),
		types: [ ...document.querySelectorAll( 'script[type="application/ld+json"]' ) ].flatMap( ( script ) =>
			JSON.parse( script.textContent )[ '@graph' ].map( ( node ) => node[ '@type' ] ),
		),
		breadcrumbs: !! document.querySelector( '.wp-block-tala-seo-breadcrumbs' ),
		phpMessages: /(Warning|Notice|Deprecated|Fatal error)<\/b>:/.test( document.body.innerHTML ),
	} ) );

test.describe( 'Tala SEO with another theme', () => {
	test.beforeAll( () => {
		wp( 'theme', 'activate', 'twentytwentyfive' );
	} );

	test.afterAll( () => {
		wp( 'theme', 'activate', 'tala-blog' );
	} );

	test( 'an article has its title, description, canonical and article markup, and no breadcrumb markup without a visible trail', async ( { page } ) => {
		const response = await page.goto( pages.article );
		expect( response.status() ).toBe( 200 );
		const article = await facts( page );
		expect( article ).toEqual( {
			title: expect.stringMatching( / \| / ),
			description: expect.stringMatching( /\S/ ),
			canonical: true,
			types: [ 'BlogPosting' ],
			breadcrumbs: false,
			phpMessages: false,
		} );
		const response2 = await page.goto( pages.home );
		expect( response2.status() ).toBe( 200 );
		expect( ( await facts( page ) ).types ).toEqual( [ 'WebSite', 'Organization' ] );
	} );
} );

test.describe( 'Tala Blog without Tala SEO', () => {
	test.use( PHONE );

	test.beforeAll( () => {
		wp( 'plugin', 'deactivate', 'tala-seo' );
	} );

	test.afterAll( () => {
		wp( 'plugin', 'activate', 'tala-seo' );
	} );

	test( 'every kind of page renders without the plugin: no errors, no breadcrumbs, accessible', async ( { page } ) => {
		for ( const [ name, path ] of Object.entries( pages ) ) {
			const response = await page.goto( path );
			expect( response.status(), name ).toBe( 'notFound' === name ? 404 : 200 );
			const info = await facts( page );
			expect( info.phpMessages, name ).toBe( false );
			expect( info.breadcrumbs, name ).toBe( false );
			expect( info.types, `${ name }: no plugin, no structured data` ).toEqual( [] );
			await expect( page.locator( 'h1' ), name ).toHaveCount( 1 );
			expect( await axeViolations( page ), name ).toEqual( [] );
		}
	} );
} );
