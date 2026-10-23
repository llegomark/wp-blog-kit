#!/usr/bin/env node
/**
 * Checks what PHP and CSS linting can't:
 *
 * - tala-blog/theme.json (and any style variations) against WordPress's theme.json schema for the
 *   theme's "Requires at least" version, and each block.json against the block.json schema for the
 *   plugin's;
 * - pattern headers: a title, a unique "tala-blog/" slug, known categories, Inserter yes/no;
 * - block markup in templates, template parts and patterns: block comments that open and close in
 *   order, attributes that are valid JSON, and patterns and template parts that exist;
 * - that templates and template parts hold no visible text: every word lives in a PHP pattern,
 *   where it can be translated.
 *
 * Run it with `npm run lint:theme`. Given file paths, as the pre-commit hook does, it still reads
 * the whole theme but only reports problems in those files.
 */

import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import Ajv from 'ajv-draft-04';

const ROOT = fileURLToPath( new URL( '..', import.meta.url ) );
const THEME = 'tala-blog';
const PLUGIN = 'tala-seo';

/**
 * Pattern categories that WordPress core registers (7.1). The theme registers none of its own.
 */
const CORE_PATTERN_CATEGORIES = [
	'about',
	'audio',
	'banner',
	'buttons',
	'call-to-action',
	'columns',
	'contact',
	'featured',
	'footer',
	'gallery',
	'header',
	'media',
	'navigation',
	'portfolio',
	'posts',
	'query',
	'services',
	'team',
	'testimonials',
	'text',
	'videos',
];

const BLOCK_COMMENT =
	/<!--\s+(\/)?wp:([a-z][a-z0-9_-]*\/)?([a-z][a-z0-9_-]*)\s+(?:(\{[\s\S]*?\})\s+)?(\/)?-->/g;

const problems = [];
const only = new Set(
	process.argv.slice( 2 ).map( ( file ) => relative( ROOT, resolve( file ) ) ),
);

/**
 * Records a problem, unless file paths were given and this file isn't one of them.
 *
 * @param {string} file    Path relative to the repository.
 * @param {number} line    Line number.
 * @param {string} rule    Short rule name.
 * @param {string} message What is wrong.
 */
function report( file, line, rule, message ) {
	if ( ! only.size || only.has( file ) ) {
		problems.push( { file, line, rule, message } );
	}
}

/**
 * @param {string} file Path relative to the repository.
 * @return {string} The file's contents.
 */
function read( file ) {
	return readFileSync( join( ROOT, file ), 'utf8' );
}

/**
 * @param {string} dir       Folder relative to the repository.
 * @param {string} extension File extension, with the dot.
 * @return {string[]} The folder's files with that extension, relative to the repository.
 */
function list( dir, extension ) {
	if ( ! existsSync( join( ROOT, dir ) ) ) {
		return [];
	}
	return readdirSync( join( ROOT, dir ), { recursive: true } )
		.filter( ( name ) => name.endsWith( extension ) )
		.map( ( name ) => join( dir, name ) )
		.sort();
}

/**
 * @param {string} text  Text.
 * @param {number} index Offset in the text.
 * @return {number} The line the offset is on.
 */
function lineAt( text, index ) {
	return text.slice( 0, index ).split( '\n' ).length;
}

/**
 * A header value from a theme file or plugin file ("Requires at least: 6.6").
 *
 * @param {string} source File contents.
 * @param {string} name   Header name.
 * @return {string} The value, or ''.
 */
function header( source, name ) {
	const match = source.match( new RegExp( `^[ \\t/*#@]*${ name }:(.*)$`, 'mi' ) );
	return match ? match[ 1 ].trim() : '';
}

/**
 * Replaces PHP with a placeholder of the same number of lines, so the markup around it can be
 * parsed and line numbers stay right. The placeholder holds letters, like the text PHP prints.
 *
 * @param {string} source PHP file contents.
 * @return {string} The markup.
 */
function withoutPhp( source ) {
	return source.replace( /<\?php[\s\S]*?(?:\?>|$)/g, ( php ) =>
		'php' + '\n'.repeat( php.split( '\n' ).length - 1 ),
	);
}

/**
 * Line numbers of the values in a JSON text, by JSON pointer ("/settings/color/palette/0").
 *
 * @param {string} text JSON text.
 * @return {Map<string, number>} Pointer to line.
 */
function pointerLines( text ) {
	const lines = new Map();
	let i = 0;
	const skip = () => {
		while ( /\s/.test( text[ i ] ) ) {
			i++;
		}
	};
	const string = () => {
		const start = i++;
		while ( text[ i ] !== '"' ) {
			i += text[ i ] === '\\' ? 2 : 1;
		}
		i++;
		return JSON.parse( text.slice( start, i ) );
	};
	const value = ( pointer ) => {
		skip();
		lines.set( pointer, lineAt( text, i ) );
		if ( text[ i ] === '{' ) {
			i++;
			skip();
			while ( text[ i ] !== '}' ) {
				skip();
				const key = string().replace( /~/g, '~0' ).replace( /\//g, '~1' );
				skip();
				i++; // The colon.
				value( `${ pointer }/${ key }` );
				skip();
				if ( text[ i ] === ',' ) {
					i++;
				}
				skip();
			}
			i++;
		} else if ( text[ i ] === '[' ) {
			i++;
			for ( let index = 0; ; index++ ) {
				skip();
				if ( text[ i ] === ']' ) {
					break;
				}
				value( `${ pointer }/${ index }` );
				skip();
				if ( text[ i ] === ',' ) {
					i++;
				}
			}
			i++;
		} else if ( text[ i ] === '"' ) {
			string();
		} else {
			while ( i < text.length && ! /[,}\]\s]/.test( text[ i ] ) ) {
				i++;
			}
		}
	};
	value( '' );
	return lines;
}

/**
 * Validates JSON files against a WordPress schema (schemas.wp.org). Their "$schema" must name the
 * same version, or trunk.
 *
 * @param {string[]} files   Paths relative to the repository.
 * @param {string}   kind    "theme" or "block".
 * @param {string}   version The "Requires at least" version.
 */
async function validateSchema( files, kind, version ) {
	files = files.filter( ( file ) => ! only.size || only.has( file ) );
	if ( ! files.length ) {
		return;
	}
	const url = `https://schemas.wp.org/wp/${ version }/${ kind }.json`;
	const trunk = `https://schemas.wp.org/trunk/${ kind }.json`;
	let validate;
	try {
		const response = await fetch( url, { signal: AbortSignal.timeout( 30000 ) } );
		if ( ! response.ok ) {
			throw new Error( `HTTP ${ response.status }` );
		}
		validate = new Ajv( { strict: false, allErrors: true } ).compile( await response.json() );
	} catch ( error ) {
		// Offline, a commit shouldn't fail for want of the schema; in CI it must.
		const message = `Could not load ${ url } (${ error.message }), so ${ kind }.json files were not checked against it.`;
		if ( process.env.CI ) {
			report( files[ 0 ], 1, `${ kind }-json-schema`, message );
		} else {
			process.stderr.write( `Warning: ${ message }\n` );
		}
		return;
	}
	for ( const file of files ) {
		const text = read( file );
		let data;
		try {
			data = JSON.parse( text );
		} catch ( error ) {
			report( file, 1, `${ kind }-json-syntax`, error.message );
			continue;
		}
		if ( ! [ url, trunk ].includes( data.$schema ) ) {
			report(
				file,
				1,
				`${ kind }-json-schema-version`,
				`"$schema" should be ${ url } (matching "Requires at least") or ${ trunk }, found ${ JSON.stringify( data.$schema ) }.`,
			);
		}
		if ( validate( data ) ) {
			continue;
		}
		const lines = pointerLines( text );
		const seen = new Set();
		for ( const error of validate.errors ) {
			// "must match a schema in anyOf" repeats what the specific errors already say.
			if ( /^must match (?:exactly one|a) schema in (?:oneOf|anyOf)$/.test( error.message ) ) {
				continue;
			}
			const detail = error.params?.additionalProperty
				? `has an unknown property "${ error.params.additionalProperty }"`
				: error.message;
			const key = `${ error.instancePath } ${ detail }`;
			if ( ! seen.has( key ) ) {
				seen.add( key );
				report(
					file,
					lines.get( error.instancePath ) ?? 1,
					`${ kind }-json-schema`,
					`${ error.instancePath || '(top level)' } ${ detail }`,
				);
			}
		}
	}
}

/**
 * Checks the pattern headers and returns the slugs of the theme's patterns.
 *
 * @return {Set<string>} Pattern slugs.
 */
function validatePatternHeaders() {
	const slugs = new Set();
	for ( const file of list( `${ THEME }/patterns`, '.php' ) ) {
		const source = read( file );
		const title = header( source, 'Title' );
		const slug = header( source, 'Slug' );
		if ( ! title ) {
			report( file, 1, 'pattern-title', 'The pattern has no "Title" header.' );
		}
		if ( ! slug ) {
			report( file, 1, 'pattern-slug', 'The pattern has no "Slug" header.' );
		} else if ( ! slug.startsWith( `${ THEME }/` ) ) {
			report( file, 1, 'pattern-slug', `The slug "${ slug }" should start with "${ THEME }/".` );
		} else if ( slugs.has( slug ) ) {
			report( file, 1, 'pattern-slug', `Another pattern already uses the slug "${ slug }".` );
		}
		slugs.add( slug );
		for ( const category of header( source, 'Categories' ).split( ',' ).map( ( c ) => c.trim() ).filter( Boolean ) ) {
			if ( ! CORE_PATTERN_CATEGORIES.includes( category ) ) {
				report( file, 1, 'pattern-category', `"${ category }" is not a pattern category WordPress registers.` );
			}
		}
		const inserter = header( source, 'Inserter' ).toLowerCase();
		if ( inserter && ! [ 'yes', 'no', 'true', 'false' ].includes( inserter ) ) {
			report( file, 1, 'pattern-inserter', `"Inserter" should be yes or no, found "${ inserter }".` );
		}
	}
	return slugs;
}

/**
 * Checks the block markup of one file.
 *
 * @param {string}      file         Path relative to the repository.
 * @param {string}      markup       The markup (PHP already replaced).
 * @param {Set<string>} patternSlugs The theme's pattern slugs.
 * @param {Set<string>} partSlugs    The theme's template part slugs.
 */
function validateBlocks( file, markup, patternSlugs, partSlugs ) {
	const open = [];
	for ( const match of markup.matchAll( BLOCK_COMMENT ) ) {
		const [ , closer, namespace = 'core/', name, attributes, selfClosing ] = match;
		const block = namespace + name;
		const line = lineAt( markup, match.index );
		let attrs = {};
		if ( attributes ) {
			try {
				attrs = JSON.parse( attributes );
			} catch ( error ) {
				report( file, line, 'block-attributes', `The attributes of ${ block } are not valid JSON: ${ error.message }` );
			}
		}
		if ( closer ) {
			const last = open.pop();
			if ( ! last ) {
				report( file, line, 'block-nesting', `${ block } is closed but was never opened.` );
			} else if ( last.block !== block ) {
				report( file, line, 'block-nesting', `${ block } closes, but ${ last.block } (line ${ last.line }) is still open.` );
			}
			continue;
		}
		if ( ! selfClosing ) {
			open.push( { block, line } );
		}
		if ( block === 'core/pattern' && ! patternSlugs.has( attrs.slug ) ) {
			report( file, line, 'block-pattern', `No pattern has the slug ${ JSON.stringify( attrs.slug ) }.` );
		}
		if ( block === 'core/template-part' && ! partSlugs.has( attrs.slug ) ) {
			report( file, line, 'block-template-part', `No template part ${ JSON.stringify( attrs.slug ) } in ${ THEME }/parts.` );
		}
	}
	for ( const { block, line } of open ) {
		report( file, line, 'block-nesting', `${ block } is never closed.` );
	}
}

/**
 * Reports visible text in a template or template part: it belongs in a PHP pattern, where it can be
 * translated.
 *
 * @param {string} file   Path relative to the repository.
 * @param {string} markup The file's contents.
 */
function validateNoText( file, markup ) {
	const bare = markup.replace( /<!--[\s\S]*?-->/g, ( comment ) => comment.replace( /[^\n]/g, ' ' ) ).replace( /<[^>]*>/g, ( tag ) => tag.replace( /[^\n]/g, ' ' ) );
	const text = bare.match( /\p{L}[^<\n]*/u );
	if ( text ) {
		report( file, lineAt( bare, text.index ), 'template-text', `Visible text "${ text[ 0 ].trim() }" belongs in a PHP pattern, so it can be translated.` );
	}
}

const themeHeader = read( `${ THEME }/style.css` );
const pluginHeader = read( `${ PLUGIN }/${ PLUGIN }.php` );
const themeVersion = header( themeHeader, 'Requires at least' );
const pluginVersion = header( pluginHeader, 'Requires at least' );
if ( ! themeVersion ) {
	report( `${ THEME }/style.css`, 1, 'theme-header', 'Missing "Requires at least", so theme.json can\'t be checked against its schema.' );
}
if ( ! pluginVersion ) {
	report( `${ PLUGIN }/${ PLUGIN }.php`, 1, 'plugin-header', 'Missing "Requires at least", so block.json can\'t be checked against its schema.' );
}

await Promise.all( [
	themeVersion && validateSchema( [ `${ THEME }/theme.json`, ...list( `${ THEME }/styles`, '.json' ) ], 'theme', themeVersion ),
	pluginVersion && validateSchema( list( `${ PLUGIN }/blocks`, 'block.json' ), 'block', pluginVersion ),
] );

const patternSlugs = validatePatternHeaders();
const partSlugs = new Set( list( `${ THEME }/parts`, '.html' ).map( ( file ) => file.split( '/' ).pop().replace( /\.html$/, '' ) ) );
for ( const file of [ ...list( `${ THEME }/templates`, '.html' ), ...list( `${ THEME }/parts`, '.html' ) ] ) {
	const markup = read( file );
	validateBlocks( file, markup, patternSlugs, partSlugs );
	validateNoText( file, markup );
}
for ( const file of list( `${ THEME }/patterns`, '.php' ) ) {
	validateBlocks( file, withoutPhp( read( file ) ), patternSlugs, partSlugs );
}

for ( const { file, line, rule, message } of problems ) {
	process.stdout.write( `${ file }:${ line }  ${ message }  [${ rule }]\n` );
}
if ( problems.length ) {
	process.stdout.write( `\n${ problems.length } problem${ problems.length === 1 ? '' : 's' } found.\n` );
	process.exitCode = 1;
} else {
	process.stdout.write( 'Theme check: no problems found.\n' );
}
