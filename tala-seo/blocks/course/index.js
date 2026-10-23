/**
 * Course block (editor side). The details are entered in the block settings; PHP builds the card
 * and the structured data, and the editor shows a preview of the card.
 *
 * @param {Object} wp WordPress's script globals.
 */
( function( wp ) {
	const el = wp.element.createElement;
	const Fragment = wp.element.Fragment;
	const { __ } = wp.i18n;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { Notice, PanelBody, Placeholder, SelectControl, TextControl, TextareaControl } = wp.components;
	const { useSelect } = wp.data;
	const ServerSideRender = wp.serverSideRender;

	/**
	 * A text field bound to one attribute.
	 *
	 * @param {Object} props Block props.
	 * @param {string} key   Attribute.
	 * @param {string} label Label.
	 * @param {Object} more  More TextControl props (type, help, placeholder).
	 * @return {Object} Element.
	 */
	function field( props, key, label, more ) {
		return el(
			TextControl,
			Object.assign(
				{
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
					label,
					value: props.attributes[ key ],
					onChange( value ) {
						props.setAttributes( { [ key ]: value } );
					},
				},
				more,
			),
		);
	}

	/**
	 * A select bound to one attribute.
	 *
	 * @param {Object}   props   Block props.
	 * @param {string}   key     Attribute.
	 * @param {string}   label   Label.
	 * @param {Object[]} options Options ({ label, value }).
	 * @return {Object} Element.
	 */
	function choice( props, key, label, options ) {
		return el( SelectControl, {
			__nextHasNoMarginBottom: true,
			__next40pxDefaultSize: true,
			label,
			value: String( props.attributes[ key ] ),
			options,
			onChange( value ) {
				props.setAttributes( { [ key ]: 'level' === key ? Number( value ) : value } );
			},
		} );
	}

	function Settings( props ) {
		const a = props.attributes;
		return el(
			InspectorControls,
			null,
			el(
				PanelBody,
				{ title: __( 'Course', 'tala-seo' ) },
				! a.description.trim() &&
					el(
						Notice,
						{ status: 'warning', isDismissible: false },
						__( 'For search engines, add:', 'tala-seo' ),
						el( 'ul', null, el( 'li', null, __( 'A description', 'tala-seo' ) ) ),
					),
				el(
					'p',
					{ className: 'tala-seo-course-help' },
					__( 'A course has lessons or modules, a clear learning outcome and an instructor. An event or a single short video isn\'t a course.', 'tala-seo' ),
				),
				field( props, 'name', __( 'Name', 'tala-seo' ), {
					placeholder: props.postTitle,
					help: __( 'Leave empty to use the post title. No prices or promotions in the name.', 'tala-seo' ),
				} ),
				choice( props, 'level', __( 'Name as', 'tala-seo' ), [
					{ label: __( 'Heading 2', 'tala-seo' ), value: '2' },
					{ label: __( 'Heading 3', 'tala-seo' ), value: '3' },
					{ label: __( 'Heading 4', 'tala-seo' ), value: '4' },
				] ),
				el( TextareaControl, {
					__nextHasNoMarginBottom: true,
					label: __( 'Description', 'tala-seo' ),
					value: a.description,
					rows: 4,
					help: __( 'What the learner will be able to do. Google shows about the first 60 characters.', 'tala-seo' ),
					onChange( value ) {
						props.setAttributes( { description: value } );
					},
				} ),
				field( props, 'provider', __( 'Offered by', 'tala-seo' ), {
					help: __( 'Leave empty when this site offers the course.', 'tala-seo' ),
				} ),
				a.provider.trim() &&
					field( props, 'providerUrl', __( 'Their website', 'tala-seo' ), { type: 'url', placeholder: 'https://' } ),
			),
			el(
				PanelBody,
				{ title: __( 'Details', 'tala-seo' ), initialOpen: false },
				field( props, 'educationalLevel', __( 'Level', 'tala-seo' ), {
					help: __( 'For example: Beginner, Intermediate, Advanced.', 'tala-seo' ),
				} ),
				choice( props, 'courseMode', __( 'Format', 'tala-seo' ), [
					{ label: __( 'Not stated', 'tala-seo' ), value: '' },
					{ label: __( 'Online', 'tala-seo' ), value: 'Online' },
					{ label: __( 'In person', 'tala-seo' ), value: 'Onsite' },
					{ label: __( 'Online and in person', 'tala-seo' ), value: 'Blended' },
				] ),
				field( props, 'workload', __( 'Hours to complete', 'tala-seo' ), { type: 'number', min: 0, step: 'any' } ),
				field( props, 'instructor', __( 'Instructor', 'tala-seo' ) ),
			),
			el(
				PanelBody,
				{ title: __( 'Cost', 'tala-seo' ), initialOpen: false },
				field( props, 'price', __( 'Price', 'tala-seo' ), {
					type: 'number',
					min: 0,
					step: 'any',
					help: __( '0 for a free course.', 'tala-seo' ),
				} ),
				field( props, 'priceCurrency', __( 'Currency', 'tala-seo' ), {
					maxLength: 3,
					help: __( 'Three-letter code, such as PHP, USD or EUR.', 'tala-seo' ),
				} ),
				field( props, 'enrollUrl', __( 'Enrollment page', 'tala-seo' ), { type: 'url', placeholder: 'https://' } ),
			),
		);
	}

	wp.blocks.registerBlockType( 'tala-seo/course', {
		edit: function Edit( props ) {
			const postTitle = useSelect( ( select ) => select( 'core/editor' )?.getEditedPostAttribute( 'title' ) || '', [] );
			const blockProps = useBlockProps();
			const preview = props.attributes.name || postTitle
				? el( ServerSideRender, {
					block: 'tala-seo/course',
					// The server knows only the saved title; show the one being edited.
					attributes: Object.assign( {}, props.attributes, { name: props.attributes.name || postTitle } ),
					// The preview's own wrapper would repeat the colours and margins of this one.
					skipBlockSupportAttributes: true,
				} )
				: el( Placeholder, {
					icon: 'welcome-learn-more',
					label: __( 'Course', 'tala-seo' ),
					instructions: __( 'Give the post a title, or enter the course name in the block settings.', 'tala-seo' ),
				} );
			return el(
				Fragment,
				null,
				el( Settings, Object.assign( {}, props, { postTitle } ) ),
				el( 'div', blockProps, preview ),
			);
		},
		save() {
			return null;
		},
	} );
}( window.wp ) );
