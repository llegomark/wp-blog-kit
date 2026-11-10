/**
 * The SEO panel in the block editor, and the checklist shown on Publish.
 *
 * What the panel shows while writing: the length meters (Google cuts by width), the preview of the
 * search result, what is still needed to publish and what Google recommends, the primary category,
 * and the switches. The server enforces the publish check (PHPUnit covers that); these tests cover
 * what the editor tells the person in front of it.
 *
 * And who may point a post elsewhere. A post's "Redirect to" and canonical fields are for people who
 * publish: a contributor's panel doesn't show them, and their draft still saves (the editor sends
 * every SEO field with a save, those two included). Whoever publishes a post sees both addresses in
 * the checklist.
 */
import { execFileSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';
import { test, expect } from '@playwright/test';
import { wp, logIn, SITE } from './utils.mjs';

/** A 900 px wide image of the kit's demo media, narrower than Google Discover asks for. */
const SMALL_IMAGE = new URL( '../../dev/media/small.jpg', import.meta.url ).pathname;

const CONTRIBUTOR = 'kit-contributor';
const PASSWORD = randomUUID();

/**
 * Opens the block editor and its SEO panel.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @param {string}                          path Editor address.
 * @return {Promise<import('@playwright/test').Locator>} The SEO panel.
 */
async function openSeoPanel( page, path ) {
	await page.goto( path );
	await page.waitForFunction( () => window.wp?.data?.select( 'core/editor' )?.getCurrentPostId() );
	await page.evaluate( () => {
		const { dispatch, select } = window.wp.data;
		dispatch( 'core/preferences' ).set( 'core/edit-post', 'welcomeGuide', false );
		dispatch( 'core/edit-post' ).openGeneralSidebar( 'edit-post/document' );
		if ( ! select( 'core/editor' ).isEditorPanelOpened( 'tala-seo/tala-seo' ) ) {
			dispatch( 'core/editor' ).toggleEditorPanelOpened( 'tala-seo/tala-seo' );
		}
	} );
	const panel = page.locator( '.tala-seo-panel' );
	await expect( panel.getByLabel( /^Meta description/ ) ).toBeVisible();
	return panel;
}

test.describe( 'who may set a redirect or a canonical URL', () => {
	let draft = '';

	test.beforeAll( () => {
		wp( 'user', 'create', CONTRIBUTOR, `${ CONTRIBUTOR }@example.com`, '--role=contributor', '--porcelain' );
		// The password reaches WordPress on standard input, not on a command line.
		execFileSync(
			'wp',
			[ `--path=${ SITE }`, 'eval', `wp_set_password( trim( (string) fgets( STDIN ) ), get_user_by( 'login', '${ CONTRIBUTOR }' )->ID );` ],
			{ input: `${ PASSWORD }\n`, stdio: [ 'pipe', 'ignore', 'ignore' ] },
		);
		draft = wp(
			'post',
			'create',
			'--post_status=draft',
			'--post_title=A guest article first published elsewhere',
			'--meta_input={"_tala_seo_redirect":"https://example.com/merged-guide/","_tala_seo_canonical":"https://example.com/first-published-here/"}',
			'--porcelain',
		);
	} );

	test.afterAll( () => {
		wp( 'post', 'delete', draft, '--force' );
		// Without --reassign, the contributor's own posts go too.
		wp( 'user', 'delete', CONTRIBUTOR, '--yes' );
	} );

	test( 'a contributor has no redirect or canonical field, and their draft saves with its description', async ( { page } ) => {
		await page.goto( '/wp-login.php' );
		await page.locator( '#user_login' ).fill( CONTRIBUTOR );
		await page.locator( '#user_pass' ).fill( PASSWORD );
		await Promise.all( [ page.waitForURL( /wp-admin/ ), page.locator( '#wp-submit' ).click() ] );

		const panel = await openSeoPanel( page, '/wp-admin/post-new.php' );
		await expect( panel.getByLabel( /Canonical URL/ ) ).toHaveCount( 0 );
		await expect( panel.getByLabel( /Redirect to/ ) ).toHaveCount( 0 );
		await expect( panel.getByLabel( 'Hide from search engines' ), 'The other fields are there' ).toBeVisible();

		await panel.getByLabel( /^Meta description/ ).fill( 'What the guest article is about.' );
		const saved = await page.evaluate( async () => {
			const { dispatch, select } = window.wp.data;
			dispatch( 'core/editor' ).editPost( { title: 'Written by a contributor' } );
			await dispatch( 'core/editor' ).savePost();
			return {
				id: select( 'core/editor' ).getCurrentPostId(),
				failed: select( 'core/editor' ).didPostSaveRequestFail(),
				errors: select( 'core/notices' )
					.getNotices()
					.filter( ( notice ) => 'error' === notice.status )
					.map( ( notice ) => notice.content ),
			};
		} );
		expect( saved.errors ).toEqual( [] );
		expect( saved.failed ).toBe( false );
		expect( wp( 'post', 'meta', 'get', String( saved.id ), '_tala_seo_description' ) ).toBe( 'What the guest article is about.' );
		expect( wp( 'post', 'get', String( saved.id ), '--field=post_title' ) ).toBe( 'Written by a contributor' );
	} );

	test( 'whoever publishes sees where a post redirects and which address it names as the original', async ( { page } ) => {
		await logIn( page );
		const panel = await openSeoPanel( page, `/wp-admin/post.php?post=${ draft }&action=edit` );
		await expect( panel.getByLabel( /Canonical URL/ ) ).toHaveValue( 'https://example.com/first-published-here/' );
		await expect( panel.getByLabel( /Redirect to/ ) ).toHaveValue( 'https://example.com/merged-guide/' );
		await expect( panel.getByText( 'This post redirects to https://example.com/merged-guide/, so visitors won\'t see it' ) ).toBeVisible();
		await expect( panel.getByText( 'Search engines are told the original is at https://example.com/first-published-here/ (canonical URL), not here' ) ).toBeVisible();

		// The same list is shown on Publish, where a reviewer who never opens the panel meets it.
		await page.getByRole( 'region', { name: 'Editor top bar' } ).getByRole( 'button', { name: 'Publish', exact: true } ).click();
		const prePublish = page.locator( '.tala-seo-prepublish' );
		await expect( prePublish.getByText( /This post redirects to https:\/\/example\.com\/merged-guide\// ) ).toBeVisible();
		await expect( prePublish.getByText( /the original is at https:\/\/example\.com\/first-published-here\// ) ).toBeVisible();

		// A canonical that names the post's own address is no news.
		await panel.getByLabel( /Canonical URL/ ).fill( await page.evaluate( () => window.wp.data.select( 'core/editor' ).getPermalink() ) );
		await expect( panel.getByText( /Search engines are told the original is at/ ) ).toHaveCount( 0 );

		// Nor is the same address with its letters written as escapes, in capitals or not.
		await page.evaluate( () => window.wp.data.dispatch( 'core/editor' ).editPost( { slug: 'ガイド' } ) );
		const own = await page.evaluate( () => window.wp.data.select( 'core/editor' ).getPermalink() );
		expect( decodeURI( own ) ).toContain( '/ガイド' );
		await panel.getByLabel( /Canonical URL/ ).fill( 'https://example.com/another-guide/' );
		await expect( panel.getByText( /Search engines are told the original is at/ ) ).toHaveCount( 1 );
		for ( const spelling of [ encodeURI( decodeURI( own ) ), encodeURI( decodeURI( own ) ).toLowerCase(), decodeURI( own ) ] ) {
			await panel.getByLabel( /Canonical URL/ ).fill( spelling );
			await expect( panel.getByText( /Search engines are told the original is at/ ), spelling ).toHaveCount( 0 );
		}
	} );
} );

test.describe( 'the SEO panel while writing', () => {
	const posts = [];
	let wide = '';
	let narrow = '';
	let siteName = '';

	/**
	 * A draft made with WP-CLI, deleted after the tests.
	 *
	 * @param {...string} args More arguments for `wp post create`.
	 * @return {string} Post ID.
	 */
	function draft( ...args ) {
		const id = wp( 'post', 'create', '--post_status=draft', '--post_title=Marking a quiz', ...args, '--porcelain' );
		posts.push( id );
		return id;
	}

	const edit = ( id ) => `/wp-admin/post.php?post=${ id }&action=edit`;

	/**
	 * Changes the post in the editor, as its own controls do.
	 *
	 * @param {import('@playwright/test').Page} page  Page.
	 * @param {Object}                          edits Edited attributes.
	 */
	const editPost = ( page, edits ) => page.evaluate( ( changes ) => window.wp.data.dispatch( 'core/editor' ).editPost( changes ), edits );

	/**
	 * Saves the post and returns the errors the editor shows.
	 *
	 * @param {import('@playwright/test').Page} page Page.
	 * @return {Promise<string[]>} Error notices.
	 */
	const save = ( page ) =>
		page.evaluate( async () => {
			const { dispatch, select } = window.wp.data;
			await dispatch( 'core/editor' ).savePost();
			return select( 'core/notices' )
				.getNotices()
				.filter( ( notice ) => 'error' === notice.status )
				.map( ( notice ) => notice.content );
		} );

	test.beforeAll( () => {
		siteName = wp( 'option', 'get', 'blogname' );
		// A demo article's featured image: 1600 px wide, with alternative text.
		wide = wp( 'post', 'meta', 'get', wp( 'post', 'list', '--post_type=post', '--name=keeping-school-forms-organized-all-year', '--field=ID' ), '_thumbnail_id' );
		narrow = wp( 'media', 'import', SMALL_IMAGE, '--title=A narrow image without alternative text', '--porcelain' );
	} );

	test.beforeEach( async ( { page } ) => {
		await logIn( page );
	} );

	test.afterAll( () => {
		if ( posts.length ) {
			wp( 'post', 'delete', ...posts, '--force' );
		}
		wp( 'post', 'delete', narrow, '--force' );
	} );

	test( 'the length meters judge the title and the description by their width in Google', async ( { page } ) => {
		const panel = await openSeoPanel( page, edit( draft() ) );
		const description = panel.getByLabel( /^Meta description/ );
		// Each field with its help text, where the verdict is.
		const descriptionHelp = panel.locator( '.components-base-control', { has: page.getByLabel( /^Meta description/ ) } );
		const titleHelp = panel.locator( '.components-base-control', { has: page.getByLabel( 'SEO title' ) } );

		await expect( descriptionHelp.locator( '.tala-seo-meter' ), 'No verdict on an empty description' ).toHaveCount( 0 );
		await expect( descriptionHelp.getByText( '0 characters.' ) ).toBeVisible();

		await description.fill( 'S' );
		await expect( descriptionHelp.getByText( '1 character.' ) ).toBeVisible();
		await description.fill( 'Short.' );
		await expect( descriptionHelp.getByText( '6 characters.' ) ).toBeVisible();
		await expect( descriptionHelp.locator( '.tala-seo-meter.is-bad' ) ).toContainText( 'Very short: this description says little about the page' );

		await description.fill( 'How to mark a quiz in ten minutes, step by step, with a rubric.' );
		await expect( descriptionHelp.locator( '.tala-seo-meter.is-okay' ) ).toContainText( 'A little short: this description has room for more' );

		await description.fill( 'How to mark a class quiz in ten minutes: a rubric with three levels, a tally sheet, and what to tell learners.' );
		await expect( descriptionHelp.locator( '.tala-seo-meter.is-good' ) ).toContainText( 'Good length: this description fits in Google' );
		// The meter is a picture of the same verdict: screen readers get the sentence, not the bar.
		await expect( descriptionHelp.locator( '.tala-seo-meter meter' ) ).toHaveAttribute( 'aria-hidden', 'true' );

		await description.fill( 'How to mark a class quiz in ten minutes with a rubric, a tally sheet and a note home. '.repeat( 5 ) );
		await expect( descriptionHelp.locator( '.tala-seo-meter.is-bad' ) ).toContainText( 'Too long: Google will cut this description' );

		// The title is measured as Google shows it: with the separator and the site's name.
		await expect( titleHelp.locator( '.tala-seo-meter' ), 'The post title already counts' ).toHaveCount( 1 );
		await panel.getByLabel( 'SEO title' ).fill( 'Q' );
		await expect( titleHelp.locator( '.tala-seo-meter.is-bad' ) ).toContainText( 'Very short: this title says little about the page' );
		await panel.getByLabel( 'SEO title' ).fill( 'How to mark a class quiz in ten minutes with a three-level rubric, a tally sheet and a short note home' );
		await expect( titleHelp.locator( '.tala-seo-meter.is-bad' ) ).toContainText( 'Too long: Google will cut this title' );
		await panel.getByLabel( 'SEO title' ).fill( 'How to mark a quiz in ten minutes' );
		await expect( titleHelp.locator( '.tala-seo-meter.is-good' ) ).toContainText( 'Good length: this title fits in Google' );
	} );

	test( 'the preview shows the result as Google would: site, address, title and description', async ( { page } ) => {
		const article = wp( 'post', 'list', '--post_type=post', '--name=keeping-school-forms-organized-all-year', '--field=ID' );
		const panel = await openSeoPanel( page, edit( article ) );
		const preview = panel.getByRole( 'img', { name: 'Preview of the Google search result' } );
		await expect( preview.locator( '.tala-seo-preview__site' ) ).toHaveText( siteName );
		await expect( preview.locator( '.tala-seo-preview__url' ) ).toHaveText( /^127\.0\.0\.1:8080 › (.+ › )?keeping-school-forms-organized-all-year$/ );

		// The post's own title until an SEO title is given, always with the separator and the site's name.
		await panel.getByLabel( 'SEO title' ).fill( '' );
		await expect( preview.locator( '.tala-seo-preview__title' ) ).toHaveText( `${ wp( 'post', 'get', article, '--field=post_title' ) } | ${ siteName }` );
		await panel.getByLabel( 'SEO title' ).fill( 'School forms, sorted' );
		await expect( preview.locator( '.tala-seo-preview__title' ) ).toHaveText( `School forms, sorted | ${ siteName }` );

		await panel.getByLabel( /^Meta description/ ).fill( '' );
		await expect( preview.locator( '.tala-seo-preview__description.is-empty' ) ).toHaveText( 'Add a meta description to choose the text shown here.' );

		// A description wider than Google shows is cut after a whole word, with an ellipsis.
		const long = 'One folder per form, one label per folder, and a checklist on the cabinet door keep the paperwork findable. '.repeat( 4 ).trim();
		await panel.getByLabel( /^Meta description/ ).fill( long );
		const shown = await preview.locator( '.tala-seo-preview__description' ).innerText();
		expect( shown.endsWith( ' …' ) ).toBe( true );
		const kept = shown.slice( 0, -2 );
		expect( kept.length ).toBeLessThan( long.length );
		expect( long.startsWith( kept ) ).toBe( true );
		expect( /[\s,;:.]/.test( long.charAt( kept.length ) ), `Cut at the end of a word: "${ kept.slice( -20 ) }"` ).toBe( true );
	} );

	test( 'what is needed to publish is listed, and each item turns to done as it is filled', async ( { page } ) => {
		const id = draft();
		const panel = await openSeoPanel( page, edit( id ) );
		const needed = panel.locator( '.tala-seo-checklist' ).first();
		await expect( panel.locator( '.tala-seo-checklist__title' ).first() ).toHaveText( 'Needed to publish' );
		await expect( needed.locator( 'li.is-missing', { hasText: 'Add a meta description' } ) ).toHaveCount( 1 );
		await expect( needed.locator( 'li.is-missing', { hasText: 'Set a featured image' } ) ).toHaveCount( 1 );
		await expect( needed.locator( 'li.is-missing .tala-seo-checklist__mark' ).first(), 'A mark as well as a colour' ).toHaveText( '✕' );
		await expect( panel.getByLabel( 'Meta description (required)' ) ).toBeVisible();

		await panel.getByLabel( /^Meta description/ ).fill( 'How to mark a quiz in ten minutes.' );
		await expect( needed.locator( 'li.is-ok', { hasText: 'Meta description added' } ) ).toHaveCount( 1 );

		// An image without alternative text is a featured image, but not yet enough.
		await editPost( page, { featured_media: Number( narrow ) } );
		await expect( needed.locator( 'li.is-ok', { hasText: 'Featured image set' } ) ).toHaveCount( 1 );
		await expect( needed.locator( 'li.is-missing', { hasText: 'Describe the featured image in its "Alternative text" field' } ) ).toHaveCount( 1 );

		await editPost( page, { featured_media: Number( wide ) } );
		await expect( needed.locator( 'li.is-ok', { hasText: 'Featured image has alternative text' } ) ).toHaveCount( 1 );
		await expect( needed.locator( 'li.is-missing' ) ).toHaveCount( 0 );
		await expect( needed.locator( 'li.is-ok .tala-seo-checklist__mark' ).first() ).toHaveText( '✓' );
	} );

	test( 'pressing Publish on an unfinished post says it is not ready, and the server\'s refusal is shown', async ( { page } ) => {
		const id = draft();
		await openSeoPanel( page, edit( id ) );
		await page.getByRole( 'region', { name: 'Editor top bar' } ).getByRole( 'button', { name: 'Publish', exact: true } ).click();
		const prePublish = page.locator( '.tala-seo-prepublish' );
		await expect( prePublish.getByRole( 'button', { name: 'SEO: not ready to publish' } ) ).toBeVisible();
		await expect( prePublish ).toHaveClass( /is-blocked/ );
		await expect( prePublish.getByText( 'Publishing will be refused until the items marked ✕ are done.' ) ).toBeVisible();
		await expect( prePublish.locator( 'li.is-missing', { hasText: 'Add a meta description' } ) ).toHaveCount( 1 );

		// Publishing anyway: the server refuses, the editor says why, and the post stays a draft.
		await page.getByRole( 'region', { name: 'Editor publish' } ).getByRole( 'button', { name: 'Publish', exact: true } ).click();
		await expect( page.getByText( /To publish:.*Add a meta description/ ).first() ).toBeVisible();
		expect( wp( 'post', 'get', id, '--field=post_status' ) ).toBe( 'draft' );

		// With everything there, the same panel says so.
		await editPost( page, { featured_media: Number( wide ), meta: { _tala_seo_description: 'How to mark a quiz in ten minutes.' } } );
		await expect( prePublish.getByRole( 'button', { name: 'SEO: ready to publish' } ) ).toBeVisible();
		await expect( prePublish ).not.toHaveClass( /is-blocked/ );
		await expect( prePublish.getByText( 'Publishing will be refused' ) ).toHaveCount( 0 );
	} );

	test( 'Google\'s recommendations follow the post: image width, category, images without alternative text', async ( { page } ) => {
		const panel = await openSeoPanel( page, edit( draft() ) );
		const advice = panel.locator( '.tala-seo-checklist' ).nth( 1 );
		await expect( panel.locator( '.tala-seo-checklist__title' ).nth( 1 ) ).toHaveText( 'Recommended by Google' );

		await expect( advice.locator( 'li.is-advice', { hasText: 'Choose a category other than the default one' } ) ).toHaveCount( 1 );
		await expect( advice.locator( 'li.is-advice .tala-seo-checklist__mark' ).first(), 'Advice is marked differently from what is missing' ).toHaveText( '!' );
		await expect( advice.locator( 'li.is-ok', { hasText: 'Every image in the article has alternative text' } ) ).toHaveCount( 1 );
		await expect( advice.getByText( /Featured image is/ ), 'Nothing about the image\'s width before there is one' ).toHaveCount( 0 );

		const category = Number( wp( 'term', 'list', 'category', '--slug=classroom-guides', '--field=term_id' ) );
		await editPost( page, { categories: [ category ] } );
		await expect( advice.locator( 'li.is-ok', { hasText: 'Filed under a category' } ) ).toHaveCount( 1 );

		await editPost( page, { featured_media: Number( narrow ) } );
		await expect( advice.locator( 'li.is-advice', { hasText: 'Featured image is 900 px wide; Google Discover asks for at least 1200 px' } ) ).toHaveCount( 1 );
		await editPost( page, { featured_media: Number( wide ) } );
		await expect( advice.locator( 'li.is-ok', { hasText: 'Featured image is at least 1200 px wide (Google Discover)' } ) ).toHaveCount( 1 );

		await page.evaluate( () => {
			const { createBlock } = window.wp.blocks;
			window.wp.data.dispatch( 'core/block-editor' ).insertBlocks( [
				createBlock( 'core/image', { url: 'http://127.0.0.1:8080/no-alt-1.jpg' } ),
				createBlock( 'core/group', {}, [ createBlock( 'core/image', { url: 'http://127.0.0.1:8080/no-alt-2.jpg', alt: '  ' } ) ] ),
				createBlock( 'core/image', { url: 'http://127.0.0.1:8080/described.jpg', alt: 'A marked quiz' } ),
			] );
			window.wp.data.dispatch( 'core/block-editor' ).clearSelectedBlock();
			window.wp.data.dispatch( 'core/edit-post' ).openGeneralSidebar( 'edit-post/document' );
		} );
		await expect( advice.locator( 'li.is-advice', { hasText: '2 images in the article have no alternative text' } ) ).toHaveCount( 1 );
	} );

	test( 'a hidden site is said in the checklist, where the editor shows no admin notice', async ( { page } ) => {
		const id = draft();
		wp( 'option', 'update', 'blog_public', '0' );
		try {
			const panel = await openSeoPanel( page, edit( id ) );
			await expect( panel.locator( 'li.is-advice', { hasText: 'The whole site is hidden from search engines (Settings → Reading)' } ) ).toHaveCount( 1 );
		} finally {
			wp( 'option', 'update', 'blog_public', '1' );
		}
		const panel = await openSeoPanel( page, edit( id ) );
		await expect( panel.getByText( 'The whole site is hidden from search engines' ) ).toHaveCount( 0 );
	} );

	test( 'the primary category is offered once a post has two, and the choice is saved', async ( { page } ) => {
		const id = draft();
		const guides = Number( wp( 'term', 'list', 'category', '--slug=classroom-guides', '--field=term_id' ) );
		const planning = Number( wp( 'term', 'list', 'category', '--slug=lesson-planning', '--field=term_id' ) );
		const panel = await openSeoPanel( page, edit( id ) );

		await editPost( page, { categories: [ guides ] } );
		await expect( panel.getByLabel( /^Meta description/ ) ).toBeVisible();
		await expect( panel.getByLabel( 'Primary category' ), 'Nothing to choose with one category' ).toHaveCount( 0 );

		await editPost( page, { categories: [ guides, planning ] } );
		const select = panel.getByLabel( 'Primary category' );
		await expect( select ).toBeVisible();
		await expect( select.locator( 'option' ) ).toHaveText( [ 'Automatic (the first category)', 'Classroom guides', 'Lesson planning' ], { ignoreCase: false } );
		await expect( select ).toHaveValue( '0' );

		await select.selectOption( { label: 'Lesson planning' } );
		expect( await save( page ) ).toEqual( [] );
		expect( wp( 'post', 'meta', 'get', id, '_tala_seo_primary_category' ) ).toBe( String( planning ) );

		// A category that is taken off the post is no longer its primary one.
		await editPost( page, { categories: [ guides, Number( wp( 'option', 'get', 'default_category' ) ) ] } );
		await expect( panel.getByLabel( 'Primary category' ) ).toHaveValue( '0' );
	} );

	test( 'the switches: "minor edit" only on a live post, where it keeps the Updated date; "hide" is saved', async ( { page } ) => {
		const id = draft();
		let panel = await openSeoPanel( page, edit( id ) );
		await expect( panel.getByLabel( 'Minor edit: keep the "Updated" date' ), 'A draft has no Updated date to keep' ).toHaveCount( 0 );
		await panel.getByLabel( 'Hide from search engines' ).check();
		expect( await save( page ) ).toEqual( [] );
		expect( wp( 'post', 'meta', 'get', id, '_tala_seo_noindex' ) ).toBe( '1' );

		// A post that went live last year.
		const live = wp(
			'post',
			'create',
			'--post_status=publish',
			'--post_title=Published last year',
			`--post_date=${ new Date().getFullYear() - 1 }-03-01 08:00:00`,
			`--meta_input=${ JSON.stringify( { _tala_seo_description: 'What this post is about.', _thumbnail_id: wide } ) }`,
			'--porcelain',
		);
		posts.push( live );
		wp( 'eval', `global $wpdb; $wpdb->update( $wpdb->posts, array( 'post_modified' => '${ new Date().getFullYear() - 1 }-03-02 09:00:00', 'post_modified_gmt' => '${ new Date().getFullYear() - 1 }-03-02 09:00:00' ), array( 'ID' => ${ Number( live ) } ) ); clean_post_cache( ${ Number( live ) } );` );
		const updated = wp( 'post', 'get', live, '--field=post_modified' );

		panel = await openSeoPanel( page, edit( live ) );
		await panel.getByLabel( 'Minor edit: keep the "Updated" date' ).check();
		await editPost( page, { title: 'Published last year, with a typo fixed' } );
		expect( await save( page ) ).toEqual( [] );
		expect( wp( 'post', 'get', live, '--field=post_title' ) ).toBe( 'Published last year, with a typo fixed' );
		expect( wp( 'post', 'get', live, '--field=post_modified' ), 'The Updated date is kept' ).toBe( updated );

		// Without the switch, an edit is an update.
		await page.reload();
		await page.waitForFunction( () => window.wp?.data?.select( 'core/editor' )?.getCurrentPostId() );
		await editPost( page, { title: 'Published last year, rewritten' } );
		expect( await save( page ) ).toEqual( [] );
		expect( wp( 'post', 'get', live, '--field=post_modified' ) ).not.toBe( updated );
	} );

	test( 'a page needs its description only, and gets no advice about categories', async ( { page } ) => {
		const id = draft( '--post_type=page' );
		const panel = await openSeoPanel( page, edit( id ) );
		await expect( panel.getByLabel( 'Meta description (required)' ) ).toBeVisible();
		const needed = panel.locator( '.tala-seo-checklist' ).first();
		await expect( needed.locator( 'li' ) ).toHaveCount( 1 );
		await expect( needed.locator( 'li.is-missing', { hasText: 'Add a meta description' } ) ).toHaveCount( 1 );
		await expect( panel.getByText( /category/i ) ).toHaveCount( 0 );
		await expect( panel.getByLabel( 'Primary category' ) ).toHaveCount( 0 );
	} );
} );
