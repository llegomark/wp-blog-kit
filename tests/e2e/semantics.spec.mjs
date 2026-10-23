/**
 * The HTML says what each part of an article page is: the article has a header (title and dates)
 * and a footer (categories and tags); the links to the previous and next articles are a navigation
 * landmark of their own name; each comment is an article of its own. Landmark names and heading
 * order are axe's (a11y.spec.mjs).
 */
import { test, expect } from '@playwright/test';
import { demoPages } from './utils.mjs';

const pages = demoPages();

test( 'an article: a header with the title and dates, a footer with categories and tags', async ( { page } ) => {
	await page.goto( pages.article );
	const article = page.locator( 'main > article' );
	await expect( article ).toHaveCount( 1 );
	await expect( article.locator( ':scope > header h1' ) ).toHaveCount( 1 );
	await expect( article.locator( ':scope > header time' ).first() ).toBeVisible();
	await expect( article.locator( ':scope > footer' ) ).toContainText( 'Filed under' );
} );

test( 'the previous and next articles are a navigation landmark of their own', async ( { page } ) => {
	await page.goto( pages.article );
	const links = page.getByRole( 'navigation', { name: 'Previous and next articles' } ).getByRole( 'link' );
	await expect( links ).toHaveCount( 2 );

	await page.goto( pages.page );
	await expect( page.getByRole( 'navigation', { name: 'Previous and next articles' } ) ).toHaveCount( 0 );
} );

test( 'each comment is an article', async ( { page } ) => {
	await page.goto( pages.withComments );
	const comments = page.locator( '.wp-block-comment-template li' );
	const count = await comments.count();
	expect( count ).toBeGreaterThan( 0 );
	await expect( page.locator( '.wp-block-comment-template li > article' ) ).toHaveCount( count );
} );
