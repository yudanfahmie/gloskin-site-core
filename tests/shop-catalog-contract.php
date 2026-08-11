<?php
declare(strict_types=1);

/**
 * Shop catalog SSR/AJAX architecture contract.
 */

$root = dirname( __DIR__ );

function source( string $path ): string {
	global $root;
	$content = file_get_contents( $root . '/' . $path );
	if ( false === $content ) {
		fwrite( STDERR, "FAIL: unable to read {$path}\n" );
		exit( 1 );
	}
	return $content;
}

function ok( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$shop    = source( 'plugin/gloskin-site-core/templates/pages/shop.php' );
$results = source( 'plugin/gloskin-site-core/templates/parts/shop-results.php' );
$service = source( 'plugin/gloskin-site-core/includes/class-gloskin-site-core-template-service.php' );
$adapter = source( 'plugin/gloskin-site-core/includes/class-gloskin-site-core-woocommerce-adapter.php' );
$js      = source( 'plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js' );
$css     = source( 'plugin/gloskin-site-core/assets/css/gloskin-ui1-core.css' );
$runner  = source( 'tests/check-runtime.sh' );

/* SSR + semantic navigation. */
ok( false !== strpos( $shop, 'data-gloskin-shop-catalog' ), 'Shop must render an SSR catalog root' );
ok( false !== strpos( $shop, '<nav class="gloskin-ui1-shop-categories"' ) && false !== strpos( $shop, '<ul>' ) && false !== strpos( $shop, '<li><a ' ), 'category control must remain semantic nav/list/anchors' );
ok( false === strpos( $shop, 'role="tab"' ) && false === strpos( $shop, 'role="tabpanel"' ), 'Shop category navigation must not use tab semantics' );
ok( 1 === substr_count( $shop, "__( 'Semua Produk', 'gloskin-site-core' )" ), 'sidebar must expose exactly one Semua Produk source action' );
ok( false !== strpos( $shop, "home_url( '/shop/' )" ), 'Semua Produk must retain canonical /shop/ fallback' );
ok( false !== strpos( $shop, '$gloskin_mapping[\'url\']' ) && false !== strpos( $shop, '$gloskin_mapping[\'woo_slug\']' ), 'mapped category anchors must retain canonical skincare URL while enhancement uses existing woo_slug' );
ok( false !== strpos( $shop, 'aria-current="page"' ), 'SSR active category must expose aria-current' );

/* One shared results renderer for SSR and projection. */
ok( false !== strpos( $shop, 'include $gloskin_shop_results_partial' ), 'SSR Shop must use shared results partial' );
ok( false !== strpos( $service, "'/templates/parts/shop-results.php'" ) && false !== strpos( $service, 'render_shop_results' ), 'AJAX Shop projection must use the same shared results partial' );
ok( false !== strpos( $results, "require_once __DIR__ . '/template-helpers.php'" ) && false !== strpos( $results, "require_once __DIR__ . '/readiness-helpers.php'" ), 'shared renderer must load existing helper owners when invoked by REST' );
ok( false !== strpos( $results, 'gloskin_ui1_render_product_card' ), 'shared renderer must reuse canonical product-card helper' );
ok( false !== strpos( $results, 'data-gloskin-shop-count' ) && false !== strpos( $results, 'data-gloskin-shop-status' ), 'shared renderer must own count and polite status region' );
ok( false !== strpos( $results, "home_url( '/shop/page/' . $page . '/' )" ) && false !== strpos( $results, 'data-gloskin-shop-page' ), 'shared renderer must keep canonical Shop pagination fallback even when invoked from REST' );
ok( false !== strpos( $results, 'gloskin_ui1_render_empty_state' ), 'shared renderer must own Shop empty states' );

/* Read-only projection delegates to existing Woo owner. */
ok( false !== strpos( $service, "register_rest_route( 'gloskin/v1', '/shop/catalog'" ), 'Template Service must own the small Shop projection' );
ok( false !== strpos( $service, "'methods'             => 'GET'" ), 'Shop projection must be GET only' );
ok( false !== strpos( $service, '$this->woocommerce->products_paginated( $page, 12, $category )' ), 'Shop projection must delegate exactly to existing products_paginated() with server-owned 12/page' );
ok( false !== strpos( $service, "isset( $candidate['woo_slug'] )" ) && false !== strpos( $service, '$candidate_slug === $category' ), 'Shop projection must validate category against existing woo_slug mappings' );
ok( false === strpos( $service, 'wc_get_products(' ) && false === strpos( $service, 'wp_ajax_nopriv' ), 'Template Service must not introduce a second Woo query or public wp_ajax path' );
ok( false !== strpos( $adapter, 'public function products_paginated(' ), 'existing Woo catalog owner must remain present' );
foreach ( array( "'html'", "'category'", "'page'", "'total'", "'max_pages'" ) as $field ) {
	ok( false !== strpos( $service, $field ), "Shop projection response missing {$field}" );
}

/* AJAX interaction, stale-request guard, failure preservation, history. */
ok( false !== strpos( $js, 'function initShopCatalog()' ), 'Shop catalog controller missing' );
ok( false !== strpos( $js, 'requestCatalog(category, 1,' ), 'category request must reset to page 1' );
ok( false !== strpos( $js, 'requestCatalog(currentCategory, page,' ), 'pagination must preserve current category' );
ok( false !== strpos( $js, 'new window.AbortController()' ) && false !== strpos( $js, 'var requestSequence = 0' ) && false !== strpos( $js, 'sequence !== requestSequence' ), 'Shop controller must guard stale requests with AbortController plus sequence fallback' );
ok( false !== strpos( $js, "results.setAttribute('aria-busy', busy ? 'true' : 'false')" ), 'Shop result region must expose aria-busy' );
ok( false !== strpos( $js, 'showCatalogFailure(fallbackHref)' ) && false !== strpos( $js, 'Hasil sebelumnya tetap ditampilkan' ), 'GET failure must preserve previous grid and expose recovery' );
ok( false !== strpos( $js, "window.addEventListener('popstate'" ) && false !== strpos( $js, 'buildShopCatalogHash' ), 'browser back/forward must restore hash-backed AJAX state' );
ok( false !== strpos( $js, "document.dispatchEvent(new CustomEvent('gloskin:catalog-updated'" ), 'catalog replacement must dispatch one small internal event' );
ok( false !== strpos( $js, "document.addEventListener('gloskin:catalog-updated', syncToggles)" ), 'wishlist owner must re-sync only toggle state after catalog update' );
ok( false !== strpos( $js, "event.target.closest('[data-gloskin-quickadd-open]')" ), 'Quick Add must remain delegated for injected cards' );

/* Responsive layout stays one component. */
ok( false !== strpos( $css, 'grid-template-columns:minmax(210px,240px) minmax(0,1fr)' ), 'desktop Shop must use compact 210-240px sidebar' );
ok( false !== strpos( $css, '@media (max-width:900px)' ) && false !== strpos( $css, '.gloskin-ui1-shop-categories{position:static;top:auto;overflow-x:auto' ), 'same Shop category nav must become horizontal overflow presentation on smaller screens' );
ok( false !== strpos( $css, '.gloskin-ui1-shop-results[aria-busy="true"]{opacity:.68}' ), 'loading treatment must preserve existing result geometry' );
ok( false !== strpos( $css, '@media (prefers-reduced-motion:reduce)' ), 'Shop presentation must respect reduced motion' );

/* Standard suite must execute focused contracts. */
ok( false !== strpos( $runner, 'php tests/shop-catalog-contract.php' ), 'runtime runner must execute Shop PHP contract' );
ok( false !== strpos( $runner, 'node tests/shop-catalog-controller.test.js' ), 'runtime runner must execute Shop JS contract' );
ok( false !== strpos( $runner, 'python tests/shop-catalog-browser-smoke.py' ), 'runtime browser gate must execute Shop browser smoke' );

echo "shop catalog contract: OK\n";
