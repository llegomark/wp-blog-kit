/**
 * The sitemap over real requests. A post whose canonical URL names another address is left out,
 * and WordPress counts a sitemap's pages before that, so a page the index lists can be left
 * without addresses. It must still answer as a sitemap: WordPress 7.2 serves it empty itself, and
 * on 7.0 and 7.1, which answer 404, Tala SEO does.
 */
import { test, expect } from '@playwright/test';
import { wp } from './utils.mjs';

/** A canonical no demo post has, so the clean-up takes off exactly what the test put on. */
const ELSEWHERE = 'https://example.com/kit-emptied-sitemap-page/';

test( 'a sitemap page whose posts all name another address is served empty, not as an error', async ( { request } ) => {
	const before = await ( await request.get( '/wp-sitemap-posts-post-1.xml' ) ).text();
	expect( before ).toContain( '<url>' );

	wp(
		'eval',
		`foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) {
			add_post_meta( $id, '_tala_seo_canonical', '${ ELSEWHERE }', true );
		}`,
	);
	try {
		expect( await ( await request.get( '/wp-sitemap.xml' ) ).text(), 'The index still lists the page' ).toContain( '/wp-sitemap-posts-post-1.xml' );

		const page = await request.get( '/wp-sitemap-posts-post-1.xml' );
		expect( page.status() ).toBe( 200 );
		expect( page.headers()[ 'content-type' ] ).toContain( 'xml' );
		const xml = await page.text();
		expect( xml ).toContain( '<urlset' );
		expect( xml ).not.toContain( '<url>' );

		expect( ( await request.get( '/wp-sitemap-posts-post-2.xml' ) ).status(), 'A page past the last one is still an error' ).toBe( 404 );
	} finally {
		wp( 'eval', `delete_metadata( 'post', 0, '_tala_seo_canonical', '${ ELSEWHERE }', true );` );
	}
	expect( await ( await request.get( '/wp-sitemap-posts-post-1.xml' ) ).text() ).toBe( before );
} );
