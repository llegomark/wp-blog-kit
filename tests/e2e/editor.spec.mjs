/**
 * The block editor accepts every template, template part and pattern of the theme as it is: no
 * invalid blocks, no blocks it has to update from an old format, no missing blocks.
 */
import { test, expect } from '@playwright/test';
import { logIn } from './utils.mjs';

test( 'the editor parses the theme\'s templates, parts and patterns without problems', async ( { page } ) => {
	await logIn( page );
	await page.goto( '/wp-admin/post-new.php' );
	await page.waitForFunction( () => window.wp?.blocks?.getBlockTypes().length > 50 );

	// The parser reports blocks it had to update from an old format, or couldn't validate, on the
	// browser console.
	const logged = [];
	page.on( 'console', ( message ) => logged.push( message.text() ) );

	const result = await page.evaluate( async () => {
		const api = ( path ) => window.wp.apiFetch( { path } );
		const [ templates, parts, patterns ] = await Promise.all( [
			api( '/wp/v2/templates?per_page=100&context=edit' ),
			api( '/wp/v2/template-parts?per_page=100&context=edit' ),
			api( '/wp/v2/block-patterns/patterns' ),
		] );
		const items = [
			...templates.filter( ( t ) => 'tala-blog' === t.theme ).map( ( t ) => [ `template ${ t.slug }`, t.content.raw ] ),
			...parts.filter( ( t ) => 'tala-blog' === t.theme ).map( ( t ) => [ `part ${ t.slug }`, t.content.raw ] ),
			...patterns.filter( ( p ) => p.name.startsWith( 'tala-blog/' ) ).map( ( p ) => [ `pattern ${ p.name }`, p.content ] ),
		];

		const problems = [];
		let blocks = 0;
		const walk = ( list, where ) =>
			list.forEach( ( block ) => {
				blocks++;
				if ( ! block.isValid ) {
					problems.push( `${ where }: invalid ${ block.name }` );
				}
				if ( 'core/missing' === block.name ) {
					problems.push( `${ where }: missing block ${ block.attributes.originalName }` );
				}
				walk( block.innerBlocks, where );
			} );
		for ( const [ where, content ] of items ) {
			walk( window.wp.blocks.parse( content ), where );
		}
		return { items: items.map( ( [ where ] ) => where ), blocks, problems };
	} );

	expect( result.items ).toEqual(
		expect.arrayContaining( [ 'template index', 'template single', 'part header', 'part navigation-overlay', 'pattern tala-blog/site-brand' ] ),
	);
	expect( result.blocks ).toBeGreaterThan( 200 );
	expect( result.problems ).toEqual( [] );
	expect( logged.filter( ( text ) => /^Block (successfully updated|validation)/.test( text ) ) ).toEqual( [] );
} );
