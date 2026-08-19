/**
 * gloskin-ui1-final-migration.js
 *
 * AJAX controller for the bounded 2026-08-19-final closure migration.
 *
 * DOM contract (all attributes live on or inside [data-gloskin-final-migration]):
 *   Root:     [data-gloskin-final-migration]  — carries data-ajax, data-action,
 *                data-nonce, data-status, data-processed, data-total
 *   Progress: [data-gloskin-migration-progressbar]
 *   Step:     [data-gloskin-migration-step]
 *   Error:    [data-gloskin-migration-error]  — hidden attr cleared on show, set on hide
 *   Form:     [data-gloskin-migration-form]   — POST fallback intercepted by JS
 *   Button:   [data-gloskin-migration-run]    — submit button inside the form
 *
 * State contract (AJAX response payload.data keys):
 *   status          string  pending|running|verifying|failed|consumed
 *   processed_steps int
 *   total_steps     int
 *   current_step    string
 *   last_error      string
 *
 * Mode logic:
 *   status=pending              → mode=start (captures commerce snapshot), then continue chain
 *   status=failed|running|      → mode=continue directly (resume from saved cursor/step)
 *     verifying
 *
 * On consumed: inline success, DOM cleanup (notice + menu item), navigate after 1.8 s.
 * On failure: no reload; inline error; button re-enabled for retry.
 *
 * @package GloskinSiteCore
 */
( function () {
	'use strict';

	var root = document.querySelector( '[data-gloskin-final-migration]' );
	if ( ! root ) { return; }

	var ajaxUrl = root.getAttribute( 'data-ajax' )   || '';
	var action  = root.getAttribute( 'data-action' ) || '';
	var nonce   = root.getAttribute( 'data-nonce' )  || '';

	if ( ! ajaxUrl || ! action || ! nonce ) { return; }

	var progressBar = root.querySelector( '[data-gloskin-migration-progressbar]' );
	var stepNode    = root.querySelector( '[data-gloskin-migration-step]' );
	var errorWrap   = root.querySelector( '[data-gloskin-migration-error]' );
	var errorNode   = errorWrap ? errorWrap.querySelector( 'p' ) : null;
	var form        = root.querySelector( '[data-gloskin-migration-form]' );
	var button      = root.querySelector( '[data-gloskin-migration-run]' );

	var running = false;

	/* -------------------------------------------------------------------------
	 * UI helpers
	 * ---------------------------------------------------------------------- */

	function setBusy( busy ) {
		running = busy;
		if ( button ) { button.disabled = busy; }
	}

	function showError( msg ) {
		if ( ! errorWrap || ! errorNode ) { return; }
		errorNode.textContent = msg || '';
		if ( msg ) {
			errorWrap.removeAttribute( 'hidden' );
		} else {
			errorWrap.setAttribute( 'hidden', '' );
		}
	}

	function updateProgress( state ) {
		var processed = Number( state.processed_steps || 0 );
		var total     = Number( state.total_steps     || 8 );
		if ( progressBar ) {
			progressBar.max   = total > 0 ? total : 8;
			progressBar.value = Math.min( processed, progressBar.max );
		}
		if ( stepNode && state.current_step ) {
			stepNode.textContent = state.current_step;
		}
	}

	/* -------------------------------------------------------------------------
	 * AJAX
	 * ---------------------------------------------------------------------- */

	function request( mode ) {
		var body = 'action='  + encodeURIComponent( action )
		         + '&nonce='  + encodeURIComponent( nonce )
		         + '&mode='   + encodeURIComponent( mode );
		return fetch( ajaxUrl, {
			method:      'POST',
			credentials: 'same-origin',
			headers:     { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body:        body,
		} ).then( function ( r ) {
			return r.json().then( function ( payload ) {
				if ( ! payload || ! payload.success ) {
					var d   = ( payload && payload.data ) ? payload.data : {};
					var msg = d.message || 'Checkpoint gagal.';
					throw new Error( msg );
				}
				return payload.data || {};
			} );
		} );
	}

	/* -------------------------------------------------------------------------
	 * Success
	 * ---------------------------------------------------------------------- */

	function onConsumed() {
		setBusy( false );

		/* Update inline UI */
		if ( stepNode ) { stepNode.textContent = 'Finalisasi selesai.'; }
		if ( progressBar ) { progressBar.value = progressBar.max; }
		if ( button ) {
			button.disabled    = true;
			button.textContent = 'Selesai';
		}
		showError( '' );

		/* Remove migration pending notice from admin notices area */
		var notices = document.querySelectorAll( '.notice' );
		for ( var ni = 0; ni < notices.length; ni++ ) {
			if ( notices[ ni ].textContent.indexOf( 'Finalisasi Prototype' ) !== -1 ) {
				var np = notices[ ni ].parentNode;
				if ( np ) { np.removeChild( notices[ ni ] ); }
			}
		}

		/* Remove migration submenu item from admin menu */
		var menuLinks = document.querySelectorAll( '#adminmenu a' );
		for ( var mi = 0; mi < menuLinks.length; mi++ ) {
			if ( menuLinks[ mi ].textContent.indexOf( 'Finalisasi Prototype' ) !== -1 ) {
				var li = menuLinks[ mi ];
				while ( li && li.tagName !== 'LI' ) { li = li.parentNode; }
				if ( li && li.parentNode ) { li.parentNode.removeChild( li ); }
				break;
			}
		}

		/* Inline success banner replaces form area */
		if ( form ) {
			form.innerHTML = '<div class="notice notice-success inline"><p>'
				+ 'Finalisasi selesai. Kembali ke Gloskin Content…'
				+ '</p></div>';
		}

		/* Navigate to Content Overview after a short delay */
		var baseUrl = ajaxUrl.replace( /admin-ajax\.php.*$/, '' );
		window.setTimeout( function () {
			window.location.href = baseUrl + 'admin.php?page=gloskin-content&migrated=1';
		}, 1800 );
	}

	/* -------------------------------------------------------------------------
	 * Chain
	 * ---------------------------------------------------------------------- */

	function continueChain( state ) {
		updateProgress( state );
		if ( 'consumed' === String( state.status ) ) {
			onConsumed();
			return;
		}
		window.requestAnimationFrame( function () {
			request( 'continue' ).then( continueChain ).catch( onFail );
		} );
	}

	/* -------------------------------------------------------------------------
	 * Failure
	 * ---------------------------------------------------------------------- */

	function onFail( err ) {
		setBusy( false );
		if ( button ) {
			button.disabled    = false;
			button.textContent = 'Lanjutkan Finalisasi';
		}
		showError( err && err.message ? err.message : 'Checkpoint gagal. Coba lagi.' );
	}

	/* -------------------------------------------------------------------------
	 * Start / resume
	 * ---------------------------------------------------------------------- */

	function startChain() {
		if ( running ) { return; }
		setBusy( true );
		showError( '' );

		var initialStatus = root.getAttribute( 'data-status' ) || 'pending';

		if ( 'pending' === initialStatus ) {
			/* Fresh start: handshake to capture commerce snapshot, then continue */
			request( 'start' ).then( function ( state ) {
				root.setAttribute( 'data-status', String( state.status || 'running' ) );
				return request( 'continue' ).then( continueChain );
			} ).catch( onFail );
		} else {
			/* Resume from failed / running / verifying — skip start handshake */
			request( 'continue' ).then( function ( state ) {
				root.setAttribute( 'data-status', String( state.status || 'running' ) );
				continueChain( state );
			} ).catch( onFail );
		}
	}

	/* -------------------------------------------------------------------------
	 * Event wiring — intercept form submit; button click as fallback
	 * ---------------------------------------------------------------------- */

	if ( form ) {
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			startChain();
		} );
	} else if ( button ) {
		button.addEventListener( 'click', function () {
			startChain();
		} );
	}
}() );
