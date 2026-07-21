/**
 * Front-end newsletter form: progressive AJAX enhancement.
 *
 * Without JS the form still works as a normal POST to admin-post.php. With JS we
 * submit in the background and show an inline, screen-reader-announced result.
 */
( function () {
	'use strict';

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	ready( function () {
		var forms = document.querySelectorAll( '.semnews-form' );

		Array.prototype.forEach.call( forms, function ( form ) {
			form.addEventListener( 'submit', function ( e ) {
				if ( ! window.semnewsPublic || ! window.semnewsPublic.ajaxUrl ) {
					return; // No-JS fallback path.
				}
				e.preventDefault();

				var feedback = form.querySelector( '.semnews-form-feedback' );
				var button = form.querySelector( '.semnews-submit' );
				var data = new FormData( form );

				if ( button ) {
					button.disabled = true;
					button.classList.add( 'is-busy' );
				}
				if ( feedback ) {
					feedback.textContent = '';
					feedback.className = 'semnews-form-feedback';
				}

				var xhr = new XMLHttpRequest();
				xhr.open( 'POST', window.semnewsPublic.ajaxUrl, true );
				xhr.onreadystatechange = function () {
					if ( xhr.readyState !== 4 ) {
						return;
					}
					if ( button ) {
						button.disabled = false;
						button.classList.remove( 'is-busy' );
					}

					var message = '';
					var ok = false;
					try {
						var res = JSON.parse( xhr.responseText );
						ok = !! res.success;
						message = ( res.data && res.data.message ) ? res.data.message : '';
					} catch ( err ) {
						message = '';
					}

					if ( feedback ) {
						feedback.textContent = message;
						feedback.className = 'semnews-form-feedback ' + ( ok ? 'semnews-message-success' : 'semnews-message-error' );
					}

					if ( ok ) {
						form.reset();
						// Let placement overlays know this visitor subscribed, so they
						// stop showing. Also remember it for future page loads.
						try {
							document.cookie = 'semnews_subscribed=1; max-age=31536000; path=/; samesite=lax';
						} catch ( e2 ) {}
						document.dispatchEvent( new CustomEvent( 'semnews:subscribed' ) );
					}
				};
				xhr.send( data );
			} );
		} );
	} );
}() );
