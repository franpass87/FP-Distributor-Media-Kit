/**
 * FP Distributor Media Kit — frontend (form, Media Kit bulk, password).
 */
( function () {
	'use strict';

	var i18n = window.fpDmkI18n || {};

	function initFormLoading() {
		var forms = document.querySelectorAll( '.fpdmk-login-form, .fpdmk-register-form' );
		var loading = i18n.loading || 'Invio in corso…';

		forms.forEach( function ( form ) {
			form.addEventListener( 'submit', function () {
				var btn = form.querySelector( 'button[type="submit"]' );
				if ( btn && ! btn.disabled ) {
					btn.disabled = true;
					btn.classList.add( 'is-loading' );
					btn.dataset.originalText = btn.textContent;
					btn.textContent = loading;
				}
			} );
		} );
	}

	function initPasswordToggle() {
		document.querySelectorAll( '.fpdmk-btn-password-toggle' ).forEach( function ( btn ) {
			var targetId = btn.getAttribute( 'data-target' );
			if ( ! targetId ) {
				return;
			}
			var input = document.getElementById( targetId );
			if ( ! input ) {
				return;
			}
			btn.addEventListener( 'click', function () {
				var show = i18n.pwdToggleShow || 'Mostra';
				var hide = i18n.pwdToggleHide || 'Nascondi';
				if ( input.type === 'password' ) {
					input.type = 'text';
					btn.textContent = hide;
					btn.setAttribute( 'aria-expanded', 'true' );
					btn.setAttribute( 'aria-label', i18n.hidePassword || 'Nascondi password' );
				} else {
					input.type = 'password';
					btn.textContent = show;
					btn.setAttribute( 'aria-expanded', 'false' );
					btn.setAttribute( 'aria-label', i18n.showPassword || 'Mostra password' );
				}
			} );
		} );
	}

	function scorePassword( val ) {
		var s = 0;
		if ( val.length >= 8 ) {
			s += 1;
		}
		if ( /[a-z]/.test( val ) ) {
			s += 1;
		}
		if ( /[A-Z]/.test( val ) ) {
			s += 1;
		}
		if ( /[0-9]/.test( val ) ) {
			s += 1;
		}
		return s;
	}

	function initRegisterPasswordStrength() {
		var input = document.getElementById( 'fp_dmk_reg_password' );
		var out = document.getElementById( 'fp_dmk_reg_password_strength' );
		if ( ! input || ! out ) {
			return;
		}
		function render() {
			var v = input.value || '';
			var sc = scorePassword( v );
			var label = '';
			var cls = 'fpdmk-password-strength--none';
			if ( v.length === 0 ) {
				out.textContent = '';
				out.className = 'fpdmk-password-strength';
				return;
			}
			if ( sc <= 2 ) {
				label = i18n.pwdStrengthWeak || 'Forza password: debole';
				cls = 'fpdmk-password-strength fpdmk-password-strength--weak';
			} else if ( sc === 3 ) {
				label = i18n.pwdStrengthFair || 'Forza password: discreta';
				cls = 'fpdmk-password-strength fpdmk-password-strength--fair';
			} else {
				label = i18n.pwdStrengthGood || 'Forza password: buona';
				cls = 'fpdmk-password-strength fpdmk-password-strength--good';
			}
			out.textContent = label;
			out.className = cls;
		}
		input.addEventListener( 'input', render );
		render();
	}

	function initMediaKitBulk() {
		var root = document.querySelector( '.fpdmk-media-kit[data-fpdmk-bulk-enabled]' );
		if ( ! root ) {
			return;
		}
		if ( root.getAttribute( 'data-fpdmk-bulk-enabled' ) !== '1' ) {
			return;
		}
		var action = root.getAttribute( 'data-fpdmk-bulk-action' );
		var nonce = root.getAttribute( 'data-fpdmk-bulk-nonce' );
		var max = parseInt( root.getAttribute( 'data-fpdmk-bulk-max' ) || '25', 10 );
		var btnZip = document.getElementById( 'fpdmk-bulk-zip' );
		var chkAll = document.getElementById( 'fpdmk-select-all' );
		if ( ! btnZip || ! action || ! nonce ) {
			return;
		}

		function getBoxes() {
			return root.querySelectorAll( '.fpdmk-card-checkbox' );
		}

		function updateZipButton() {
			var n = root.querySelectorAll( '.fpdmk-card-checkbox:checked' ).length;
			btnZip.disabled = n === 0;
		}

		getBoxes().forEach( function ( cb ) {
			cb.addEventListener( 'change', updateZipButton );
		} );

		if ( chkAll ) {
			chkAll.addEventListener( 'change', function () {
				var on = chkAll.checked;
				getBoxes().forEach( function ( cb ) {
					cb.checked = on;
				} );
				updateZipButton();
			} );
		}

		btnZip.addEventListener( 'click', function () {
			var ids = [];
			getBoxes().forEach( function ( cb ) {
				if ( cb.checked ) {
					ids.push( cb.value );
				}
			} );
			if ( ids.length === 0 ) {
				window.alert( i18n.bulkZipNone || 'Seleziona almeno un file.' );
				return;
			}
			if ( ids.length > max ) {
				window.alert( i18n.bulkZipTooMany || 'Troppi file selezionati.' );
				return;
			}
			btnZip.disabled = true;
			var prev = btnZip.textContent;
			btnZip.textContent = i18n.bulkZipPreparing || 'Creazione archivio…';

			var form = document.createElement( 'form' );
			form.method = 'post';
			form.action = action;
			form.style.display = 'none';

			var z = document.createElement( 'input' );
			z.name = 'fp_dmk_bulk_zip';
			z.value = '1';
			form.appendChild( z );

			var n = document.createElement( 'input' );
			n.name = '_wpnonce';
			n.value = nonce;
			form.appendChild( n );

			ids.forEach( function ( id ) {
				var h = document.createElement( 'input' );
				h.type = 'hidden';
				h.name = 'asset_ids[]';
				h.value = id;
				form.appendChild( h );
			} );

			document.body.appendChild( form );
			form.submit();
			setTimeout( function () {
				btnZip.disabled = false;
				btnZip.textContent = prev;
			}, 4000 );
		} );
	}

	function boot() {
		initFormLoading();
		initPasswordToggle();
		initRegisterPasswordStrength();
		initMediaKitBulk();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
