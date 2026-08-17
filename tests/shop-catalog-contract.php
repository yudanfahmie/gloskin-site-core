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

$shop       = $read( 'plugin/gloskin-site-core/templates/pages/shop.php' );
$results    = $read( 'plugin/gloskin-site-core/templates/parts/shop-results.php' );
$service    = $read( 'plugin/gloskin-site-core/includes/class-gloskin-site-core-template-service.php' );
$adapter    = $read( 'plugin/gloskin-site-core/includes/class-gloskin-site-core-woocommerce-adapter.php' );
$catalog    = $read( 'plugin/gloskin-site-core/includes/class-gloskin-site-core-woocommerce-adapter-shop-catalog.php' );
$route      = $read( 'plugin/gloskin-site-core/includes/gloskin-site-core-shop-discovery-route-trait.php' );
$query      = $read( 'plugin/gloskin-site-core/includes/gloskin-site-core-shop-discovery-query-trait.php' );
$rest       = $read( 'plugin/gloskin-site-core/includes/gloskin-site-core-shop-discovery-rest-trait.php' );
$core       = $read( 'plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js' );
$owner      = $read( 'plugin/gloskin-site-core/assets/js/gloskin-ui1-shop-discovery.js' );
$core_css   = $read( 'plugin/gloskin-site-core/assets/css/gloskin-ui1-core.css' );
$shop_css   = $read( 'plugin/gloskin-site-core/assets/css/gloskin-ui1-shop-discovery.css' );
$runner     = $read( 'tests/check-runtime.sh' );

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

/* Existing endpoint is retained; discovery only replaces that route callback. */
$ok( false !== strpos( $service, "register_rest_route( 'gloskin/v1', '/shop/catalog'" ), 'existing Shop endpoint missing' );
$ok( 1 === substr_count( $service . $route . $rest . $query . $catalog, "register_rest_route( 'gloskin/v1', '/shop/catalog'" ), 'there must still be exactly one /gloskin/v1/shop/catalog registration' );
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

/* Shop Discovery owns no product query/SQL. */
$ok( false === strpos( $query, 'new WP_Query' ) && false === strpos( $query, 'WP_Query(' ), 'Shop Discovery must not own direct filtered-product WP_Query' );
$ok( false === strpos( $query, 'posts_clauses' ) && false === strpos( $query, 'gloskin_price_lookup' ), 'Shop Discovery must own zero product SQL/posts_clauses' );
$ok( false === strpos( $route, 'query_scope_active' ) && false === strpos( $route, 'query_filters' ), 'obsolete Discovery query-scope state must remain removed' );
$ok( false !== strpos( $query, 'Gloskin_Site_Core_WooCommerce_Adapter_Shop_Catalog' ) && false !== strpos( $query, 'products_paginated_filtered( $page, self::PER_PAGE, $filters )' ), 'full filter state must delegate to adapter-owned catalog API' );

/* Adapter-owned filtered path is bounded and preserves Woo price overlap/search. */
$ok( false !== strpos( $catalog, 'public function products_paginated_filtered(' ), 'coherent adapter filtered API missing' );
$ok( false !== strpos( $catalog, 'min( 12, absint( $per_page ) )' ) && false !== strpos( $catalog, "'posts_per_page'              => \$per_page" ), 'filtered adapter query must remain bounded to 12' );
$ok( false === strpos( $catalog, "'posts_per_page' => -1" ) && false === strpos( $catalog, "'posts_per_page'              => -1" ), 'filtered adapter path must not contain all-product scan' );
$ok( false !== strpos( $adapter, "'posts_per_page' => -1" ) && false !== strpos( $adapter, 'private function products_paginated_unfiltered(' ), 'historical all-ID fallback must remain only in canonical unfiltered adapter path' );
$ok( false !== strpos( $catalog, 'gloskin_price_lookup.max_price >= %f' ) && false !== strpos( $catalog, 'gloskin_price_lookup.min_price <= %f' ), 'Woo-compatible variable price overlap semantics missing' );
$ok( false !== strpos( $catalog, '.post_title LIKE %s' ) && false !== strpos( $catalog, '.post_excerpt LIKE %s' ) && false !== strpos( $catalog, '.post_content LIKE %s' ), 'scoped search must cover title/excerpt/content' );
$ok( false === strpos( $catalog, "add_action( 'pre_get_posts'" ) && false === strpos( $catalog, "add_filter( 'pre_get_posts'" ), 'global pre_get_posts ownership forbidden' );

/* Presentation owner convergence: discovery CSS loads after core and neutralizes
 * the old category-only sticky/borders while the complete rail owns sticky. */
$ok( false !== strpos( $core_css, 'grid-template-columns:minmax(210px,240px) minmax(0,1fr)' ), 'desktop Shop grid changed' );
$ok( false !== strpos( $shop_css, '.gloskin-ui1-shop-catalog__rail {' ) && false !== strpos( $shop_css, 'position: sticky;' ), 'complete filter rail sticky owner missing' );
$ok( false !== strpos( $shop_css, '.gloskin-ui1-shop-categories {' ) && false !== strpos( $shop_css, 'position: static;' ) && false !== strpos( $shop_css, 'border: 0;' ), 'category legacy sticky/borders must be neutralized by scoped discovery owner' );
$ok( false !== strpos( $shop_css, '@media (max-width: 900px)' ) && false !== strpos( $shop_css, 'top: auto;' ), 'single-column rail must return to normal flow' );
$ok( false !== strpos( $shop_css, '@media (prefers-reduced-motion: reduce)' ), 'Shop discovery must respect reduced motion' );

$ok( false !== strpos( $runner, 'php tests/shop-catalog-contract.php' ), 'runtime runner must execute Shop PHP contract' );
$ok( false !== strpos( $runner, 'php tests/shop-smart-search-contract.php' ), 'runtime runner must execute focused Shop discovery contract' );
$ok( false !== strpos( $runner, 'node tests/shop-catalog-controller.test.js' ), 'runtime runner must execute Shop JS contract' );
$ok( false !== strpos( $runner, 'python tests/shop-catalog-browser-smoke.py' ), 'runtime browser gate must execute Shop browser smoke' );

echo "shop catalog contract: OK\n";
