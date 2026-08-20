/* Exercise the production AJAX controller through its DOM/fetch boundary. */
'use strict';

const fs = require( 'fs' );
const vm = require( 'vm' );
const path = require( 'path' );

const source = fs.readFileSync( path.join( __dirname, '../plugin/gloskin-site-core/assets/js/gloskin-ui1-final-migration.js' ), 'utf8' );

function requireCondition( condition, message ) {
	if ( ! condition ) { throw new Error( message ); }
}

async function exercise( raw ) {
	const attributes = {
		'data-ajax': '/wp-admin/admin-ajax.php',
		'data-action': 'gloskin_test',
		'data-nonce': 'nonce',
		'data-status': 'pending',
		'data-total': '4',
	};
	const errorNode = { textContent: '' };
	const errorWrap = {
		querySelector: () => errorNode,
		setAttribute: () => {},
		removeAttribute: () => {},
	};
	const button = { disabled: false, textContent: '' };
	const progress = { max: 4, value: 0 };
	const step = { textContent: '' };
	let submit;
	const form = {
		innerHTML: '',
		addEventListener: ( event, callback ) => { if ( 'submit' === event ) { submit = callback; } },
	};
	const root = {
		getAttribute: ( name ) => attributes[ name ] || '',
		setAttribute: ( name, value ) => { attributes[ name ] = String( value ); },
		querySelector: ( selector ) => ({
			'[data-gloskin-migration-progressbar]': progress,
			'[data-gloskin-migration-step]': step,
			'[data-gloskin-migration-error]': errorWrap,
			'[data-gloskin-migration-form]': form,
			'[data-gloskin-migration-run]': button,
		})[ selector ] || null,
	};
	const context = {
		document: {
			querySelector: () => root,
			querySelectorAll: () => [],
		},
		fetch: () => Promise.resolve( { text: () => Promise.resolve( raw ) } ),
		window: {
			requestAnimationFrame: ( callback ) => callback(),
			setTimeout: () => {},
			location: { href: '' },
		},
		Error,
		Boolean,
		Number,
		String,
		JSON,
		Promise,
	};
	vm.runInNewContext( source, context );
	requireCondition( 'function' === typeof submit, 'migration submit handler was not registered' );
	submit( { preventDefault: () => {} } );
	await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
	await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
	return { attributes, error: errorNode.textContent };
}

( async function () {
	const valid = JSON.stringify( { success: true, data: { status: 'consumed', processed_steps: 4, total_steps: 4 } } );
	let result = await exercise( valid );
	requireCondition( 'consumed' === result.attributes[ 'data-status' ], 'raw JSON response must parse' );

	result = await exercise( '\uFEFF \r\n\t' + valid );
	requireCondition( 'consumed' === result.attributes[ 'data-status' ], 'one leading BOM plus whitespace must parse' );

	for ( const prefix of [ '\uFEFF\uFEFF', '<html>', 'Warning: PHP emitted output ' ] ) {
		result = await exercise( prefix + valid );
		requireCondition( 'failed' === result.attributes[ 'data-status' ], `unsafe prefix must fail: ${JSON.stringify( prefix )}` );
		requireCondition( 'Respons AJAX bukan JSON murni.' === result.error, 'unsafe prefix failure must remain visible' );
	}

	requireCondition( ! source.includes( 'response.json()' ), 'controller must read the complete response body safely' );
	requireCondition( ! /(?:indexOf|lastIndexOf)\s*\(\s*['"]\{/.test( source ), 'controller must not extract an arbitrary JSON object' );
	console.log( 'final-migration-ajax-response-contract.js: OK (strict raw JSON + one BOM tolerance)' );
}() ).catch( ( error ) => {
	console.error( error.stack || error.message );
	process.exit( 1 );
} );
