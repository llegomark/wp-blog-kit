#!/usr/bin/env node
/**
 * Checks that each product's versions agree, before a release is cut from them
 * (.github/workflows/release.yml publishes whatever the header says):
 *
 * - the header's Version is a number like 1.2 or 1.2.3;
 * - a plugin's version constant (TALA_SEO_VERSION and the like) repeats it. The constant is the
 *   `?ver=` of every script and style, so one left behind ships a release whose files keep their
 *   old addresses, and browsers and caches keep serving the old files;
 * - a plugin's Stable tag in readme.txt repeats it;
 * - readme.txt has a changelog entry for it, the theme's included: the release notes are made from
 *   that entry, and a missing one would stop the release half-way.
 *
 *   node bin/validate-versions.mjs [--root <folder>]
 *
 * Prints one line per problem and exits 1; prints nothing and exits 0 when all four agree. Run it
 * with `npm run lint:versions`. --root checks a copy of the files elsewhere (the script's tests).
 */

import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { PRODUCTS, entry, headerField } from './lib/readme.mjs';

const args = process.argv.slice( 2 );
const at = args.indexOf( '--root' );
const ROOT = -1 === at ? fileURLToPath( new URL( '..', import.meta.url ) ) : resolve( args[ at + 1 ] ?? '.' );

const problems = [];

/**
 * A file's text, or '' (reported) when it can't be read.
 *
 * @param {string} file Path below the root.
 * @return {string} Text.
 */
function read( file ) {
	try {
		return readFileSync( resolve( ROOT, file ), 'utf8' ).replace( /\r\n?/g, '\n' );
	} catch {
		problems.push( `${ file }: can't be read.` );
		return '';
	}
}

for ( const [ slug, product ] of Object.entries( PRODUCTS ) ) {
	const header = read( product.header );
	const readmeFile = `${ slug }/readme.txt`;
	const readme = read( readmeFile );
	if ( ! header || ! readme ) {
		continue;
	}

	const version = headerField( header, 'Version' );
	if ( ! /^\d+(\.\d+){1,2}$/.test( version ) ) {
		problems.push( `${ product.header }: Version "${ version }" isn't a version number like 1.2.3.` );
		continue;
	}

	if ( product.constant ) {
		const constant = header.match( new RegExp( `^const\\s+${ product.constant }\\s*=\\s*'([^']*)'`, 'm' ) );
		if ( ! constant ) {
			problems.push( `${ product.header }: no ${ product.constant } constant.` );
		} else if ( constant[ 1 ] !== version ) {
			problems.push( `${ product.header }: ${ product.constant } is ${ constant[ 1 ] }, the header's Version is ${ version }.` );
		}

		const stable = readme.match( /^Stable tag:[ \t]*(.*?)[ \t]*$/m )?.[ 1 ] ?? '';
		if ( stable !== version ) {
			problems.push( `${ readmeFile }: Stable tag is ${ stable || 'missing' }, the header's Version is ${ version }.` );
		}
	}

	if ( ! entry( readme, 'Changelog', version ) ) {
		problems.push( `${ readmeFile }: no changelog entry for ${ version }.` );
	}
}

if ( problems.length ) {
	process.stderr.write( problems.join( '\n' ) + '\n' );
	process.exit( 1 );
}
