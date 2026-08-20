/** Secure AJAX controller for the Media Cleanup tool.
 *
 * Batch pacing: calm 500 ms setTimeout between batches.
 * Resumable: close tab → no more requests. Return later → Continue Scan.
 * No Pause/Resume state machine.
 */
( function () {
	'use strict';

	var root = document.querySelector( '[data-gloskin-media-cleanup]' );
	if ( ! root ) { return; }

	var ajaxUrl   = root.getAttribute( 'data-ajax' )        || '';
	var action    = root.getAttribute( 'data-action' )      || '';
	var nonce     = root.getAttribute( 'data-nonce' )       || '';
	var revision  = root.getAttribute( 'data-revision' )    || '';
	var token     = root.getAttribute( 'data-token' )       || '';
	var cursor    = Number( root.getAttribute( 'data-cursor' )  || 0 );
	var status    = root.getAttribute( 'data-status' )      || 'pending';
	var failedFrom = root.getAttribute( 'data-failed-from' ) || '';
	if ( 'failed' === status && failedFrom ) { status = failedFrom; }

	var running     = false;
	var retryLimit = 3;
	var batchDelay  = 500; /* ms between successful batches */

	var progress       = root.querySelector( '[data-media-cleanup-progress]' );
	var stage          = root.querySelector( '[data-media-cleanup-stage]' );
	var current        = root.querySelector( '[data-media-cleanup-current]' );
	var errorWrap      = root.querySelector( '[data-media-cleanup-error]' );
	var errorText      = errorWrap ? errorWrap.querySelector( 'p' ) : null;
	var indexButton    = root.querySelector( '[data-media-cleanup-index]' );
	var deleteButton   = root.querySelector( '[data-media-cleanup-delete]' );
	var deleteContinue = root.querySelector( '[data-media-cleanup-delete-continue]' );
	var confirmBox     = root.querySelector( '[data-media-cleanup-confirm]' );
	var table          = root.querySelector( '[data-media-cleanup-table]' );
	var pagination     = root.querySelector( '[data-media-cleanup-pagination]' );
	var resetButton    = root.querySelector( '[data-media-cleanup-reset]' );

	/* ------------------------------------------------------------------ */

	function encode( data ) {
		return Object.keys( data ).map( function ( k ) {
			return encodeURIComponent( k ) + '=' + encodeURIComponent( data[ k ] );
		} ).join( '&' );
	}

	function request( mode, extra, attempt ) {
		var body = Object.assign( { action: action, nonce: nonce, revision: revision, mode: mode }, extra || {} );
		attempt = Number( attempt || 0 );
		return fetch( ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: encode( body )
		} ).then( function ( response ) {
			return response.json().catch( function () {
				throw networkError( 'Respons server tidak valid.', response.status >= 500 );
			} ).then( function ( payload ) {
				if ( ! response.ok || ! payload || ! payload.success ) {
					var data  = payload && payload.data ? payload.data : {};
					var error = networkError( data.message || 'Request resolver gagal.', response.status >= 500 || Boolean( data.retryable ) );
					error.code = data.code || 'unexpected_error';
					throw error;
				}
				return payload.data || {};
			} );
		} ).catch( function ( error ) {
			var netFail = ! error.code && 'TypeError' === String( error.name || '' );
			if ( ( error.retryable || netFail ) && attempt < retryLimit ) {
				var delay = Math.min( 4000, 500 * Math.pow( 2, attempt ) );
				return new Promise( function ( resolve ) { window.setTimeout( resolve, delay ); } )
					.then( function () { return request( mode, extra, attempt + 1 ); } );
			}
			throw error;
		} );
	}

	function networkError( message, retryable ) {
		var e = new Error( message );
		e.retryable = Boolean( retryable );
		return e;
	}

	function setError( message ) {
		if ( ! errorWrap || ! errorText ) { return; }
		errorText.textContent = message || '';
		if ( message ) { errorWrap.removeAttribute( 'hidden' ); } else { errorWrap.setAttribute( 'hidden', '' ); }
	}

	function setText( selector, value ) {
		var node = root.querySelector( selector );
		if ( node ) { node.textContent = String( value ); }
	}

	function sync( state ) {
		if ( ! state ) { return; }
		status = String( state.status || status );
		if ( 'failed' === status && state.failed_from ) { status = String( state.failed_from ); }
		token  = String( state.manifest_token || token );
		cursor = Number( state.deletion_cursor || 0 );
		root.setAttribute( 'data-status', status );
		root.setAttribute( 'data-token',  token );
		root.setAttribute( 'data-cursor', String( cursor ) );
		if ( progress ) {
			if ( 'indexing' === status ) {
				progress.max   = Math.max( 1, Number( state.total    || 0 ) );
				progress.value = Math.min( Number( state.processed   || 0 ), progress.max );
			} else {
				progress.max   = Math.max( 1, Number( state.counts && state.counts[ 'confirmed-unused' ] || 1 ) );
				progress.value = Number( state.deletion_cursor || 0 );
			}
		}
		if ( stage ) {
			if ( 'indexing' === status ) {
				stage.textContent = Number( state.processed || 0 ) + ' / ' + Number( state.total || 0 ) + ' dipindai';
			} else if ( 'deleting' === status ) {
				stage.textContent = 'Menghapus ' + Number( state.deletion_cursor || 0 ) + ' / ' + Number( ( state.counts || {} )[ 'confirmed-unused' ] || 0 );
			} else if ( 'verifying' === status ) {
				stage.textContent = 'Memverifikasi…';
			}
		}
		if ( current ) { current.textContent = state.current_file || ''; }
		var counts = state.counts || {};
		setText( '[data-count-used]',      Number( counts.used              || 0 ) );
		setText( '[data-count-protected]', Number( counts.protected         || 0 ) );
		setText( '[data-count-ambiguous]', Number( counts.ambiguous         || 0 ) );
		setText( '[data-count-unused]',    Number( counts[ 'confirmed-unused' ] || 0 ) );
		setText( '[data-count-processed]', Number( state.processed          || 0 ) );
		if ( 'review_ready' === status ) { loadReview( 1 ); }
		if ( 'complete' === status ) { window.location.reload(); }
	}

	function setRunning( value ) {
		running = Boolean( value );
		if ( indexButton ) { indexButton.disabled = running; }
		if ( deleteButton ) { deleteButton.disabled = running || ! confirmBox || ! confirmBox.checked; }
		if ( deleteContinue ) { deleteContinue.disabled = running; }
	}

	function fail( error ) {
		setRunning( false );
		setError( error && error.message ? error.message : 'Resolver berhenti secara aman.' );
	}

	/* Scan chain: one request, calm 500 ms pause, next request. */
	function indexChain() {
		request( 'index' ).then( function ( state ) {
			sync( state );
			if ( 'indexing' === status ) {
				/* Calm setTimeout delay. */
				window.setTimeout( indexChain, batchDelay );
				return;
			}
			setRunning( false );
			if ( 'review_ready' === status ) { loadReview( 1 ); }
		} ).catch( fail );
	}

	/* Delete/verify chain with calm pacing. */
	function deleteChain() {
		var mode  = 'verifying' === status ? 'verify' : 'delete';
		var extra = 'delete' === mode ? { cursor: cursor, token: token, backup_confirmed: '1' } : {};
		request( mode, extra ).then( function ( state ) {
			sync( state );
			if ( 'deleting' === status || 'verifying' === status ) {
				window.setTimeout( deleteChain, batchDelay );
				return;
			}
			setRunning( false );
		} ).catch( fail );
	}

	function loadReview( page ) {
		request( 'review', { page: Number( page || 1 ) } ).then( function ( data ) {
			if ( ! table ) { return; }
			table.textContent = '';
			( data.items || [] ).forEach( function ( item ) {
				var row    = document.createElement( 'tr' );
				var age    = item.date ? Math.floor( ( Date.now() - new Date( item.date + ' UTC' ).getTime() ) / 86400000 ) + ' hari' : '';
				var detail = item.reason || '';
				if ( item.references && item.references.length ) { detail += ' — refs: ' + item.references.join( ', ' ); }
				if ( item.warnings   && item.warnings.length   ) { detail += ' — warnings: ' + item.warnings.join( ', ' ); }
				/* Thumbnail, filename, date/age, dimensions, size, reason. */
				var thumb = document.createElement( 'td' );
				if ( item.id ) {
					var img = document.createElement( 'img' );
					img.width  = 48;
					img.height = 48;
					img.style.objectFit = 'cover';
					img.alt  = item.filename || '';
					/* Thumbnail is non-authoritative: display only, not used for decisions. */
					thumb.appendChild( img );
				}
				row.appendChild( thumb );
				[ item.filename, item.date + ' (' + age + ')', item.dimensions, item.bytes ? Math.round( Number( item.bytes ) / 1024 ) + ' KB' : '—', detail ].forEach( function ( value ) {
					var cell = document.createElement( 'td' );
					cell.textContent = String( value || '' );
					row.appendChild( cell );
				} );
				table.appendChild( row );
			} );
			if ( pagination ) {
				pagination.textContent = '';
				for ( var p = 1; p <= Number( data.pages || 1 ); p++ ) {
					var btn = document.createElement( 'button' );
					btn.type      = 'button';
					btn.className = 'button';
					btn.textContent = String( p );
					btn.disabled    = p === Number( data.page );
					btn.setAttribute( 'data-review-page', String( p ) );
					pagination.appendChild( btn );
				}
			}
		} ).catch( fail );
	}

	/* ------------------------------------------------------------------ */

	if ( indexButton ) {
		indexButton.addEventListener( 'click', function () {
			if ( running ) { return; }
			setError( '' );
			setRunning( true );
			indexChain();
		} );
	}
	if ( deleteButton ) {
		deleteButton.addEventListener( 'click', function () {
			if ( running || ! confirmBox || ! confirmBox.checked ) { return; }
			setError( '' );
			setRunning( true );
			deleteChain();
		} );
	}
	if ( deleteContinue ) {
		deleteContinue.addEventListener( 'click', function () {
			if ( running ) { return; }
			setError( '' );
			setRunning( true );
			deleteChain();
		} );
	}
	if ( confirmBox ) {
		confirmBox.addEventListener( 'change', function () {
			if ( deleteButton ) { deleteButton.disabled = running || ! confirmBox.checked; }
		} );
	}
	if ( resetButton ) {
		resetButton.addEventListener( 'click', function () {
			if ( running ) { return; }
			if ( ! window.confirm( 'Mulai scan baru? Data hasil scan sebelumnya akan dihapus. Penghapusan permanen sebelumnya tidak dapat dibatalkan.' ) ) { return; }
			setError( '' );
			setRunning( true );
			request( 'reset' ).then( function () {
				window.location.reload();
			} ).catch( function ( error ) {
				setRunning( false );
				setError( error && error.message ? error.message : 'Reset gagal.' );
			} );
		} );
	}
	if ( pagination ) {
		pagination.addEventListener( 'click', function ( event ) {
			var btn = event.target.closest( '[data-review-page]' );
			if ( btn ) { loadReview( btn.getAttribute( 'data-review-page' ) ); }
		} );
	}
	/* On page load, restore review table if we're already past scan. */
	if ( 'review_ready' === status || 'deleting' === status || 'verifying' === status ) { loadReview( 1 ); }
}() );
