/**
 * Tala Guard in the browser: Cloudflare Turnstile renders in the comment form, a comment whose token
 * Siteverify confirms is saved, one it rejects is turned away with a way back, a comment sent
 * before the token is ready waits for it and then goes by itself, and one sent while Turnstile's
 * script can't load or doesn't arrive is told so and can be sent again.
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

/**
 * Stands in for Turnstile's script in this test only: the stub records each render's options and
 * each removal, gives no token, and calls the page's onload callback. Turnstile's script is held
 * until `release` is called, so the page can be set up before the widget renders.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @return {Promise<() => void>} Lets the script load.
 */
async function stubTurnstile( page ) {
	let release;
	const held = new Promise( ( resolve ) => {
		release = resolve;
	} );
	await page.route( 'https://challenges.cloudflare.com/turnstile/v0/api.js*', async ( route ) => {
		await held;
		const onload = new URL( route.request().url() ).searchParams.get( 'onload' );
		await route.fulfill( {
			contentType: 'text/javascript',
			body: `( () => {
				window.stubRenders = [];
				window.stubRemoved = [];
				let next = 0;
				window.turnstile = {
					render( el, opts ) {
						next += 1;
						window.stubRenders.push( { id: 'w' + next, theme: opts.theme } );
						return 'w' + next;
					},
					remove( id ) {
						window.stubRemoved.push( id );
					},
					reset() {},
					getResponse() {
						return '';
					},
					isExpired() {
						return false;
					},
				};
				window[ ${ JSON.stringify( onload ) } ]();
			} )();`,
		} );
	} );
	return release;
}

/**
 * The themes the stub's widgets were rendered with, in order.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @return {Promise<string[]>} Themes.
 */
const renderedThemes = ( page ) => page.evaluate( () => ( window.stubRenders || [] ).map( ( r ) => r.theme ) );

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

	test( 'a comment sent while Turnstile\'s script can\'t load is told so, and the next try loads it again', async ( { page } ) => {
		setKeys( PASSES );
		let reachable = false;
		let requests = 0;
		// Counted until the comment is posted: the page shown after it asks for the script again.
		let posted = false;
		page.on( 'request', ( request ) => {
			posted = posted || request.url().endsWith( '/wp-comments-post.php' );
		} );
		await page.route( 'https://challenges.cloudflare.com/turnstile/v0/api.js*', async ( route ) => {
			requests += posted ? 0 : 1;
			if ( reachable ) {
				await route.continue();
			} else {
				await route.abort( 'internetdisconnected' );
			}
		} );
		await page.goto( pages.withComments );
		const text = await fillComment( page );
		await page.locator( '#submit' ).click();
		const status = page.locator( '.tala-guard-status' );
		await expect( status ).toContainText( 'couldn\'t load or finish' );
		await expect( status ).not.toContainText( 'Checking that a person is writing' );
		expect( page.url() ).not.toMatch( /#comment-/ );
		expect( commentsWith( text ) ).toEqual( [] );
		await expect( page.locator( 'script[src*="challenges.cloudflare.com"]' ), 'The script that failed is gone' ).toHaveCount( 0 );

		// The connection is back: sending again loads the script, runs the check and sends the comment.
		reachable = true;
		const failed = requests;
		await page.locator( '#submit' ).click();
		await expect( status ).toContainText( 'Checking that a person is writing' );
		await page.waitForURL( /#comment-\d+$/, { timeout: 20_000 } );
		const ids = commentsWith( text );
		expect( ids ).toHaveLength( 1 );
		wp( 'comment', 'delete', ...ids, '--force' );
		expect( requests - failed, 'One new request for the script' ).toBe( 1 );
	} );

	test( 'a script that doesn\'t arrive ends the wait after 10 seconds, and still works when it comes', async ( { page } ) => {
		setKeys( PASSES );
		let release;
		const held = new Promise( ( resolve ) => {
			release = resolve;
		} );
		let requests = 0;
		await page.route( 'https://challenges.cloudflare.com/turnstile/v0/api.js*', async ( route ) => {
			requests += 1;
			await held;
			await route.continue();
		} );
		await page.clock.install();
		await page.goto( pages.withComments );
		const text = await fillComment( page );
		await page.locator( '#submit' ).click();
		const status = page.locator( '.tala-guard-status' );
		await expect( status ).toContainText( 'Checking that a person is writing' );

		await page.clock.runFor( 9_000 );
		await expect( status, 'Still waiting after 9 seconds' ).toContainText( 'Checking that a person is writing' );
		await page.clock.runFor( 1_500 );
		await expect( status ).toContainText( 'couldn\'t load or finish' );

		// Sending again waits again, without asking for the script a second time while the first
		// request is still on its way; when the script arrives, the comment goes.
		await page.locator( '#submit' ).click();
		await expect( status ).toContainText( 'Checking that a person is writing' );
		expect( requests ).toBe( 1 );
		release();
		await page.clock.resume();
		await page.waitForURL( /#comment-\d+$/, { timeout: 20_000 } );
		const ids = commentsWith( text );
		expect( ids ).toHaveLength( 1 );
		wp( 'comment', 'delete', ...ids, '--force' );
	} );

	test( 'in a browser without requestSubmit, a comment that waited for its token still goes', async ( { page } ) => {
		setKeys( PASSES );
		// WordPress names the form's button "submit", which hides the form's own submit() method.
		await page.addInitScript( () => {
			delete window.HTMLFormElement.prototype.requestSubmit;
		} );
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
		release();
		await page.waitForURL( /#comment-\d+$/, { timeout: 20_000 } );
		const ids = commentsWith( text );
		expect( ids ).toHaveLength( 1 );
		wp( 'comment', 'delete', ...ids, '--force' );
	} );

	test( 'the widget follows the page\'s colour scheme, as Tala Blog\'s Light/Dark switch sets it', async ( { page } ) => {
		setKeys( PASSES );
		const release = await stubTurnstile( page );
		await page.goto( pages.withComments );
		await page.evaluate( () => {
			document.documentElement.dataset.theme = 'light';
		} );
		release();
		await page.locator( '.tala-guard-turnstile' ).scrollIntoViewIfNeeded();
		await expect.poll( () => renderedThemes( page ) ).toEqual( [ 'light' ] );

		await page.evaluate( () => {
			document.documentElement.dataset.theme = 'dark';
		} );
		await expect.poll( () => renderedThemes( page ) ).toEqual( [ 'light', 'dark' ] );
		expect( await page.evaluate( () => window.stubRemoved ) ).toEqual( [ 'w1' ] );
	} );

	test( 'the widget follows any page\'s declared color-scheme, and "auto" when none is declared', async ( { page } ) => {
		setKeys( PASSES );
		const release = await stubTurnstile( page );
		await page.goto( pages.withComments );
		await page.evaluate( () => {
			document.documentElement.style.colorScheme = 'normal';
		} );
		release();
		await page.locator( '.tala-guard-turnstile' ).scrollIntoViewIfNeeded();
		await expect.poll( () => renderedThemes( page ) ).toEqual( [ 'auto' ] );

		await page.evaluate( () => {
			document.documentElement.style.colorScheme = 'dark';
		} );
		await expect.poll( () => renderedThemes( page ) ).toEqual( [ 'auto', 'dark' ] );
		await page.evaluate( () => {
			document.documentElement.style.colorScheme = 'only light';
		} );
		await expect.poll( () => renderedThemes( page ) ).toEqual( [ 'auto', 'dark', 'light' ] );
		await page.evaluate( () => {
			document.documentElement.style.colorScheme = 'light dark';
		} );
		await expect.poll( () => renderedThemes( page ) ).toEqual( [ 'auto', 'dark', 'light', 'auto' ] );
		// A change that leaves the scheme as it was doesn't render the widget again. The observer runs
		// in a microtask and renders synchronously, so once the next task starts, any render is done.
		await page.evaluate( () => {
			document.body.classList.add( 'unrelated-change' );
			return new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
		} );
		expect( await renderedThemes( page ) ).toHaveLength( 4 );
		expect( await page.evaluate( () => window.stubRemoved ) ).toEqual( [ 'w1', 'w2', 'w3' ] );
	} );

	test( 'after switching to dark, the widget renders again, gives a new token and the comment is saved', async ( { page } ) => {
		setKeys( PASSES );
		await page.goto( pages.withComments );
		const widget = page.locator( '.tala-guard-turnstile' );
		await widget.scrollIntoViewIfNeeded();
		const token = widget.locator( 'input[name="cf-turnstile-response"]' );
		await expect( token ).toHaveValue( 'XXXX.DUMMY.TOKEN.XXXX', { timeout: 20_000 } );
		await page.evaluate( () => {
			window.removedWidgets = 0;
			const remove = window.turnstile.remove.bind( window.turnstile );
			window.turnstile.remove = ( id ) => {
				window.removedWidgets += 1;
				remove( id );
			};
			document.documentElement.dataset.theme = 'dark';
		} );
		await expect.poll( () => page.evaluate( () => window.removedWidgets ) ).toBe( 1 );
		await expect( token ).toHaveValue( 'XXXX.DUMMY.TOKEN.XXXX', { timeout: 20_000 } );

		const text = await fillComment( page );
		await Promise.all( [ page.waitForURL( /#comment-\d+$/, { timeout: 20_000 } ), page.locator( '#submit' ).click() ] );
		const ids = commentsWith( text );
		expect( ids ).toHaveLength( 1 );
		wp( 'comment', 'delete', ...ids, '--force' );
	} );
} );
