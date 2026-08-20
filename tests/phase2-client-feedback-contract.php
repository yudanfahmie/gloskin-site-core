<?php
declare(strict_types=1);

/** Focused Phase-2 client-feedback ownership and presentation contract. */
$root = dirname( __DIR__ );
$read = static function ( string $relative ) use ( $root ): string {
	$data = file_get_contents( $root . '/' . $relative );
	if ( false === $data ) {
		fwrite( STDERR, "FAIL: unable to read {$relative}\n" );
		exit( 1 );
	}
	return $data;
};
$ok = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
};

$helpers  = $read( 'plugin/gloskin-site-core/templates/parts/template-helpers.php' );
$skincare = $read( 'plugin/gloskin-site-core/templates/pages/skincare.php' );
$shop     = $read( 'plugin/gloskin-site-core/templates/parts/shop-results.php' );
$home     = $read( 'plugin/gloskin-site-core/templates/pages/home.php' );
$home_why = $read( 'plugin/gloskin-site-core/templates/parts/home-why-local-media.php' );
$promo    = $read( 'plugin/gloskin-site-core/templates/pages/promo.php' );
$service  = $read( 'plugin/gloskin-site-core/includes/class-gloskin-site-core-template-service.php' );
$css      = $read( 'plugin/gloskin-site-core/assets/css/gloskin-ui1-prototype-refresh.css' );
$js       = $read( 'plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js' );
$plugin   = $read( 'plugin/gloskin-site-core/gloskin-site-core.php' );
$kernel   = $read( 'plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php' );

/* FB-989356: one canonical renderer, explicit Skincare presentation seam. */
$ok( 1 === substr_count( $helpers, 'function gloskin_ui1_render_product_card(' ), 'product-card renderer must have exactly one owner' );
$ok( false !== strpos( $skincare, "gloskin_ui1_render_product_card( \$gloskin_product, 'skincare' )" ), 'Skincare grid must explicitly request the skincare product-card variant' );
$ok( false !== strpos( $shop, 'gloskin_ui1_render_product_card( $gloskin_product )' ), 'Shop must keep the default catalog product-card presentation' );
$variant_start = strpos( $helpers, "if ( 'skincare' === \$variant )" );
$variant_end   = false !== $variant_start ? strpos( $helpers, "\n\t\t<article class=\"gloskin-ui1-card gloskin-ui1-card--product", $variant_start + 1 ) : false;
$ok( false !== $variant_start && false !== $variant_end, 'skincare presentation branch must be bounded inside the canonical renderer' );
$variant = substr( $helpers, $variant_start, $variant_end - $variant_start );
foreach ( array( 'rating', 'review', 'star', 'wishlist' ) as $forbidden ) {
	$ok( false === stripos( $variant, $forbidden ), "Skincare variant must deliberately omit {$forbidden} presentation" );
}
$ok( false === strpos( $variant, 'short_description' ), 'Skincare variant must not leave dense description/review spacing' );
foreach ( array( 'price_html', 'wp_get_attachment_image', '$render_purchase_action()' ) as $required ) {
	$ok( false !== strpos( $variant, $required ), "Skincare variant must retain canonical product field/action: {$required}" );
}
$action_owner_start = strpos( $helpers, '$render_purchase_action = static function' );
$action_owner_end   = false !== $action_owner_start ? strpos( $helpers, "\n\t\t};", $action_owner_start ) : false;
$action_owner       = ( false !== $action_owner_start && false !== $action_owner_end ) ? substr( $helpers, $action_owner_start, $action_owner_end - $action_owner_start ) : '';
foreach ( array( 'add_to_cart_button', 'product_type_', 'ajax_add_to_cart', 'data-product_id', 'data-product_sku', 'data-gloskin-quickadd-open' ) as $required ) {
	$ok( false !== strpos( $action_owner, $required ), "shared Woo purchase owner lost {$required}" );
}
foreach ( array( 'data-gloskin-chip-filter', 'data-gloskin-product-card', 'data-category-slugs' ) as $required ) {
	$ok( false !== strpos( $skincare, $required ), "Skincare filtering contract lost {$required}" );
}
$ok( 1 === substr_count( $js, 'function initSkincareChips()' ), 'Skincare must retain one filter controller' );
$ok( false !== strpos( $css, '[data-gloskin-section="skincare-products"] .gloskin-ui1-card--product-skincare' ), 'Skincare card skin must be route/section scoped' );
$ok( false !== strpos( $css, 'object-fit:contain' ), 'Skincare packshot presentation must use contained product imagery' );

/* FB-989350: verified reference order, existing owners only. */
$ok( 1 === substr_count( $home, 'gloskin_ui1_render_hero(' ), 'Home must keep exactly one hero owner' );
$order_needles = array(
	'gloskin_ui1_render_hero(',
	'home-why-local-media.php',
	'data-gloskin-section="home-treatments"',
	'gloskin_ui1_render_testimonials',
	'gloskin_ui1_render_achievements',
	'data-gloskin-section="home-closing"',
);
$order = array_map( static fn( string $needle ) => strpos( $home, $needle ), $order_needles );
$sorted = $order;
sort( $sorted );
$ok( ! in_array( false, $order, true ) && $order === $sorted, 'Home must follow Hero -> Why -> Treatment -> Testimoni -> Piagam -> closing CTA' );
foreach ( array( 'render_managed_promo_carousel', 'home-discovery', 'home-brand-story' ) as $omitted ) {
	$ok( false === strpos( $home, $omitted ), "reference-absent Home composition must stay omitted: {$omitted}" );
}
foreach ( array( "\$gloskin_context['treatments']", "\$gloskin_context['testimonials']", "\$gloskin_context['achievements']" ) as $owner ) {
	$ok( false !== strpos( $home, $owner ), "Home must keep canonical data owner {$owner}" );
}
$ok( false !== strpos( $home_why, "gloskin_ui1_render_why_gloskin( \$gloskin_context['page'] )" ), 'Home Why composition must keep the existing helper owner' );
$home_context = explode( 'private function about_context()', explode( 'private function home_context()', $service, 2 )[1], 2 )[0];
$ok( false !== strpos( $home_context, "\$hero['mode'] = 'campaign';" ), 'Phase 2 must preserve the existing Home campaign hero behavior' );
$ok( false === strpos( $home_context, "'video-only'" ), 'deferred FB-989362 video-only hero must not be introduced' );
$ok( false === stripos( $home, 'language-switch' ) && false === stripos( $home, 'multilingual' ), 'deferred FB-989348 multilingual behavior must not be introduced' );

/* FB-989352: same managed records + same renderer/controller, page-only composition. */
$ok( 1 === substr_count( $helpers, 'function gloskin_ui1_render_managed_promo_carousel(' ), 'Promo carousel must keep one canonical renderer owner' );
$ok( false !== strpos( $promo, "gloskin_ui1_render_managed_promo_carousel( \$gloskin_context['promos'], 'h1', false )" ), 'Promo page must remain driven by its managed promo context' );
$ok( false !== strpos( $service, "'promos' => \$this->managed_promo_records(" ), 'promo_context must keep managed_promo_records as the campaign data owner' );
foreach ( array( 'gloskin-ui1-promo-carousel--page', 'gloskin-ui1-promo-carousel--compact', 'Promo Terbatas', 'data-gloskin-promo-posters', 'data-gloskin-promo-thumb' ) as $required ) {
	$ok( false !== strpos( $helpers, $required ), "Promo renderer missing Phase-2/shared seam: {$required}" );
}
$ok( false !== strpos( $helpers, 'foreach ( $promos as $poster_index => $poster_promo )' ), 'Promo poster selector must reuse the exact managed promo collection' );
$ok( false !== strpos( $helpers, 'if ( 0 === $count )' ) && false !== strpos( $helpers, 'if ( $count > 1 )' ), 'Promo zero/single/multiple record paths must remain explicit' );
$ok( false !== strpos( $helpers, "( \$compact && \$count > 1 ) ? ' data-gloskin-promo-autoplay'" ), 'compact Promo autoplay contract must remain isolated' );
$ok( 1 === substr_count( $js, 'function initPromoCarousel()' ), 'Promo must retain one carousel controller' );
$ok( false !== strpos( $css, '.gloskin-ui1-promo-carousel--page{' ) && false !== strpos( $css, '.gloskin-ui1-promo-carousel--compact{' ), 'Promo page styling must use the existing page/compact seam' );
foreach ( array( 'diskon', 'harga promo', 'berlaku sampai', 'syarat promo' ) as $fabricated ) {
	$ok( false === stripos( $promo, $fabricated ) && false === stripos( $variant, $fabricated ), "Phase 2 must not fabricate commercial fact: {$fabricated}" );
}

$ok( false === strpos( $css, '!important' ), 'Phase-2 canonical presentation owner must not add !important' );
$ok( false !== strpos( $plugin, 'Version: 0.7.178' ) && false !== strpos( $kernel, "const VERSION = '0.7.178';" ), 'Phase-2 runtime/cache version must be synchronized at 0.7.178' );

echo "phase2-client-feedback-contract.php: OK\n";
