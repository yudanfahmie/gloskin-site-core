<?php
declare(strict_types=1);
$root = dirname( __DIR__ );
function gs_read( string $path ): string {
	$value = file_get_contents( $path );
	if ( false === $value ) { fwrite( STDERR, "FAIL unreadable {$path}\n" ); exit( 1 ); }
	return $value;
}
function gs_has( string $source, string $needle, string $label ): void {
	if ( false === strpos( $source, $needle ) ) { fwrite( STDERR, "FAIL {$label}\n" ); exit( 1 ); }
}
function gs_not( string $source, string $needle, string $label ): void {
	if ( false !== strpos( $source, $needle ) ) { fwrite( STDERR, "FAIL {$label}\n" ); exit( 1 ); }
}
$plugin = $root . '/plugin/gloskin-site-core';
$shop = gs_read( $plugin . '/templates/pages/shop.php' );
$route = gs_read( $plugin . '/includes/gloskin-site-core-shop-discovery-route-trait.php' );
$query = gs_read( $plugin . '/includes/gloskin-site-core-shop-discovery-query-trait.php' );
$js = gs_read( $plugin . '/assets/js/gloskin-ui1-shop-discovery.js' );
gs_has( $shop, 'data-gloskin-shop-search', 'Shop search UI missing' );
gs_has( $shop, 'data-gloskin-shop-min-price', 'Shop min price UI missing' );
gs_has( $shop, 'data-gloskin-shop-max-price', 'Shop max price UI missing' );
gs_has( $route, '/gloskin/v1/shop/catalog', 'same Shop endpoint missing' );
gs_not( $route, 'register_rest_route', 'second Shop endpoint introduced' );
gs_has( $query, "'posts_per_page' => self::PER_PAGE", 'filtered Shop query not bounded' );
gs_not( $query, "'posts_per_page' => -1", 'filtered Shop query contains all-ID scan' );
gs_not( $query, 'pre_get_posts', 'global Shop query hook introduced' );
gs_has( $query, 'max_price >= %f', 'price lower-overlap missing' );
gs_has( $query, 'min_price <= %f', 'price upper-overlap missing' );
gs_has( $js, 'SEARCH_DELAY = 325', 'search debounce contract missing' );

$contact = gs_read( $plugin . '/includes/class-gloskin-site-core-contact-service.php' );
$boot = gs_read( $plugin . '/includes/gloskin-site-core-contact-service-bootstrap-trait.php' );
$submit = gs_read( $plugin . '/includes/gloskin-site-core-contact-service-submit-trait.php' );
$security = gs_read( $plugin . '/includes/gloskin-site-core-contact-service-security-trait.php' );
$mailer = gs_read( $plugin . '/includes/class-gloskin-site-core-contact-mailer.php' );
$admin = gs_read( $plugin . '/includes/class-gloskin-site-core-contact-admin.php' );
gs_has( $contact, 'gloskin_contact_message', 'private Contact CPT missing' );
gs_has( $contact, 'gloskin_site_core_contact_settings', 'dedicated Contact option missing' );
gs_has( $boot, "'publicly_queryable'  => false", 'Contact public query disabled contract missing' );
gs_has( $boot, "'show_in_rest'        => false", 'Contact REST exposure disabled contract missing' );
$persist = strpos( $submit, 'persist_message' );
$mail = strpos( $submit, 'send_staff_mail' );
if ( false === $persist || false === $mail || $persist >= $mail ) { fwrite( STDERR, "FAIL Contact persistence must precede mail\n" ); exit( 1 ); }
gs_has( $security, 'siteverify', 'reCAPTCHA server verification missing' );
gs_has( $security, "'timeout' => 5", 'reCAPTCHA timeout missing' );
gs_has( $mailer, "'gloskin_contact'", 'Contact-only SMTP default missing' );
gs_has( $mailer, 'Reply-To: ', 'visitor Reply-To missing' );
gs_has( $admin, 'gloskin-contact-inbox', 'private inbox admin missing' );

$bundle = gs_read( $plugin . '/includes/class-gloskin-site-core-doctor-bundle.php' );
$state = gs_read( $plugin . '/includes/gloskin-site-core-doctor-importer-state-trait.php' );
$upsert = gs_read( $plugin . '/includes/gloskin-site-core-doctor-importer-upsert-trait.php' );
$finalize = gs_read( $plugin . '/includes/gloskin-site-core-doctor-importer-finalize-trait.php' );
$payload = json_decode( gs_read( $plugin . '/migration-runtime/gloskin-doctors-v1/doctors.json' ), true );
if ( ! is_array( $payload ) || 13 !== count( $payload['doctors'] ?? array() ) ) { fwrite( STDERR, "FAIL doctor roster count\n" ); exit( 1 ); }
gs_has( $bundle, "hash_file( 'sha256'", 'doctor SHA-256 validation missing' );
gs_has( $state, 'upsert_doctor', 'doctor checkpoint missing' );
gs_has( $upsert, 'Unowned doctor collision', 'doctor collision guard missing' );
$consumed = strpos( $finalize, "'consumed'" );
$cleanup = strpos( $finalize, 'cleanup_runtime' );
if ( false === $consumed || false === $cleanup || $consumed >= $cleanup ) { fwrite( STDERR, "FAIL consumed-before-cleanup contract\n" ); exit( 1 ); }
foreach ( $payload['doctors'] as $doctor ) {
	foreach ( array( 'source_id', 'source_url', 'source_checked_at', 'source_display_name', 'post_title', 'slug' ) as $key ) {
		if ( empty( $doctor[ $key ] ) ) { fwrite( STDERR, "FAIL doctor provenance {$key}\n" ); exit( 1 ); }
	}
}
echo "shop-contact-doctor-production-contract.php: OK\n";
