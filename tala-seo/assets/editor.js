/**
 * Tala SEO in the block editor: the "SEO" panel (search preview, title, meta description,
 * minor edit, noindex, canonical) and a checklist in the pre-publish sidebar.
 * Publishing is enforced on the server; this only explains what is missing.
 */
( function ( wp, cfg ) {
	'use strict';

	if ( ! cfg ) {
		return;
	}

	const el = wp.element.createElement;
	const Fragment = wp.element.Fragment;
	const { __, _n, sprintf } = wp.i18n;
	const { TextControl, TextareaControl, ToggleControl } = wp.components;
	const { useSelect } = wp.data;
	const { useEntityProp } = wp.coreData;
	const editorApi = wp.editor || {};
	const legacyApi = wp.editPost || {};
	const DocumentPanel = editorApi.PluginDocumentSettingPanel || legacyApi.PluginDocumentSettingPanel;
	const PrePublishPanel = editorApi.PluginPrePublishPanel || legacyApi.PluginPrePublishPanel;
	const KEYS = cfg.keys;
	const NONE = [];

	function countImagesWithoutAlt( blocks ) {
		let count = 0;
		blocks.forEach( function ( block ) {
			if ( block.name === 'core/image' && block.attributes.url && ! String( block.attributes.alt || '' ).trim() ) {
				count++;
			}
			if ( block.innerBlocks && block.innerBlocks.length ) {
				count += countImagesWithoutAlt( block.innerBlocks );
			}
		} );
		return count;
	}

	function useSeo() {
		const state = useSelect( function ( select ) {
			const editor = select( 'core/editor' );
			const core = select( 'core' );
			const featuredId = editor.getEditedPostAttribute( 'featured_media' ) || 0;
			const authorId = editor.getEditedPostAttribute( 'author' );
			return {
				postType: editor.getCurrentPostType(),
				isLive: [ 'publish', 'future' ].indexOf( editor.getCurrentPostAttribute( 'status' ) ) !== -1,
				title: editor.getEditedPostAttribute( 'title' ) || '',
				link: editor.getPermalink() || '',
				featuredId: featuredId,
				media: featuredId ? core.getMedia( featuredId ) : null,
				categories: editor.getEditedPostAttribute( 'categories' ) || NONE,
				author: authorId ? core.getEntityRecord( 'root', 'user', authorId, { context: 'view' } ) : null,
				imagesWithoutAlt: countImagesWithoutAlt( select( 'core/block-editor' ).getBlocks() ),
			};
		}, [] );
		const [ meta, setMeta ] = useEntityProp( 'postType', state.postType, 'meta' );
		const values = meta || {};
		return Object.assign( {}, state, {
			meta: values,
			set: function ( key, value ) {
				const next = Object.assign( {}, values );
				next[ key ] = value;
				setMeta( next );
			},
		} );
	}

	/**
	 * Requirements (enforced on publish) and Google recommendations (advice only).
	 */
	function buildChecks( s ) {
		const needed = cfg.requirements[ s.postType ] || [];
		const description = String( s.meta[ KEYS.description ] || '' ).trim();
		const required = [];
		const advice = [];

		// The block editor doesn't show admin notices, so the hidden-site warning goes here.
		if ( ! cfg.sitePublic ) {
			advice.push( {
				ok: false,
				label: __( 'The whole site is hidden from search engines (Settings → Reading)', 'tala-seo' ),
			} );
		}
		if ( needed.indexOf( 'description' ) !== -1 ) {
			required.push( {
				ok: description.length > 0,
				label: description ? __( 'Meta description added', 'tala-seo' ) : __( 'Add a meta description', 'tala-seo' ),
			} );
		}
		if ( needed.indexOf( 'featured_image' ) !== -1 ) {
			required.push( {
				ok: s.featuredId > 0,
				label: s.featuredId ? __( 'Featured image set', 'tala-seo' ) : __( 'Set a featured image', 'tala-seo' ),
			} );
		}
		if ( needed.indexOf( 'featured_image_alt' ) !== -1 && s.featuredId ) {
			const alt = s.media ? String( s.media.alt_text || '' ).trim() : '';
			required.push( {
				ok: alt.length > 0,
				label: alt
					? __( 'Featured image has alternative text', 'tala-seo' )
					: __( 'Describe the featured image in its "Alternative text" field', 'tala-seo' ),
			} );
		}

		const width = s.media && s.media.media_details ? s.media.media_details.width : 0;
		if ( width ) {
			advice.push( {
				ok: width >= 1200,
				label:
					width >= 1200
						? __( 'Featured image is at least 1200 px wide (Google Discover)', 'tala-seo' )
						: sprintf(
								/* translators: %d: image width in pixels. */
								__( 'Featured image is %d px wide; Google Discover asks for at least 1200 px', 'tala-seo' ),
								width
						  ),
			} );
		}
		if ( s.postType === 'post' ) {
			const real = s.categories.filter( function ( id ) {
				return id !== cfg.defaultCategory;
			} );
			advice.push( {
				ok: real.length > 0,
				label: real.length
					? __( 'Filed under a category', 'tala-seo' )
					: __( 'Choose a category other than the default one', 'tala-seo' ),
			} );
		}
		advice.push( {
			ok: s.imagesWithoutAlt === 0,
			label: s.imagesWithoutAlt
				? sprintf(
						/* translators: %d: number of images. */
						_n( '%d image in the article has no alternative text', '%d images in the article have no alternative text', s.imagesWithoutAlt, 'tala-seo' ),
						s.imagesWithoutAlt
				  )
				: __( 'Every image in the article has alternative text', 'tala-seo' ),
		} );
		if ( s.author ) {
			const bio = String( s.author.description || '' ).trim();
			advice.push( {
				ok: bio.length > 0,
				label: bio
					? __( 'The author has a biography', 'tala-seo' )
					: __( 'Add a biography to the author profile; it is shown under the article', 'tala-seo' ),
			} );
		}
		return { required: required, advice: advice };
	}

	function CheckList( props ) {
		if ( ! props.items.length ) {
			return null;
		}
		return el(
			Fragment,
			null,
			el( 'p', { className: 'tala-seo-checklist__title' }, props.title ),
			el(
				'ul',
				{ className: 'tala-seo-checklist' },
				props.items.map( function ( item, i ) {
					const state = item.ok ? 'ok' : props.required ? 'missing' : 'advice';
					return el(
						'li',
						{ key: i, className: 'is-' + state },
						el( 'span', { className: 'tala-seo-checklist__mark', 'aria-hidden': 'true' }, item.ok ? '✓' : props.required ? '✕' : '!' ),
						el( 'span', null, item.label )
					);
				} )
			)
		);
	}

	function Checks( props ) {
		const checks = buildChecks( props.seo );
		return el(
			Fragment,
			null,
			el( CheckList, { title: __( 'Needed to publish', 'tala-seo' ), items: checks.required, required: true } ),
			el( CheckList, { title: __( 'Recommended by Google', 'tala-seo' ), items: checks.advice, required: false } )
		);
	}

	function displayUrl( link ) {
		try {
			const url = new URL( link );
			const parts = url.pathname.split( '/' ).filter( Boolean ).map( decodeURIComponent );
			return [ url.host ].concat( parts ).join( ' › ' );
		} catch ( e ) {
			return link;
		}
	}

	function SearchPreview( props ) {
		const s = props.seo;
		const custom = String( s.meta[ KEYS.title ] || '' ).trim();
		const title = ( custom || s.title || __( '(no title)', 'tala-seo' ) ) + ' ' + cfg.separator + ' ' + cfg.siteName;
		const description = String( s.meta[ KEYS.description ] || '' ).trim();
		return el(
			'div',
			{ className: 'tala-seo-preview', role: 'img', 'aria-label': __( 'Preview of the Google search result', 'tala-seo' ) },
			el( 'div', { className: 'tala-seo-preview__site' }, cfg.siteName ),
			el( 'div', { className: 'tala-seo-preview__url' }, displayUrl( s.link ) ),
			el( 'div', { className: 'tala-seo-preview__title' }, title ),
			el(
				'div',
				{ className: 'tala-seo-preview__description' + ( description ? '' : ' is-empty' ) },
				description || __( 'Add a meta description to choose the text shown here.', 'tala-seo' )
			)
		);
	}

	function SeoPanel() {
		const s = useSeo();
		if ( ! DocumentPanel || ! Object.prototype.hasOwnProperty.call( cfg.requirements, s.postType ) ) {
			return null;
		}
		const description = String( s.meta[ KEYS.description ] || '' );
		const descriptionRequired = ( cfg.requirements[ s.postType ] || [] ).indexOf( 'description' ) !== -1;
		const count = sprintf(
			/* translators: %d: number of characters. */
			_n( '%d character.', '%d characters.', description.length, 'tala-seo' ),
			description.length
		);

		return el(
			DocumentPanel,
			{ name: 'tala-seo', title: __( 'SEO', 'tala-seo' ), className: 'tala-seo-panel' },
			el( SearchPreview, { seo: s } ),
			el( TextControl, {
				__nextHasNoMarginBottom: true,
				__next40pxDefaultSize: true,
				label: __( 'SEO title', 'tala-seo' ),
				value: s.meta[ KEYS.title ] || '',
				placeholder: s.title,
				help: __( 'Leave empty to use the post title. The site name is added at the end.', 'tala-seo' ),
				onChange: function ( value ) {
					s.set( KEYS.title, value );
				},
			} ),
			el( TextareaControl, {
				__nextHasNoMarginBottom: true,
				label: descriptionRequired ? __( 'Meta description (required)', 'tala-seo' ) : __( 'Meta description', 'tala-seo' ),
				value: description,
				rows: 4,
				help: count + ' ' + __( 'Summarize the page in a sentence or two. Google may shorten long descriptions in results.', 'tala-seo' ),
				onChange: function ( value ) {
					s.set( KEYS.description, value );
				},
			} ),
			s.isLive
				? el( ToggleControl, {
						__nextHasNoMarginBottom: true,
						label: __( 'Minor edit: keep the "Updated" date', 'tala-seo' ),
						help: __( 'For typo fixes. Google asks that the updated date only change when the content really changes.', 'tala-seo' ),
						checked: !! s.meta[ KEYS.minorEdit ],
						onChange: function ( value ) {
							s.set( KEYS.minorEdit, value );
						},
				  } )
				: null,
			el( ToggleControl, {
				__nextHasNoMarginBottom: true,
				label: __( 'Hide from search engines', 'tala-seo' ),
				help: __( 'Adds noindex and leaves this page out of the sitemap.', 'tala-seo' ),
				checked: !! s.meta[ KEYS.noindex ],
				onChange: function ( value ) {
					s.set( KEYS.noindex, value );
				},
			} ),
			el( TextControl, {
				__nextHasNoMarginBottom: true,
				__next40pxDefaultSize: true,
				type: 'url',
				label: __( 'Canonical URL (advanced)', 'tala-seo' ),
				value: s.meta[ KEYS.canonical ] || '',
				placeholder: s.link,
				help: __( 'Only when this content was first published at another address. Leave empty otherwise.', 'tala-seo' ),
				onChange: function ( value ) {
					s.set( KEYS.canonical, value );
				},
			} ),
			el( Checks, { seo: s } )
		);
	}

	function PrePublish() {
		const s = useSeo();
		if ( ! PrePublishPanel || ! Object.prototype.hasOwnProperty.call( cfg.requirements, s.postType ) ) {
			return null;
		}
		const blocked = buildChecks( s ).required.some( function ( item ) {
			return ! item.ok;
		} );
		return el(
			PrePublishPanel,
			{
				name: 'tala-seo-checklist',
				title: blocked ? __( 'SEO: not ready to publish', 'tala-seo' ) : __( 'SEO: ready to publish', 'tala-seo' ),
				initialOpen: true,
				className: blocked ? 'tala-seo-prepublish is-blocked' : 'tala-seo-prepublish',
			},
			blocked
				? el( 'p', { className: 'tala-seo-prepublish__note' }, __( 'Publishing will be refused until the items marked ✕ are done.', 'tala-seo' ) )
				: null,
			el( Checks, { seo: s } )
		);
	}

	wp.plugins.registerPlugin( 'tala-seo', {
		render: function () {
			return el( Fragment, null, el( SeoPanel ), el( PrePublish ) );
		},
	} );
} )( window.wp, window.talaSeo );
