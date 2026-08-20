/** Secure AJAX controller for the one-shot Media Cleanup Resolver. */
( function () {
	'use strict';

	var root = document.querySelector( '[data-gloskin-media-cleanup]' );
	if ( ! root ) { return; }
	var ajaxUrl = root.getAttribute( 'data-ajax' ) || '';
	var action = root.getAttribute( 'data-action' ) || '';
	var nonce = root.getAttribute( 'data-nonce' ) || '';
	var revision = root.getAttribute( 'data-revision' ) || '';
	var token = root.getAttribute( 'data-token' ) || '';
	var cursor = Number( root.getAttribute( 'data-cursor' ) || 0 );
	var status = root.getAttribute( 'data-status' ) || 'pending';
	var resumeStatus = root.getAttribute( 'data-resume-status' ) || '';
	if ( 'failed' === status && resumeStatus ) { status = resumeStatus; }
	var running = false;
	var paused = false;
	var retryLimit = 3;
	var progress = root.querySelector( '[data-media-cleanup-progress]' );
	var stage = root.querySelector( '[data-media-cleanup-stage]' );
	var current = root.querySelector( '[data-media-cleanup-current]' );
	var errorWrap = root.querySelector( '[data-media-cleanup-error]' );
	var errorText = errorWrap ? errorWrap.querySelector( 'p' ) : null;
	var indexButton = root.querySelector( '[data-media-cleanup-index]' );
	var pauseButton = root.querySelector( '[data-media-cleanup-pause]' );
	var deleteButton = root.querySelector( '[data-media-cleanup-delete]' );
	var confirmBox = root.querySelector( '[data-media-cleanup-confirm]' );
	var review = root.querySelector( '[data-media-cleanup-review]' );
	var table = root.querySelector( '[data-media-cleanup-table]' );
	var pagination = root.querySelector( '[data-media-cleanup-pagination]' );

	function encode( data ) {
		return Object.keys( data ).map( function ( key ) { return encodeURIComponent( key ) + '=' + encodeURIComponent( data[ key ] ); } ).join( '&' );
	}

	function request( mode, extra, attempt ) {
		var body = Object.assign( { action: action, nonce: nonce, revision: revision, mode: mode }, extra || {} );
		attempt = Number( attempt || 0 );
		return fetch( ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: encode( body ) } ).then( function ( response ) {
			return response.json().catch( function () { throw networkError( 'Respons server tidak valid.', response.status >= 500 ); } ).then( function ( payload ) {
				if ( ! response.ok || ! payload || ! payload.success ) {
					var data = payload && payload.data ? payload.data : {};
					var error = networkError( data.message || 'Request resolver gagal.', response.status >= 500 || Boolean( data.retryable ) );
					error.code = data.code || 'unexpected_error';
					throw error;
				}
				return payload.data || {};
			} );
		} ).catch( function ( error ) {
			var networkFailure = ! error.code && 'TypeError' === String( error.name || '' );
			if ( ( error.retryable || networkFailure ) && attempt < retryLimit ) {
				var delay = Math.min( 4000, 500 * Math.pow( 2, attempt ) );
				return new Promise( function ( resolve ) { window.setTimeout( resolve, delay ); } ).then( function () { return request( mode, extra, attempt + 1 ); } );
			}
			throw error;
		} );
	}

	function networkError( message, retryable ) { var error = new Error( message ); error.retryable = Boolean( retryable ); return error; }

	function setError( message ) {
		if ( ! errorWrap || ! errorText ) { return; }
		errorText.textContent = message || '';
		if ( message ) { errorWrap.removeAttribute( 'hidden' ); } else { errorWrap.setAttribute( 'hidden', '' ); }
	}

	function setText( selector, value ) { var node = root.querySelector( selector ); if ( node ) { node.textContent = String( value ); } }

	function sync( state ) {
		if ( ! state ) { return; }
		status = String( state.status || status );
		token = String( state.manifest_token || token );
		cursor = Number( state.deletion_cursor || 0 );
		root.setAttribute( 'data-status', status ); root.setAttribute( 'data-token', token ); root.setAttribute( 'data-cursor', String( cursor ) );
		if ( progress ) { progress.max = Math.max( 1, Number( state.total || 0 ) ); progress.value = Math.min( Number( state.processed || 0 ), progress.max ); }
		if ( stage ) { stage.textContent = status + ' — ' + Number( state.processed || 0 ) + '/' + Number( state.total || 0 ); }
		if ( current ) { current.textContent = state.current_file || ''; }
		var counts = state.counts || {};
		setText( '[data-count-total]', Number( state.total || 0 ) );
		setText( '[data-count-used]', Number( counts.used || 0 ) );
		setText( '[data-count-protected]', Number( counts.protected || 0 ) );
		setText( '[data-count-ambiguous]', Number( counts.ambiguous || 0 ) );
		setText( '[data-count-unused]', Number( counts[ 'confirmed-unused' ] || 0 ) );
		setText( '[data-delete-counts]', Object.keys( state.deleted || {} ).length + ' / ' + Object.keys( state.skipped || {} ).length + ' / ' + Object.keys( state.failed || {} ).length );
		setText( '[data-byte-counts]', Number( state.estimated_bytes || 0 ) + ' / ' + Number( state.actual_bytes || 0 ) + ' bytes' );
		if ( 'review_ready' === status || 'deleting' === status || 'verifying' === status ) { if ( review ) { review.removeAttribute( 'hidden' ); } loadReview( 1 ); }
		if ( 'consumed' === status ) { onConsumed(); }
	}

	function setRunning( value ) {
		running = Boolean( value );
		if ( indexButton ) { indexButton.disabled = running; }
		if ( deleteButton ) { deleteButton.disabled = running || ! confirmBox || ! confirmBox.checked; }
		if ( pauseButton ) { if ( running ) { pauseButton.removeAttribute( 'hidden' ); } else { pauseButton.setAttribute( 'hidden', '' ); } }
	}

	function fail( error ) { setRunning( false ); setError( error && error.message ? error.message : 'Resolver berhenti secara aman.' ); }

	function indexChain() {
		if ( paused ) { setRunning( false ); return; }
		request( 'index' ).then( function ( state ) {
			sync( state );
			if ( 'indexing' === status ) { window.requestAnimationFrame( indexChain ); return; }
			setRunning( false );
			if ( indexButton ) { indexButton.setAttribute( 'hidden', '' ); }
		} ).catch( fail );
	}

	function deleteChain() {
		if ( paused ) { setRunning( false ); return; }
		var mode = 'verifying' === status ? 'verify' : 'delete';
		var extra = 'delete' === mode ? { cursor: cursor, token: token, backup_confirmed: '1' } : {};
		request( mode, extra ).then( function ( state ) {
			sync( state );
			if ( 'deleting' === status || 'verifying' === status ) { window.requestAnimationFrame( deleteChain ); return; }
			setRunning( false );
		} ).catch( fail );
	}

	function loadReview( page ) {
		request( 'review', { page: Number( page || 1 ) } ).then( function ( data ) {
			if ( ! table ) { return; }
			table.textContent = '';
			( data.items || [] ).forEach( function ( item ) {
				var row = document.createElement( 'tr' );
				var detail = item.reason || '';
				if ( item.references && item.references.length ) { detail += ' — refs: ' + item.references.join( ', ' ); }
				if ( item.warnings && item.warnings.length ) { detail += ' — warnings: ' + item.warnings.join( ', ' ); }
				[ item.id, item.filename, item.mime, item.date, item.dimensions, item.bytes, item.classification, detail ].forEach( function ( value ) { var cell = document.createElement( 'td' ); cell.textContent = String( value || '' ); row.appendChild( cell ); } );
				table.appendChild( row );
			} );
			if ( pagination ) {
				pagination.textContent = '';
				for ( var p = 1; p <= Number( data.pages || 1 ); p++ ) { var button = document.createElement( 'button' ); button.type = 'button'; button.className = 'button'; button.textContent = String( p ); button.disabled = p === Number( data.page ); button.setAttribute( 'data-review-page', String( p ) ); pagination.appendChild( button ); }
			}
		} ).catch( fail );
	}

	function onConsumed() {
		setRunning( false ); setError( '' );
		if ( stage ) { stage.textContent = 'consumed — verifikasi selesai'; }
		if ( deleteButton ) { deleteButton.disabled = true; }
		var links = document.querySelectorAll( '#adminmenu a' );
		for ( var i = 0; i < links.length; i++ ) { if ( links[ i ].textContent.indexOf( 'Media Cleanup Resolver' ) !== -1 ) { var item = links[ i ].closest( 'li' ); if ( item ) { item.remove(); } break; } }
	}

	if ( indexButton ) { indexButton.addEventListener( 'click', function () { if ( running ) { return; } paused = false; setError( '' ); setRunning( true ); indexChain(); } ); }
	if ( deleteButton ) { deleteButton.addEventListener( 'click', function () { if ( running || ! confirmBox || ! confirmBox.checked ) { return; } paused = false; setError( '' ); setRunning( true ); deleteChain(); } ); }
	if ( confirmBox ) { confirmBox.addEventListener( 'change', function () { if ( deleteButton ) { deleteButton.disabled = running || ! confirmBox.checked; } } ); }
	if ( pauseButton ) { pauseButton.addEventListener( 'click', function () { paused = true; request( 'pause' ).then( sync ).catch( fail ); setRunning( false ); } ); }
	if ( pagination ) { pagination.addEventListener( 'click', function ( event ) { var button = event.target.closest( '[data-review-page]' ); if ( button ) { loadReview( button.getAttribute( 'data-review-page' ) ); } } ); }
	if ( 'review_ready' === status || 'deleting' === status || 'verifying' === status ) { loadReview( 1 ); }
}() );
