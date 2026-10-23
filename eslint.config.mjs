/**
 * ESLint: WordPress's own JavaScript rules (@wordpress/eslint-plugin), with its formatting rules
 * rather than Prettier. The theme and plugin scripts are plain browser scripts that WordPress loads
 * as they are (no build step) and that use the `wp` globals.
 */
import wordpress from '@wordpress/eslint-plugin';
import globals from 'globals';

export default [
	{
		ignores: [ 'node_modules/', 'vendor/', 'dist/', 'dev/.tools/', '.agents/', '.claude/' ],
	},
	...wordpress.configs[ 'recommended-with-formatting' ],
	{
		files: [ 'tala-seo/**/*.js', 'tala-blog/**/*.js' ],
		languageOptions: {
			sourceType: 'script',
			globals: { ...globals.browser, wp: 'readonly' },
		},
	},
	{
		files: [ 'tala-seo/**/*.js' ],
		rules: {
			'@wordpress/i18n-text-domain': [ 'error', { allowedTextDomain: 'tala-seo' } ],
		},
	},
	{
		files: [ 'tala-blog/**/*.js' ],
		rules: {
			'@wordpress/i18n-text-domain': [ 'error', { allowedTextDomain: 'tala-blog' } ],
		},
	},
	{
		// Tools that run in Node: this file, lint-staged's, and bin/.
		files: [ '*.mjs', 'bin/**/*.mjs' ],
		languageOptions: {
			sourceType: 'module',
			globals: globals.node,
		},
	},
];
