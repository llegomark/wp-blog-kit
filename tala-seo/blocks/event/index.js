/**
 * Event block (editor side). The details are entered in the block settings; PHP builds the card
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

	/**
	 * What Google needs that the event doesn't have yet.
	 *
	 * @param {Object} a Attributes.
	 * @return {string[]} Missing details.
	 */
	function missing( a ) {
		const needs = [];
		if ( ! a.startDate ) {
			needs.push( __( 'The date it starts', 'tala-seo' ) );
		}
		if ( ! a.streetAddress.trim() && ! a.addressLocality.trim() ) {
			needs.push( __( 'The street address or town', 'tala-seo' ) );
		}
		return needs;
	}

	function Settings( props ) {
		const a = props.attributes;
		const needs = missing( a );
		return el(
			InspectorControls,
			null,
			el(
				PanelBody,
				{ title: __( 'Event', 'tala-seo' ) },
				needs.length > 0 &&
					el(
						Notice,
						{ status: 'warning', isDismissible: false },
						__( 'For search engines, add:', 'tala-seo' ),
						el(
							'ul',
							null,
							needs.map( ( need ) => el( 'li', { key: need }, need ) ),
						),
					),
				field( props, 'name', __( 'Name', 'tala-seo' ), {
					placeholder: props.postTitle,
					help: __( 'Leave empty to use the post title.', 'tala-seo' ),
				} ),
				choice( props, 'level', __( 'Name as', 'tala-seo' ), [
					{ label: __( 'Heading 2', 'tala-seo' ), value: '2' },
					{ label: __( 'Heading 3', 'tala-seo' ), value: '3' },
					{ label: __( 'Heading 4', 'tala-seo' ), value: '4' },
				] ),
				field( props, 'startDate', __( 'Starts on', 'tala-seo' ), { type: 'date' } ),
				field( props, 'startTime', __( 'Starts at', 'tala-seo' ), {
					type: 'time',
					help: __( 'Leave the times empty for an all-day event. Times are in the site\'s timezone.', 'tala-seo' ),
				} ),
				field( props, 'endDate', __( 'Ends on', 'tala-seo' ), {
					type: 'date',
					help: __( 'For an event over several days. Leave empty when it ends the day it starts.', 'tala-seo' ),
				} ),
				a.startTime && field( props, 'endTime', __( 'Ends at', 'tala-seo' ), { type: 'time' } ),
				choice( props, 'status', __( 'Status', 'tala-seo' ), [
					{ label: __( 'Taking place as planned', 'tala-seo' ), value: 'EventScheduled' },
					{ label: __( 'Rescheduled to the dates above', 'tala-seo' ), value: 'EventRescheduled' },
					{ label: __( 'Postponed, new date not set', 'tala-seo' ), value: 'EventPostponed' },
					{ label: __( 'Cancelled', 'tala-seo' ), value: 'EventCancelled' },
				] ),
				'EventRescheduled' === a.status &&
					field( props, 'previousStartDate', __( 'First planned for', 'tala-seo' ), { type: 'date' } ),
				el( TextareaControl, {
					__nextHasNoMarginBottom: true,
					label: __( 'Description', 'tala-seo' ),
					value: a.description,
					rows: 4,
					onChange( value ) {
						props.setAttributes( { description: value } );
					},
				} ),
			),
			el(
				PanelBody,
				{ title: __( 'Place', 'tala-seo' ) },
				el(
					'p',
					{ className: 'tala-seo-event-help' },
					__( 'Google shows events that take place at a real address and that anyone can book. Online-only events aren\'t eligible.', 'tala-seo' ),
				),
				field( props, 'place', __( 'Venue', 'tala-seo' ) ),
				field( props, 'streetAddress', __( 'Street address', 'tala-seo' ) ),
				field( props, 'addressLocality', __( 'Town or city', 'tala-seo' ) ),
				field( props, 'addressRegion', __( 'Province, state or region', 'tala-seo' ) ),
				field( props, 'postalCode', __( 'Postal code', 'tala-seo' ) ),
				field( props, 'addressCountry', __( 'Country', 'tala-seo' ) ),
			),
			el(
				PanelBody,
				{ title: __( 'Tickets', 'tala-seo' ), initialOpen: false },
				field( props, 'price', __( 'Price', 'tala-seo' ), {
					type: 'number',
					min: 0,
					step: 'any',
					help: __( 'The lowest price, fees included. 0 for a free event.', 'tala-seo' ),
				} ),
				field( props, 'priceCurrency', __( 'Currency', 'tala-seo' ), {
					maxLength: 3,
					help: __( 'Three-letter code, such as PHP, USD or EUR.', 'tala-seo' ),
				} ),
				choice( props, 'availability', __( 'Availability', 'tala-seo' ), [
					{ label: __( 'Not stated', 'tala-seo' ), value: '' },
					{ label: __( 'On sale', 'tala-seo' ), value: 'InStock' },
					{ label: __( 'Sold out', 'tala-seo' ), value: 'SoldOut' },
					{ label: __( 'Pre-order', 'tala-seo' ), value: 'PreOrder' },
				] ),
				field( props, 'ticketUrl', __( 'Ticket page', 'tala-seo' ), { type: 'url', placeholder: 'https://' } ),
			),
			el(
				PanelBody,
				{ title: __( 'Organizer', 'tala-seo' ), initialOpen: false },
				field( props, 'organizer', __( 'Name', 'tala-seo' ) ),
				field( props, 'organizerUrl', __( 'Website', 'tala-seo' ), { type: 'url', placeholder: 'https://' } ),
			),
		);
	}

	wp.blocks.registerBlockType( 'tala-seo/event', {
		edit: function Edit( props ) {
			const postTitle = useSelect( ( select ) => select( 'core/editor' )?.getEditedPostAttribute( 'title' ) || '', [] );
			const blockProps = useBlockProps();
			const preview = props.attributes.startDate
				? el( ServerSideRender, {
					block: 'tala-seo/event',
					// The server knows only the saved title; show the one being edited.
					attributes: Object.assign( {}, props.attributes, { name: props.attributes.name || postTitle } ),
					// The preview's own wrapper would repeat the colours and margins of this one.
					skipBlockSupportAttributes: true,
				} )
				: el( Placeholder, {
					icon: 'calendar-alt',
					label: __( 'Event', 'tala-seo' ),
					instructions: __( 'Enter the date, place and tickets in the block settings.', 'tala-seo' ),
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
