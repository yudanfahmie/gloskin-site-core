<?php
declare(strict_types=1);

/** Shop catalog SSR/AJAX architecture contract. */
$root = dirname( __DIR__ );
$read = static function ( string $relative ) use ( $root ): string {
	$data = file_get_contents( $root . '/' . $relative );
	if ( false === $data ) { fwrite( STDERR, "FAIL: unable to read {$relative}\n" ); exit( 1 ); }
	return $data;
};
$ok = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
};

$shop    = $read( 'plugin/gloskin-site-core/templates/pages/shop.php' );
$results = $read( 'plugin/gloskin-site-core/templates/parts/shop-results.php' );
$service = $read( 'plugin/gloskin-site-core/includes/class-gloskin-site-core-template-service.php' );
$adapter = $read( 'plugin/gloskin-site-core/includes/class-gloskin-site-core-woocommerce-adapter.php' );
$route   = $read( 'plugin/gloskin-site-core/includes/gloskin-site-core-shop-discovery-route-trait.php' );
$query   = $read( 'plugin/gloskin-site-core/includes/gloskin-site-core-shop-discovery-query-trait.php' );
$core    = $read( 'plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js' );
$owner   = $read( 'plugin/gloskin-site-core/assets/js/gloskin-ui1-shop-discovery.js' );
$css     = $read( 'plugin/gloskin-site-core/assets/css/gloskin-ui1-core.css' );
$runner  = $read( 'tests/check-runtime.sh' );

/* SSR + semantic fallback behavior remains intact. */
$ok( false !== strpos( $shop, 'data-gloskin-shop-catalog-owner' ), 'Shop must render one canonical catalog owner marker' );
$ok( 0 === preg_match( '/\sdata-gloskin-shop-catalog(?:\s|>)/', $shop ), 'legacy core root marker must be inactive' );
$ok( false !== strpos( $shop, '<nav class="gloskin-ui1-shop-categories"' ) && false !== strpos( $shop, '<ul>' ) && false !== strpos( $shop, '<li><a ' ), 'category control must remain semantic nav/list/anchors' );
$ok( false === strpos( $shop, 'role="tab"' ) && false === strpos( $shop, 'role="tabpanel"' ), 'Shop category navigation must not use tab semantics' );
$ok( 1 === substr_count( $shop, "__( 'Semua Produk', 'gloskin-site-core' )" ), 'sidebar must expose exactly one Semua Produk source action' );
$ok( false !== strpos( $shop, "home_url( '/shop/' )" ), 'Semua Produk must retain canonical /shop/ fallback' );
$ok( false !== strpos( $shop, '$gloskin_mapping[\'url\']' ) && false !== strpos( $shop, '$gloskin_mapping[\'woo_slug\']' ), 'mapped category anchors must retain existing URL/slug ownership' );
$ok( false !== strpos( $shop, 'include $gloskin_shop_results_partial' ), 'SSR Shop must use shared results partial' );
$ok( false !== strpos( $service, "'/templates/parts/shop-results.php'" ) && false !== strpos( $service, 'render_shop_results' ), 'AJAX projection must use same shared results partial' );
$ok( false !== strpos( $results, 'gloskin_ui1_render_product_card' ), 'shared renderer must reuse canonical product-card helper' );
$ok( false !== strpos( $results, 'data-gloskin-shop-count' ) && false !== strpos( $results, 'data-gloskin-shop-status' ), 'shared renderer must own count/status region' );
$ok( false !== strpos( $results, 'data-gloskin-shop-page' ) && false !== strpos( $results, "home_url( '/shop/page/' . \$page . '/' )" ), 'pagination must keep canonical real-URL fallback' );

/* Existing endpoint is retained; discovery service only extends that route. */
$ok( false !== strpos( $service, "register_rest_route( 'gloskin/v1', '/shop/catalog'" ), 'existing Shop endpoint missing' );
$ok( false !== strpos( $service, "'methods'             => 'GET'" ), 'Shop projection must remain GET only' );
$ok( false !== strpos( $route, "\$route = '/gloskin/v1/shop/catalog';" ), 'Shop discovery must extend the existing route only' );
$ok( false !== strpos( $route, 'gloskin-ui1-shop-discovery.js' ) && false !== strpos( $route, 'gloskin-ui1-shop-discovery.css' ), 'scoped Shop owner/CSS assets missing' );
$ok( false !== strpos( $adapter, 'public function products_paginated(' ), 'existing Woo catalog owner must remain' );

/* One active browser owner for category + q + min/max + page. */
$ok( false !== strpos( $owner, "document.querySelector('[data-gloskin-shop-catalog-owner]')" ), 'active Shop owner marker mismatch' );
$ok( false !== strpos( $core, "document.querySelector('[data-gloskin-shop-catalog]')" ), 'legacy controller contract unexpectedly moved' );
$ok( 1 === substr_count( $owner, 'function buildShopCatalogRequestUrl(' ), 'one active URL builder expected' );
$ok( 1 === substr_count( $owner, 'function requestCatalog(' ), 'one active request owner expected' );
$ok( 1 === substr_count( $owner, 'return window.fetch(' ), 'one active Shop fetch path expected' );
$ok( false !== strpos( $owner, 'new window.AbortController()' ) && false !== strpos( $owner, 'var requestSequence = 0' ) && false !== strpos( $owner, 'sequence !== requestSequence' ), 'Shop stale-request guard missing' );
$ok( false !== strpos( $owner, "category: String(state.category || '')" ) && false !== strpos( $owner, "q: String(state.q || '')" ) && false !== strpos( $owner, "min_price: String(state.min_price || '')" ) && false !== strpos( $owner, "max_price: String(state.max_price || '')" ), 'full filter state missing' );
$ok( false !== strpos( $owner, 'nextState.page = 1;' ), 'filter changes must reset page 1' );
$ok( false !== strpos( $owner, 'nextPageState = normalizeShopCatalogState(currentState, 1);' ), 'pagination must preserve current filters' );
$ok( false !== strpos( $owner, "window.addEventListener('popstate'" ) && false !== strpos( $owner, 'syncControls(state);' ), 'back/forward must restore controls/state' );
$ok( false !== strpos( $owner, "searchForm.addEventListener('submit'" ) && false !== strpos( $owner, 'window.clearTimeout(searchTimer);' ), 'Enter must apply search immediately without pending debounce duplicate' );
$ok( ! preg_match( '/window\.fetch\s*=(?!=)/', $owner . $core ), 'global fetch monkeypatch forbidden' );
$ok( ! preg_match( '/(?:window\.)?history\.pushState\s*=(?!=)/', $owner . $core ), 'global pushState monkeypatch forbidden' );
$ok( ! preg_match( '/(?:window\.)?history\.replaceState\s*=(?!=)/', $owner . $core ), 'global replaceState monkeypatch forbidden' );
$ok( false === strpos( $owner, 'originalFetch' ) && false === strpos( $owner, 'originalPushState' ) && false === strpos( $owner, 'originalReplaceState' ), 'legacy decorator interception code still present' );

/* q/price stay bounded server-side; the documented unfiltered fallback stays. */
$ok( false !== strpos( $query, 'products_paginated( $page, self::PER_PAGE, $category )' ), 'historical unfiltered Woo compatibility path must remain' );
$ok( false !== strpos( $query, "'posts_per_page' => self::PER_PAGE" ), 'filtered query must remain bounded' );
$ok( false === strpos( $query, "'posts_per_page' => -1" ), 'q/price must not add all-product scan' );
$ok( false === strpos( $query, "add_action( 'pre_get_posts'" ) && false === strpos( $query, "add_filter( 'pre_get_posts'" ), 'global pre_get_posts ownership forbidden' );
$ok( false !== strpos( $query, 'gloskin_price_lookup.max_price >= %f' ) && false !== strpos( $query, 'gloskin_price_lookup.min_price <= %f' ), 'Woo-compatible variable price overlap semantics missing' );

/* Loading/presentation behavior remains the same component contract. */
$ok( false !== strpos( $owner, "results.setAttribute('aria-busy', busy ? 'true' : 'false')" ), 'aria-busy ownership missing' );
$ok( false !== strpos( $owner, 'function skeletonMarkup()' ) && false !== strpos( $owner, "results.insertAdjacentHTML('beforeend', skeletonMarkup())" ), 'Shop skeleton overlay missing' );
$ok( false !== strpos( $owner, 'results.style.minHeight = height' ) && false !== strpos( $owner, "results.style.removeProperty('min-height')" ), 'Shop skeleton geometry preservation missing' );
$ok( false === strpos( $owner, 'setInterval(' ), 'Shop loading must not add polling' );
$ok( false !== strpos( $css, 'grid-template-columns:minmax(210px,240px) minmax(0,1fr)' ), 'desktop Shop layout changed' );
$ok( false !== strpos( $css, '@media (max-width:900px)' ) && false !== strpos( $css, '.gloskin-ui1-shop-categories{position:static;top:auto;overflow-x:auto' ), 'responsive Shop category layout changed' );
$ok( false !== strpos( $css, '@media (prefers-reduced-motion:reduce)' ), 'Shop must respect reduced motion' );

$ok( false !== strpos( $runner, 'php tests/shop-catalog-contract.php' ), 'runtime runner must execute Shop PHP contract' );
$ok( false !== strpos( $runner, 'node tests/shop-catalog-controller.test.js' ), 'runtime runner must execute Shop JS contract' );
$ok( false !== strpos( $runner, 'python tests/shop-catalog-browser-smoke.py' ), 'runtime browser gate must execute Shop browser smoke' );

echo "shop catalog contract: OK\n";
