/**
 * Breadcrumbs block (editor side). The trail is built by PHP; the editor shows a preview.
 */
( function ( wp ) {
	const el = wp.element.createElement;
	const ServerSideRender = wp.serverSideRender;
	const useBlockProps = wp.blockEditor.useBlockProps;

	wp.blocks.registerBlockType( 'tala-seo/breadcrumbs', {
		edit: function Edit( props ) {
			return el(
				'div',
				useBlockProps(),
				el( ServerSideRender, { block: 'tala-seo/breadcrumbs', attributes: props.attributes } )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
