<?php
declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );

function get_option( $key, $default = false ) { return $default; }

final class Gloskin_Site_Core_Content_Service {
	const FAMILY_TAXONOMY = 'gloskin_product_family';
	const CONCERN_TAXONOMY = 'gloskin_concern';
	const CONSULTATION_TAXONOMY = 'gloskin_consultation_path';
	const QUESTION_POST_TYPE = 'gloskin_question';
	const ANSWER_META_KEY = 'gloskin_question_answers';
	const PATH_META_ORDER = 'gloskin_path_order';
	const PATH_META_BASELINE = 'gloskin_path_baseline_concerns';
	const FAMILY_TREATMENT = 'treatment';
	const QUESTION_MIN_PUBLISHED = 13;
}

require dirname( __DIR__ ) . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-consultation-demo-importer.php';

function fail_contract( $message ) { fwrite( STDERR, $message . "\n" ); exit( 1 ); }
function assert_contract( $condition, $message ) { if ( ! $condition ) { fail_contract( $message ); } }
function private_static( $method ) {
	$reflection = new ReflectionMethod( 'Gloskin_Site_Core_Consultation_Demo_Importer', $method );
	$reflection->setAccessible( true );
	return $reflection->invoke( null );
}

// Demo import is an explicit privileged admin workflow, not an environment
// gate: run() must refuse cleanly without the explicit confirmation, and
// the class must carry zero WP_ENVIRONMENT_TYPE/wp_get_environment_type()/
// is_environment_allowed() dependency (or any replacement environment
// signal -- hostname guessing, WP_DEBUG, a second environment constant).
try {
	Gloskin_Site_Core_Consultation_Demo_Importer::run( false );
	fail_contract( 'run( false ) must refuse without the explicit synthetic-data confirmation' );
} catch ( RuntimeException $error ) {
	assert_contract( false !== stripos( $error->getMessage(), 'konfirmasi' ), 'refusal message must explain the missing confirmation' );
}
assert_contract( ! method_exists( 'Gloskin_Site_Core_Consultation_Demo_Importer', 'is_environment_allowed' ), 'is_environment_allowed() must be removed entirely, not merely bypassed' );

$paths = private_static( 'path_definitions' );
$concerns = private_static( 'concern_definitions' );
$questions = private_static( 'question_definitions' );
$products = private_static( 'product_definitions' );

assert_contract( count( $paths ) >= 4, 'Demo bundle must provide at least four consultation paths' );
assert_contract( count( $concerns ) >= 10, 'Demo bundle must provide at least ten concerns' );
assert_contract( count( $questions ) >= 13, 'Demo bundle must provide at least thirteen questions' );
assert_contract( 8 === count( $products ), 'Demo bundle must provide exactly eight Woo Treatment Products' );

$question_slugs = array_column( $questions, 'slug' );
$product_skus = array_column( $products, 'sku' );
assert_contract( count( $question_slugs ) === count( array_unique( $question_slugs ) ), 'Question demo identities must be deterministic/unique' );
assert_contract( count( $product_skus ) === count( array_unique( $product_skus ) ), 'Treatment Product demo SKUs must be deterministic/unique' );
foreach ( $questions as $question ) {
	foreach ( $question['answers'] as $answer ) {
		$weight = (int) $answer['weight'];
		assert_contract( $weight >= 1 && $weight <= 3, 'Demo answer weight escaped 1..3 contract' );
		if ( '' !== $answer['concern'] ) { assert_contract( isset( $concerns[ $answer['concern'] ] ), 'Demo answer references an undefined concern' ); }
	}
}
foreach ( $products as $product ) {
	foreach ( $product['concerns'] as $concern ) { assert_contract( isset( $concerns[ $concern ] ), 'Demo product references an undefined concern' ); }
}

$source = file_get_contents( dirname( __DIR__ ) . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-consultation-demo-importer.php' );
if ( false === $source ) { fail_contract( 'Unable to read consultation demo importer source' ); }
foreach ( array( 'upsert_paths', 'upsert_concerns', 'upsert_questions', 'upsert_products' ) as $method ) {
	assert_contract( false !== strpos( $source, 'function ' . $method ), "Missing idempotent phase {$method}" );
}
assert_contract( false !== strpos( $source, "self::SOURCE_PREFIX . 'question:'" ), 'Question source identity must be stable across partial reruns' );
assert_contract( false !== strpos( $source, "self::SOURCE_PREFIX . 'product:'" ), 'Product source identity must be stable across partial reruns' );
assert_contract( false !== strpos( $source, "get_term_by( 'slug'" ), 'Term upserts must resolve existing deterministic slugs before insert' );
assert_contract( false !== strpos( $source, 'wc_get_product_id_by_sku' ), 'Woo product upsert must resolve deterministic SKU before create' );
assert_contract( false !== strpos( $source, "'meta_key'       => 'gloskin_demo_source'" ), 'Question upsert must resolve its stable source identity before create' );
assert_contract( false === strpos( $source, '$wpdb' ), 'Demo importer must not use a custom table' );
assert_contract( false !== strpos( $source, 'wp_set_object_terms' ), 'Demo mapping must remain native taxonomy relationships' );

// Explicit privileged confirmation replaces the environment gate entirely --
// no WP_ENVIRONMENT_TYPE, wp_get_environment_type(), hostname guessing,
// WP_DEBUG check, or a second environment constant may reappear.
foreach ( array( 'wp_get_environment_type', 'WP_ENVIRONMENT_TYPE', 'WP_DEBUG', 'gethostname', '$_SERVER[\'HTTP_HOST\'' ) as $forbidden ) {
	assert_contract( false === strpos( $source, $forbidden ), "Demo importer must not depend on: {$forbidden}" );
}
assert_contract( false !== strpos( $source, 'function run( $confirmed = false )' ), 'run() must take an explicit confirmed parameter' );
assert_contract( false !== strpos( $source, 'if ( ! $confirmed )' ), 'run() must refuse without the explicit confirmation' );

$admin_source = file_get_contents( dirname( __DIR__ ) . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-admin-service.php' );
if ( false === $admin_source ) { fail_contract( 'Unable to read AdminService source' ); }
assert_contract( false !== strpos( $admin_source, "'confirm_demo_import'" ), 'Server must independently re-verify confirm_demo_import, never trust the HTML checkbox alone' );
assert_contract( false !== strpos( $admin_source, "'1' === sanitize_text_field( wp_unslash( \$_POST['confirm_demo_import'] ) )" ), 'confirm_demo_import must be verified server-side against an exact value' );
assert_contract( false !== strpos( $admin_source, 'Consultation_Demo_Importer::run( $confirmed )' ), 'handle_demo_import() must pass the server-verified confirmation into run()' );
assert_contract( false !== strpos( $admin_source, 'name="confirm_demo_import" value="1" required' ), 'the demo import form must render the required confirmation checkbox' );
assert_contract( false !== strpos( $admin_source, 'Saya memahami bahwa data demo sintetis akan dibuat pada situs ini.' ), 'the confirmation label must state the exact required copy' );
assert_contract( false === strpos( $admin_source, 'is_environment_allowed' ), 'AdminService must no longer reference the removed environment gate' );

echo "consultation-demo-importer-contract.php: OK\n";
