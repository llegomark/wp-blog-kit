/**
 * Classic content, the cases of WordPress's Theme Unit Test that blocks don't cover: images
 * aligned by the classic editor's classes, [caption] and [gallery], fixed-width embeds, plain tables
 * and quotes, password forms, and titles with very long words. On a phone nothing may widen the page.
 */
import { test, expect } from '@playwright/test';
import { wp, axeViolations, PHONE } from './utils.mjs';

const covers = () => wp( 'post', 'list', '--post_type=attachment', '--s=Cover illustration', '--orderby=ID', '--order=asc', '--field=ID' ).split( '\n' );

/**
 * Classic editor HTML (no block markup), as older posts and the Classic block hold it.
 *
 * @param {string[]} images Attachment IDs.
 * @return {string} Post content.
 */
function classicContent( images ) {
	const url = ( id, size ) => wp( 'eval', `echo wp_get_attachment_image_url( ${ id }, '${ size }' );` );
	const words = 'Text wraps around the image beside it. '.repeat( 12 );
	return [
		`<p><img class="alignleft size-thumbnail" src="${ url( images[ 0 ], 'thumbnail' ) }" width="150" height="150" alt="Left">${ words }</p>`,
		`<p><img class="alignright size-thumbnail" src="${ url( images[ 1 ], 'thumbnail' ) }" width="150" height="150" alt="Right">${ words }</p>`,
		`[caption id="" align="aligncenter" width="1200"]<img src="${ url( images[ 2 ], 'full' ) }" width="1200" height="675" alt="Wide"> A caption under a wide image[/caption]`,
		'<iframe src="about:blank" width="560" height="315" title="A video player"></iframe>',
		'<blockquote><p>A quote from the classic editor.</p></blockquote>',
		'<table><thead><tr><th>Grade</th><th>Learners</th></tr></thead><tbody><tr><td>1</td><td>32</td></tr></tbody></table>',
		`[gallery ids="${ images.slice( 0, 3 ).join( ',' ) }"]`,
	].join( '\n\n' );
}

test.describe( 'phone', () => {
	test.use( PHONE );
	let page_;
	let protectedPage;

	test.beforeAll( () => {
		const images = covers();
		const meta = '--meta_input={"_tala_seo_description":"Classic content."}';
		page_ = wp( 'post', 'create', '--post_type=page', '--post_status=publish', '--post_title=Classic content', meta, `--post_content=${ classicContent( images ) }`, '--porcelain' );
		protectedPage = wp( 'post', 'create', '--post_type=page', '--post_status=publish', '--post_title=Members', '--post_password=enter', meta, '--post_content=Hidden.', '--porcelain' );
	} );

	test.afterAll( () => {
		wp( 'post', 'delete', page_, protectedPage, '--force' );
	} );

	test( 'aligned images, captions, embeds, quotes, tables and galleries fit and look like their blocks', async ( { page } ) => {
		await page.goto( `/?page_id=${ page_ }` );
		await page.waitForFunction( () => [ ...document.images ].every( ( image ) => image.complete ) );
		const layout = await page.evaluate( () => {
			const content = document.querySelector( '.wp-block-post-content' );
			const column = content.getBoundingClientRect();
			const style = ( selector, property ) => getComputedStyle( content.querySelector( selector ) )[ property ];
			const fits = ( selector ) => [ ...content.querySelectorAll( selector ) ].every( ( el ) => el.getBoundingClientRect().right <= column.right + 0.5 );
			return {
				pageScrolls: document.documentElement.scrollWidth > document.documentElement.clientWidth,
				left: style( 'img.alignleft', 'float' ),
				right: style( 'img.alignright', 'float' ),
				fit: { caption: fits( '.wp-caption' ), iframe: fits( 'iframe' ), gallery: fits( '.gallery img' ) },
				captionText: style( '.wp-caption-text', 'fontSize' ) !== style( 'p', 'fontSize' ),
				// Washed like the Quote block: a background of its own, not the page's.
				quoteWashed: ! [ 'rgba(0, 0, 0, 0)', getComputedStyle( document.body ).backgroundColor ].includes( style( 'blockquote', 'backgroundColor' ) ),
				cell: style( 'td', 'borderTopWidth' ),
				galleryColumns: style( '.gallery', 'gridTemplateColumns' ).split( ' ' ).length,
			};
		} );
		expect( layout ).toEqual( {
			pageScrolls: false,
			left: 'left',
			right: 'right',
			fit: { caption: true, iframe: true, gallery: true },
			captionText: true,
			quoteWashed: true,
			cell: '1px',
			galleryColumns: 3,
		} );
		expect( await axeViolations( page ) ).toEqual( [] );
	} );

	test( 'the password field of a protected page is a full field', async ( { page } ) => {
		await page.goto( `/?page_id=${ protectedPage }` );
		const field = page.getByLabel( 'Password:' );
		await expect( field ).toBeVisible();
		expect( ( await field.boundingBox() ).width, 'Up to 20rem (320 px) wide, not 20 characters' ).toBeGreaterThanOrEqual( 300 );
	} );
} );

test.describe( 'a title with a very long word', () => {
	test.use( PHONE );
	let longTitle;
	let neighbour;

	test.beforeAll( () => {
		neighbour = wp( 'post', 'list', '--post_type=post', '--post_status=publish', '--orderby=date', '--order=DESC', '--posts_per_page=1', '--field=ID' );
		// As agents publish (Tala SEO's readme): a draft, its description and featured image, then publish.
		longTitle = wp(
			'post',
			'create',
			'--post_title=Taumatawhakatangihangakoauauotamateaturipukakapikimaungahoronukupokaiwhenuakitanatahu',
			'--meta_input={"_tala_seo_description":"A long word."}',
			'--post_content=A post with a very long word in its title.',
			'--porcelain',
		);
		wp( 'post', 'meta', 'set', longTitle, '_thumbnail_id', covers()[ 0 ] );
		wp( 'post', 'update', longTitle, '--post_status=publish' );
	} );

	test.afterAll( () => {
		wp( 'post', 'delete', longTitle, '--force' );
	} );

	test( 'wraps in the title and in the next-article link of the article before it', async ( { page } ) => {
		for ( const id of [ longTitle, neighbour ] ) {
			await page.goto( `/?p=${ id }` );
			expect( await page.evaluate( () => document.documentElement.scrollWidth > document.documentElement.clientWidth ), `post ${ id }` ).toBe( false );
		}
		await expect( page.getByRole( 'link', { name: /^Taumata/ } ) ).toBeVisible();
	} );
} );
