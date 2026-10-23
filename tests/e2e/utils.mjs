/**
 * Helpers for the browser tests: WP-CLI on the local site, the demo content's addresses, logging
 * in, and axe.
 */
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { homedir } from 'node:os';
import { basename } from 'node:path';
import AxeBuilder from '@axe-core/playwright';

/** The local site (dev/site.sh). */
export const SITE = process.env.WP_DEV_SITE || `${ homedir() }/Sites/blogkit`;

/** Its admin login, which dev/site.sh keeps outside the served site folder. */
const CREDENTIALS = `${ process.env.XDG_STATE_HOME || `${ homedir() }/.local/state` }/wp-blog-kit/${ basename( SITE ) }.credentials`;

/** A phone (Pixel 7) and a desktop window. */
export const PHONE = {
	viewport: { width: 412, height: 915 },
	deviceScaleFactor: 2.625,
	isMobile: true,
	hasTouch: true,
};
export const DESKTOP = { viewport: { width: 1280, height: 900 } };

/**
 * Runs WP-CLI on the local site and returns its output.
 *
 * @param {...string} args WP-CLI arguments.
 * @return {string} Output, trimmed.
 */
export function wp( ...args ) {
	return execFileSync( 'wp', [ `--path=${ SITE }`, ...args ], {
		encoding: 'utf8',
		// WP-CLI's own PHP deprecation notices go to stderr.
		stdio: [ 'ignore', 'pipe', 'ignore' ],
	} ).trim();
}

/**
 * Addresses of each kind of page in the demo content (dev/seed.php), as paths.
 *
 * @return {Object<string, string>} Page kind => path.
 */
export function demoPages() {
	const path = ( url ) => new URL( url ).pathname + new URL( url ).search;
	const article = wp( 'post', 'list', '--post_type=post', '--name=keeping-school-forms-organized-all-year', '--field=ID' );
	const [ year, month ] = wp( 'post', 'get', article, '--field=post_date' ).split( '-' );
	return {
		home: '/',
		page2: '/page/2/',
		article: path( wp( 'post', 'url', article ) ),
		withComments: '/hello-world/',
		page: '/about/',
		category: '/category/classroom-guides/',
		tag: '/tag/teaching/',
		author: path( wp( 'eval', 'echo get_author_posts_url( 1 );' ) ),
		date: `/${ year }/${ month }/`,
		search: '/?s=reading',
		searchNone: '/?s=zzqxnothing',
		notFound: '/no-such-page/',
	};
}

/**
 * Logs in as the site's administrator (the login dev/site.sh wrote).
 *
 * @param {import('@playwright/test').Page} page Page.
 */
export async function logIn( page ) {
	const credentials = Object.fromEntries(
		readFileSync( CREDENTIALS, 'utf8' )
			.trim()
			.split( '\n' )
			.map( ( line ) => line.split( /=(.*)/s ).slice( 0, 2 ) ),
	);
	await page.goto( '/wp-login.php' );
	await page.locator( '#user_login' ).fill( credentials.USER );
	await page.locator( '#user_pass' ).fill( credentials.PASSWORD );
	await Promise.all( [ page.waitForURL( /wp-admin/ ), page.locator( '#wp-submit' ).click() ] );
}

/**
 * Opens the phone menu and waits until it has finished fading in.
 *
 * @param {import('@playwright/test').Page} page Page.
 */
export async function openMenu( page ) {
	await page.getByRole( 'button', { name: 'Open menu' } ).click();
	await page
		.locator( '.wp-block-navigation__responsive-container' )
		.evaluate( ( menu ) => Promise.all( menu.getAnimations().map( ( animation ) => animation.finished ) ) );
}

/**
 * Gives the site the settings and styles of one of the theme's style variations, as the Site
 * Editor saves them when the administrator picks it. Returns the site's styles from before, for
 * restoreStyles().
 *
 * @param {string} title The variation's title.
 * @return {string} The styles before, base64-encoded.
 */
export function chooseStyleVariation( title ) {
	return wp(
		'--user=1',
		'eval',
		`$post = WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles( wp_get_theme(), true );
		$variation = current( wp_list_filter( WP_Theme_JSON_Resolver::get_style_variations(), array( 'title' => ${ JSON.stringify( title ) } ) ) );
		if ( ! $variation ) {
			exit( 1 );
		}
		$user = (array) json_decode( $post['post_content'], true );
		foreach ( array( 'settings', 'styles' ) as $part ) {
			$user[ $part ] = array_replace_recursive( (array) ( $user[ $part ] ?? array() ), (array) ( $variation[ $part ] ?? array() ) );
		}
		wp_update_post( wp_slash( array( 'ID' => $post['ID'], 'post_content' => wp_json_encode( $user ) ) ) );
		echo base64_encode( $post['post_content'] );`,
	);
}

/**
 * Puts back the site's styles that chooseStyleVariation() returned.
 *
 * @param {string} saved The styles, base64-encoded.
 */
export function restoreStyles( saved ) {
	wp(
		'--user=1',
		'eval',
		`$post = WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles( wp_get_theme(), true );
		wp_update_post( wp_slash( array( 'ID' => $post['ID'], 'post_content' => base64_decode( '${ saved }' ) ) ) );`,
	);
}

/**
 * The accessibility violations axe finds on the page, as "rule: element" lines.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @return {Promise<string[]>} Violations.
 */
export async function axeViolations( page ) {
	const results = await new AxeBuilder( { page } ).analyze();
	return results.violations.flatMap( ( v ) => v.nodes.map( ( node ) => `${ v.id }: ${ node.target.join( ' ' ) }` ) );
}
