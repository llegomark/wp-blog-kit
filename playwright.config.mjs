/**
 * Browser tests (tests/e2e/) against the local site from dev/site.sh: the phone menu, layout,
 * logo, accessibility (axe) and the block editor's validation of the theme's templates.
 *
 * They run in the Google Chrome installed on the machine (GitHub's runners have it too), so no
 * browser is downloaded. Tests change site settings with WP-CLI and put them back, so they run
 * one at a time.
 */
import { defineConfig } from '@playwright/test';

const baseURL = process.env.WP_BASE_URL || 'http://127.0.0.1:8080';

export default defineConfig( {
	testDir: 'tests/e2e',
	testMatch: '*.spec.mjs',
	workers: 1,
	fullyParallel: false,
	forbidOnly: !! process.env.CI,
	retries: process.env.CI ? 1 : 0,
	reporter: process.env.CI ? [ [ 'github' ], [ 'list' ] ] : 'list',
	outputDir: '.cache/playwright',
	use: {
		baseURL,
		channel: 'chrome',
		viewport: { width: 1280, height: 900 },
		trace: 'retain-on-failure',
		// Classic scrollbars, as desktop Chrome on Linux and Windows has them: Playwright hides them
		// by default, which would hide layout jumps between short and long pages.
		launchOptions: { ignoreDefaultArgs: [ '--hide-scrollbars' ] },
	},
	// dev/serve.sh, unless the site is already being served.
	webServer: {
		command: 'dev/serve.sh',
		url: baseURL,
		reuseExistingServer: true,
		timeout: 60_000,
	},
} );
