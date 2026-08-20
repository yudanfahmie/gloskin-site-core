<?php
declare(strict_types=1);

/** Focused contract for FB-989346, FB-989369 and FB-989364. */

define( 'ABSPATH', __DIR__ . '/' );
$root = dirname( __DIR__ );
$plugin_root = $root . '/plugin/gloskin-site-core';

function phase1_ok( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "phase1-client-feedback-contract.php: FAIL: {$message}\n" );
		exit( 1 );
	}
}

/* -------------------------------------------------------------------------
 * FB-989346: one versioned persistent option, resolved once by lifecycle.
 * ------------------------------------------------------------------------- */
$GLOBALS['phase1_options'] = array();
$GLOBALS['phase1_option_writes'] = 0;
function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['phase1_options'] ) ? $GLOBALS['phase1_options'][ $name ] : $default;
}
function update_option( $name, $value, $autoload = null ) {
	$GLOBALS['phase1_option_writes']++;
	$GLOBALS['phase1_options'][ $name ] = $value;
	return true;
}

require_once $plugin_root . '/includes/class-gloskin-site-core-lifecycle-service.php';
$lifecycle = new Gloskin_Site_Core_Lifecycle_Service();
$lifecycle->resolve_primary_navigation_labels();

$option = Gloskin_Site_Core_Navigation_Service::APPROVED_LABELS_OPTION;
$state  = get_option( $option, array() );
$expected_labels = array(
	'/treatments/' => 'Treatment',
	'/promo/'      => 'Promo',
	'/skincare/'   => 'Skincare',
	'/about/'      => 'Tentang Kami',
);
phase1_ok( 'gloskin_site_core_primary_navigation_labels_v1' === $option, 'approved nav labels must use one bounded Gloskin option' );
phase1_ok( 2 === $GLOBALS['phase1_option_writes'], 'first resolution must write resolving then complete exactly once' );
phase1_ok( is_array( $state ) && 'complete' === ( $state['status'] ?? '' ), 'resolved label option must be marked complete' );
phase1_ok( Gloskin_Site_Core_Navigation_Service::APPROVED_LABELS_VERSION === ( $state['version'] ?? '' ), 'resolved label option version mismatch' );
phase1_ok( $expected_labels === ( $state['labels'] ?? array() ), 'persisted approved nav labels mismatch' );

$writes_after_first_resolution = $GLOBALS['phase1_option_writes'];
$lifecycle->resolve_primary_navigation_labels();
phase1_ok( $writes_after_first_resolution === $GLOBALS['phase1_option_writes'], 'completed resolver must not write or repair on later requests' );

/* -------------------------------------------------------------------------
 * FB-989369: no visible Gloskin breadcrumb, Woo classic suppression remains.
 * ------------------------------------------------------------------------- */
$shell = file_get_contents( $plugin_root . '/templates/shell.php' );
phase1_ok( false !== $shell, 'unable to read shell.php' );
phase1_ok( false === strpos( $shell, 'gloskin_ui1_render_breadcrumbs(' ), 'shell must not render the Gloskin visible breadcrumb' );
phase1_ok(
	false !== strpos( $shell, "remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );" ),
	'Woo classic breadcrumb suppression must remain on Gloskin commerce requests'
);

/* -------------------------------------------------------------------------
 * FB-989364: shared closing CTA keeps semantic anchors and uses compatible
 * existing button utilities. --ghost supplied a brown foreground while
 * --on-dark only changed border/background, so the secondary label lost
 * contrast on the black closing band. --primary supplies the existing inverse
 * foreground; --on-dark keeps the transparent light-border surface.
 * ------------------------------------------------------------------------- */
$composition = file_get_contents( $plugin_root . '/templates/parts/composition-helpers.php' );
$core        = file_get_contents( $plugin_root . '/assets/css/gloskin-ui1-core.css' );
$base        = file_get_contents( $plugin_root . '/assets/css/gloskin-ui1-core-base.css' );
$prototype   = file_get_contents( $plugin_root . '/assets/css/gloskin-ui1-prototype-refresh.css' );
foreach ( array( $composition, $core, $base, $prototype ) as $source ) {
	phase1_ok( false !== $source, 'unable to read CTA presentation source' );
}
phase1_ok(
	false !== strpos( $composition, 'gloskin-ui1-button--primary gloskin-ui1-button--on-dark' ),
	'secondary closing CTA must compose the inverse-foreground primary token with on-dark'
);
phase1_ok(
	false === strpos( $composition, 'gloskin-ui1-button--ghost gloskin-ui1-button--on-dark' ),
	'low-contrast ghost/on-dark CTA composition must not return'
);
phase1_ok( false === strpos( $composition, 'style=' ), 'closing CTA must not introduce inline-style repair' );

$primary_start = strpos( $base, '.gloskin-ui1-button--primary{' );
$primary_end   = false === $primary_start ? false : strpos( $base, '}', $primary_start );
$primary_rule  = false === $primary_start || false === $primary_end ? '' : substr( $base, $primary_start, $primary_end - $primary_start + 1 );
phase1_ok(
	false !== strpos( $primary_rule, 'background:var(--gloskin-accent)' )
	&& false !== strpos( $primary_rule, 'color:var(--gloskin-inverse)' ),
	'primary button utility must continue supplying the inverse readable foreground'
);
phase1_ok(
	false !== strpos( $core, '.gloskin-ui1-button--on-dark{border-color:color-mix(in srgb,var(--gloskin-inverse) 42%,transparent);background:transparent}' ),
	'on-dark utility must keep the transparent light-border surface'
);
phase1_ok(
	false !== strpos( $base, '.gloskin-ui1-button--primary:hover{' )
	&& false !== strpos( $base, 'background:var(--gloskin-accent-strong)' ),
	'closing CTA secondary action must retain readable hover feedback'
);
phase1_ok(
	false !== strpos( $base, '.gloskin-ui1 :focus-visible{' )
	&& false !== strpos( $base, 'outline:3px solid var(--gloskin-accent-readable)' ),
	'closing CTA anchors must retain the global keyboard focus-visible treatment'
);
phase1_ok(
	false !== strpos( $core, '.gloskin-ui1-closing-cta{display:grid;grid-template-columns:minmax(0,1fr) auto' )
	&& false !== strpos( $core, '@media (max-width:900px){.gloskin-ui1-featured-entry,.gloskin-ui1-closing-cta{grid-template-columns:1fr}' ),
	'closing CTA must retain desktop geometry and the 768px/tablet stack path'
);
phase1_ok(
	false !== strpos( $core, '@media (max-width:760px)' )
	&& false !== strpos( $core, '.gloskin-ui1-closing-cta__actions{align-items:stretch;flex-direction:column}' )
	&& false !== strpos( $core, '.gloskin-ui1-closing-cta__actions .gloskin-ui1-button{width:100%}' ),
	'closing CTA actions must retain 390px narrow-mobile no-overflow safety'
);
phase1_ok(
	false !== strpos( $prototype, '.gloskin-ui1-closing-cta .gloskin-ui1-button--light' ),
	'primary closing CTA presentation owner must remain intact'
);

foreach ( array( 'home.php', 'treatments.php', 'promo.php', 'about.php' ) as $caller ) {
	$caller_source = file_get_contents( $plugin_root . '/templates/pages/' . $caller );
	phase1_ok( false !== $caller_source, 'unable to read closing CTA caller: ' . $caller );
	phase1_ok( 1 === substr_count( $caller_source, 'gloskin_ui1_render_closing_cta(' ), 'shared closing CTA caller drifted: ' . $caller );
}

/* Deferred feedback tickets are intentionally outside this contract/pass. */
echo "phase1-client-feedback-contract.php: OK\n";
