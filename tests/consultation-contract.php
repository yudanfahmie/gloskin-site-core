<?php
declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['gl_taxonomies'] = array();
$GLOBALS['gl_post_types'] = array();
$GLOBALS['gl_terms'] = array();
$GLOBALS['gl_inserted_terms'] = array();

function __( $text, $domain = null ) { return $text; }
function add_action() {}
function register_post_type( $type, $args ) { $GLOBALS['gl_post_types'][ $type ] = $args; }
function register_taxonomy( $taxonomy, $object_type, $args = array() ) { $GLOBALS['gl_taxonomies'][ $taxonomy ] = array( 'object_type' => $object_type, 'args' => $args ); }
function register_post_meta() {}
function register_term_meta() {}
function post_type_exists( $type ) { return false; }
function taxonomy_exists( $taxonomy ) { return isset( $GLOBALS['gl_taxonomies'][ $taxonomy ] ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
if ( ! function_exists( 'mb_substr' ) ) { function mb_substr( $value, $start, $length = null ) { return null === $length ? substr( $value, $start ) : substr( $value, $start, $length ); } }
function current_user_can() { return true; }
function term_exists( $term, $taxonomy = '' ) {
	if ( is_int( $term ) || ctype_digit( (string) $term ) ) { return 7 === (int) $term ? array( 'term_id' => 7 ) : null; }
	return isset( $GLOBALS['gl_terms'][ $taxonomy ][ (string) $term ] ) ? $GLOBALS['gl_terms'][ $taxonomy ][ (string) $term ] : null;
}
function wp_insert_term( $label, $taxonomy, $args = array() ) {
	$slug = isset( $args['slug'] ) ? (string) $args['slug'] : strtolower( str_replace( ' ', '-', $label ) );
	$id = count( $GLOBALS['gl_inserted_terms'] ) + 100;
	$term = array( 'term_id' => $id, 'slug' => $slug, 'name' => $label );
	$GLOBALS['gl_terms'][ $taxonomy ][ $slug ] = $term;
	$GLOBALS['gl_inserted_terms'][] = array( $taxonomy, $slug );
	return array( 'term_id' => $id );
}

require dirname( __DIR__ ) . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-content-service.php';

function fail_contract( $message ) { fwrite( STDERR, $message . "\n" ); exit( 1 ); }
function assert_contract( $condition, $message ) { if ( ! $condition ) { fail_contract( $message ); } }

Gloskin_Site_Core_Content_Service::register_content_types();
Gloskin_Site_Core_Content_Service::register_taxonomies();

$required_taxonomies = array(
	Gloskin_Site_Core_Content_Service::FAMILY_TAXONOMY => 'product',
	Gloskin_Site_Core_Content_Service::CONCERN_TAXONOMY => 'product',
	Gloskin_Site_Core_Content_Service::CONSULTATION_TAXONOMY => Gloskin_Site_Core_Content_Service::QUESTION_POST_TYPE,
);
foreach ( $required_taxonomies as $taxonomy => $object_type ) {
	assert_contract( isset( $GLOBALS['gl_taxonomies'][ $taxonomy ] ), "Missing consultation taxonomy: {$taxonomy}" );
	assert_contract( $GLOBALS['gl_taxonomies'][ $taxonomy ]['object_type'] === $object_type, "Wrong object type for {$taxonomy}" );
	assert_contract( empty( $GLOBALS['gl_taxonomies'][ $taxonomy ]['args']['public'] ), "{$taxonomy} must remain private" );
	assert_contract( false === $GLOBALS['gl_taxonomies'][ $taxonomy ]['args']['rewrite'], "{$taxonomy} must not own a public rewrite" );
}

$question_args = $GLOBALS['gl_post_types'][ Gloskin_Site_Core_Content_Service::QUESTION_POST_TYPE ] ?? array();
assert_contract( ! empty( $question_args['show_ui'] ), 'Question CPT must keep native admin UI available' );
assert_contract( empty( $question_args['public'] ) && empty( $question_args['publicly_queryable'] ), 'Question CPT must remain private/non-public' );
assert_contract( false === $question_args['rewrite'] && false === $question_args['query_var'], 'Question CPT must not own a public route' );
assert_contract( false === $question_args['show_in_menu'], 'Question CPT must not create a second sidebar owner' );

Gloskin_Site_Core_Content_Service::ensure_family_terms();
Gloskin_Site_Core_Content_Service::ensure_family_terms();
$family_slugs = array_map( static function ( $row ) { return $row[1]; }, $GLOBALS['gl_inserted_terms'] );
sort( $family_slugs );
assert_contract( $family_slugs === array( 'skincare', 'treatment' ), 'Stable family terms must be exactly skincare+treatment and idempotent' );

$service = new Gloskin_Site_Core_Content_Service();
$answers = $service->sanitize_answer_options( array(
	array( 'label' => ' Valid high ', 'concern_id' => 7, 'weight' => 9 ),
	array( 'label' => 'Valid low', 'concern_id' => 7, 'weight' => 0 ),
	array( 'label' => 'Invalid concern', 'concern_id' => 999, 'weight' => 2 ),
	array( 'label' => '', 'concern_id' => 7, 'weight' => 2 ),
) );
assert_contract( 2 === count( $answers ), 'Invalid/empty answer options must be rejected without becoming stored mappings' );
assert_contract( 3 === $answers[0]['weight'] && 1 === $answers[1]['weight'], 'Answer weights must clamp to 1..3' );
assert_contract( 7 === $answers[0]['concern_id'] && 7 === $answers[1]['concern_id'], 'Only valid gloskin_concern IDs may survive sanitization' );

$admin_source = file_get_contents( dirname( __DIR__ ) . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-admin-service.php' );
if ( false === $admin_source ) { fail_contract( 'Unable to read AdminService source' ); }
assert_contract( 1 === preg_match_all( "/add_submenu_page\(\s*\n?\s*'edit\.php\?post_type=product'/", $admin_source ), 'Expected exactly one consultation submenu under Woo Products' );
$mapping_start = strpos( $admin_source, 'public function handle_save_mapping()' );
$mapping_end = strpos( $admin_source, 'public function handle_demo_import()', $mapping_start );
assert_contract( false !== $mapping_start && false !== $mapping_end, 'Mapping persistence method boundary missing' );
$mapping_body = substr( $admin_source, $mapping_start, $mapping_end - $mapping_start );
assert_contract( false !== strpos( $mapping_body, 'wp_set_object_terms' ), 'Mapping must persist through native taxonomy relationships' );
assert_contract( false === strpos( $mapping_body, 'update_option' ) && false === strpos( $mapping_body, 'update_post_meta' ) && false === strpos( $mapping_body, '$wpdb' ), 'Mapping must not introduce option/meta/custom-table persistence' );

$plugin_source = file_get_contents( dirname( __DIR__ ) . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-content-service.php' );
assert_contract( false === strpos( (string) $plugin_source, '$wpdb' ), 'Consultation schema must not use a custom table' );
assert_contract( Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE === 'gloskin_treatment', 'Existing informational treatment CPT identity changed' );
assert_contract( Gloskin_Site_Core_Content_Service::TREATMENT_TARGET_COUNT === 8, 'Existing eight informational treatment target changed' );

echo "consultation-contract.php: OK\n";
