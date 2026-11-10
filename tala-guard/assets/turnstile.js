/**
 * Tala Guard: renders Cloudflare Turnstile in each comment form and keeps a token ready for it.
 *
 * Turnstile's script comes straight from Cloudflare (never proxied or cached, as Cloudflare asks) and
 * loads when a comment form comes near the screen or is used, so readers who never comment don't
 * download it, and the check has finished by the time a comment is written. A form sent before its
 * token is ready waits for it, then goes. When the script can't be fetched, or hasn't arrived after
 * ten seconds, the wait ends and the form says so; sending again tries again. A token can be used
 * once: when the browser shows the page again from its back/forward cache (after a comment was
 * turned away), each widget starts over for a new one.
 *
 * The widget takes the colour scheme the page declares for it (CSS color-scheme, as a theme's
 * Light/Dark switch sets it): dark or light when the page declares only that one, and otherwise
 * Turnstile's "auto", which follows the device. When the scheme changes after the widget is shown,
 * it is shown again in the new one, unless its token is already on its way with the comment.
 */
( function() {
	// How long a comment that was sent waits for Turnstile's script, in milliseconds.
	const PATIENCE = 10000;

	const forms = [ ...document.querySelectorAll( '.tala-guard-turnstile' ) ]
		.map( ( container ) => ( {
			container,
			form: container.closest( 'form' ),
			status: container.nextElementSibling,
			id: null,
			theme: null,
			pending: false,
			sending: false,
			timer: 0,
		} ) )
		.filter( ( entry ) => entry.form && entry.status?.classList.contains( 'tala-guard-status' ) );
	if ( ! forms.length ) {
		return;
	}

	const say = ( entry, message ) => {
		entry.status.textContent = message || '';
	};

	const send = ( entry ) => {
		window.clearTimeout( entry.timer );
		entry.pending = false;
		say( entry, '' );
		if ( entry.form.requestSubmit ) {
			entry.form.requestSubmit();
		} else {
			// Not entry.form.submit(): WordPress names the form's button "submit", and a control's name
			// takes the place of the form's own method of that name.
			window.HTMLFormElement.prototype.submit.call( entry.form );
		}
	};

	// Ends a form's wait for a token that isn't coming, and says so; sending again starts over.
	const giveUp = ( entry ) => {
		window.clearTimeout( entry.timer );
		if ( entry.pending ) {
			entry.pending = false;
			say( entry, entry.status.dataset.failed );
		}
	};

	/**
	 * The widget theme for the colour scheme computed on its container: "dark" or "light" when that
	 * is the only scheme declared ("only dark" included), and "auto" for "normal" or both.
	 *
	 * @param {Element} container Widget container.
	 * @return {string} "dark", "light" or "auto".
	 */
	const themeOf = ( container ) => {
		const schemes = ( window.getComputedStyle( container ).colorScheme || '' )
			.split( /\s+/ )
			.filter( ( word ) => 'light' === word || 'dark' === word );
		return 1 === schemes.length ? schemes[ 0 ] : 'auto';
	};

	const renderOne = ( entry ) => {
		entry.theme = themeOf( entry.container );
		entry.id = window.turnstile.render( entry.container, {
			sitekey: entry.container.dataset.sitekey,
			action: entry.container.dataset.action,
			theme: entry.theme,
			size: 'flexible',
			callback: () => {
				if ( entry.pending ) {
					send( entry );
				} else {
					say( entry, '' );
				}
			},
			'error-callback': () => {
				window.clearTimeout( entry.timer );
				say( entry, entry.status.dataset.failed );
			},
		} );
	};

	// Shows each widget again when the page's colour scheme has changed since it was shown. A form
	// waiting for its token gets it from the new widget; one whose token is being sent keeps it.
	const follow = () => {
		forms.forEach( ( entry ) => {
			if ( null === entry.id || entry.sending || themeOf( entry.container ) === entry.theme ) {
				return;
			}
			window.turnstile.remove( entry.id );
			entry.id = null;
			renderOne( entry );
		} );
	};

	let following = false;
	const render = () => {
		forms.forEach( ( entry ) => {
			if ( null === entry.id ) {
				renderOne( entry );
			}
		} );
		if ( following ) {
			return;
		}
		following = true;
		if ( 'MutationObserver' in window ) {
			const observer = new window.MutationObserver( follow );
			observer.observe( document.documentElement, { attributes: true } );
			observer.observe( document.body, { attributes: true } );
		}
		// A page may declare a single scheme only for dark or light devices.
		window.matchMedia?.( '(prefers-color-scheme: dark)' ).addEventListener?.( 'change', follow );
	};

	let requested = false;
	const load = () => {
		if ( requested ) {
			return;
		}
		requested = true;
		window.talaGuardTurnstileLoaded = render;
		const script = document.createElement( 'script' );
		script.src =
			'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=talaGuardTurnstileLoaded';
		script.async = true;
		// The browser couldn't fetch it (no connection, a blocked address). Cloudflare's own callbacks
		// only exist once the script runs, so the forms that wait are told here, and the script is
		// asked for again the next time a form needs it.
		script.addEventListener( 'error', () => {
			script.remove();
			requested = false;
			forms.forEach( giveUp );
		} );
		document.head.append( script );
	};

	const hasToken = ( entry ) =>
		null !== entry.id &&
		!! window.turnstile.getResponse( entry.id ) &&
		! window.turnstile.isExpired( entry.id );

	forms.forEach( ( entry ) => {
		entry.form.addEventListener( 'focusin', load, { once: true } );
		entry.form.addEventListener( 'submit', ( event ) => {
			if ( hasToken( entry ) ) {
				entry.sending = true;
				return;
			}
			event.preventDefault();
			entry.pending = true;
			say( entry, entry.status.dataset.checking );
			// A script that neither arrives nor fails (a stalled connection) ends the wait as well. It
			// isn't asked for a second time while it may still arrive: when it does, the widget renders,
			// and a form sent again in the meantime goes with its token.
			window.clearTimeout( entry.timer );
			entry.timer = window.setTimeout( () => {
				if ( null === entry.id ) {
					giveUp( entry );
				}
			}, PATIENCE );
			load();
		} );
	} );

	if ( 'IntersectionObserver' in window ) {
		const observer = new window.IntersectionObserver(
			( entries ) => {
				if ( entries.some( ( entry ) => entry.isIntersecting ) ) {
					observer.disconnect();
					load();
				}
			},
			{ rootMargin: '800px 0px' },
		);
		forms.forEach( ( entry ) => observer.observe( entry.container ) );
	} else {
		load();
	}

	window.addEventListener( 'pageshow', ( event ) => {
		if ( event.persisted && window.turnstile ) {
			forms.forEach( ( entry ) => {
				if ( null !== entry.id ) {
					window.clearTimeout( entry.timer );
					entry.pending = false;
					entry.sending = false;
					say( entry, '' );
					window.turnstile.reset( entry.id );
				}
			} );
		}
	} );
}() );
