/**
 * AJAX controller for the bounded 2026-08-19-final migration.
 *
 * Exact DOM contract:
 * [data-gloskin-final-migration]
 *   [data-gloskin-migration-progressbar]
 *   [data-gloskin-migration-step]
 *   [data-gloskin-migration-error]
 *   [data-gloskin-migration-form]
 *   [data-gloskin-migration-run]
 *
 * @package GloskinSiteCore
 */
( function () {
	'use strict';

	var root = document.querySelector( '[data-gloskin-final-migration]' );
	if ( ! root ) { return; }

	var ajaxUrl = root.getAttribute( 'data-ajax' ) || '';
	var action  = root.getAttribute( 'data-action' ) || '';
	var nonce   = root.getAttribute( 'data-nonce' ) || '';
	if ( ! ajaxUrl || ! action || ! nonce ) { return; }

	var progressBar = root.querySelector( '[data-gloskin-migration-progressbar]' );
	var stepNode    = root.querySelector( '[data-gloskin-migration-step]' );
	var errorWrap   = root.querySelector( '[data-gloskin-migration-error]' );
	var errorNode   = errorWrap ? errorWrap.querySelector( 'p' ) : null;
	var form        = root.querySelector( '[data-gloskin-migration-form]' );
	var button      = root.querySelector( '[data-gloskin-migration-run]' );
	var running     = false;

	function setBusy( busy ) {
		running = busy;
		if ( button ) { button.disabled = busy; }
	}

	function showError( message ) {
		if ( ! errorWrap || ! errorNode ) { return; }
		errorNode.textContent = message || '';
		if ( message ) {
			errorWrap.removeAttribute( 'hidden' );
		} else {
			errorWrap.setAttribute( 'hidden', '' );
		}
	}

	function updateProgress( state ) {
		var processed = Number( state.processed_steps || 0 );
		var total     = Number( state.total_steps || 8 );
		if ( progressBar ) {
			progressBar.max   = total > 0 ? total : 8;
			progressBar.value = Math.min( processed, progressBar.max );
		}
		if ( stepNode && state.current_step ) {
			stepNode.textContent = state.current_step;
		}
	}

	function syncState( state ) {
		if ( ! state ) { return; }
		root.setAttribute( 'data-status', String( state.status || 'running' ) );
		root.setAttribute( 'data-processed', String( Number( state.processed_steps || 0 ) ) );
		root.setAttribute( 'data-total', String( Number( state.total_steps || 8 ) ) );
		updateProgress( state );
	}

	/**
	 * Parse the complete AJAX response without hiding server-side output.
	 *
	 * A single legacy UTF-8 BOM is tolerated at byte zero. Any other prefix
	 * remains visible as a hard failure instead of being trimmed to a later
	 * JSON object.
	 *
	 * @param {*} raw Complete response body.
	 * @return {Object} Parsed WordPress AJAX payload.
	 */
	function parseAjaxResponse( raw ) {
		var text = String( null == raw ? '' : raw );

		if ( '\uFEFF' === text.charAt( 0 ) ) {
			text = text.slice( 1 );
		}
		text = text.replace( /^[\t\n\r ]+/, '' );

		if ( '{' !== text.charAt( 0 ) ) {
			throw new Error( 'Respons AJAX bukan JSON murni.' );
		}

		return JSON.parse( text );
	}

	function request( mode ) {
		var body = 'action=' + encodeURIComponent( action )
			+ '&nonce=' + encodeURIComponent( nonce )
			+ '&mode=' + encodeURIComponent( mode );

		return fetch( ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body,
		} ).then( function ( response ) {
			return response.text().then( function ( raw ) {
				var payload = parseAjaxResponse( raw );
				if ( ! payload || ! payload.success ) {
					var data  = payload && payload.data ? payload.data : {};
					var error = new Error( data.message || 'Checkpoint gagal.' );
					error.code      = data.code || 'unexpected_error';
					error.step      = data.step || '';
					error.retryable = Boolean( data.retryable );
					throw error;
				}
				return payload.data || {};
			} );
		} );
	}

	function removeMigrationChrome() {
		var notices = document.querySelectorAll( '.notice' );
		for ( var i = 0; i < notices.length; i++ ) {
			if ( notices[ i ].textContent.indexOf( 'Finalisasi Prototype' ) !== -1 ) {
				notices[ i ].remove();
			}
		}

		var menuLinks = document.querySelectorAll( '#adminmenu a' );
		for ( var j = 0; j < menuLinks.length; j++ ) {
			if ( menuLinks[ j ].textContent.indexOf( 'Finalisasi Prototype' ) !== -1 ) {
				var li = menuLinks[ j ].closest ? menuLinks[ j ].closest( 'li' ) : null;
				if ( li ) { li.remove(); }
				break;
			}
		}
	}

	function onConsumed( state ) {
		syncState( state || {
			status: 'consumed',
			processed_steps: Number( root.getAttribute( 'data-total' ) || 8 ),
			total_steps: Number( root.getAttribute( 'data-total' ) || 8 ),
			current_step: 'Finalisasi selesai',
		} );
		root.setAttribute( 'data-status', 'consumed' );
		setBusy( false );
		showError( '' );

		if ( stepNode ) { stepNode.textContent = 'Finalisasi selesai'; }
		if ( progressBar ) { progressBar.value = progressBar.max; }
		if ( button ) {
			button.disabled = true;
			button.textContent = 'Selesai';
		}
		removeMigrationChrome();

		if ( form ) {
			form.innerHTML = '<div class="notice notice-success inline"><p>Finalisasi selesai</p></div>';
		}

		var baseUrl = ajaxUrl.replace( /admin-ajax\.php.*$/, '' );
		window.setTimeout( function () {
			window.location.href = baseUrl + 'admin.php?page=gloskin-content&migrated=1';
		}, 1800 );
	}

	function onFail( error ) {
		setBusy( false );
		root.setAttribute( 'data-status', 'failed' );
		if ( error && error.code ) {
			root.setAttribute( 'data-error-code', String( error.code ) );
		}
		if ( error && error.step ) {
			root.setAttribute( 'data-error-step', String( error.step ) );
			if ( stepNode ) { stepNode.textContent = String( error.step ); }
		}
		root.setAttribute( 'data-error-retryable', error && error.retryable ? '1' : '0' );

		if ( button ) {
			button.disabled = false;
			button.textContent = 'Lanjutkan Finalisasi';
		}
		showError( error && error.message ? error.message : 'Checkpoint gagal. Coba lagi.' );
	}

	function continueChain( state ) {
		syncState( state );
		if ( 'consumed' === String( state.status ) ) {
			onConsumed( state );
			return;
		}
		window.requestAnimationFrame( function () {
			request( 'continue' ).then( continueChain ).catch( onFail );
		} );
	}

	function startChain() {
		if ( running ) { return; }

		var status = root.getAttribute( 'data-status' ) || 'pending';
		if ( 'consumed' === status ) {
			onConsumed();
			return;
		}

		setBusy( true );
		showError( '' );

		if ( 'pending' === status ) {
			request( 'start' ).then( continueChain ).catch( onFail );
			return;
		}

		/* failed/running/verifying always resume the persisted server state. */
		request( 'continue' ).then( continueChain ).catch( onFail );
	}

	if ( form ) {
		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			startChain();
		} );
	} else if ( button ) {
		button.addEventListener( 'click', function ( event ) {
			if ( event ) { event.preventDefault(); }
			startChain();
		} );
	}
}() );
