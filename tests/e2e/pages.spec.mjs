/**
 * Every kind of page in the demo content, over HTTP: status, PHP messages, headings, search forms,
 * structured data, theme colours, and the headers of feeds and sitemaps.
 */
import { test, expect } from '@playwright/test';
import { demoPages } from './utils.mjs';

const pages = demoPages();

// What each kind of page should have: HTTP status, the name of its one search form (at the top of
// search results and the 404 page, in the footer elsewhere) and its structured data. WebSite and
// Organization belong to the home page (its first page) only.
const expected = {
	home: [ 200, 'Site', [ 'WebSite', 'Organization' ] ],
	page2: [ 200, 'Site', [] ],
	article: [ 200, 'Site', [ 'BlogPosting', 'BreadcrumbList' ] ],
	withComments: [ 200, 'Site', [ 'BlogPosting', 'BreadcrumbList' ] ],
	page: [ 200, 'Site', [ 'BreadcrumbList' ] ],
	category: [ 200, 'Site', [ 'BreadcrumbList' ] ],
	tag: [ 200, 'Site', [ 'BreadcrumbList' ] ],
	author: [ 200, 'Site', [ 'ProfilePage', 'BreadcrumbList' ] ],
	date: [ 200, 'Site', [] ],
	search: [ 200, 'Articles', [] ],
	searchNone: [ 200, 'Articles', [] ],
	notFound: [ 404, 'Articles', [] ],
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
	const [ status, searchForm, types ] = expected[ kind ];

	test( `${ kind } (${ path }) renders cleanly`, async ( { request } ) => {
		const response = await request.get( path );
		expect( response.status() ).toBe( status );
		const html = await response.text();

		expect( html ).not.toMatch( /<b>(Warning|Notice|Deprecated|Fatal error)<\/b>/ );
		expect( html.match( /<h1[\s>]/g ), 'One H1' ).toHaveLength( 1 );
		expect( [ ...html.matchAll( /<form[^>]*role="search"[^>]*aria-label="([^"]+)"/g ) ].map( ( m ) => m[ 1 ] ) ).toEqual( [ searchForm ] );
		expect( schemaTypes( html ) ).toEqual( types );

		expect( html ).toContain( '<meta name="theme-color" content="#fcfcfc" media="(prefers-color-scheme: light)">' );
		expect( html ).toContain( '<meta name="theme-color" content="#121212" media="(prefers-color-scheme: dark)">' );
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
