#!/usr/bin/env node
/**
 * dev/unit-test-check.mjs: runs the automatic part of WordPress's Theme Unit Test on the site from
 * dev/unit-test-site.sh (serve it first with `dev/unit-test-site.sh --serve`). Every post, page,
 * category, tag, author page and a few more (paged home, a date archive, search, a 404), on a phone
 * and a desktop:
 * - every page answers (404 only where it should);
 * - no PHP notices (wp-content/debug.log) and no JavaScript errors;
 * - nothing widens the page sideways;
 * - every page has a level-one heading;
 * - axe finds nothing the theme causes, in light and dark mode (the test posts' own content is left
 *   out: some of it is inaccessible on purpose).
 * What it can't judge (how things look) is the handbook's checklist:
 * https://make.wordpress.org/themes/handbook/review/theme-unit-test/
 *
 * Exits 1 when it finds anything.
 */
import { execFileSync } from 'node:child_process';
import { readFileSync, writeFileSync } from 'node:fs';
import { homedir } from 'node:os';
import { chromium } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

const SITE = process.env.WP_UNIT_SITE || `${ homedir() }/Sites/themeunit`;
const BASE = 'http://127.0.0.1:8081';

// Test posts whose content is meant to overflow: blocks set not to wrap.
const OVERFLOW_ON_PURPOSE = [ '/2024/02/author/', '/2024/02/buttons/' ];

/**
 * Runs WP-CLI on the unit test site.
 *
 * @param {...string} args Arguments.
 * @return {string} Output.
 */
const wp = ( ...args ) => execFileSync( 'wp', [ `--path=${ SITE }`, ...args ], { encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'ignore' ] } ).trim();

const urls = [
	...wp( 'post', 'list', '--post_type=post,page', '--post_status=publish', '--posts_per_page=-1', '--field=url' ).split( '\n' ),
	...wp( 'term', 'list', 'category', '--field=url' ).split( '\n' ),
	...wp( 'term', 'list', 'post_tag', '--field=url' ).split( '\n' ),
	...wp( 'eval', 'foreach ( get_users() as $u ) { echo get_author_posts_url( $u->ID ), "\n"; }' ).split( '\n' ),
	...[ '/page/2/', '/page/3/', '/2013/01/', '/?s=lorem', '/?s=zzqnothing', '/no-such-page/' ].map( ( path ) => BASE + path ),
].filter( Boolean );

const log = `${ SITE }/wp-content/debug.log`;
writeFileSync( log, '' );

const browser = await chromium.launch( { channel: 'chrome', ignoreDefaultArgs: [ '--hide-scrollbars' ] } );
const devices = {
	phone: { viewport: { width: 412, height: 915 }, isMobile: true, hasTouch: true, colorScheme: 'light' },
	'phone, dark': { viewport: { width: 412, height: 915 }, isMobile: true, hasTouch: true, colorScheme: 'dark' },
	desktop: { viewport: { width: 1280, height: 900 }, colorScheme: 'light' },
};
const problems = [];

for ( const [ device, options ] of Object.entries( devices ) ) {
	const context = await browser.newContext( options );
	for ( const url of urls ) {
		const path = url.replace( BASE, '' );
		const page = await context.newPage();
		page.on( 'pageerror', ( error ) => problems.push( `${ path } (${ device }): JavaScript: ${ error.message }` ) );
		const response = await page.goto( url );
		const status = response?.status() ?? 0;
		if ( status !== ( path === '/no-such-page/' ? 404 : 200 ) ) {
			problems.push( `${ path } (${ device }): HTTP ${ status }` );
		}
		const layout = await page.evaluate( () => ( {
			scrolls: document.documentElement.scrollWidth > document.documentElement.clientWidth,
			h1: document.querySelectorAll( 'h1' ).length,
		} ) );
		if ( layout.scrolls && ! OVERFLOW_ON_PURPOSE.includes( path ) ) {
			problems.push( `${ path } (${ device }): the page scrolls sideways` );
		}
		if ( ! layout.h1 ) {
			problems.push( `${ path } (${ device }): no level-one heading` );
		}
		if ( device.startsWith( 'phone' ) ) {
			const result = await new AxeBuilder( { page } ).exclude( '.wp-block-post-content' ).exclude( '.wp-block-comment-content' ).analyze();
			for ( const violation of result.violations ) {
				problems.push( `${ path } (${ device }): axe ${ violation.id }: ${ violation.nodes.map( ( node ) => node.target.join( ' ' ) ).join( ', ' ) }` );
			}
		}
		await page.close();
	}
	await context.close();
	process.stdout.write( `${ device }: ${ urls.length } pages\n` );
}
await browser.close();

// PHP errors, warnings, notices and deprecations, and database errors (not WordPress's own notes,
// such as "Automatic updates starting"). WP-CLI's own deprecations come from its phar.
const PHP_PROBLEM = /PHP (Fatal error|Parse error|Warning|Notice|Deprecated)|WordPress database error/;
for ( const line of readFileSync( log, 'utf8' ).split( '\n' ).filter( ( text ) => PHP_PROBLEM.test( text ) && ! text.includes( 'phar://' ) ) ) {
	problems.push( `debug.log: ${ line }` );
}
for ( const problem of problems ) {
	process.stdout.write( `${ problem }\n` );
}
process.stdout.write( problems.length ? `\n${ problems.length } problems found.\n` : 'Theme Unit Test check: no problems found.\n' );
process.exitCode = problems.length ? 1 : 0;
