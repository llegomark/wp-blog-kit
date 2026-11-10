/**
 * bin/validate-versions.mjs: a product whose header, version constant, Stable tag or changelog
 * disagree is turned down before release. Each case changes one thing in a copy of the files the
 * check reads. Run with `npm run test:tooling`.
 */
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { cpSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { after, before, test } from 'node:test';
import { fileURLToPath } from 'node:url';

const ROOT = fileURLToPath( new URL( '../..', import.meta.url ) );
const SCRIPT = join( ROOT, 'bin/validate-versions.mjs' );
const FILES = [
	'tala-seo/tala-seo.php',
	'tala-seo/readme.txt',
	'tala-ads/tala-ads.php',
	'tala-ads/readme.txt',
	'tala-guard/tala-guard.php',
	'tala-guard/readme.txt',
	'tala-blog/style.css',
	'tala-blog/readme.txt',
];

let copy = '';

before( () => {
	copy = mkdtempSync( join( tmpdir(), 'kit-versions-' ) );
} );

after( () => {
	rmSync( copy, { recursive: true, force: true } );
} );

/**
 * Runs the check on a fresh copy of the files, after `change` edited one of them.
 *
 * @param {string}                   file   The file to change, or ''.
 * @param {(text: string) => string} change What to do with its text.
 * @return {{status: number, stderr: string}} The check's exit status and what it reported.
 */
function check( file = '', change = ( text ) => text ) {
	for ( const name of FILES ) {
		mkdirSync( dirname( join( copy, name ) ), { recursive: true } );
		cpSync( join( ROOT, name ), join( copy, name ) );
	}
	if ( file ) {
		const original = readFileSync( join( copy, file ), 'utf8' );
		const changed = change( original );
		assert.notEqual( changed, original, `The test changed nothing in ${ file }.` );
		writeFileSync( join( copy, file ), changed );
	}
	const run = spawnSync( process.execPath, [ SCRIPT, '--root', copy ], { encoding: 'utf8' } );
	return { status: run.status, stderr: run.stderr };
}

test( 'the repository as it is passes', () => {
	const run = spawnSync( process.execPath, [ SCRIPT ], { encoding: 'utf8' } );
	assert.equal( run.stderr, '' );
	assert.equal( run.status, 0 );
	assert.equal( check().status, 0, 'And so does an unchanged copy.' );
} );

test( 'a version constant that was not bumped with the header is reported', () => {
	const run = check( 'tala-ads/tala-ads.php', ( text ) => text.replace( /(const TALA_ADS_VERSION = ')[^']+/, '$10.0.1' ) );
	assert.equal( run.status, 1 );
	assert.match( run.stderr, /tala-ads\/tala-ads\.php: TALA_ADS_VERSION is 0\.0\.1, the header's Version is \d/ );
} );

test( 'a missing version constant is reported', () => {
	const run = check( 'tala-guard/tala-guard.php', ( text ) => text.replace( /const TALA_GUARD_VERSION/, 'const TALA_GUARD_RELEASE' ) );
	assert.equal( run.status, 1 );
	assert.match( run.stderr, /tala-guard\/tala-guard\.php: no TALA_GUARD_VERSION constant/ );
} );

test( 'a Stable tag that differs from the header is reported', () => {
	const run = check( 'tala-seo/readme.txt', ( text ) => text.replace( /^Stable tag:.*$/m, 'Stable tag: 0.0.1' ) );
	assert.equal( run.status, 1 );
	assert.match( run.stderr, /tala-seo\/readme\.txt: Stable tag is 0\.0\.1, the header's Version is \d/ );
} );

test( 'a plugin version without a changelog entry is reported', () => {
	const run = check( 'tala-guard/tala-guard.php', ( text ) => text.replace( /(Version:\s+)[\d.]+/, '$19.9.9' ).replace( /(const TALA_GUARD_VERSION = ')[^']+/, '$19.9.9' ) );
	assert.equal( run.status, 1 );
	assert.match( run.stderr, /tala-guard\/readme\.txt: no changelog entry for 9\.9\.9/ );
} );

test( 'a theme version without a changelog entry is reported', () => {
	const run = check( 'tala-blog/style.css', ( text ) => text.replace( /^(Version:\s*)[\d.]+/m, '$19.9.9' ) );
	assert.equal( run.status, 1 );
	assert.match( run.stderr, /tala-blog\/readme\.txt: no changelog entry for 9\.9\.9/ );
} );

test( 'a version that is not a number like 1.2.3 is reported', () => {
	const run = check( 'tala-blog/style.css', ( text ) => text.replace( /^(Version:\s*)[\d.]+/m, '$12.1-beta' ) );
	assert.equal( run.status, 1 );
	assert.match( run.stderr, /tala-blog\/style\.css: Version "2\.1-beta" isn't a version number like 1\.2\.3/ );
} );
