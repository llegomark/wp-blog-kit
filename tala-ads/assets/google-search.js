/**
 * Google's search box has no hint of its own: once Google has drawn it, it gets the hint the page
 * sets (data-hint), as its placeholder.
 */
( function() {
	const boxes = document.querySelectorAll( '.tala-ads-google-search[data-hint]' );

	/**
	 * Gives Google's field in a box its hint; true once the field is there.
	 *
	 * @param {HTMLElement} box The wrapper around Google's search element.
	 * @return {boolean} Whether the field was found.
	 */
	function hint( box ) {
		const field = box.querySelector( 'input.gsc-input' );
		// Not over Google's name, which some search engines show in the empty field.
		if ( field && 'none' === window.getComputedStyle( field ).backgroundImage ) {
			field.placeholder = box.dataset.hint;
		}
		return Boolean( field );
	}

	boxes.forEach( ( box ) => {
		if ( hint( box ) ) {
			return;
		}
		const observer = new window.MutationObserver( () => {
			if ( hint( box ) ) {
				observer.disconnect();
			}
		} );
		observer.observe( box, { childList: true, subtree: true } );
	} );
}() );
