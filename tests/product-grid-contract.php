<?php
declare(strict_types=1);

/** First-party product-grid and Shop skeleton geometry contract. */
$root = dirname( __DIR__ );
$read = static function ( string $relative ) use ( $root ): string {
	$data = file_get_contents( $root . '/' . $relative );
	if ( false === $data ) { fwrite( STDERR, "FAIL: unable to read {$relative}\n" ); exit( 1 ); }
	return $data;
};
$ok = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
};

$home       = $read( 'plugin/gloskin-site-core/templates/pages/home.php' );
$skincare   = $read( 'plugin/gloskin-site-core/templates/pages/skincare.php' );
$results    = $read( 'plugin/gloskin-site-core/templates/parts/shop-results.php' );
$productcss = $read( 'plugin/gloskin-site-core/assets/css/gloskin-ui1-product-grid.css' );
$shopcss    = $read( 'plugin/gloskin-site-core/assets/css/gloskin-ui1-shop-discovery.css' );
$corebase   = $read( 'plugin/gloskin-site-core/assets/css/gloskin-ui1-core-base.css' );
$shopjs     = $read( 'plugin/gloskin-site-core/assets/js/gloskin-ui1-shop-discovery.js' );
$discovery  = $read( 'plugin/gloskin-site-core/includes/class-gloskin-site-core-shop-discovery.php' );
$assets     = $read( 'plugin/gloskin-site-core/config/assets.php' );

/* Phase-2 Home no longer renders the product-discovery surface; Skincare and
 * Shop remain the two public consumers of the same first-party grid owner. */
$ok( false === strpos( $home, 'data-gloskin-product-grid' ) && false === strpos( $home, 'data-gloskin-section="home-discovery"' ), 'Phase-2 Home must not retain the retired product-discovery grid' );
$ok( false !== strpos( $skincare, 'gloskin-ui1-grid--cards gloskin-ui1-product-grid" data-gloskin-product-grid' ), 'Skincare products must keep the shared product grid' );
$ok( false !== strpos( $skincare, "gloskin_ui1_render_product_card( \$gloskin_product, 'skincare' )" ), 'Skincare grid must use the canonical renderer variant seam' );
$ok( false === strpos( $productcss, '.gloskin-ui1-grid--cards{' ), 'shared product geometry must not override generic editorial card grids' );

/* Shop SSR and AJAX already share this partial, so one modifier covers both. */
$ok( false !== strpos( $results, 'gloskin-ui1-grid--cards gloskin-ui1-product-grid gloskin-ui1-shop-grid' ), 'Shop results must use shared product grid' );
$ok( false !== strpos( $results, 'data-gloskin-product-grid data-gloskin-shop-grid' ), 'Shop product-grid marker missing' );
$ok( false !== strpos( $results, 'gloskin_ui1_render_product_card( $gloskin_product )' ), 'Shop results must keep the shared product-card component' );
$ok( 1 === preg_match( '/const\s+PER_PAGE\s*=\s*12;/', $discovery ), 'Shop must remain 12 products per page' );

/* One deterministic matrix: 4 / 3 / 2 / 1. */
$ok( false !== strpos( $productcss, 'grid-template-columns: repeat(4, minmax(0, 1fr));' ), 'wide product grid must be exactly four columns' );
$ok( false !== strpos( $productcss, '@media (max-width: 1100px)' ) && false !== strpos( $productcss, 'repeat(3, minmax(0, 1fr))' ), 'medium product grid must be three columns' );
$ok( false !== strpos( $productcss, '@media (max-width: 820px)' ) && false !== strpos( $productcss, 'repeat(2, minmax(0, 1fr))' ), 'tablet product grid must be two columns' );
$ok( false !== strpos( $productcss, '@media (max-width: 520px)' ) && false !== strpos( $productcss, 'grid-template-columns: minmax(0, 1fr);' ), 'mobile product grid must be one column' );
$ok( false === strpos( $productcss . $shopcss, 'auto-fill' ) && false === strpos( $productcss . $shopcss, 'auto-fit' ), 'product/skeleton geometry must not use adaptive auto-fill/auto-fit' );
$ok( false !== strpos( $assets, "'gloskin-ui1-product-grid'" ) && false !== strpos( $assets, "'assets/css/gloskin-ui1-product-grid.css'" ), 'shared product-grid stylesheet must be registered by canonical AssetService registry' );

/* Skeleton stays bounded and visually tracks the real card footprint. */
$ok( false !== strpos( $shopjs, 'var SKELETON_CARD_COUNT = 8;' ), 'Shop skeleton count must remain bounded at eight' );
$ok( false !== strpos( $shopjs, 'data-gloskin-shop-skeleton aria-hidden="true"' ), 'Shop skeleton must remain aria-hidden' );
$ok( false !== strpos( $shopcss, '.gloskin-ui1-shop-skeleton__card' ) && false !== strpos( $shopcss, 'border-radius: var(--gloskin-radius-md);' ), 'skeleton card must mirror canonical card radius' );
$ok( false !== strpos( $shopcss, '.gloskin-ui1-shop-skeleton__media' ) && false !== strpos( $shopcss, 'aspect-ratio: 1;' ), 'skeleton media must remain square' );
$ok( 1 === preg_match( '/\.gloskin-ui1-card--product\s+\.gloskin-ui1-card__image\s*\{[^}]*aspect-ratio\s*:\s*1/s', $corebase ), 'real catalog product-card image must remain square' );
$ok( false !== strpos( $shopcss, 'height: 38px;' ) && false !== strpos( $shopcss, '.gloskin-ui1-shop-skeleton__card::after' ), 'skeleton must mirror the persistent small CTA footprint' );
$ok( false !== strpos( $shopcss, 'animation: gloskin-skeleton-shimmer 1.4s ease-in-out infinite;' ), 'lightweight CSS skeleton shimmer missing' );
$ok( false !== strpos( $shopcss, '@media (prefers-reduced-motion: reduce)' ) && false !== strpos( $shopcss, 'animation: none;' ), 'skeleton reduced-motion fallback missing' );

/* Just-approved Shop rail and smart-request architecture stay untouched. */
$ok( false !== strpos( $shopcss, '.gloskin-ui1-shop-catalog__rail {' ) && false !== strpos( $shopcss, 'position: sticky;' ), 'desktop Shop rail sticky owner changed' );
$ok( false !== strpos( $shopcss, '.gloskin-ui1-shop-categories {' ) && false !== strpos( $shopcss, 'position: static;' ) && false !== strpos( $shopcss, 'border: 0;' ), 'category static/no-border contract changed' );
$ok( false !== strpos( $shopcss, '.gloskin-ui1-shop-rail-section:not(:last-of-type)' ), 'rail divider ownership changed' );
$ok( false !== strpos( $shopcss, '@media (max-width: 900px)' ) && false !== strpos( $shopcss, '.gloskin-ui1-shop-catalog__rail {' ), 'mobile sticky-off breakpoint missing' );
$ok( 1 === substr_count( $shopjs, 'function requestCatalog(' ) && 1 === substr_count( $shopjs, 'return window.fetch(' ), 'Shop must retain one request owner/path' );
$ok( false !== strpos( $shopjs, 'new window.AbortController()' ) && false !== strpos( $shopjs, 'sequence !== requestSequence' ), 'Shop abort/stale guards changed' );
$ok( false === strpos( $shopjs, 'MutationObserver' ) && false === strpos( $shopjs, 'setInterval(' ) && false === strpos( $shopjs, 'gridTemplateColumns' ), 'Shop must not gain a JS grid/sticky/layout owner' );
$ok( 0 === substr_count( $productcss . $shopcss, '!important' ), 'new/changed product-grid presentation must add zero !important declarations' );

echo "product-grid-contract.php: OK\n";
