#!/usr/bin/env node
/**
 * The notes of a GitHub release of the theme or the plugin (.github/workflows/release.yml), from
 * its own files: the version and requirements from its header, the changelog entry and upgrade
 * notice of that version from its readme.txt, then how to install it and check the download.
 *
 *   node bin/release-notes.mjs tala-seo [sha256-of-the-zip]
 *   node bin/release-notes.mjs tala-blog [sha256-of-the-zip]
 *
 * Prints Markdown. Exits 1 when the readme has no changelog entry for the version.
 */

import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const ROOT = fileURLToPath( new URL( '..', import.meta.url ) );
const PRODUCTS = {
	'tala-seo': { name: 'Tala SEO', header: 'tala-seo/tala-seo.php', kind: 'plugin' },
	'tala-blog': { name: 'Tala Blog', header: 'tala-blog/style.css', kind: 'theme' },
};

const [ slug, sha256 = '' ] = process.argv.slice( 2 );
const product = PRODUCTS[ slug ];
if ( ! product ) {
	process.stderr.write( `Usage: node bin/release-notes.mjs <${ Object.keys( PRODUCTS ).join( '|' ) }> [sha256]\n` );
	process.exit( 2 );
}

const read = ( file ) => readFileSync( `${ ROOT }/${ file }`, 'utf8' ).replace( /\r\n?/g, '\n' );
const header = ( field ) => read( product.header ).match( new RegExp( `^[ \\t/*#@]*${ field }:[ \\t]*(.+?)[ \\t]*$`, 'm' ) )?.[ 1 ] ?? '';

/**
 * The text under "= <version> =" in a readme section, or ''.
 *
 * @param {string} readme  Readme text.
 * @param {string} section Section title ("Changelog").
 * @param {string} version Version.
 * @return {string} Text.
 */
function entry( readme, section, version ) {
	const body = readme.split( /^==\s*/m ).find( ( part ) => part.toLowerCase().startsWith( `${ section.toLowerCase() } ==` ) ) ?? '';
	const items = body.split( /^=\s*/m ).slice( 1 );
	const item = items.find( ( text ) => text.startsWith( `${ version } =` ) );
	return item ? item.slice( item.indexOf( '\n' ) + 1 ).trim() : '';
}

const version = header( 'Version' );
const readme = read( `${ slug }/readme.txt` );
const changes = entry( readme, 'Changelog', version );
if ( ! changes ) {
	process.stderr.write( `${ slug }/readme.txt has no changelog entry for ${ version }.\n` );
	process.exit( 1 );
}
const notice = entry( readme, 'Upgrade Notice', version );
const repository = process.env.GITHUB_REPOSITORY || 'llegomark/wp-blog-kit';
const zip = `${ slug }-${ version }.zip`;
const url = `https://github.com/${ repository }/releases/download/${ slug }-${ version }/${ zip }`;
const where = 'plugin' === product.kind ? 'Plugins → Add New Plugin → Upload Plugin' : 'Appearance → Themes → Add New Theme → Upload Theme';

const notes = [
	`## ${ product.name } ${ version }`,
	'',
	changes,
	...( notice ? [ '', `**Upgrade notice:** ${ notice }` ] : [] ),
	'',
	'### Install or update',
	'',
	`Download **${ zip }** below (not the "Source code" archives, which hold the whole repository). In WordPress, go to ${ where }, choose the zip, and install it; to update, choose "Replace current with uploaded". Or with WP-CLI:`,
	'',
	'```',
	`wp ${ product.kind } install ${ url } --force`,
	'```',
	'',
	`Requires WordPress ${ header( 'Requires at least' ) } or newer and PHP ${ header( 'Requires PHP' ) } or newer.`,
	'',
	'### Check the download',
	'',
	...( sha256 ? [ `SHA-256: \`${ sha256 }\` (also in ${ zip }.sha256).`, '' ] : [] ),
	`The zip carries a build attestation: \`gh attestation verify ${ zip } --repo ${ repository } --signer-workflow ${ repository }/.github/workflows/release.yml --source-ref refs/heads/main\` shows it was built by this repository's release workflow from the tagged commit on main.`,
];
process.stdout.write( notes.join( '\n' ) + '\n' );
