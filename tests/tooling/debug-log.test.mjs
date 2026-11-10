/**
 * The browser tests' look at the site's debug.log (tests/e2e/debug-log.mjs): only what was written
 * during the run counts, and only lines that name the kit.
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { appendFileSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { kitLines, logSize } from '../e2e/debug-log.mjs';

const KIT = '[11-Nov-2026 01:02:03 UTC] PHP Warning:  Undefined variable $x in /home/dev/wp-blog-kit/tala-seo/includes/head.php on line 12';
const EARLY = '[11-Nov-2026 01:02:04 UTC] PHP Notice:  Function _load_textdomain_just_in_time was called incorrectly. Translation loading for the <code>tala-ads</code> domain was triggered too early.';
const CLI = '[11-Nov-2026 01:02:05 UTC] PHP Deprecated:  Implicitly marking parameter $x as nullable is deprecated in phar:///usr/bin/wp/vendor/wp-cli/wp-cli/php/utils.php on line 99';
const OTHER = '[11-Nov-2026 01:02:06 UTC] PHP Warning:  Something in /home/dev/Sites/blogkit/wp-content/plugins/query-monitor/x.php on line 3';

/**
 * A log file in a folder of its own, removed after the test.
 *
 * @param {import('node:test').TestContext} t     Test.
 * @param {string[]}                        lines Lines to start with.
 * @return {string} Path.
 */
function logWith( t, lines ) {
	const dir = mkdtempSync( join( tmpdir(), 'kit-debug-log-' ) );
	t.after( () => rmSync( dir, { recursive: true, force: true } ) );
	const file = join( dir, 'debug.log' );
	writeFileSync( file, lines.map( ( line ) => line + '\n' ).join( '' ) );
	return file;
}

test( 'a site without a log has nothing to report', () => {
	const missing = join( tmpdir(), 'kit-debug-log-none', 'debug.log' );
	assert.equal( logSize( missing ), 0 );
	assert.deepEqual( kitLines( missing, 0 ), [] );
} );

test( 'lines written during the run that name the kit are reported', ( t ) => {
	const file = logWith( t, [ KIT ] );
	const from = logSize( file );
	assert.ok( from > 0 );
	assert.deepEqual( kitLines( file, from ), [], 'What was there before the run is not the run\'s.' );

	appendFileSync( file, [ CLI, OTHER, EARLY, KIT.replace( 'tala-seo', 'tala-blog' ), '' ].join( '\n' ) );
	assert.deepEqual( kitLines( file, from ), [ EARLY, KIT.replace( 'tala-seo', 'tala-blog' ) ], 'WP-CLI\'s own notices and other plugins\' are not the kit\'s.' );
} );

test( 'a log that was emptied during the run is read from its start', ( t ) => {
	const file = logWith( t, [ OTHER, OTHER, OTHER ] );
	const from = logSize( file );
	writeFileSync( file, KIT + '\n' );
	assert.deepEqual( kitLines( file, from ), [ KIT ] );
} );

test( 'a kit function\'s name counts as naming the kit', ( t ) => {
	const line = '[11-Nov-2026 01:02:07 UTC] PHP Fatal error:  Uncaught TypeError: tala_guard_keys(): Return value must be of type array';
	const file = logWith( t, [ line ] );
	assert.deepEqual( kitLines( file, 0 ), [ line ] );
} );
