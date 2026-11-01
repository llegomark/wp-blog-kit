/**
 * Tala Guard: renders Cloudflare Turnstile in each comment form and keeps a token ready for it.
 *
 * Turnstile's script comes straight from Cloudflare (never proxied or cached, as Cloudflare asks) and
 * loads when a comment form comes near the screen or is used, so readers who never comment don't
 * download it, and the check has finished by the time a comment is written. A form sent before its
 * token is ready waits for it, then goes. A token can be used once: when the browser shows the page
 * again from its back/forward cache (after a comment was turned away), each widget starts over for a
 * new one.
 */
( function() {
	const forms = [ ...document.querySelectorAll( '.tala-guard-turnstile' ) ]
		.map( ( container ) => ( {
			container,
			form: container.closest( 'form' ),
			status: container.nextElementSibling,
			id: null,
			pending: false,
		} ) )
		.filter( ( entry ) => entry.form && entry.status?.classList.contains( 'tala-guard-status' ) );
	if ( ! forms.length ) {
		return;
	}

	const say = ( entry, message ) => {
		entry.status.textContent = message || '';
	};

	const send = ( entry ) => {
		entry.pending = false;
		say( entry, '' );
		if ( entry.form.requestSubmit ) {
			entry.form.requestSubmit();
		} else {
			entry.form.submit();
		}
	};

	const render = () => {
		forms.forEach( ( entry ) => {
			if ( null !== entry.id ) {
				return;
			}
			entry.id = window.turnstile.render( entry.container, {
				sitekey: entry.container.dataset.sitekey,
				action: entry.container.dataset.action,
				theme: 'auto',
				size: 'flexible',
				callback: () => {
					if ( entry.pending ) {
						send( entry );
					} else {
						say( entry, '' );
					}
				},
				'error-callback': () => {
					say( entry, entry.status.dataset.failed );
				},
			} );
		} );
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
				return;
			}
			event.preventDefault();
			entry.pending = true;
			say( entry, entry.status.dataset.checking );
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
					entry.pending = false;
					say( entry, '' );
					window.turnstile.reset( entry.id );
				}
			} );
		}
	} );
}() );
