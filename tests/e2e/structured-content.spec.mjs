/**
 * Tala SEO's FAQ, How-to, Event and Course blocks and the YouTube video dates: they are in the
 * editor's inserter and sidebar, the editor accepts what it saved, and the published page shows
 * them accessibly with matching structured data.
 */
import { test, expect } from '@playwright/test';
import { wp, logIn, axeViolations, PHONE } from './utils.mjs';

const CONTENT = [
	'<!-- wp:group {"className":"tala-seo-faq","layout":{"type":"constrained"}} -->',
	'<div class="wp-block-group tala-seo-faq"><!-- wp:heading -->',
	'<h2 class="wp-block-heading">Frequently asked questions</h2>',
	'<!-- /wp:heading -->',
	'<!-- wp:details -->',
	'<details class="wp-block-details"><summary>Do I need to register?</summary><!-- wp:paragraph -->',
	'<p>Yes, by 1 November.</p>',
	'<!-- /wp:paragraph --></details>',
	'<!-- /wp:details --></div>',
	'<!-- /wp:group -->',
	'<!-- wp:group {"className":"tala-seo-howto","layout":{"type":"constrained"}} -->',
	'<div class="wp-block-group tala-seo-howto"><!-- wp:heading -->',
	'<h2 class="wp-block-heading">How to register</h2>',
	'<!-- /wp:heading -->',
	'<!-- wp:list {"ordered":true} -->',
	'<ol class="wp-block-list"><!-- wp:list-item -->',
	'<li>Fill in the form.</li>',
	'<!-- /wp:list-item -->',
	'<!-- wp:list-item -->',
	'<li>Show it at the door.</li>',
	'<!-- /wp:list-item --></ol>',
	'<!-- /wp:list --></div>',
	'<!-- /wp:group -->',
	'<!-- wp:tala-seo/event {"startDate":"2026-11-14","startTime":"09:00","endTime":"17:00","place":"Quezon City Hall","streetAddress":"Elliptical Road","addressLocality":"Quezon City","addressCountry":"PH","price":"500","priceCurrency":"PHP","availability":"InStock","ticketUrl":"https://tickets.example.com/","organizer":"Guro Notes"} /-->',
	'<!-- wp:tala-seo/course {"name":"Teaching early reading","description":"Plan phonics lessons that get every grade 1 learner reading.","courseMode":"Online","workload":"6","instructor":"Ana Reyes","price":"0","priceCurrency":"PHP","enrollUrl":"https://learn.example.com/"} /-->',
].join( '\n' );

/**
 * Opens the block editor with the welcome guide off.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @param {string}                          path Editor address.
 */
async function openEditor( page, path ) {
	await page.goto( path );
	await page.waitForFunction( () => window.wp?.data?.select( 'core/editor' )?.getCurrentPostId() );
	await page.evaluate( () => {
		window.wp.data.dispatch( 'core/preferences' ).set( 'core/edit-post', 'welcomeGuide', false );
	} );
}

test.describe( 'a page with the blocks', () => {
	let id;

	test.beforeAll( () => {
		id = wp(
			'post',
			'create',
			'--post_type=page',
			'--post_status=publish',
			'--post_title=Reading workshop',
			'--meta_input={"_tala_seo_description":"A reading workshop for teachers."}',
			`--post_content=${ CONTENT }`,
			'--porcelain',
		);
	} );

	test.afterAll( () => {
		wp( 'post', 'delete', id, '--force' );
	} );

	test.describe( 'phone', () => {
		test.use( PHONE );

		test( 'shows the FAQ, the steps, the event and the course accessibly, described in the JSON-LD', async ( { page } ) => {
			await page.goto( `/?page_id=${ id }` );

			const question = page.locator( '.tala-seo-faq summary' );
			await question.click();
			await expect( page.getByText( 'Yes, by 1 November.' ) ).toBeVisible();

			const card = page.locator( '.wp-block-tala-seo-event' );
			await expect( card.getByRole( 'heading', { level: 2, name: 'Reading workshop' } ) ).toBeVisible();
			await expect( card.locator( 'time' ).first() ).toHaveAttribute( 'datetime', /^2026-11-14T09:00:00[+-]\d\d:\d\d$/ );
			await expect( card.getByRole( 'link', { name: 'Get tickets' } ) ).toHaveAttribute( 'href', 'https://tickets.example.com/' );
			const fits = await card.evaluate( ( el ) => el.scrollWidth <= el.clientWidth );
			expect( fits, 'The card fits the phone screen' ).toBe( true );

			const course = page.locator( '#course-teaching-early-reading' );
			await expect( course.getByRole( 'heading', { level: 2, name: 'Teaching early reading' } ) ).toBeVisible();
			await expect( course.getByRole( 'link', { name: 'Enroll' } ) ).toHaveAttribute( 'href', 'https://learn.example.com/' );
			expect( await course.evaluate( ( el ) => el.scrollWidth <= el.clientWidth ), 'The course card fits too' ).toBe( true );

			const types = await page.locator( 'script[type="application/ld+json"]' ).evaluate( ( el ) =>
				JSON.parse( el.textContent )[ '@graph' ].map( ( node ) => node[ '@type' ] ),
			);
			expect( types ).toEqual( expect.arrayContaining( [ 'FAQPage', 'HowTo', 'Event', 'Course' ] ) );

			expect( await axeViolations( page ) ).toEqual( [] );
		} );
	} );

	test( 'the editor opens the page without invalid blocks and previews the event', async ( { page } ) => {
		const logged = [];
		page.on( 'console', ( message ) => logged.push( message.text() ) );
		await logIn( page );
		await openEditor( page, `/wp-admin/post.php?post=${ id }&action=edit` );

		const problems = await page.evaluate( () => {
			const found = [];
			const walk = ( blocks ) =>
				blocks.forEach( ( block ) => {
					if ( ! block.isValid || 'core/missing' === block.name ) {
						found.push( block.name );
					}
					walk( block.innerBlocks );
				} );
			walk( window.wp.data.select( 'core/block-editor' ).getBlocks() );
			return found;
		} );
		expect( problems ).toEqual( [] );

		const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
		await expect( canvas.locator( '.wp-block-tala-seo-event dt' ).first() ).toHaveText( 'When' );
		await expect( canvas.locator( '.wp-block-tala-seo-course dt' ).first() ).toHaveText( 'Offered by' );
		await expect( canvas.locator( '.wp-block-group.tala-seo-faq' ) ).toHaveCount( 1 );
		expect( logged.filter( ( text ) => /^Block (successfully updated|validation)/.test( text ) ) ).toEqual( [] );
	} );
} );

test.describe( 'a new post', () => {
	test.beforeEach( async ( { page } ) => {
		await logIn( page );
		await openEditor( page, '/wp-admin/post-new.php' );
	} );

	test( 'FAQ and How-to are in the inserter, built from core blocks', async ( { page } ) => {
		// The inserter also lists plugins from WordPress.org that match; this test doesn't need them.
		await page.route( /block-directory(\/|%2F)search/, ( route ) => route.fulfill( { json: [] } ) );
		// The inserter stays open after each block it inserts.
		await page.getByRole( 'button', { name: 'Block Inserter', exact: true } ).click();
		const insert = async ( name ) => {
			await page.getByRole( 'searchbox', { name: 'Search' } ).fill( name );
			// The accessible name starts with the icon's character.
			await page.getByRole( 'listbox', { name: 'Blocks' } ).getByRole( 'option', { name: new RegExp( `\\b${ name }$` ) } ).click();
		};
		await insert( 'FAQ' );
		await insert( 'How-to' );

		const blocks = await page.evaluate( () => {
			const shape = ( block ) => [ block.name, block.attributes.className || '', block.innerBlocks.map( shape ) ];
			return window.wp.data.select( 'core/block-editor' ).getBlocks().map( shape );
		} );
		const details = [ 'core/details', '', [ [ 'core/paragraph', '', [] ] ] ];
		const step = [ 'core/list-item', '', [] ];
		expect( blocks ).toEqual( [
			[ 'core/group', 'tala-seo-faq', [ [ 'core/heading', '', [] ], details, details, details ] ],
			[ 'core/group', 'tala-seo-howto', [ [ 'core/heading', '', [] ], [ 'core/paragraph', '', [] ], [ 'core/list', '', [ step, step, step ] ] ] ],
		] );
	} );

	test( 'the Event block asks for its details and previews the card', async ( { page } ) => {
		await page.evaluate( () => {
			const { dispatch } = window.wp.data;
			dispatch( 'core/block-editor' ).insertBlocks( window.wp.blocks.createBlock( 'tala-seo/event' ) );
		} );
		const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
		await expect( canvas.getByText( 'Enter the date, place and tickets in the block settings.' ) ).toBeVisible();

		const sidebar = page.getByRole( 'region', { name: 'Editor settings' } );
		await expect( sidebar.getByText( 'The date it starts' ) ).toBeVisible();
		await sidebar.getByLabel( 'Starts on' ).fill( '2026-11-14' );
		await sidebar.getByLabel( 'Town or city' ).fill( 'Quezon City' );
		await expect( sidebar.getByText( 'For search engines, add:' ) ).toBeHidden();
		await expect( canvas.locator( '.wp-block-tala-seo-event time' ) ).toHaveAttribute( 'datetime', '2026-11-14' );
		await expect( canvas.locator( '.wp-block-tala-seo-event dd' ).nth( 1 ) ).toHaveText( 'Quezon City' );
	} );

	test( 'the Course block asks for a description and previews the card with the title being edited', async ( { page } ) => {
		await page.evaluate( () => {
			const { dispatch } = window.wp.data;
			dispatch( 'core/editor' ).editPost( { title: 'Teaching early reading' } );
			dispatch( 'core/block-editor' ).insertBlocks( window.wp.blocks.createBlock( 'tala-seo/course' ) );
		} );
		const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
		const card = canvas.locator( '.wp-block-tala-seo-course' );
		await expect( card.getByRole( 'heading', { name: 'Teaching early reading' } ), 'Named after the unsaved title' ).toBeVisible();

		const sidebar = page.getByRole( 'region', { name: 'Editor settings' } );
		await expect( sidebar.getByText( 'A description', { exact: true } ) ).toBeVisible();
		await sidebar.getByLabel( 'Description' ).fill( 'Plan phonics lessons.' );
		await expect( sidebar.getByText( 'For search engines, add:' ) ).toBeHidden();
		await expect( card.getByText( 'Plan phonics lessons.' ) ).toBeVisible();
	} );

	test( 'each YouTube video gets a publish date field in the SEO panel', async ( { page } ) => {
		// The editor's embed preview, as WordPress's oEmbed proxy would return it from YouTube.
		await page.route( /oembed(\/|%2F)1\.0(\/|%2F)proxy/, ( route ) =>
			route.fulfill( {
				json: {
					type: 'video',
					provider_name: 'YouTube',
					title: 'Big Buck Bunny',
					html: '<iframe title="Big Buck Bunny" width="500" height="281" src="about:blank"></iframe>',
					thumbnail_url: 'https://i.ytimg.com/vi/aqz-KE-bpKQ/hqdefault.jpg',
				},
			} ),
		);
		await page.evaluate( () => {
			const { dispatch } = window.wp.data;
			dispatch( 'core/block-editor' ).insertBlocks( [
				window.wp.blocks.createBlock( 'core/embed', { url: 'https://youtu.be/aqz-KE-bpKQ', providerNameSlug: 'youtube', type: 'video' } ),
				window.wp.blocks.createBlock( 'core/embed', { url: 'https://www.youtube.com/watch?v=aqz-KE-bpKQ', providerNameSlug: 'youtube', type: 'video' } ),
			] );
			dispatch( 'core/block-editor' ).clearSelectedBlock();
			dispatch( 'core/edit-post' ).openGeneralSidebar( 'edit-post/document' );
			if ( ! window.wp.data.select( 'core/editor' ).isEditorPanelOpened( 'tala-seo/tala-seo' ) ) {
				dispatch( 'core/editor' ).toggleEditorPanelOpened( 'tala-seo/tala-seo' );
			}
		} );

		const panel = page.locator( '.tala-seo-panel' );
		const date = panel.getByLabel( 'Published on YouTube: Big Buck Bunny' );
		await expect( date, 'One field per video, however often it is embedded' ).toHaveCount( 1 );
		await expect( panel.getByText( '1 YouTube video has no publish date' ) ).toBeVisible();

		await date.fill( '2014-11-10' );
		await expect( panel.getByText( 'Every YouTube video has its publish date' ) ).toBeVisible();
		const dates = await page.evaluate( () => window.wp.data.select( 'core/editor' ).getEditedPostAttribute( 'meta' )._tala_seo_video_dates );
		expect( dates ).toEqual( { 'aqz-KE-bpKQ': '2014-11-10' } );
	} );
} );
