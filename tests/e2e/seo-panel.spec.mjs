/**
 * The SEO panel and who may point a post elsewhere. A post's "Redirect to" and canonical fields are
 * for people who publish: a contributor's panel doesn't show them, and their draft still saves
 * (the editor sends every SEO field with a save, those two included). Whoever publishes a post sees
 * both addresses in the checklist.
 */
import { execFileSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';
import { test, expect } from '@playwright/test';
import { wp, logIn, SITE } from './utils.mjs';

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
	} );
} );
