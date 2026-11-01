#!/usr/bin/env node
/**
 * dev/screenshots.mjs: takes the screenshots listed in the plugins' readme.txt (== Screenshots ==)
 * on the local demo site and saves them where WordPress.org keeps a plugin's SVN assets/ folder:
 * .wordpress-org/screenshot-<n>.png for Tala SEO, .wordpress-org/<plugin>/screenshot-<n>.png for
 * Tala Ads and Tala Guard. They aren't part of the plugin zips.
 *
 * Run it after a visible change to the SEO panel, the publish check, Settings → SEO, the SEO
 * column or the Event and Course blocks, to Settings → Ads, or to Tala Guard's widget or settings:
 * `node dev/screenshots.mjs` for every plugin, `node dev/screenshots.mjs tala-seo` (or tala-ads, or
 * tala-guard) for one (the site from dev/site.sh, served by dev/serve.sh). It changes nothing on the
 * site that it doesn't undo. Tala Guard's widget comes from Cloudflare (with its test site key).
 */
import { mkdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';
import { wp, logIn } from '../tests/e2e/utils.mjs';

const OUT = fileURLToPath( new URL( '../.wordpress-org/', import.meta.url ) );
const ADS_OUT = `${ OUT }tala-ads/`;
const GUARD_OUT = `${ OUT }tala-guard/`;
const ONLY = process.argv[ 2 ] ?? '';
if ( ! [ '', 'tala-seo', 'tala-ads', 'tala-guard' ].includes( ONLY ) ) {
	process.stderr.write( 'Usage: node dev/screenshots.mjs [tala-seo|tala-ads|tala-guard]\n' );
	process.exit( 2 );
}
const want = ( plugin ) => '' === ONLY || plugin === ONLY;
// Example IDs for Settings → Ads, shaped like real ones.
const ADS_EXAMPLE = {
	adsense: 'ca-pub-1234567890123456',
	consent: true,
	gtm: 'GTM-ABC1234',
	innity_key: '0123456789abcdef0123456789abcdef',
	innity_zone: '100000',
	innity_channel: 'EDUCATION,LIFESTYLE',
	cse: '0123456789abcdef0',
};
const BASE = process.env.WP_DEV_URL || 'http://127.0.0.1:8080';
// Reduced motion: panels are in place at once, not caught sliding in.
const DESKTOP = { viewport: { width: 1280, height: 900 }, colorScheme: 'light', reducedMotion: 'reduce' };
const PHONE = { viewport: { width: 412, height: 915 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, colorScheme: 'light' };

// An event and a course, as an author would fill them in.
const CARDS = [
	'<!-- wp:paragraph --><p>Two days of workshops on early reading, then a short online course to keep going at your own pace.</p><!-- /wp:paragraph -->',
	'<!-- wp:tala-seo/event {"name":"Early reading workshop","startDate":"2026-11-14","startTime":"09:00","endTime":"16:00","place":"Quezon City Hall","streetAddress":"Elliptical Road","addressLocality":"Quezon City","addressCountry":"Philippines","price":"0","priceCurrency":"PHP","availability":"InStock","ticketUrl":"https://example.com/register","organizer":"Guro Notes"} /-->',
	'<!-- wp:tala-seo/course {"name":"Teaching early reading","description":"Plan phonics lessons that get every grade 1 learner reading.","educationalLevel":"Beginner","courseMode":"Online","workload":"6","instructor":"Ana Reyes","price":"0","priceCurrency":"PHP","enrollUrl":"https://example.com/enroll"} /-->',
].join( '\n\n' );

/**
 * Hides what local debugging tools add to the admin bar (timings, memory, queries), and the
 * "WordPress x.y is available" notice of a local site that is behind.
 *
 * @param {import('@playwright/test').Page} page Page.
 */
async function tidy( page ) {
	await page.addStyleTag( { content: '#wp-admin-bar-query-monitor, .update-nag { display: none !important; }' } );
}

/**
 * Opens the block editor with the welcome guide off.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @param {string}                          path Editor address.
 */
async function openEditor( page, path ) {
	await page.goto( path );
	await page.waitForFunction( () => window.wp?.data?.select( 'core/editor' )?.getCurrentPostId() );
	await page.evaluate( () => window.wp.data.dispatch( 'core/preferences' ).set( 'core/edit-post', 'welcomeGuide', false ) );
	await tidy( page );
}

mkdirSync( ADS_OUT, { recursive: true } );
mkdirSync( GUARD_OUT, { recursive: true } );
const browser = await chromium.launch( { channel: 'chrome' } );
const admin = await browser.newContext( { ...DESKTOP, baseURL: BASE } );
const page = await admin.newPage();
// Leaving the unsaved post of screenshot 2 asks for confirmation: leave without saving.
page.on( 'dialog', ( dialog ) => dialog.accept() );
await logIn( page );
let saved = 0;

/**
 * Tala SEO's screenshots 1 to 4, in wp-admin.
 */
async function seoAdminShots() {
	// 1. The SEO panel on a demo article.
	const article = wp( 'post', 'list', '--post_type=post', '--name=keeping-school-forms-organized-all-year', '--field=ID' );
	await openEditor( page, `/wp-admin/post.php?post=${ article }&action=edit` );
	await page.evaluate( () => {
		const { dispatch, select } = window.wp.data;
		dispatch( 'core/edit-post' ).openGeneralSidebar( 'edit-post/document' );
		if ( ! select( 'core/editor' ).isEditorPanelOpened( 'tala-seo/tala-seo' ) ) {
			dispatch( 'core/editor' ).toggleEditorPanelOpened( 'tala-seo/tala-seo' );
		}
	} );
	await page.locator( '.tala-seo-preview' ).evaluate( ( el ) => el.closest( '.components-panel__body' ).scrollIntoView() );
	await page.screenshot( { path: `${ OUT }screenshot-1.png` } );

	// 2. The publish check on a new post that has only a title and some text.
	await openEditor( page, '/wp-admin/post-new.php' );
	await page.evaluate( () => {
		const { dispatch } = window.wp.data;
		dispatch( 'core/editor' ).editPost( { title: 'Seating plans that calm a noisy class' } );
		dispatch( 'core/block-editor' ).insertBlocks(
			window.wp.blocks.createBlock( 'core/paragraph', { content: 'Where learners sit changes how a lesson goes. Three plans to try this week.' } ),
		);
		dispatch( 'core/block-editor' ).clearSelectedBlock();
	} );
	await page.getByRole( 'button', { name: 'Publish', exact: true } ).click();
	await page.locator( '.tala-seo-prepublish' ).waitFor();
	await page.screenshot( { path: `${ OUT }screenshot-2.png` } );

	// 3. Settings → SEO.
	await page.goto( '/wp-admin/options-general.php?page=tala-seo' );
	await tidy( page );
	await page.screenshot( { path: `${ OUT }screenshot-3.png` } );

	// 4. The SEO column in the list of published posts.
	await page.goto( '/wp-admin/edit.php?post_status=publish' );
	await tidy( page );
	await page.screenshot( { path: `${ OUT }screenshot-4.png` } );
	saved += 4;
}

/**
 * Tala SEO's screenshot 5: the Event and Course blocks on a phone.
 */
async function seoPhoneShot() {
	const id = wp( 'post', 'create', '--post_type=page', '--post_status=publish', '--post_title=Early reading', '--meta_input={"_tala_seo_description":"Workshops and a course on early reading."}', `--post_content=${ CARDS }`, '--porcelain' );
	try {
		const phone = await browser.newContext( { ...PHONE, baseURL: BASE } );
		const visitor = await phone.newPage();
		await visitor.goto( `/?page_id=${ id }` );
		await visitor.locator( '.wp-block-tala-seo-event' ).evaluate( ( el ) => window.scrollTo( 0, el.getBoundingClientRect().top + window.scrollY - 24 ) );
		await visitor.screenshot( { path: `${ OUT }screenshot-5.png` } );
		await phone.close();
	} finally {
		wp( 'post', 'delete', id, '--force' );
	}
	saved += 1;
}

/**
 * Tala Ads' screenshot 1: Settings → Ads, filled in with example IDs. The site's own settings
 * are put back afterwards.
 */
async function adsShot() {
	let before = '';
	try {
		before = wp( 'option', 'get', 'tala_ads', '--format=json' ).trim();
	} catch {
		// No settings yet (WP-CLI exits with an error): there is nothing to put back.
	}
	const about = Number( wp( 'post', 'list', '--post_type=page', '--name=about', '--field=ID' ).trim() );
	wp( 'option', 'update', 'tala_ads', JSON.stringify( { ...ADS_EXAMPLE, search_page: about } ), '--format=json' );
	try {
		await page.goto( '/wp-admin/options-general.php?page=tala-ads' );
		await tidy( page );
		await page.screenshot( { path: `${ ADS_OUT }screenshot-1.png`, fullPage: true } );
	} finally {
		if ( before && '[]' !== before ) {
			wp( 'option', 'update', 'tala_ads', before, '--format=json' );
		} else {
			wp( 'option', 'delete', 'tala_ads' );
		}
	}
	saved += 1;
}

/**
 * Runs a screenshot with Tala Guard's keys set, then puts the site's own keys back.
 *
 * @param {Object}              keys Keys to set.
 * @param {() => Promise<void>} shot What to do with them.
 */
async function withGuardKeys( keys, shot ) {
	let before = '';
	try {
		before = wp( 'option', 'get', 'tala_guard', '--format=json' ).trim();
	} catch {
		// No keys yet (WP-CLI exits with an error): there is nothing to put back.
	}
	wp( 'option', 'update', 'tala_guard', JSON.stringify( keys ), '--format=json' );
	try {
		await shot();
	} finally {
		if ( before && '[]' !== before ) {
			wp( 'option', 'update', 'tala_guard', before, '--format=json' );
		} else {
			wp( 'option', 'delete', 'tala_guard' );
		}
	}
}

/**
 * Tala Guard's screenshot 1: the comment form on a phone with Turnstile's check done (Cloudflare's
 * test site key, which works on any address).
 */
async function guardPhoneShot() {
	await withGuardKeys( { site_key: '1x00000000000000000000AA', secret_key: '1x0000000000000000000000000000000AA' }, async () => {
		const phone = await browser.newContext( { ...PHONE, baseURL: BASE } );
		const visitor = await phone.newPage();
		await visitor.goto( '/hello-world/' );
		const widget = visitor.locator( '.tala-guard-turnstile' );
		await widget.scrollIntoViewIfNeeded();
		await visitor.locator( 'input[name="cf-turnstile-response"]' ).waitFor( { state: 'attached', timeout: 20_000 } );
		await visitor.waitForFunction( () => document.querySelector( 'input[name="cf-turnstile-response"]' )?.value );
		await visitor.locator( '#comment' ).fill( 'This helped me plan our reading week. Thank you!' );
		await visitor.locator( '#author' ).fill( 'Ana Reyes' );
		await visitor.locator( '#email' ).fill( 'ana@example.com' );
		await visitor.locator( '#email' ).blur();
		await visitor.locator( '#commentform' ).evaluate( ( form ) => window.scrollTo( 0, form.getBoundingClientRect().bottom + window.scrollY - window.innerHeight + 24 ) );
		await visitor.waitForTimeout( 1500 ); // The widget's own "Success" animation.
		await visitor.screenshot( { path: `${ GUARD_OUT }screenshot-1.png` } );
		await phone.close();
	} );
	saved += 1;
}

/**
 * Tala Guard's screenshot 2: its section on Settings → Discussion, with example keys shaped like real
 * ones (the secret saved, so not shown).
 */
async function guardSettingsShot() {
	await withGuardKeys( { site_key: '0x4AAAAAAABkMYinukE8nzYS', secret_key: '0x4AAAAAAABkMYinukE8nzYSecretKeyAbCdEf' }, async () => {
		await page.goto( '/wp-admin/options-discussion.php' );
		await tidy( page );
		await page.locator( '#tala-guard' ).screenshot( { path: `${ GUARD_OUT }screenshot-2.png` } );
	} );
	saved += 1;
}

if ( want( 'tala-seo' ) ) {
	await seoAdminShots();
}
if ( want( 'tala-ads' ) ) {
	await adsShot();
}
if ( want( 'tala-guard' ) ) {
	await guardSettingsShot();
}
await admin.close();
if ( want( 'tala-seo' ) ) {
	await seoPhoneShot();
}
if ( want( 'tala-guard' ) ) {
	await guardPhoneShot();
}

await browser.close();
process.stdout.write( `Saved ${ saved } screenshot${ 1 === saved ? '' : 's' } in ${ OUT }\n` );
