/**
 * Every kind of page in the demo content, over HTTP: status, PHP messages, headings, search forms,
 * structured data, theme colours, and the headers of feeds and sitemaps.
 */
import { test, expect } from '@playwright/test';
import { demoPages } from './utils.mjs';

const pages = demoPages();

// What each kind of page should have: HTTP status, the names of its search forms (the site search
// in the header of every page; the home page's title band, search results and the 404 page also
// have one in the page) and its structured data. WebSite and Organization belong to the home page
// (its first page) only.
const site = [ 'Site' ];
const both = [ 'Site', 'Articles' ];
const expected = {
	home: [ 200, both, [ 'WebSite', 'Organization' ] ],
	page2: [ 200, both, [] ],
	article: [ 200, site, [ 'BlogPosting', 'BreadcrumbList' ] ],
	withComments: [ 200, site, [ 'BlogPosting', 'BreadcrumbList' ] ],
	page: [ 200, site, [ 'BreadcrumbList' ] ],
	category: [ 200, site, [ 'BreadcrumbList' ] ],
	tag: [ 200, site, [ 'BreadcrumbList' ] ],
	author: [ 200, site, [ 'ProfilePage', 'BreadcrumbList' ] ],
	date: [ 200, site, [] ],
	search: [ 200, both, [] ],
	searchNone: [ 200, both, [] ],
	notFound: [ 404, both, [] ],
};

/**
 * The `@type` of every node in a page's JSON-LD.
 *
 * @param {string} html Page HTML.
 * @return {string[]} Types.
 */
function schemaTypes( html ) {
	const types = [];
	for ( const [ , json ] of html.matchAll( /<script type="application\/ld\+json">(.*?)<\/script>/gs ) ) {
		for ( const node of JSON.parse( json )[ '@graph' ] ) {
			types.push( node[ '@type' ] );
		}
	}
	return types;
}

for ( const [ kind, path ] of Object.entries( pages ) ) {
	const [ status, searchForms, types ] = expected[ kind ];

	test( `${ kind } (${ path }) renders cleanly`, async ( { request } ) => {
		const response = await request.get( path );
		expect( response.status() ).toBe( status );
		const html = await response.text();

		expect( html ).not.toMatch( /<b>(Warning|Notice|Deprecated|Fatal error)<\/b>/ );
		expect( html.match( /<h1[\s>]/g ), 'One H1' ).toHaveLength( 1 );
		expect( [ ...html.matchAll( /<form[^>]*role="search"[^>]*aria-label="([^"]+)"/g ) ].map( ( m ) => m[ 1 ] ) ).toEqual( searchForms );
		expect( schemaTypes( html ) ).toEqual( types );

		expect( html ).toContain( '<meta name="theme-color" content="#f4f4f2" media="(prefers-color-scheme: light)">' );
		expect( html ).toContain( '<meta name="theme-color" content="#16181d" media="(prefers-color-scheme: dark)">' );
	} );
}

test( 'feeds and sitemaps stay out of search results; pages carry no header', async ( { request } ) => {
	for ( const path of [ '/feed/', '/comments/feed/', '/category/classroom-guides/feed/', '/wp-sitemap.xml', '/wp-sitemap-posts-post-1.xml' ] ) {
		const response = await request.get( path );
		expect( response.status(), path ).toBe( 200 );
		expect( response.headers()[ 'x-robots-tag' ], path ).toBe( 'noindex, follow' );
	}
	expect( ( await request.get( pages.article ) ).headers()[ 'x-robots-tag' ] ).toBeUndefined();
} );

test( 'robots.txt points to the sitemap and blocks no crawler', async ( { request, baseURL } ) => {
	const robots = await ( await request.get( '/robots.txt' ) ).text();
	expect( robots ).toContain( `Sitemap: ${ baseURL }/wp-sitemap.xml` );
	expect( robots.match( /^Disallow:.*$/gm ) ).toEqual( [ 'Disallow: /wp-admin/' ] );
} );

test( 'theme and plugin files do nothing when requested directly', async ( { request } ) => {
	for ( const path of [ '/wp-content/themes/tala-blog/patterns/post-list.php', '/wp-content/plugins/tala-seo/blocks/event/index.asset.php' ] ) {
		const response = await request.get( path );
		expect( response.status(), path ).toBe( 200 );
		expect( await response.text(), path ).toBe( '' );
	}
} );

test( 'the dev site does not serve its admin login', async ( { request } ) => {
	const response = await request.get( '/.dev-credentials' );
	expect( await response.text() ).not.toContain( 'PASSWORD=' );
} );
