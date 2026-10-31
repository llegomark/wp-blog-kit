#!/usr/bin/env node
/**
 * Checks a plugin's readme.txt (tala-seo or tala-ads, named on the command line) as WordPress.org's
 * readme validator does
 * (https://wordpress.org/plugins/developers/readme-validator/): the same parsing rules and the same
 * errors, warnings and notes, ported from the plugin directory's Readme\Parser and Readme\Validator
 * (github.com/WordPress/wordpress.org, public_html/wp-content/plugins/plugin-directory/readme/).
 * Plugin Check, which `composer check` runs, validates the fields that are there; it doesn't
 * report the ones that are missing, which the validator's notes do.
 *
 * On top, what Plugin Check and this repository ask for:
 * - the readme agrees with the plugin header: Stable tag = Version, same Requires at least and
 *   Requires PHP;
 * - "Tested up to" is the current WordPress release or the next;
 * - each upgrade notice fits in 300 characters;
 * - the changelog has an entry for the Stable tag;
 * - each screenshot caption has its image in .wordpress-org/ for Tala SEO and .wordpress-org/<plugin>/
 *   for the others (screenshot-1.png, …), the folder WordPress.org's SVN calls assets/, and each
 *   image a caption.
 *
 * Contributors, tag usage and the current WordPress release are looked up on WordPress.org.
 * Offline those checks are skipped with a warning; in CI they must run.
 *
 * Everything found fails the check, notes included, except the notes this project decided to
 * leave (ACCEPTED_NOTES, with the reason). Run it with `npm run lint:readme` (both plugins), or
 * `node bin/validate-readme.mjs tala-ads` for one.
 */

import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = fileURLToPath( new URL( '..', import.meta.url ) );
const PLUGINS = [ 'tala-seo', 'tala-ads' ];
const PLUGIN = process.argv[ 2 ] ?? 'tala-seo';
if ( ! PLUGINS.includes( PLUGIN ) ) {
	process.stderr.write( `Usage: node bin/validate-readme.mjs <${ PLUGINS.join( '|' ) }>\n` );
	process.exit( 2 );
}
const README = `${ PLUGIN }/readme.txt`;
const SCREENSHOTS = 'tala-seo' === PLUGIN ? '.wordpress-org' : `.wordpress-org/${ PLUGIN }`;

/**
 * Validator notes left on purpose.
 */
const ACCEPTED_NOTES = {
	donate_link_missing: 'The plugin is free and takes no donations (the maintainer\'s decision).',
};

// From the plugin directory's Readme\Parser.
const VALID_HEADERS = {
	tested: 'tested',
	'tested up to': 'tested',
	requires: 'requires',
	'requires at least': 'requires',
	'requires php': 'requires_php',
	tags: 'tags',
	contributors: 'contributors',
	'donate link': 'donate_link',
	'stable tag': 'stable_tag',
	license: 'license',
	'license uri': 'license_uri',
};
const EXPECTED_SECTIONS = [ 'description', 'installation', 'faq', 'screenshots', 'changelog', 'upgrade_notice', 'other_notes' ];
const ALIAS_SECTIONS = { frequently_asked_questions: 'faq', change_log: 'changelog', screenshot: 'screenshots' };
const IGNORE_TAGS = [ 'plugin', 'wordpress' ];
const MAXIMUM = { short_description: 150, section: 2500, 'section-changelog': 5000, 'section-faq': 5000 };
// Readme\Parser::validate_license(): keywords of licences that are probably GPL-compatible, and of
// ones that probably aren't. (The directory also accepts the WTFPL under its long name.)
const PROBABLY_COMPATIBLE = [ 'GPL', 'General Public License', 'MIT', 'ISC', 'Expat', 'Apache 2', 'Apache License 2', 'X11', 'Modified BSD', 'New BSD', '3 Clause BSD', 'BSD 3', 'FreeBSD', 'Simplified BSD', '2 Clause BSD', 'BSD 2', 'MPL', 'Mozilla Public License', 'WTFPL', 'Public Domain', 'CC0', 'Unlicense', 'CC BY', 'zlib' ];
const PROBABLY_INCOMPATIBLE = [ '4 Clause BSD', 'BSD 4 Clause', 'Apache 1', 'CC BY-NC', 'CC-NC', 'NonCommercial', 'CC BY-ND', 'NoDerivative', 'EUPL', 'OSL', 'Personal use', 'without permission', 'without prior auth', 'you may not', 'Proprietery', 'proprietary' ];
// Plugin Check's limit for an upgrade notice (Plugin_Readme_Check).
const UPGRADE_NOTICE_LIMIT = 300;

const problems = [];

/**
 * Records a finding.
 *
 * @param {string} level   error, warning or note (the validator's levels).
 * @param {string} code    The validator's code, or this script's.
 * @param {string} message What is wrong.
 * @param {number} line    Line in the readme.
 */
function report( level, code, message, line = 1 ) {
	if ( 'note' === level && ACCEPTED_NOTES[ code ] ) {
		return;
	}
	problems.push( { level, code, message, line } );
}

/**
 * Something looked up on WordPress.org, or null when it can't be reached. Offline, a commit
 * shouldn't fail for want of it; in CI it must.
 *
 * @param {string} url  Address.
 * @param {string} what What it was for.
 * @return {Promise<Response|null>} The response (any status), or null.
 */
async function lookUp( url, what ) {
	try {
		return await fetch( url, { signal: AbortSignal.timeout( 30000 ), headers: { 'User-Agent': `${ PLUGIN } readme check` } } );
	} catch ( error ) {
		const message = `Could not reach ${ new URL( url ).host } (${ error.message }), so ${ what } were not checked.`;
		if ( process.env.CI ) {
			report( 'error', 'wordpress_org_unreachable', message );
		} else {
			process.stderr.write( `Warning: ${ message }\n` );
		}
		return null;
	}
}

/**
 * The header fields of a PHP file ("Version: 1.2.3").
 *
 * @param {string} source PHP source.
 * @return {Object<string, string>} Field => value.
 */
function phpHeaders( source ) {
	const fields = {};
	for ( const match of source.matchAll( /^[ \t/*#@]*([A-Za-z][A-Za-z ]+):[ \t]*(.+?)[ \t]*$/gm ) ) {
		fields[ match[ 1 ].trim() ] ??= match[ 2 ];
	}
	return fields;
}

/**
 * Readme\Parser::parse_possible_header().
 *
 * @param {string}  line      Line.
 * @param {boolean} onlyValid Only known header names.
 * @return {Array|false} [ key, value ], or false.
 */
function possibleHeader( line, onlyValid = false ) {
	if ( ! line.includes( ':' ) || line.startsWith( '#' ) || line.startsWith( '=' ) ) {
		return false;
	}
	const at = line.indexOf( ':' );
	const key = line.slice( 0, at ).replace( /^[ \t*\-\r\n]+|[ \t*\-\r\n]+$/g, '' ).toLowerCase();
	const value = line.slice( at + 1 ).replace( /^[ \t*\-\r\n<>]+|[ \t*\-\r\n<>]+$/g, '' );
	return onlyValid && ! VALID_HEADERS[ key ] ? false : [ key, value ];
}

/**
 * Whether a line starts a section: "== Title ==" or "## Title", not "### Title".
 *
 * @param {string} trimmed Trimmed line.
 * @return {boolean} A section heading.
 */
function isSection( trimmed ) {
	return trimmed.startsWith( '==' ) || ( trimmed.startsWith( '##' ) && '#' !== trimmed[ 2 ] && undefined !== trimmed[ 2 ] );
}

/**
 * Readme\Parser::parse_section(): "= Heading =" items of a section, heading => text.
 *
 * @param {string} text Section text.
 * @return {Map<string, string>} Items.
 */
function sectionItems( text ) {
	const items = new Map();
	const lines = text.split( '\n' );
	const style = lines.some( ( line ) => /^[#=]/.test( line.trim() ) ) ? 'heading' : 'bold';
	let key = '';
	let value = '';
	for ( const line of lines ) {
		const trimmed = line.trim();
		const heading = 'heading' === style ? /^[#=]/.test( trimmed ) : trimmed.length > 4 && trimmed.startsWith( '**' ) && trimmed.endsWith( '**' );
		if ( ! heading ) {
			value += line + '\n';
			continue;
		}
		if ( value.trim() ) {
			items.set( key, value.trim() );
		}
		value = '';
		key = trimmed.replace( new RegExp( `^[\\${ trimmed[ 0 ] } \\t]+|[\\${ trimmed[ 0 ] } \\t]+$`, 'g' ), '' );
	}
	if ( key || value.trim() ) {
		items.set( key, value.trim() );
	}
	return items;
}

/**
 * Readme text as the directory shows it: Markdown links, emphasis and code reduced to their text.
 *
 * @param {string} text Readme text.
 * @return {string} Plain text.
 */
function plain( text ) {
	return text
		.replace( /<[^>]*>/g, '' )
		.replace( /\[([^\]]*)\]\([^)]*\)/g, '$1' )
		.replace( /(\*\*|__|\*|_|`)(.+?)\1/g, '$2' )
		.trim();
}

/**
 * A version from "Requires at least" or "Tested up to", as Readme\Parser reads it, or '' when it
 * isn't one.
 *
 * @param {string}      value  Header value.
 * @param {string|null} stable Current WordPress branch ("7.1"), when known.
 * @param {boolean}     loose  Also strip "or higher", "and above" and "+" (Requires at least).
 * @return {string} Version, or ''.
 */
function wordpressVersion( value, stable, loose ) {
	let version = value.replace( /WordPress|WP/gi, '' );
	if ( loose ) {
		version = version.replace( /or higher|and above|\+/gi, '' );
	}
	version = version.trim().split( '-' )[ 0 ];
	if ( ! /^\d+\.\d(\.\d+)?$/.test( version ) ) {
		return '';
	}
	// Up to the next release (trunk) is accepted.
	return stable && parseFloat( version ) > parseFloat( stable ) + 0.1 + 1e-9 ? '' : version;
}

/**
 * Readme\Parser::validate_license().
 *
 * @param {string} license Licence.
 * @return {true|string} true, invalid_license or unknown_license.
 */
function licenseVerdict( license ) {
	const clean = ( text ) =>
		text
			.toLowerCase()
			.replace( /licence/g, 'license' )
			.replace( /clauses/g, 'clause' )
			.replace( /creative commons/g, 'cc' )
			.replace( /(version |v)([0-9])/gi, '$2' )
			.replace( /(\s*[^a-z0-9. ]+\s*)/gi, '' )
			.replace( /\s+/g, '' );
	const value = clean( license );
	if ( PROBABLY_INCOMPATIBLE.some( ( match ) => value.includes( clean( match ) ) ) ) {
		return 'invalid_license';
	}
	return PROBABLY_COMPATIBLE.some( ( match ) => value.includes( clean( match ) ) ) ? true : 'unknown_license';
}

/**
 * Text as esc_html() would leave it, for counting an upgrade notice's characters as Plugin Check
 * does.
 *
 * @param {string} text Text.
 * @return {string} Escaped text.
 */
function escHtml( text ) {
	return text.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' ).replace( /"/g, '&quot;' ).replace( /'/g, '&#039;' );
}

const lines = readFileSync( join( ROOT, README ), 'utf8' ).replace( /^\uFEFF/, '' ).split( /\r\n|\r|\n/ );
const lineOf = ( pattern ) => Math.max( 1, lines.findIndex( ( line ) => pattern.test( line ) ) + 1 );

// The plugin name: the first line that isn't blank.
let at = lines.findIndex( ( line ) => line.trim() );
const nameLine = lines[ at ] ?? '';
const name = possibleHeader( nameLine, true ) ? '' : plain( nameLine.replace( /^[#= \t]+|[#= \t]+$/g, '' ) );
if ( ! name || 'plugin name' === name.toLowerCase() ) {
	report( 'error', 'invalid_plugin_name_header', 'We cannot find a plugin name in your readme. Plugin names look like: === Plugin Name ===.', at + 1 );
}
if ( ! possibleHeader( nameLine, true ) ) {
	at++;
}

// Headers, up to the first line that isn't one.
const headers = {};
const headerLine = {};
let lastBlank = false;
for ( ; at < lines.length; at++ ) {
	const header = possibleHeader( lines[ at ] );
	if ( ! header ) {
		if ( ! lines[ at ].trim() ) {
			lastBlank = true;
			continue;
		}
		break;
	}
	if ( VALID_HEADERS[ header[ 0 ] ] ) {
		headers[ VALID_HEADERS[ header[ 0 ] ] ] = header[ 1 ];
		headerLine[ VALID_HEADERS[ header[ 0 ] ] ] = at + 1;
	} else if ( lastBlank ) {
		break;
	}
	lastBlank = false;
}

// The short description: up to the first section.
let shortDescription = '';
for ( ; at < lines.length && ! isSection( lines[ at ].trim() ); at++ ) {
	if ( lines[ at ].trim() ) {
		shortDescription += lines[ at ] + ' ';
	}
}

// Sections; unknown ones become "other notes", which the directory adds to the description.
const sections = Object.fromEntries( EXPECTED_SECTIONS.map( ( section ) => [ section, '' ] ) );
let current = '';
let section = '';
for ( ; at < lines.length; at++ ) {
	const trimmed = lines[ at ].trim();
	if ( trimmed && isSection( trimmed ) ) {
		if ( section ) {
			sections[ section ] += current.trim();
		}
		current = '';
		const title = trimmed.replace( /^[#= \t]+|[#= \t]+$/g, '' );
		section = title.toLowerCase().replace( / /g, '_' );
		section = ALIAS_SECTIONS[ section ] ?? section;
		if ( ! EXPECTED_SECTIONS.includes( section ) ) {
			current += `<h3>${ title }</h3>`;
			section = 'other_notes';
		}
		continue;
	}
	current += trimmed ? lines[ at ] + '\n' : '\n';
}
if ( section ) {
	sections[ section ] += current.trim();
}
if ( ! sections.description ) {
	sections.description = shortDescription.trim();
}
if ( sections.other_notes ) {
	sections.description += '\n' + sections.other_notes;
}
delete sections.other_notes;

// The network lookups run together.
const tags = ( headers.tags ?? '' ).split( ',' ).map( ( tag ) => tag.trim() ).filter( Boolean );
const contributors = ( headers.contributors ?? '' ).split( ',' ).map( ( user ) => user.trim().replace( /^@+/, '' ) ).filter( Boolean );
const [ release, profiles, tagCounts ] = await Promise.all( [
	lookUp( 'https://api.wordpress.org/core/version-check/1.7/', 'the WordPress versions' ),
	Promise.all( contributors.map( ( user ) => lookUp( `https://profiles.wordpress.org/${ encodeURIComponent( user ) }/`, 'the contributors' ) ) ),
	Promise.all(
		tags.map( ( tag ) =>
			lookUp( `https://api.wordpress.org/plugins/info/1.2/?action=query_plugins&request[tag]=${ encodeURIComponent( tag ) }&request[per_page]=1`, 'the tags' ),
		),
	),
] );
const latest = release?.ok ? ( await release.json() ).offers?.[ 0 ]?.current ?? null : null;
const stable = latest ? latest.split( '.' ).slice( 0, 2 ).join( '.' ) : null;

// Tags.
const ignored = tags.filter( ( tag ) => IGNORE_TAGS.includes( tag ) );
if ( ignored.length ) {
	report( 'warning', 'ignored_tags', `One or more tags were ignored. The following tags are not permitted: ${ ignored.join( ', ' ) }`, headerLine.tags );
}
const kept = tags.filter( ( tag ) => ! IGNORE_TAGS.includes( tag ) );
if ( kept.length > 5 ) {
	report( 'warning', 'too_many_tags', `One or more tags were ignored: ${ kept.slice( 5 ).join( ', ' ) }. Please limit your plugin to 5 tags.`, headerLine.tags );
}
const lowUsage = [];
for ( const [ i, response ] of tagCounts.entries() ) {
	if ( response?.ok && ( ( await response.json() ).info?.results ?? 0 ) < 5 ) {
		lowUsage.push( tags[ i ] );
	}
}
if ( lowUsage.length ) {
	report( 'note', 'low_usage_tags', `The following tags are not widely used: ${ lowUsage.join( ', ' ) }`, headerLine.tags );
}

// Contributors.
if ( ! contributors.length ) {
	report( 'note', 'contributors_missing', 'The Contributors field is missing.' );
}
const unknownUsers = contributors.filter( ( user, i ) => profiles[ i ] && 404 === profiles[ i ].status );
if ( unknownUsers.length ) {
	report(
		'warning',
		'contributor_ignored',
		`The following contributors listed were ignored, as the WordPress.org user could not be found. ${ unknownUsers.join( ', ' ) }. The Contributors field should only contain WordPress.org usernames.`,
		headerLine.contributors,
	);
}

// WordPress and PHP versions.
const pluginHeader = phpHeaders( readFileSync( join( ROOT, PLUGIN, `${ PLUGIN }.php` ), 'utf8' ) );
const requires = headers.requires ? wordpressVersion( headers.requires, stable, true ) : '';
if ( headers.requires && ! requires ) {
	report( 'warning', 'requires_header_ignored', 'The Requires at least field was ignored. This field should only contain a valid WordPress version.', headerLine.requires );
}
if ( ! headers.requires ) {
	report( 'note', 'requires_header_missing', 'The Requires at least field is missing. It should be defined here, or in your main plugin file.' );
}
const tested = headers.tested ? wordpressVersion( headers.tested, stable, false ) : '';
if ( headers.tested && ! tested ) {
	report( 'warning', 'tested_header_ignored', `The Tested up to field was ignored. This field should only contain a valid WordPress version${ stable ? `, such as ${ stable }` : '' }.`, headerLine.tested );
} else if ( ! headers.tested ) {
	report( 'warning', 'tested_header_missing', 'The Tested up to field is missing.' );
} else if ( stable && parseFloat( tested ) < parseFloat( stable ) - 1e-9 ) {
	report( 'warning', 'outdated_tested_upto_header', `Tested up to: ${ tested } is older than the current WordPress release, ${ stable }. Test the plugin with it and update the field.`, headerLine.tested );
}
if ( headers.requires_php && ! /^\d+(\.\d+){1,2}$/.test( headers.requires_php.trim() ) ) {
	report( 'warning', 'requires_php_header_ignored', 'The Requires PHP field was ignored. This field should only contain a PHP version such as 8.2.', headerLine.requires_php );
}
if ( ! headers.requires_php ) {
	report( 'note', 'requires_php_header_missing', 'The Requires PHP field is missing. It should be defined here, or in your main plugin file.' );
}
for ( const [ field, key, label ] of [ [ 'Requires at least', 'requires', requires ], [ 'Requires PHP', 'requires_php', headers.requires_php?.trim() ] ] ) {
	if ( label && pluginHeader[ field ] && label !== pluginHeader[ field ] ) {
		report( 'error', `mismatched_${ key }`, `${ field }: ${ label } in the readme, but ${ pluginHeader[ field ] } in ${ PLUGIN }.php.`, headerLine[ key ] );
	}
}

// Stable tag.
const stableTag = ( headers.stable_tag ?? '' ).trim().replace( /^["']|["']$/g, '' ).replace( /^\/?tags\//i, '' ).replace( /[^a-z0-9_.-]/gi, '' );
if ( ! stableTag || stableTag.includes( 'trunk' ) ) {
	report( 'warning', 'stable_tag_invalid', 'The Stable tag field is missing or invalid. Note: We ask you no longer attempt to use /trunk/ as stable, so that all plugins can be rolled back.', headerLine.stable_tag );
} else if ( stableTag !== pluginHeader.Version ) {
	report( 'error', 'stable_tag_mismatch', `Stable tag: ${ stableTag }, but the plugin header says Version: ${ pluginHeader.Version }.`, headerLine.stable_tag );
}

// Licence.
let license = headers.license ?? '';
if ( license && ! headers.license_uri ) {
	license = license.replace( /(https?:\/\/\S+)/i, '' ).replace( /^[ \-*\t()<>]+|[ \-*\t()<>]+$/g, '' );
}
if ( ! license.trim() ) {
	report( 'warning', 'license_missing', 'No license was found. Please add a License field with a GPL-compatible license.' );
} else if ( true !== licenseVerdict( license ) ) {
	const verdict = licenseVerdict( license );
	report(
		'invalid_license' === verdict ? 'error' : 'note',
		verdict,
		'invalid_license' === verdict
			? `The license "${ license }" is not GPL-compatible.`
			: `The license "${ license }" could not be validated as GPL-compatible.`,
		headerLine.license,
	);
}

// Donate link.
if ( ! headers.donate_link ) {
	report( 'note', 'donate_link_missing', 'No donate link was found.' );
} else if ( ! URL.canParse( headers.donate_link ) || ! /^https?:$/.test( new URL( headers.donate_link ).protocol ) ) {
	report( 'error', 'readme_invalid_donate_link', 'The Donate link must be a valid URL.', headerLine.donate_link );
}

// Short description.
const summary = plain( shortDescription.trim() );
if ( ! summary ) {
	report( 'note', 'no_short_description_present', 'The Short Description section is missing. An excerpt was generated from your main plugin description.' );
} else if ( [ ...summary ].length > MAXIMUM.short_description ) {
	report(
		'warning',
		'trimmed_short_description',
		`The Short Description section is too long and was truncated. A maximum of ${ MAXIMUM.short_description } characters is supported (it has ${ [ ...summary ].length }).`,
		lineOf( new RegExp( `^${ shortDescription.trim().slice( 0, 20 ).replace( /[.*+?^${}()|[\]\\]/g, '\\$&' ) }` ) ),
	);
}

// Sections: present, and not longer than the directory shows.
const titles = { faq: 'FAQ', changelog: 'Changelog', upgrade_notice: 'Upgrade Notice', screenshots: 'Screenshots' };
for ( const [ key, title ] of Object.entries( titles ) ) {
	if ( ! sections[ key ] ) {
		report( 'note', `${ key }_missing`, `No == ${ title } == section was found.` );
	}
}
for ( const [ key, text ] of Object.entries( sections ) ) {
	if ( ! text || 'upgrade_notice' === key ) {
		continue;
	}
	const limit = MAXIMUM[ `section-${ key }` ] ?? MAXIMUM.section;
	const words = text.split( /\s+/u ).filter( Boolean ).length;
	if ( text.split( /(\s+)/u ).length >= limit * 2 ) {
		report( 'warning', `trimmed_section_${ key }`, `The ${ key } section is too long and was truncated. A maximum of ${ limit } words is supported (it has ${ words }).` );
	}
}

// Upgrade notices (Plugin Check's limit) and the changelog entry for this release.
for ( const [ version, notice ] of sectionItems( sections.upgrade_notice ) ) {
	const length = Buffer.byteLength( escHtml( plain( notice ) ) );
	if ( length > UPGRADE_NOTICE_LIMIT ) {
		report( 'warning', 'upgrade_notice_limit', `The upgrade notice for "${ version }" exceeds the limit of ${ UPGRADE_NOTICE_LIMIT } characters (${ length }).`, lineOf( new RegExp( `^= ${ version.replace( /\./g, '\\.' ) } =` ) ) );
	}
}
if ( stableTag && sections.changelog && ! sectionItems( sections.changelog ).has( stableTag ) ) {
	report( 'error', 'changelog_entry_missing', `The changelog has no entry for the Stable tag, ${ stableTag }.`, lineOf( /^== Changelog ==/ ) );
}

// Screenshots: a caption per image, an image per caption.
const captions = [ ...sections.screenshots.matchAll( /^\s*\d+\.\s+(.+)$/gm ) ].map( ( match ) => match[ 1 ] );
const images = existsSync( join( ROOT, SCREENSHOTS ) )
	? readdirSync( join( ROOT, SCREENSHOTS ) ).filter( ( file ) => /^screenshot-\d+\.(png|jpe?g|gif|webp)$/i.test( file ) )
	: [];
const imageNumbers = new Set( images.map( ( file ) => Number( file.match( /\d+/ )[ 0 ] ) ) );
captions.forEach( ( caption, i ) => {
	if ( ! imageNumbers.has( i + 1 ) ) {
		report( 'error', 'screenshot_image_missing', `Screenshot ${ i + 1 } ("${ caption }") has no image: add ${ SCREENSHOTS }/screenshot-${ i + 1 }.png.`, lineOf( /^== Screenshots ==/ ) );
	}
} );
for ( const number of [ ...imageNumbers ].filter( ( n ) => n > captions.length ) ) {
	report( 'error', 'screenshot_caption_missing', `${ SCREENSHOTS }/screenshot-${ number } has no caption in the Screenshots section.`, lineOf( /^== Screenshots ==/ ) );
}

problems.sort( ( a, b ) => a.line - b.line );
for ( const { level, code, message, line } of problems ) {
	process.stdout.write( `${ README }:${ line }  ${ level }: ${ message }  [${ code }]\n` );
}
if ( problems.length ) {
	process.stdout.write( `\n${ problems.length } problem${ problems.length === 1 ? '' : 's' } found.\n` );
	process.exitCode = 1;
} else {
	const accepted = Object.keys( ACCEPTED_NOTES ).join( ', ' );
	process.stdout.write( `Readme check: no problems found${ accepted ? ` (accepted notes: ${ accepted })` : '' }.\n` );
}
