/**
 * What the pre-commit hook (.husky/pre-commit) checks: each task gets the staged files that match
 * its pattern.
 */
import { existsSync } from 'node:fs';

const hasPhpcs = existsSync( new URL( 'vendor/bin/phpcs', import.meta.url ) );
const validateTheme = 'node bin/validate-theme.mjs';

export default {
	// WordPress's CSS and JavaScript coding standards.
	'*.css': 'stylelint',
	'*.{js,mjs}': 'eslint',
	// WordPress Coding Standards for PHP. Without the Composer packages, PHPCS can't run, so the
	// check is skipped with a note instead.
	'*.php': hasPhpcs
		? 'composer run lint --'
		: {
			title: 'PHPCS skipped: run "composer install" to check PHP files',
			task: () => {},
		},
	// The validator reads the whole theme and plugin, but only reports problems in the files it's given.
	'tala-blog/{theme.json,style.css,styles/**/*.json,templates/**/*.html,parts/**/*.html,patterns/**/*.php}':
		validateTheme,
	'tala-seo/{tala-seo.php,blocks/**/block.json}': validateTheme,
};
