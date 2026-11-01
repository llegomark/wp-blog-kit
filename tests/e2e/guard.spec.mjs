/**
 * Tala Guard in the browser: Cloudflare Turnstile renders in the comment form, a comment whose token
 * Siteverify confirms is saved, one it rejects is turned away with a way back, and a comment sent
 * before the token is ready waits for it and then goes by itself.
 *
 * With Cloudflare's test keys (developers.cloudflare.com/turnstile/troubleshooting/testing/): the
 * test site key gives the dummy token on any address, the "always passes" secret key confirms it
 * and the "always fails" one rejects it, through the real Siteverify. The site's keys are put back
 * afterwards.
 */
import { test, expect } from '@playwright/test';
import { wp, demoPages, axeViolations } from './utils.mjs';

const pages = demoPages();
const SITE_KEY = '1x00000000000000000000AA';
const PASSES = '1x0000000000000000000000000000000AA';
const FAILS = '2x0000000000000000000000000000000AA';

let before = '';

/**
 * Saves the Turnstile keys on the local site.
 *
 * @param {string} secret Secret key.
 */
const setKeys = ( secret ) =>
	wp( 'option', 'update', 'tala_guard', JSON.stringify( { site_key: SITE_KEY, secret_key: secret } ), '--format=json' );

/**
 * Fills the comment form as a visitor.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @return {Promise<string>} The comment's text, unique to this test.
 */
async function fillComment( page ) {
	const text = `Turnstile test comment ${ Date.now() }`;
	await page.locator( '#comment' ).fill( text );
	await page.locator( '#author' ).fill( 'Test Reader' );
	await page.locator( '#email' ).fill( 'reader@example.com' );
	return text;
}

/**
 * The IDs of the comments with this text.
 *
 * @param {string} text Comment text.
 * @return {string[]} IDs.
 */
const commentsWith = ( text ) =>
	wp( 'comment', 'list', `--search=${ text }`, '--status=all', '--format=ids' ).split( /\s+/ ).filter( Boolean );

test.describe( 'Turnstile on the comment form', () => {
	test.beforeAll( () => {
		try {
			before = wp( 'option', 'get', 'tala_guard', '--format=json' );
		} catch {
			// No keys yet (WP-CLI exits with an error): there is nothing to put back.
		}
	} );

	test.afterAll( () => {
		if ( before && '[]' !== before ) {
			wp( 'option', 'update', 'tala_guard', before, '--format=json' );
		} else {
			wp( 'option', 'delete', 'tala_guard' );
		}
	} );

	test( 'the widget renders and gives a token, and a confirmed comment is saved', async ( { page } ) => {
		setKeys( PASSES );
		await page.goto( pages.withComments );
		const widget = page.locator( '.tala-guard-turnstile' );
		await expect( widget ).toHaveAttribute( 'data-sitekey', SITE_KEY );
		await widget.scrollIntoViewIfNeeded();
		await expect( widget.locator( 'input[name="cf-turnstile-response"]' ) ).toHaveValue( 'XXXX.DUMMY.TOKEN.XXXX', { timeout: 20_000 } );
		expect( await axeViolations( page ) ).toEqual( [] );

		const text = await fillComment( page );
		await Promise.all( [ page.waitForURL( /#comment-\d+$/ ), page.locator( '#submit' ).click() ] );
		const ids = commentsWith( text );
		expect( ids ).toHaveLength( 1 );
		wp( 'comment', 'delete', ...ids, '--force' );
	} );

	test( 'a comment Siteverify rejects is turned away with a link back', async ( { page } ) => {
		setKeys( FAILS );
		await page.goto( pages.withComments );
		await page.locator( '.tala-guard-turnstile' ).scrollIntoViewIfNeeded();
		await expect( page.locator( 'input[name="cf-turnstile-response"]' ) ).toHaveValue( /\S/, { timeout: 20_000 } );
		const text = await fillComment( page );
		const [ response ] = await Promise.all( [
			page.waitForResponse( ( r ) => r.url().endsWith( '/wp-comments-post.php' ) ),
			page.locator( '#submit' ).click(),
		] );
		expect( response.status() ).toBe( 403 );
		await expect( page.locator( 'body' ) ).toContainText( /didn.t pass the check that keeps out spam/ );
		await expect( page.getByRole( 'link', { name: /back/i } ) ).toBeVisible();
		expect( commentsWith( text ) ).toEqual( [] );
	} );

	test( 'with another theme (Twenty Twenty-Five), the widget renders in its comment form too', async ( { page } ) => {
		setKeys( PASSES );
		wp( 'theme', 'activate', 'twentytwentyfive' );
		try {
			await page.goto( pages.withComments );
			const widget = page.locator( '#commentform .tala-guard-turnstile' );
			await widget.scrollIntoViewIfNeeded();
			await expect( widget.locator( 'input[name="cf-turnstile-response"]' ) ).toHaveValue( 'XXXX.DUMMY.TOKEN.XXXX', { timeout: 20_000 } );
		} finally {
			wp( 'theme', 'activate', 'tala-blog' );
		}
	} );

	test( 'a comment sent before the token is ready waits for it, then goes', async ( { page } ) => {
		setKeys( PASSES );
		let release;
		const held = new Promise( ( resolve ) => {
			release = resolve;
		} );
		await page.route( 'https://challenges.cloudflare.com/turnstile/v0/api.js*', async ( route ) => {
			await held;
			await route.continue();
		} );
		await page.goto( pages.withComments );
		const text = await fillComment( page );
		await page.locator( '#submit' ).click();
		await expect( page.locator( '.tala-guard-status' ) ).toContainText( 'Checking that a person is writing' );
		expect( page.url() ).not.toMatch( /#comment-/ );

		release();
		await page.waitForURL( /#comment-\d+$/, { timeout: 20_000 } );
		const ids = commentsWith( text );
		expect( ids ).toHaveLength( 1 );
		wp( 'comment', 'delete', ...ids, '--force' );
	} );
} );
