/**
 * What the release tools share: the four products and how to read a header field and a readme
 * entry (bin/release-notes.mjs, bin/validate-versions.mjs).
 */

/**
 * The products: the file with the header, and for the plugins the constant that repeats the
 * version (the `?ver=` of every script and style).
 */
export const PRODUCTS = {
	'tala-seo': { name: 'Tala SEO', header: 'tala-seo/tala-seo.php', kind: 'plugin', constant: 'TALA_SEO_VERSION' },
	'tala-ads': { name: 'Tala Ads', header: 'tala-ads/tala-ads.php', kind: 'plugin', constant: 'TALA_ADS_VERSION' },
	'tala-guard': { name: 'Tala Guard', header: 'tala-guard/tala-guard.php', kind: 'plugin', constant: 'TALA_GUARD_VERSION' },
	'tala-blog': { name: 'Tala Blog', header: 'tala-blog/style.css', kind: 'theme', constant: '' },
};

/**
 * A field of a plugin or theme header ("Version"), or ''.
 *
 * @param {string} text  The header file's text.
 * @param {string} field Field name.
 * @return {string} Value.
 */
export function headerField( text, field ) {
	return text.match( new RegExp( `^[ \\t/*#@]*${ field }:[ \\t]*(.+?)[ \\t]*$`, 'm' ) )?.[ 1 ] ?? '';
}

/**
 * The text under "= <version> =" in a readme section, or ''.
 *
 * @param {string} readme  Readme text.
 * @param {string} section Section title ("Changelog").
 * @param {string} version Version.
 * @return {string} Text.
 */
export function entry( readme, section, version ) {
	const body = readme.split( /^==\s*/m ).find( ( part ) => part.toLowerCase().startsWith( `${ section.toLowerCase() } ==` ) ) ?? '';
	const items = body.split( /^=\s*/m ).slice( 1 );
	const item = items.find( ( text ) => text.startsWith( `${ version } =` ) );
	return item ? item.slice( item.indexOf( '\n' ) + 1 ).trim() : '';
}
