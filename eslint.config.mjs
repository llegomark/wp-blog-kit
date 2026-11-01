/**
 * ESLint: WordPress's own JavaScript rules (@wordpress/eslint-plugin), with its formatting rules
 * rather than Prettier. The theme and plugin scripts are plain browser scripts that WordPress loads
 * as they are (no build step) and that use the `wp` globals.
 */
import wordpress from '@wordpress/eslint-plugin';
import globals from 'globals';

export default [
	{
		// .cache/ holds the tests' copy of WordPress and the browser tests' output.
		ignores: [ 'node_modules/', 'vendor/', 'dist/', 'dev/.tools/', '.agents/', '.claude/', '.cache/' ],
	},
	...wordpress.configs[ 'recommended-with-formatting' ],
	{
		files: [ 'tala-seo/**/*.js', 'tala-blog/**/*.js', 'tala-ads/**/*.js' ],
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
		// Tools that run in Node: this file, lint-staged's, Playwright's, and bin/.
		files: [ '*.mjs', 'bin/**/*.mjs' ],
		languageOptions: {
			sourceType: 'module',
			globals: globals.node,
		},
	},
	// Browser tests: WordPress's rules for Playwright. They run in Node, and the functions they pass
	// to page.evaluate() run in the browser.
	...wordpress.configs[ 'test-playwright' ].map( ( config ) => ( { ...config, files: [ 'tests/e2e/**/*.mjs' ] } ) ),
	{
		files: [ 'tests/e2e/**/*.mjs' ],
		languageOptions: {
			sourceType: 'module',
			globals: { ...globals.node, ...globals.browser },
		},
	},
];
