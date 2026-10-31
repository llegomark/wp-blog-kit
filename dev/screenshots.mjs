#!/usr/bin/env node
/**
 * dev/screenshots.mjs: takes the screenshots listed in the plugins' readme.txt (== Screenshots ==)
 * on the local demo site and saves them where WordPress.org keeps a plugin's SVN assets/ folder:
 * .wordpress-org/screenshot-<n>.png for Tala SEO, .wordpress-org/tala-ads/screenshot-<n>.png for
 * Tala Ads. They aren't part of the plugin zips.
 *
 * Run it after a visible change to the SEO panel, the publish check, Settings → SEO, the SEO
 * column or the Event and Course blocks, or to Settings → Ads: `node dev/screenshots.mjs` for both
 * plugins, `node dev/screenshots.mjs tala-seo` or `node dev/screenshots.mjs tala-ads` for one (the
 * site from dev/site.sh, served by dev/serve.sh). It changes nothing on the site that it doesn't undo.
 */
import { mkdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';
import { wp, logIn } from '../tests/e2e/utils.mjs';

const OUT = fileURLToPath( new URL( '../.wordpress-org/', import.meta.url ) );
const ADS_OUT = `${ OUT }tala-ads/`;
const ONLY = process.argv[ 2 ] ?? '';
if ( ! [ '', 'tala-seo', 'tala-ads' ].includes( ONLY ) ) {
	process.stderr.write( 'Usage: node dev/screenshots.mjs [tala-seo|tala-ads]\n' );
	process.exit( 2 );
}
// Example IDs for Settings → Ads, shaped like real ones.
const ADS_EXAMPLE = {
	adsense: 'ca-pub-1234567890123456',
	consent: true,
	gtm: 'GTM-ABC1234',
	innity_key: '0123456789abcdef0123456789abcdef',
	innity_zone: '100000',
	innity_channel: 'EDUCATION,LIFESTYLE',
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
	wp( 'option', 'update', 'tala_ads', JSON.stringify( ADS_EXAMPLE ), '--format=json' );
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

if ( 'tala-ads' !== ONLY ) {
	await seoAdminShots();
}
if ( 'tala-seo' !== ONLY ) {
	await adsShot();
}
await admin.close();
if ( 'tala-ads' !== ONLY ) {
	await seoPhoneShot();
}

await browser.close();
process.stdout.write( `Saved ${ saved } screenshot${ 1 === saved ? '' : 's' } in ${ OUT }\n` );
