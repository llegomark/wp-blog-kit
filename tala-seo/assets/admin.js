/**
 * Media picker for Settings → SEO (default share image) and user profiles (author photo).
 * A button with data-tala-media="<input id>" opens the library; data-tala-media-clear empties it.
 */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		const pick = event.target.closest( '[data-tala-media]' );
		const clear = event.target.closest( '[data-tala-media-clear]' );

		if ( pick ) {
			event.preventDefault();
			const input = document.getElementById( pick.dataset.talaMedia );
			const preview = document.getElementById( pick.dataset.preview );
			const frame = wp.media( {
				title: pick.dataset.title,
				button: { text: pick.dataset.button },
				library: { type: 'image' },
				multiple: false,
			} );
			frame.on( 'select', function () {
				const image = frame.state().get( 'selection' ).first().toJSON();
				input.value = image.id;
				preview.src = image.sizes && image.sizes.medium ? image.sizes.medium.url : image.url;
				preview.hidden = false;
			} );
			frame.open();
		}

		if ( clear ) {
			event.preventDefault();
			document.getElementById( clear.dataset.talaMediaClear ).value = '';
			const preview = document.getElementById( clear.dataset.preview );
			preview.removeAttribute( 'src' );
			preview.hidden = true;
		}
	} );
} )();
