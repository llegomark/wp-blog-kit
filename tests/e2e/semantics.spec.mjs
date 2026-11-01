/**
 * The HTML says what each part of an article page is: the article has a header (title and dates)
 * and a footer (categories and tags); "Keep reading" (related articles, then the older and newer
 * ones) is a navigation landmark of its own name, right after the article and before the author
 * box; each comment is an article of its own. "In this article" (the table of contents) is a
 * navigation landmark after the opening paragraphs, folded until opened, with links that jump to
 * their sections. Landmark names and heading order are axe's
 * (a11y.spec.mjs).
 */
import { test, expect } from '@playwright/test';
import { axeViolations, demoPages, wp } from './utils.mjs';

const pages = demoPages();

test( 'an article: a header with the title and dates, a footer with categories and tags', async ( { page } ) => {
	await page.goto( pages.article );
	const article = page.locator( 'main > article' );
	await expect( article ).toHaveCount( 1 );
	await expect( article.locator( ':scope > header h1' ) ).toHaveCount( 1 );
	await expect( article.locator( ':scope > header time' ).first() ).toBeVisible();
	await expect( article.locator( ':scope > footer' ) ).toContainText( 'Filed under' );
} );

test( '"Keep reading" is a navigation landmark right after the article, before the author box', async ( { page } ) => {
	await page.goto( pages.article );
	const nav = page.getByRole( 'navigation', { name: 'Keep reading' } );
	await expect( nav.getByRole( 'heading', { level: 2, name: 'Keep reading' } ) ).toBeVisible();
	await expect( nav.locator( '.tala-related h3 a' ).first() ).toBeVisible();
	await expect( nav.getByRole( 'link', { name: /Older article/ } ) ).toBeVisible();
	await expect( page.locator( 'main > article + nav[aria-label="Keep reading"] + .wp-block-post-author' ) ).toHaveCount( 1 );

	await page.goto( pages.page );
	await expect( page.getByRole( 'navigation', { name: 'Keep reading' } ) ).toHaveCount( 0 );
} );

test( 'each comment is an article', async ( { page } ) => {
	await page.goto( pages.withComments );
	const comments = page.locator( '.wp-block-comment-template li' );
	const count = await comments.count();
	expect( count ).toBeGreaterThan( 0 );
	await expect( page.locator( '.wp-block-comment-template li > article' ) ).toHaveCount( count );
} );

test( '"In this article": after the opening, folded until opened, its links jump to their sections', async ( { page } ) => {
	// The demo articles have two sections (and a subsection): two are enough here.
	wp( 'theme', 'mod', 'set', 'tala_blog_contents_sections', '2' );
	try {
		await page.goto( pages.article );
		const contents = page.getByRole( 'navigation', { name: 'In this article' } );
		await expect( contents ).toHaveCount( 1 );
		await expect( contents.locator( 'summary' ) ).toHaveText( 'In this article 2 sections' );
		expect( await contents.evaluate( ( nav ) => nav.previousElementSibling?.tagName ), 'After the opening paragraph' ).toBe( 'P' );
		const first = contents.getByRole( 'link' ).first();
		await expect( first ).toBeHidden();
		await contents.locator( 'summary' ).click();
		await expect( first ).toBeVisible();
		await expect( contents.locator( 'ol ol a' ) ).toHaveCount( 1 );
		const target = ( await first.getAttribute( 'href' ) ).slice( 1 );
		await expect( page.locator( `h2[id="${ target }"]` ) ).toHaveText( await first.textContent() );
		await first.click();
		await expect( page ).toHaveURL( new RegExp( `#${ target }$` ) );
		await expect( page.locator( `[id="${ target }"]` ) ).toBeInViewport();
		expect( await axeViolations( page ) ).toEqual( [] );
	} finally {
		wp( 'theme', 'mod', 'remove', 'tala_blog_contents_sections' );
	}
	await page.goto( pages.article );
	await expect( page.getByRole( 'navigation', { name: 'In this article' } ), 'Three sections by default' ).toHaveCount( 0 );
} );
