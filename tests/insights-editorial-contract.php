<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$read = static function ( string $relative ) use ( $root ): string {
	$path = $root . '/' . $relative;
	$content = file_get_contents( $path );
	if ( false === $content ) {
		fwrite( STDERR, "Unable to read {$relative}\n" );
		exit( 1 );
	}
	return $content;
};
$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
};

$template = $read( 'plugin/gloskin-site-core/includes/class-gloskin-site-core-template-service.php' );
$archive = $read( 'plugin/gloskin-site-core/templates/pages/insights.php' );
$single = $read( 'plugin/gloskin-site-core/templates/pages/insight-single.php' );
$not_found = $read( 'plugin/gloskin-site-core/templates/pages/not-found.php' );
$card = $read( 'plugin/gloskin-site-core/templates/parts/insight-card.php' );
$breadcrumbs = $read( 'plugin/gloskin-site-core/templates/parts/readiness-helpers.php' );
$css = $read( 'plugin/gloskin-site-core/assets/css/gloskin-ui1-editorial.css' );
$config = $read( 'plugin/gloskin-site-core/config/assets.php' );
$admin = $read( 'plugin/gloskin-site-core/includes/class-gloskin-site-core-insight-migration-admin.php' );
$importer = $read( 'plugin/gloskin-site-core/includes/class-gloskin-site-core-insight-importer.php' );

$assert( false === strpos( $archive, 'gloskin-ui1-pathway' ), 'static Insights pathway component still renders' );
$assert( false === strpos( $archive, 'Pelajari kategori perawatan' ), 'legacy Perawatan pathway copy still renders' );
$assert( false === strpos( $archive, 'Jelajahi kategori skincare' ), 'legacy Skincare pathway copy still renders' );
$assert( false === strpos( $archive, 'Temukan klinik Gloskin' ), 'legacy Lokasi pathway copy still renders' );

$assert( false !== strpos( $template, "'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 9" ), 'Insights archive lost native post publish query / 9 per page' );
$assert( false !== strpos( $template, "'paged' => \$paged" ), 'Insights pagination query missing' );
$assert( false !== strpos( $archive, "gloskin_ui1_render_empty_state( 'insight'" ), 'Insights must reuse shared insight empty state' );
$assert( false !== strpos( $archive, 'array_shift( $gloskin_insight_cards )' ), 'lead + remaining card composition missing' );
$assert( false !== strpos( $archive, 'paginate_links' ), 'archive pagination rendering missing' );
$assert( false !== strpos( $card, 'gloskin-ui1-insights-archive__category' ) && false === strpos( $card, 'category_url' ), 'category chip must be text-only in this slice' );

$assert( false !== strpos( $template, "if ( is_singular( 'post' ) )" ) && false !== strpos( $template, "return 'insight-single';" ), 'Template Service does not own singular native posts' );
$assert( false !== strpos( $template, 'private function insight_single_context()' ), 'single post context missing' );
$assert( false !== strpos( $single, "apply_filters( 'the_content', \$gloskin_post->post_content )" ), 'single post bypasses canonical the_content filter' );
$assert( false !== strpos( $template, "'no_found_rows' => true" ) && false !== strpos( $template, "'post__not_in' => \$exclude" ), 'related post query is not bounded/excluding current' );
$assert( substr_count( $template, "'no_found_rows' => true" ) >= 2, 'same-category and latest fallback should both skip found rows' );
$assert( false !== strpos( $single, 'gloskin-ui1-insight-single__reading' ), 'single reading column missing' );
$assert( false !== strpos( $single, 'Kembali ke Insight' ), 'single back-to-Insight action missing' );

$assert( false !== strpos( $template, 'if ( is_404() )' ) && false !== strpos( $template, "return 'not-found';" ), 'Template Service does not own 404 route' );
$assert( false !== strpos( $not_found, 'status_header( 404 )' ), '404 template does not preserve status' );
$assert( false !== strpos( $not_found, 'Kembali ke Beranda' ) && false !== strpos( $not_found, 'Buka Insight' ), '404 recovery actions missing' );
foreach ( array( 'template_redirect', 'register_shutdown_function', 'ob_start' ) as $forbidden ) {
	$assert( false === strpos( $not_found, $forbidden ), "404 copied forbidden Sangspa takeover primitive: {$forbidden}" );
}

$assert( false !== strpos( $breadcrumbs, "case 'insight-single':" ) && false !== strpos( $breadcrumbs, "'/insights/'" ), 'single Insight fallback breadcrumb missing' );
$assert( false !== strpos( $breadcrumbs, "case 'not-found':" ) && false !== strpos( $breadcrumbs, 'Halaman tidak ditemukan' ), '404 fallback breadcrumb missing' );
$assert( false !== strpos( $breadcrumbs, 'rank_math_the_breadcrumbs' ), 'existing Rank Math visible breadcrumb precedence lost' );

foreach ( array( '.gloskin-ui1-insights-archive__', '.gloskin-ui1-insight-single__', '.gloskin-ui1-not-found__' ) as $scope ) {
	$assert( false !== strpos( $css, $scope ), "editorial CSS scope missing: {$scope}" );
}
$assert( false === strpos( $css, '!important' ), 'new editorial CSS must not introduce !important' );
$assert( 0 === preg_match( '/(^|[,{]\s*)\.(?:post|entry-content)(?:\b|[\s:{.#>])/', $css ), 'broad post/entry-content CSS leakage detected' );
$assert( false !== strpos( $config, "'gloskin-ui1-editorial'" ), 'editorial stylesheet is not registered' );

$new_surface = $template . $archive . $single . $not_found . $card;
foreach ( array( 'application/ld+json', 'schema.org', 'rank_math/json_ld', 'wpseo_schema' ) as $schema_owner ) {
	$assert( false === stripos( $new_surface, $schema_owner ), "new SEO/schema owner detected: {$schema_owner}" );
}
foreach ( array( 'register_post_type', 'register_taxonomy' ) as $content_owner ) {
	$assert( false === strpos( $new_surface, $content_owner ), "new Insight content owner detected: {$content_owner}" );
}


$assert( false !== strpos( $admin, "const CAPABILITY = 'manage_options';" ), 'Insight migration admin capability must be administrator-level' );
$assert( false !== strpos( $admin, 'check_admin_referer( self::NONCE )' ), 'Insight migration admin nonce check missing' );
$assert( false !== strpos( $admin, "add_action( 'admin_post_' . self::ACTION" ), 'Insight migration must use authenticated admin-post action' );
$assert( false === strpos( $admin, 'wp_ajax_nopriv_' ) && false === strpos( $admin, 'register_rest_route' ), 'public AJAX/REST import endpoint detected' );
$assert( false !== strpos( $importer, "const SOURCE_META = '_gloskin_insight_source_id';" ), 'post ownership meta missing' );
$assert( false !== strpos( $importer, "const MEDIA_SOURCE_META = '_gloskin_insight_media_source_id';" ), 'media ownership meta missing' );
$consumed_pos = strpos( $importer, "\$state['status'] = 'consumed';" );
$cleanup_pos = strpos( $importer, '$this->bundle->cleanup( $manifest )' );
$assert( false !== $consumed_pos && false !== $cleanup_pos && $consumed_pos < $cleanup_pos, 'consumed state must persist before runtime cleanup' );
$assert( false === strpos( $importer, 'wp_delete_post' ) && false === strpos( $importer, 'wp_delete_attachment' ), 'Insight importer must never delete WordPress posts/media' );

echo "insights-editorial-contract.php: OK\n";
