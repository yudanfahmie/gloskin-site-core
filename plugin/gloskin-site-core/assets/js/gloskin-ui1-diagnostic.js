/**
 * Progressive AJAX download for the namaste-only diagnostic page.
 * The native admin-post form remains the no-JS fallback.
 *
 * @package GloskinSiteCore
 */
( function () {
	'use strict';

	var form = document.querySelector( '[data-gloskin-diagnostic-form]' );
	if ( ! form || ! window.fetch || ! window.FormData || ! window.URL ) { return; }

	var ajaxUrl  = form.getAttribute( 'data-ajax' ) || '';
	var button   = form.querySelector( '[data-gloskin-diagnostic-submit]' );
	var spinner  = form.querySelector( '[data-gloskin-diagnostic-spinner]' );
	var status   = form.querySelector( '[data-gloskin-diagnostic-status]' );
	var progress = form.querySelector( '[data-gloskin-diagnostic-progress]' );
	var running  = false;
	var attempts = 3;

	if ( ! ajaxUrl || ! button ) { return; }

	function setBusy( busy ) {
		running = busy;
		button.disabled = busy;
		form.setAttribute( 'aria-busy', busy ? 'true' : 'false' );
		if ( spinner ) { spinner.setAttribute( 'aria-hidden', busy ? 'false' : 'true' ); }
		if ( progress ) {
			progress.hidden = ! busy;
			progress.setAttribute( 'aria-hidden', busy ? 'false' : 'true' );
		}
	}

	function setStatus( message, state ) {
		if ( status ) { status.textContent = message; status.setAttribute( 'data-state', state || '' ); }
	}

	function sleep( milliseconds ) {
		return new Promise( function ( resolve ) { window.setTimeout( resolve, milliseconds ); } );
	}

	function filenameFrom( response ) {
		var disposition = response.headers.get( 'Content-Disposition' ) || '';
		var match = disposition.match( /filename="?([^";]+)"?/i );
		return match && match[ 1 ] ? match[ 1 ].replace( /[\\/]/g, '-' ) : 'gloskin-diagnostic.zip';
	}

	function responseError( response ) {
		return response.text().then( function ( text ) {
			var message = 'Diagnostic generation failed (HTTP ' + response.status + ').';
			var retryable = 429 === response.status || response.status >= 500;
			try {
				var payload = JSON.parse( text );
				if ( payload && payload.data ) {
					if ( payload.data.message ) { message = payload.data.message; }
					if ( Object.prototype.hasOwnProperty.call( payload.data, 'retryable' ) ) { retryable = Boolean( payload.data.retryable ); }
				}
			} catch ( ignored ) {}
			var error = new Error( message );
			error.retryable = retryable;
			error.status = response.status;
			throw error;
		} );
	}

	function requestArchive() {
		var body = new URLSearchParams();
		new FormData( form ).forEach( function ( value, key ) { body.append( key, value ); } );
		return fetch( ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'Cache-Control': 'no-store' },
			body: body.toString(),
		} ).then( function ( response ) {
			if ( ! response.ok ) { return responseError( response ); }
			var type = response.headers.get( 'Content-Type' ) || '';
			if ( -1 === type.indexOf( 'application/zip' ) ) {
				var error = new Error( 'The server returned an unexpected response instead of a ZIP archive.' );
				error.retryable = false;
				throw error;
			}
			return response.blob().then( function ( blob ) {
				if ( ! blob || blob.size < 1 ) {
					var empty = new Error( 'The generated ZIP archive was empty.' );
					empty.retryable = true;
					throw empty;
				}
				return { blob: blob, filename: filenameFrom( response ) };
			} );
		} );
	}

	function withRetry() {
		var attempt = 1;
		function run() {
			setStatus( 1 === attempt ? 'Collecting diagnostic data and building ZIP…' : 'Retrying safely (' + attempt + '/' + attempts + ')…', 'loading' );
			return requestArchive().catch( function ( error ) {
				var networkFailure = error instanceof TypeError;
				var retryable = networkFailure || Boolean( error && error.retryable );
				if ( ! retryable || attempt >= attempts ) { throw error; }
				var delay = 800 * Math.pow( 2, attempt - 1 );
				attempt += 1;
				return sleep( delay ).then( run );
			} );
		}
		return run();
	}

	function download( result ) {
		var objectUrl = window.URL.createObjectURL( result.blob );
		var link = document.createElement( 'a' );
		link.href = objectUrl;
		link.download = result.filename;
		link.style.display = 'none';
		document.body.appendChild( link );
		link.click();
		link.remove();
		window.setTimeout( function () { window.URL.revokeObjectURL( objectUrl ); }, 30000 );
	}

	form.addEventListener( 'submit', function ( event ) {
		if ( running ) { event.preventDefault(); return; }
		event.preventDefault();
		setBusy( true );
		setStatus( 'Preparing diagnostic…', 'loading' );
		withRetry().then( function ( result ) {
			download( result );
			setStatus( 'Download ready. Temporary files were removed from the server.', 'success' );
		} ).catch( function ( error ) {
			setStatus( error && error.message ? error.message : 'Diagnostic generation failed. Please try again.', 'error' );
		} ).then( function () { setBusy( false ); } );
	} );
}() );
