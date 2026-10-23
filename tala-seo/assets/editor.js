/**
 * Tala SEO in the block editor: the "SEO" panel (search preview, title and meta description with
 * length meters, primary category, minor edit, noindex, canonical, redirect, YouTube video dates)
 * and a checklist in the pre-publish sidebar.
 * Publishing is enforced on the server; this only explains what is missing.
 *
 * @param {Object} wp  WordPress's script globals.
 * @param {Object} cfg Settings from PHP (window.talaSeo).
 */
( function( wp, cfg ) {
	'use strict';

	if ( ! cfg ) {
		return;
	}

	const el = wp.element.createElement;
	const Fragment = wp.element.Fragment;
	const { __, _n, sprintf } = wp.i18n;
	const { SelectControl, TextControl, TextareaControl, ToggleControl } = wp.components;
	const decodeEntities = wp.htmlEntities.decodeEntities;
	const { useSelect } = wp.data;
	const { useEntityProp } = wp.coreData;
	const { PluginDocumentSettingPanel: DocumentPanel, PluginPrePublishPanel: PrePublishPanel } = wp.editor;
	const KEYS = cfg.keys;
	const NONE = [];

	/*
	 * Google shortens titles and descriptions by their width on screen, not their number of
	 * characters: "W" takes far more room than "i". These widths are measured in Google's results
	 * font.
	 */
	const GUIDE = {
		title: { font: '18px arial, helvetica, sans-serif', lower: 200, goodLower: 280, goodUpper: 520, upper: 600 },
		description: { font: '13px arial, helvetica, sans-serif', lower: 256, goodLower: 455, goodUpper: 910, upper: 1820 },
	};
	let measureContext = null;

	function textWidth( text, font ) {
		if ( ! measureContext ) {
			measureContext = document.createElement( 'canvas' ).getContext( '2d' );
		}
		measureContext.font = font;
		return Math.round( measureContext.measureText( text ).width );
	}

	// Cuts text to the width Google shows, after the last whole word, ending with an ellipsis.
	function fitToWidth( text, font, max ) {
		if ( textWidth( text, font ) <= max ) {
			return text;
		}
		let low = 0;
		let high = text.length;
		while ( low < high ) {
			const mid = Math.ceil( ( low + high ) / 2 );
			if ( textWidth( text.slice( 0, mid ).trimEnd() + ' …', font ) <= max ) {
				low = mid;
			} else {
				high = mid - 1;
			}
		}
		const cut = text.slice( 0, low );
		// Keep the last word when the cut falls right before a space; otherwise drop the partial one.
		const space = /\s/.test( text.charAt( low ) ) ? low : cut.lastIndexOf( ' ' );
		return ( space > 0 ? cut.slice( 0, space ) : cut ).replace( /[\s,;:.–—-]+$/, '' ) + ' …';
	}

	function lengthVerdict( kind, width ) {
		const g = GUIDE[ kind ];
		const name = kind === 'title' ? __( 'title', 'tala-seo' ) : __( 'description', 'tala-seo' );
		if ( ! width ) {
			return { state: 'empty', text: '' };
		}
		if ( width > g.upper ) {
			/* translators: 1: "title" or "description", 2: width in pixels, 3: width Google shows. */
			return { state: 'bad', text: sprintf( __( 'Too long: Google will cut this %1$s (%2$d px; about %3$d px fit).', 'tala-seo' ), name, width, g.goodUpper ) };
		}
		if ( width > g.goodUpper ) {
			/* translators: 1: "title" or "description", 2: width in pixels, 3: width Google shows. */
			return { state: 'okay', text: sprintf( __( 'Long: Google may shorten this %1$s on some screens (%2$d px; about %3$d px fit).', 'tala-seo' ), name, width, g.goodUpper ) };
		}
		if ( width < g.lower ) {
			/* translators: 1: "title" or "description", 2: width in pixels. */
			return { state: 'bad', text: sprintf( __( 'Very short: this %1$s says little about the page (%2$d px).', 'tala-seo' ), name, width ) };
		}
		if ( width < g.goodLower ) {
			/* translators: 1: "title" or "description", 2: width in pixels. */
			return { state: 'okay', text: sprintf( __( 'A little short: this %1$s has room for more (%2$d px).', 'tala-seo' ), name, width ) };
		}
		/* translators: 1: "title" or "description", 2: width in pixels, 3: width Google shows. */
		return { state: 'good', text: sprintf( __( 'Good length: this %1$s fits in Google (%2$d of about %3$d px).', 'tala-seo' ), name, width, g.goodUpper ) };
	}

	/*
	 * Rendered inside the field's help text (spans only: help is a <p>), so screen readers hear the
	 * verdict with the field's description.
	 */
	function LengthMeter( props ) {
		const g = GUIDE[ props.kind ];
		const width = textWidth( props.text, g.font );
		const verdict = lengthVerdict( props.kind, width );
		if ( verdict.state === 'empty' ) {
			return null;
		}
		return el(
			'span',
			{ className: 'tala-seo-meter is-' + verdict.state },
			el( 'meter', {
				min: 0,
				max: g.upper,
				low: g.goodLower,
				high: g.goodUpper,
				optimum: ( g.goodLower + g.goodUpper ) / 2,
				value: Math.min( width, g.upper ),
				'aria-hidden': 'true',
			} ),
			el( 'span', { className: 'tala-seo-meter__text' }, verdict.text ),
		);
	}

	function countImagesWithoutAlt( blocks ) {
		let count = 0;
		blocks.forEach( function( block ) {
			if ( block.name === 'core/image' && block.attributes.url && ! String( block.attributes.alt || '' ).trim() ) {
				count++;
			}
			if ( block.innerBlocks && block.innerBlocks.length ) {
				count += countImagesWithoutAlt( block.innerBlocks );
			}
		} );
		return count;
	}

	// A YouTube video's address, as content-schema.php reads it (tala_seo_youtube_id()).
	const YOUTUBE = /^https?:\/\/(?:(?:www\.|m\.)?youtube(?:-nocookie)?\.com\/(?:watch\?(?:[^#]*&)?v=|shorts\/|live\/|embed\/)|youtu\.be\/)([A-Za-z0-9_-]{11})(?![A-Za-z0-9_-])/i;

	/**
	 * The YouTube videos in Embed blocks, each once, as "ID address" lines (a string, so the
	 * editor's state stays comparable).
	 *
	 * @param {Object[]} blocks Blocks.
	 * @param {Object}   seen   Video IDs found so far.
	 * @return {string[]} Lines.
	 */
	function youtubeVideos( blocks, seen = {} ) {
		const found = [];
		blocks.forEach( function( block ) {
			const url = block.name === 'core/embed' ? String( block.attributes.url || '' ).trim() : '';
			const match = YOUTUBE.exec( url );
			if ( match && ! seen[ match[ 1 ] ] ) {
				seen[ match[ 1 ] ] = true;
				found.push( match[ 1 ] + ' ' + url );
			}
			if ( block.innerBlocks && block.innerBlocks.length ) {
				found.push( ...youtubeVideos( block.innerBlocks, seen ) );
			}
		} );
		return found;
	}

	function useSeo() {
		const state = useSelect( function( select ) {
			const editor = select( 'core/editor' );
			const core = select( 'core' );
			const featuredId = editor.getEditedPostAttribute( 'featured_media' ) || 0;
			const authorId = editor.getEditedPostAttribute( 'author' );
			return {
				postType: editor.getCurrentPostType(),
				isLive: [ 'publish', 'future' ].indexOf( editor.getCurrentPostAttribute( 'status' ) ) !== -1,
				title: editor.getEditedPostAttribute( 'title' ) || '',
				link: editor.getPermalink() || '',
				featuredId,
				media: featuredId ? core.getEntityRecord( 'postType', 'attachment', featuredId ) : null,
				categories: editor.getEditedPostAttribute( 'categories' ) || NONE,
				hasCategories: cfg.primaryTypes.indexOf( editor.getCurrentPostType() ) !== -1,
				author: authorId ? core.getEntityRecord( 'root', 'user', authorId, { context: 'view' } ) : null,
				imagesWithoutAlt: countImagesWithoutAlt( select( 'core/block-editor' ).getBlocks() ),
				videos: youtubeVideos( select( 'core/block-editor' ).getBlocks() ).join( '\n' ),
			};
		}, [] );
		const [ meta, setMeta ] = useEntityProp( 'postType', state.postType, 'meta' );
		const values = meta || {};
		return Object.assign( {}, state, {
			meta: values,
			set( key, value ) {
				const next = Object.assign( {}, values );
				next[ key ] = value;
				setMeta( next );
			},
		} );
	}

	/**
	 * Requirements (enforced on publish) and Google recommendations (advice only).
	 *
	 * @param {Object} s The editor state from useSeo().
	 * @return {{required: Object[], advice: Object[]}} The checklist items.
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
		if ( String( s.meta[ KEYS.redirect ] || '' ).trim() ) {
			advice.push( {
				ok: false,
				label: __( 'This post redirects to another address, so visitors won\'t see it', 'tala-seo' ),
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
			let widthLabel = __( 'Featured image is at least 1200 px wide (Google Discover)', 'tala-seo' );
			if ( width < 1200 ) {
				widthLabel = sprintf(
					/* translators: %d: image width in pixels. */
					__( 'Featured image is %d px wide; Google Discover asks for at least 1200 px', 'tala-seo' ),
					width,
				);
			}
			advice.push( { ok: width >= 1200, label: widthLabel } );
		}
		if ( s.postType === 'post' ) {
			const real = s.categories.filter( function( id ) {
				return id !== cfg.defaultCategory;
			} );
			advice.push( {
				ok: real.length > 0,
				label: real.length
					? __( 'Filed under a category', 'tala-seo' )
					: __( 'Choose a category other than the default one', 'tala-seo' ),
			} );
		}
		let altLabel = __( 'Every image in the article has alternative text', 'tala-seo' );
		if ( s.imagesWithoutAlt ) {
			altLabel = sprintf(
				/* translators: %d: number of images. */
				_n( '%d image in the article has no alternative text', '%d images in the article have no alternative text', s.imagesWithoutAlt, 'tala-seo' ),
				s.imagesWithoutAlt,
			);
		}
		advice.push( { ok: s.imagesWithoutAlt === 0, label: altLabel } );
		const videos = videoList( s );
		if ( videos.length ) {
			const undated = videos.filter( ( video ) => ! video.date ).length;
			advice.push( {
				ok: undated === 0,
				label: undated
					? sprintf(
						/* translators: %d: number of videos. */
						_n( '%d YouTube video has no publish date, so it gets no video markup', '%d YouTube videos have no publish date, so they get no video markup', undated, 'tala-seo' ),
						undated,
					)
					: __( 'Every YouTube video has its publish date', 'tala-seo' ),
			} );
		}
		if ( s.author ) {
			const bio = String( s.author.description || '' ).trim();
			advice.push( {
				ok: bio.length > 0,
				label: bio
					? __( 'The author has a biography', 'tala-seo' )
					: __( 'Add a biography to the author profile; it is shown under the article', 'tala-seo' ),
			} );
		}
		return { required, advice };
	}

	/**
	 * The post's YouTube videos with the publish dates entered for them.
	 *
	 * @param {Object} s The editor state from useSeo().
	 * @return {{id: string, url: string, date: string}[]} Videos.
	 */
	function videoList( s ) {
		const dates = s.meta[ KEYS.videoDates ] || {};
		return s.videos
			? s.videos.split( '\n' ).map( function( line ) {
				const [ id, url ] = line.split( ' ' );
				return { id, url, date: dates[ id ] || '' };
			} )
			: [];
	}

	/*
	 * One date field per video. The title comes from the embed preview the editor already
	 * fetched; the ID stands in until it has.
	 */
	function VideoDate( props ) {
		const title = useSelect(
			function( select ) {
				const preview = select( 'core' ).getEmbedPreview( props.video.url );
				return preview && preview.title ? String( preview.title ) : '';
			},
			[ props.video.url ],
		);
		return el( TextControl, {
			__nextHasNoMarginBottom: true,
			__next40pxDefaultSize: true,
			type: 'date',
			/* translators: %s: video title. */
			label: sprintf( __( 'Published on YouTube: %s', 'tala-seo' ), title ? decodeEntities( title ) : props.video.id ),
			value: props.video.date,
			onChange: props.onChange,
		} );
	}

	function VideoDates( props ) {
		const s = props.seo;
		const videos = videoList( s );
		if ( ! videos.length ) {
			return null;
		}
		return el(
			Fragment,
			null,
			el( 'p', { className: 'tala-seo-checklist__title' }, __( 'Videos', 'tala-seo' ) ),
			el(
				'p',
				{ className: 'tala-seo-videos__help' },
				__( 'Google needs the date each video first appeared on YouTube (shown under it there). Videos without it get no video markup.', 'tala-seo' ),
			),
			videos.map( function( video ) {
				return el( VideoDate, {
					key: video.id,
					video,
					onChange( value ) {
						const dates = Object.assign( {}, s.meta[ KEYS.videoDates ] || {} );
						dates[ video.id ] = value;
						s.set( KEYS.videoDates, dates );
					},
				} );
			} ),
		);
	}

	const CHECK_MARKS = { ok: '✓', missing: '✕', advice: '!' };

	/**
	 * A checklist item's state: done, missing (needed to publish) or advice.
	 *
	 * @param {Object}  item     Item with an `ok` flag.
	 * @param {boolean} required Whether the list holds publish requirements.
	 * @return {string} ok, missing or advice.
	 */
	function checkState( item, required ) {
		if ( item.ok ) {
			return 'ok';
		}
		return required ? 'missing' : 'advice';
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
				props.items.map( function( item, i ) {
					const state = checkState( item, props.required );
					return el(
						'li',
						{ key: i, className: 'is-' + state },
						el( 'span', { className: 'tala-seo-checklist__mark', 'aria-hidden': 'true' }, CHECK_MARKS[ state ] ),
						el( 'span', null, item.label ),
					);
				} ),
			),
		);
	}

	function Checks( props ) {
		const checks = buildChecks( props.seo );
		return el(
			Fragment,
			null,
			el( CheckList, { title: __( 'Needed to publish', 'tala-seo' ), items: checks.required, required: true } ),
			el( CheckList, { title: __( 'Recommended by Google', 'tala-seo' ), items: checks.advice, required: false } ),
		);
	}

	function displayUrl( link ) {
		try {
			const url = new URL( link );
			const parts = url.pathname.split( '/' ).filter( Boolean ).map( decodeURIComponent );
			return [ url.host ].concat( parts ).join( ' › ' );
		} catch {
			return link;
		}
	}

	function fullTitle( s ) {
		const custom = String( s.meta[ KEYS.title ] || '' ).trim();
		return ( custom || s.title || __( '(no title)', 'tala-seo' ) ) + ' ' + cfg.separator + ' ' + cfg.siteName;
	}

	function SearchPreview( props ) {
		const s = props.seo;
		const title = fitToWidth( fullTitle( s ), GUIDE.title.font, GUIDE.title.upper );
		const description = fitToWidth( String( s.meta[ KEYS.description ] || '' ).trim(), GUIDE.description.font, GUIDE.description.goodUpper );
		return el(
			'div',
			{ className: 'tala-seo-preview', role: 'img', 'aria-label': __( 'Preview of the Google search result', 'tala-seo' ) },
			el( 'div', { className: 'tala-seo-preview__site' }, cfg.siteName ),
			el( 'div', { className: 'tala-seo-preview__url' }, displayUrl( s.link ) ),
			el( 'div', { className: 'tala-seo-preview__title' }, title ),
			el(
				'div',
				{ className: 'tala-seo-preview__description' + ( description ? '' : ' is-empty' ) },
				description || __( 'Add a meta description to choose the text shown here.', 'tala-seo' ),
			),
		);
	}

	function PrimaryCategory( props ) {
		const s = props.seo;
		const ids = s.categories;
		const terms = useSelect(
			function( select ) {
				return ids.length > 1
					? select( 'core' ).getEntityRecords( 'taxonomy', 'category', { include: ids, per_page: 100, context: 'view' } )
					: null;
			},
			[ ids ],
		);
		if ( ! s.hasCategories || ids.length < 2 || ! terms ) {
			return null;
		}
		const current = Number( s.meta[ KEYS.primary ] || 0 );
		return el( SelectControl, {
			__nextHasNoMarginBottom: true,
			__next40pxDefaultSize: true,
			label: __( 'Primary category', 'tala-seo' ),
			value: ids.indexOf( current ) !== -1 ? current : 0,
			options: [ { label: __( 'Automatic (the first category)', 'tala-seo' ), value: 0 } ].concat(
				terms.map( function( term ) {
					return { label: decodeEntities( term.name ), value: term.id };
				} ),
			),
			help: __( 'Leads the breadcrumbs, and the address too if it contains the category.', 'tala-seo' ),
			onChange( value ) {
				s.set( KEYS.primary, Number( value ) );
			},
		} );
	}

	function SeoPanel() {
		const s = useSeo();
		if ( ! Object.prototype.hasOwnProperty.call( cfg.requirements, s.postType ) ) {
			return null;
		}
		const description = String( s.meta[ KEYS.description ] || '' );
		const descriptionRequired = ( cfg.requirements[ s.postType ] || [] ).indexOf( 'description' ) !== -1;
		const count = sprintf(
			/* translators: %d: number of characters. */
			_n( '%d character.', '%d characters.', description.length, 'tala-seo' ),
			description.length,
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
				help: el(
					Fragment,
					null,
					el( LengthMeter, { kind: 'title', text: fullTitle( s ) } ),
					' ',
					__( 'Leave empty to use the post title. The site name is added at the end.', 'tala-seo' ),
				),
				onChange( value ) {
					s.set( KEYS.title, value );
				},
			} ),
			el( TextareaControl, {
				__nextHasNoMarginBottom: true,
				label: descriptionRequired ? __( 'Meta description (required)', 'tala-seo' ) : __( 'Meta description', 'tala-seo' ),
				value: description,
				rows: 4,
				help: el(
					Fragment,
					null,
					el( LengthMeter, { kind: 'description', text: description.trim() } ),
					' ',
					count + ' ' + __( 'Summarize the page in a sentence or two.', 'tala-seo' ),
				),
				onChange( value ) {
					s.set( KEYS.description, value );
				},
			} ),
			el( PrimaryCategory, { seo: s } ),
			s.isLive &&
				el( ToggleControl, {
					__nextHasNoMarginBottom: true,
					label: __( 'Minor edit: keep the "Updated" date', 'tala-seo' ),
					help: __( 'For typo fixes. Google asks that the updated date only change when the content really changes.', 'tala-seo' ),
					checked: !! s.meta[ KEYS.minorEdit ],
					onChange( value ) {
						s.set( KEYS.minorEdit, value );
					},
				} ),
			el( ToggleControl, {
				__nextHasNoMarginBottom: true,
				label: __( 'Hide from search engines', 'tala-seo' ),
				help: __( 'Adds noindex and leaves this page out of the sitemap.', 'tala-seo' ),
				checked: !! s.meta[ KEYS.noindex ],
				onChange( value ) {
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
				onChange( value ) {
					s.set( KEYS.canonical, value );
				},
			} ),
			el( TextControl, {
				__nextHasNoMarginBottom: true,
				__next40pxDefaultSize: true,
				type: 'url',
				label: __( 'Redirect to (advanced)', 'tala-seo' ),
				value: s.meta[ KEYS.redirect ] || '',
				placeholder: 'https://',
				help: __( 'Sends visitors and search engines from this post to another address (301), for example after merging two guides. Leave empty otherwise.', 'tala-seo' ),
				onChange( value ) {
					s.set( KEYS.redirect, value );
				},
			} ),
			el( VideoDates, { seo: s } ),
			el( Checks, { seo: s } ),
		);
	}

	function PrePublish() {
		const s = useSeo();
		if ( ! Object.prototype.hasOwnProperty.call( cfg.requirements, s.postType ) ) {
			return null;
		}
		const blocked = buildChecks( s ).required.some( function( item ) {
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
			el( Checks, { seo: s } ),
		);
	}

	wp.plugins.registerPlugin( 'tala-seo', {
		render() {
			return el( Fragment, null, el( SeoPanel ), el( PrePublish ) );
		},
	} );
}( window.wp, window.talaSeo ) );
