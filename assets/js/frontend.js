/**
 * FP Distributor Media Kit — Frontend form handling.
 */
( function() {
	'use strict';

	function initFormLoading() {
		var forms = document.querySelectorAll( '.fpdmk-login-form, .fpdmk-register-form' );
		var i18n = window.fpDmkI18n || { loading: 'Invio in corso...' };

		forms.forEach( function( form ) {
			form.addEventListener( 'submit', function() {
				var btn = form.querySelector( 'button[type="submit"]' );
				if ( btn && ! btn.disabled ) {
					btn.disabled = true;
					btn.classList.add( 'is-loading' );
					var originalText = btn.textContent;
					btn.dataset.originalText = originalText;
					btn.textContent = i18n.loading;
				}
			} );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initFormLoading );
	} else {
		initFormLoading();
	}
} )();
