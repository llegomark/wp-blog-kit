/**
 * Tala SEO under the real WP-CLI, on the local site. The PHPUnit tests run the plugin's command
 * against a stand-in for WP-CLI, with the command's file always loaded; here WP-CLI itself starts
 * WordPress, and the plugin decides what to load, as on a server: the command exists, and the
 * publish check holds for posts made or changed from the command line.
 */
import { test, expect } from '@playwright/test';
import { wp, wpRun } from './utils.mjs';

const created = [];

/**
 * Creates a post with WP-CLI and remembers it for the clean-up.
 *
 * @param {...string} args Arguments of `wp post create`.
 * @return {{id: string, stderr: string}} The post's ID and what WP-CLI warned about.
 */
function createPost( ...args ) {
	const result = wpRun( 'post', 'create', ...args, '--porcelain' );
	expect( result.status, result.stderr ).toBe( 0 );
	created.push( result.stdout );
	return { id: result.stdout, stderr: result.stderr };
}

const status = ( id ) => wp( 'post', 'get', id, '--field=post_status' );

/** A featured image of the demo content: it has alternative text. */
const image = () => wp( 'post', 'meta', 'get', wp( 'post', 'list', '--post_type=post', '--name=keeping-school-forms-organized-all-year', '--field=ID' ), '_thumbnail_id' );

test.afterAll( () => {
	if ( created.length ) {
		wp( 'post', 'delete', ...created, '--force' );
	}
} );

test( 'a post published from the command line without the essentials stays a draft, and WP-CLI says why', () => {
	const { id, stderr } = createPost( '--post_title=Published from the command line', '--post_status=publish' );
	expect( status( id ) ).toBe( 'draft' );
	expect( stderr ).toContain( 'Warning: Kept as a draft. Add a meta description' );
	expect( stderr ).toContain( 'Set a featured image.' );
} );

test( 'with a description and a described featured image it is published', () => {
	const { id, stderr } = createPost(
		'--post_title=Ready from the command line',
		'--post_status=publish',
		`--meta_input=${ JSON.stringify( { _tala_seo_description: 'What this post is about.', _thumbnail_id: image() } ) }`,
	);
	expect( stderr ).not.toContain( 'Kept as a draft.' );
	expect( status( id ) ).toBe( 'publish' );
} );

test( 'a draft can\'t be scheduled from the command line without the essentials', () => {
	const { id } = createPost( '--post_title=Scheduled from the command line', '--post_status=draft' );
	const date = `${ new Date().getFullYear() + 1 }-01-15 08:00:00`;
	const result = wpRun( 'post', 'update', id, '--post_status=future', `--post_date=${ date }`, `--post_date_gmt=${ date }` );
	expect( result.stderr ).toContain( 'Warning: Kept as a draft.' );
	expect( status( id ) ).toBe( 'draft' );
} );

test( 'wp tala-seo audit lists a live post that is missing something, and not a complete one', () => {
	// A post that went live before the plugin was there: made with the publish check's requirements off.
	const incomplete = wp(
		'eval',
		`add_filter( 'tala_seo_publish_requirements', '__return_empty_array' );
		echo wp_insert_post( array( 'post_title' => 'Live without a description', 'post_status' => 'publish', 'post_content' => '' ) );`,
	);
	created.push( incomplete );
	const complete = createPost(
		'--post_title=Live and complete',
		'--post_status=publish',
		`--meta_input=${ JSON.stringify( { _tala_seo_description: 'What this post is about.', _thumbnail_id: image() } ) }`,
	).id;
	expect( status( incomplete ) ).toBe( 'publish' );

	const result = wpRun( 'tala-seo', 'audit', '--format=json' );
	expect( result.status, result.stderr ).toBe( 0 );
	const rows = JSON.parse( result.stdout );
	const row = rows.find( ( item ) => String( item.ID ) === incomplete );
	expect( row, 'The incomplete post is listed' ).toBeTruthy();
	expect( row.missing ).toContain( 'description' );
	expect( row.missing ).toContain( 'featured_image' );
	expect( rows.some( ( item ) => String( item.ID ) === complete ), 'The complete post is not' ).toBe( false );
} );

test( 'the other commands run: describe and import as dry runs, and the PDF map', () => {
	const describe = wpRun( 'tala-seo', 'describe', '--dry-run' );
	expect( describe.status, describe.stderr ).toBe( 0 );
	expect( describe.stdout ).toContain( 'Dry run, nothing changed.' );

	const copied = wpRun( 'tala-seo', 'import', '--dry-run' );
	expect( copied.status, copied.stderr ).toBe( 0 );
	expect( copied.stdout ).toContain( 'Dry run, nothing changed.' );

	const map = wpRun( 'tala-seo', 'pdf-canonicals' );
	expect( map.status, map.stderr ).toBe( 0 );
	expect( map.stdout ).toContain( 'map $uri $tala_seo_pdf_canonical {' );
} );
