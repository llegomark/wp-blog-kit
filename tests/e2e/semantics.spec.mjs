/**
 * The HTML says what each part of an article page is: the article is one article element, its
 * title band (title, byline and dates) its header and its topics (categories and tags) its footer;
 * "Read next" (related articles, then the older and newer ones) is a navigation landmark of its own
 * name, after the topics and before the author box; each comment is an article of its own. "On this
 * page" (the table of contents) is a disclosure after the opening paragraphs, closed until opened,
 * with a navigation landmark of links that jump to their sections. The article's text has NASH's
 * typography, and the 404 page is a status panel. Landmark names and heading order are axe's
 * (a11y.spec.mjs).
 */
import { test, expect } from '@playwright/test';
import { axeViolations, demoPages, wp, PHONE } from './utils.mjs';

const pages = demoPages();

test( 'an article: its title band is its header, its topics its footer', async ( { page } ) => {
	await page.goto( pages.article );
	const article = page.locator( 'main > article' );
	await expect( article ).toHaveCount( 1 );
	const header = article.locator( ':scope > header.tala-band--paper' );
	await expect( header.locator( 'h1.tala-display' ) ).toHaveCount( 1 );
	await expect( header.locator( '.tala-eyebrow' ) ).toHaveText( 'Classroom guides' );
	await expect( header.getByText( 'By Maria Santos' ) ).toBeVisible();
	await expect( header.getByRole( 'link', { name: 'Maria Santos' } ) ).toHaveAttribute( 'rel', 'author' );
	await expect( header.locator( 'time' ).first() ).toBeVisible();
	await expect( header.locator( '.tala-reading-time' ) ).toHaveText( /^\d+ min read$/ );
	const footer = article.locator( 'footer.tala-terms' );
	await expect( footer ).toContainText( 'Topics' );
	// Chips: square (2px), 44px tall.
	const chip = footer.getByRole( 'link', { name: 'Classroom guides' } );
	expect( await chip.evaluate( ( a ) => [ getComputedStyle( a ).borderRadius, a.getBoundingClientRect().height ] ) ).toEqual( [ '2px', 44 ] );
} );

test( '"Read next" is a navigation landmark after the topics, before the author box', async ( { page } ) => {
	await page.goto( pages.article );
	const nav = page.getByRole( 'navigation', { name: 'Read next' } );
	await expect( nav.getByRole( 'heading', { level: 2, name: 'Read next' } ) ).toBeVisible();
	await expect( nav.locator( '.tala-related .tala-row h3.tala-subtitle a' ).first() ).toBeVisible();
	await expect( nav.locator( 'img' ), 'No pictures' ).toHaveCount( 0 );
	await expect( nav.getByRole( 'link', { name: /Older article/ } ) ).toBeVisible();
	await expect( page.locator( 'main .tala-measure > footer.tala-terms + nav[aria-label="Read next"] + .wp-block-post-author' ) ).toHaveCount( 1 );

	// The newest article has only an older one: its cell takes the whole row.
	await page.goto( pages.withComments );
	const cells = page.locator( '.tala-post-nav__links' );
	const older = cells.locator( '.post-navigation-link-previous' );
	expect( await older.evaluate( ( el ) => Math.round( el.getBoundingClientRect().width ) ) ).toBe( await cells.evaluate( ( el ) => el.clientWidth ) );

	await page.goto( pages.page );
	await expect( page.getByRole( 'navigation', { name: 'Read next' } ) ).toHaveCount( 0 );
} );

test( 'each comment is an article', async ( { page } ) => {
	await page.goto( pages.withComments );
	const comments = page.locator( '.wp-block-comment-template li' );
	const count = await comments.count();
	expect( count ).toBeGreaterThan( 0 );
	await expect( page.locator( '.wp-block-comment-template li > article' ) ).toHaveCount( count );
} );

test( '"On this page": after the opening, closed until opened, its links jump to their sections', async ( { page } ) => {
	// The demo articles have two sections (and a subsection): two are enough here.
	wp( 'theme', 'mod', 'set', 'tala_blog_contents_sections', '2' );
	try {
		await page.goto( pages.article );
		const box = page.locator( 'details.tala-contents' );
		await expect( box ).toHaveCount( 1 );
		await expect( box ).not.toHaveAttribute( 'open' );
		await expect( box.locator( 'summary' ) ).toHaveText( 'On this page 2 sections' );
		expect( await box.evaluate( ( el ) => el.previousElementSibling?.tagName ), 'After the opening paragraph' ).toBe( 'P' );
		const contents = page.getByRole( 'navigation', { name: 'On this page' } );
		await expect( contents, 'Hidden while closed' ).toHaveCount( 0 );
		await box.locator( 'summary' ).click();
		await expect( contents ).toBeVisible();
		const first = contents.getByRole( 'link' ).first();
		await expect( contents.locator( 'ol ol a' ) ).toHaveCount( 1 );
		const target = ( await first.getAttribute( 'href' ) ).slice( 1 );
		const heading = page.locator( `h2[id="${ target }"]` );
		await expect( first, 'Named by the heading alone, without its number' ).toHaveAccessibleName( await heading.textContent() );
		await expect( first.locator( '.tala-contents-num' ) ).toHaveText( '01' );
		await first.click();
		await expect( page ).toHaveURL( new RegExp( `#${ target }$` ) );
		await expect( heading ).toBeInViewport();
		expect( await axeViolations( page ) ).toEqual( [] );
	} finally {
		wp( 'theme', 'mod', 'remove', 'tala_blog_contents_sections' );
	}
	await page.goto( pages.article );
	await expect( page.locator( 'details.tala-contents' ), 'Three sections by default' ).toHaveCount( 0 );
} );

test( 'the article\'s text: h2 under a rule, blockquote on wash with a 2px cobalt rule, cobalt links', async ( { page } ) => {
	await page.goto( pages.article );
	const text = page.locator( '.wp-block-post-content' );
	const style = ( locator, props ) => locator.evaluate( ( el, names ) => names.map( ( name ) => getComputedStyle( el )[ name ] ).join( ' | ' ), props );
	const h2 = text.locator( 'h2' ).first();
	expect( await style( h2, [ 'borderTopWidth', 'borderTopStyle', 'borderTopColor', 'paddingTop', 'marginTop', 'fontWeight' ] ) ).toBe( '1px | solid | rgb(203, 207, 212) | 28px | 40px | 400' );
	const h3 = text.locator( 'h3' ).first();
	expect( await style( h3, [ 'fontSize', 'fontWeight' ] ) ).toBe( '19px | 450' );
	const quote = text.locator( 'blockquote' ).first();
	expect( await quote.evaluate( ( el ) => el.getBoundingClientRect().width === el.parentElement.getBoundingClientRect().width ), 'The width of the text' ).toBe( true );
	expect( await style( quote, [ 'borderLeftWidth', 'borderLeftStyle', 'borderLeftColor', 'backgroundColor', 'borderTopLeftRadius' ] ) ).toBe( '2px | solid | rgb(19, 72, 220) | rgb(235, 235, 236) | 0px' );
	expect( await style( text.locator( 'ul' ).first(), [ 'listStyleType' ] ) ).toBe( 'square' );
	expect( await style( text.locator( 'table' ).first(), [ 'fontVariantNumeric' ] ) ).toBe( 'tabular-nums' );
	expect( await style( text.locator( '.wp-block-table' ).first(), [ 'overflowX', 'borderTopWidth', 'borderTopStyle' ] ) ).toBe( 'auto | 1px | solid' );
} );

test.describe( 'the 404 page', () => {
	test.use( PHONE );

	for ( const colorScheme of [ 'light', 'dark' ] ) {
		test( `a graph-paper status panel: "404", the heading, a search field and the way home (${ colorScheme })`, async ( { page } ) => {
			await page.emulateMedia( { colorScheme } );
			await page.goto( pages.notFound );
			const panel = page.locator( 'main .tala-band--paper .tala-status' );
			const code = panel.locator( '.tala-status-code' );
			await expect( code ).toHaveText( '404' );
			await expect( code ).toHaveAttribute( 'aria-hidden', 'true' );
			expect( await code.evaluate( ( el ) => [ getComputedStyle( el ).fontStyle, getComputedStyle( el ).fontWeight ] ) ).toEqual( [ 'italic', '300' ] );
			await expect( panel.getByRole( 'heading', { level: 1, name: 'Page not found' } ) ).toBeVisible();
			await expect( panel.getByRole( 'searchbox' ) ).toBeVisible();
			await expect( panel.getByRole( 'link', { name: 'Back to the home page' } ) ).toHaveAttribute( 'href', /\/$/ );
			expect( await axeViolations( page ) ).toEqual( [] );
		} );
	}
} );

for ( const colorScheme of [ 'light', 'dark' ] ) {
	test.describe( `article, phone, ${ colorScheme }`, () => {
		test.use( { ...PHONE, colorScheme } );

		test( 'axe finds nothing in the article, its open contents and its footer', async ( { page } ) => {
			wp( 'theme', 'mod', 'set', 'tala_blog_contents_sections', '2' );
			try {
				await page.goto( pages.article );
				await page.locator( 'details.tala-contents summary' ).click();
				expect( await axeViolations( page ) ).toEqual( [] );
			} finally {
				wp( 'theme', 'mod', 'remove', 'tala_blog_contents_sections' );
			}
		} );
	} );
}
