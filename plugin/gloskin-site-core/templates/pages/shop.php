<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

gloskin_ui1_render_hero( $gloskin_context['hero'] );
$gloskin_shop_url = home_url( '/shop/' );

/* Initial price availability is resolved through the same adapter-owned Shop
 * catalog component used by AJAX. The older context field remains tolerated
 * for compatibility, but it is never allowed to invent a range for an
 * equal-price or empty catalog. */
$gloskin_shop_price_bounds = array( 'state' => 'empty', 'min' => null, 'max' => null );
if ( ! empty( $gloskin_context['woo_ready'] )
	&& class_exists( 'Gloskin_Site_Core_WooCommerce_Adapter' )
	&& class_exists( 'Gloskin_Site_Core_WooCommerce_Adapter_Shop_Catalog' )
) {
	$gloskin_shop_price_adapter = new Gloskin_Site_Core_WooCommerce_Adapter();
	$gloskin_shop_price_catalog = new Gloskin_Site_Core_WooCommerce_Adapter_Shop_Catalog( $gloskin_shop_price_adapter );
	$gloskin_shop_price_bounds  = $gloskin_shop_price_catalog->price_bounds( '', '' );
}
$gloskin_shop_price_state = isset( $gloskin_shop_price_bounds['state'] )
	&& in_array( $gloskin_shop_price_bounds['state'], array( 'normal', 'single', 'empty' ), true )
	? (string) $gloskin_shop_price_bounds['state']
	: 'empty';
$gloskin_shop_available_min = isset( $gloskin_shop_price_bounds['min'] ) && is_numeric( $gloskin_shop_price_bounds['min'] )
	? (float) $gloskin_shop_price_bounds['min']
	: null;
$gloskin_shop_available_max = isset( $gloskin_shop_price_bounds['max'] ) && is_numeric( $gloskin_shop_price_bounds['max'] )
	? (float) $gloskin_shop_price_bounds['max']
	: null;
if ( 'normal' === $gloskin_shop_price_state && ( null === $gloskin_shop_available_min || null === $gloskin_shop_available_max || $gloskin_shop_available_max <= $gloskin_shop_available_min ) ) {
	$gloskin_shop_price_state = 'empty';
	$gloskin_shop_available_min = null;
	$gloskin_shop_available_max = null;
}
if ( 'single' === $gloskin_shop_price_state && ( null === $gloskin_shop_available_min || null === $gloskin_shop_available_max ) ) {
	$gloskin_shop_price_state = 'empty';
	$gloskin_shop_available_min = null;
	$gloskin_shop_available_max = null;
}

$gloskin_shop_results = array(
	'products'            => isset( $gloskin_context['products'] ) ? $gloskin_context['products'] : array(),
	'total'               => isset( $gloskin_context['products_total'] ) ? $gloskin_context['products_total'] : 0,
	'page'                => isset( $gloskin_context['current_page'] ) ? $gloskin_context['current_page'] : 1,
	'max_pages'           => isset( $gloskin_context['total_pages'] ) ? $gloskin_context['total_pages'] : 1,
	'category'            => '',
	'category_label'      => '',
	'woo_ready'           => ! empty( $gloskin_context['woo_ready'] ),
	'filtered'            => false,
	'q'                   => '',
	'min_price'           => null,
	'max_price'           => null,
	'price_state'         => $gloskin_shop_price_state,
	'available_min_price' => $gloskin_shop_available_min,
	'available_max_price' => $gloskin_shop_available_max,
);

$gloskin_shop_input_min = null === $gloskin_shop_available_min ? 0 : (int) round( $gloskin_shop_available_min );
$gloskin_shop_input_max = null === $gloskin_shop_available_max ? $gloskin_shop_input_min : (int) round( $gloskin_shop_available_max );
$gloskin_shop_format_idr = static function ( $value ) {
	return 'Rp ' . number_format( (float) $value, 0, ',', '.' );
};
$gloskin_shop_price_label_min = 'empty' === $gloskin_shop_price_state
	? __( 'Harga belum tersedia', 'gloskin-site-core' )
	: $gloskin_shop_format_idr( $gloskin_shop_available_min );
$gloskin_shop_price_label_max = 'normal' === $gloskin_shop_price_state
	? $gloskin_shop_format_idr( $gloskin_shop_available_max )
	: '';
$gloskin_shop_results_partial = dirname( __DIR__ ) . '/parts/shop-results.php';
?>
<?php if ( gloskin_ui1_has_content( $gloskin_context['page'] ) ) : ?>
<section class="gloskin-ui1-section gloskin-ui1-section--tight gloskin-ui1-section--intro-only" data-gloskin-section="shop-intro">
	<div class="gloskin-ui1-container">
		<div class="gloskin-ui1-container--narrow gloskin-ui1-section__intro"><?php gloskin_ui1_render_page_content( $gloskin_context['page'] ); ?></div>
	</div>
</section>
<?php endif; ?>
<section class="gloskin-ui1-section gloskin-ui1-section--soft" data-gloskin-section="shop-products"
	data-gloskin-shop-catalog-owner
	data-gloskin-shop-url="<?php echo esc_url( $gloskin_shop_url ); ?>"
	data-gloskin-shop-endpoint="<?php echo esc_url( rest_url( 'gloskin/v1/shop/catalog' ) ); ?>"
	data-gloskin-shop-initial-page="<?php echo esc_attr( (string) $gloskin_shop_results['page'] ); ?>">
	<div class="gloskin-ui1-container">
		<div class="gloskin-ui1-shop-catalog">

			<aside class="gloskin-ui1-shop-catalog__rail" aria-label="<?php echo esc_attr__( 'Penyaring produk', 'gloskin-site-core' ); ?>">

				<?php /* ── PENCARIAN ─────────────────────────────────────── */ ?>
				<div class="gloskin-ui1-shop-rail-section">
					<span class="gloskin-ui1-shop-rail-section__label"><?php echo esc_html__( 'Pencarian', 'gloskin-site-core' ); ?></span>
					<form class="gloskin-ui1-shop-search-field" data-gloskin-shop-search-form role="search" aria-label="<?php echo esc_attr__( 'Cari produk', 'gloskin-site-core' ); ?>">
						<span class="gloskin-ui1-shop-search-field__icon" aria-hidden="true">
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" width="16" height="16" aria-hidden="true">
								<circle cx="7.5" cy="7.5" r="5.25"/>
								<line x1="11.25" y1="11.25" x2="16" y2="16"/>
							</svg>
						</span>
						<input
							id="gloskin-shop-search"
							type="search"
							name="q"
							maxlength="100"
							placeholder="<?php echo esc_attr__( 'Cari produk, SKU, atau kebutuhan kulit…', 'gloskin-site-core' ); ?>"
							autocomplete="off"
							data-gloskin-shop-search />
						<button
							type="button"
							class="gloskin-ui1-shop-search-field__clear"
							data-gloskin-shop-search-clear
							hidden
							aria-label="<?php echo esc_attr__( 'Hapus pencarian', 'gloskin-site-core' ); ?>">
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" width="14" height="14" aria-hidden="true">
								<line x1="3.5" y1="3.5" x2="12.5" y2="12.5"/>
								<line x1="12.5" y1="3.5" x2="3.5" y2="12.5"/>
							</svg>
						</button>
					</form>
				</div>

				<?php /* ── HARGA ──────────────────────────────────────────── */ ?>
				<div class="gloskin-ui1-shop-rail-section">
					<span class="gloskin-ui1-shop-rail-section__label"><?php echo esc_html__( 'Harga', 'gloskin-site-core' ); ?></span>
					<div class="gloskin-ui1-price-filter"
						data-gloskin-shop-price-filter
						data-gloskin-price-state="<?php echo esc_attr( $gloskin_shop_price_state ); ?>"
						data-gloskin-price-avail-min="<?php echo null === $gloskin_shop_available_min ? '' : esc_attr( (string) $gloskin_shop_available_min ); ?>"
						data-gloskin-price-avail-max="<?php echo null === $gloskin_shop_available_max ? '' : esc_attr( (string) $gloskin_shop_available_max ); ?>">
						<div class="gloskin-ui1-price-filter__labels" aria-live="polite" aria-atomic="true">
							<span class="gloskin-ui1-price-filter__label-min" data-gloskin-price-label-min><?php echo esc_html( $gloskin_shop_price_label_min ); ?></span>
							<span class="gloskin-ui1-price-filter__label-sep"<?php echo 'normal' === $gloskin_shop_price_state ? '' : ' hidden'; ?> aria-hidden="true">&ndash;</span>
							<span class="gloskin-ui1-price-filter__label-max" data-gloskin-price-label-max<?php echo 'normal' === $gloskin_shop_price_state ? '' : ' hidden'; ?>><?php echo esc_html( $gloskin_shop_price_label_max ); ?></span>
						</div>
						<div class="gloskin-ui1-price-slider" data-gloskin-price-slider<?php echo 'empty' === $gloskin_shop_price_state ? ' hidden' : ''; ?>>
							<div class="gloskin-ui1-price-slider__track" data-gloskin-price-track aria-hidden="true"></div>
							<input
								type="range"
								class="gloskin-ui1-price-slider__input gloskin-ui1-price-slider__input--min"
								data-gloskin-shop-min-price-slider
								min="<?php echo esc_attr( (string) $gloskin_shop_input_min ); ?>"
								max="<?php echo esc_attr( (string) $gloskin_shop_input_max ); ?>"
								value="<?php echo esc_attr( (string) $gloskin_shop_input_min ); ?>"
								step="10000"
								aria-label="<?php echo esc_attr__( 'Harga minimum', 'gloskin-site-core' ); ?>"<?php echo 'normal' === $gloskin_shop_price_state ? '' : ' hidden disabled'; ?> />
							<input
								type="range"
								class="gloskin-ui1-price-slider__input gloskin-ui1-price-slider__input--max"
								data-gloskin-shop-max-price-slider
								min="<?php echo esc_attr( (string) $gloskin_shop_input_min ); ?>"
								max="<?php echo esc_attr( (string) $gloskin_shop_input_max ); ?>"
								value="<?php echo esc_attr( (string) $gloskin_shop_input_max ); ?>"
								step="10000"
								aria-label="<?php echo esc_attr__( 'Harga maksimum', 'gloskin-site-core' ); ?>"<?php echo 'normal' === $gloskin_shop_price_state ? '' : ' hidden disabled'; ?> />
						</div>
						<button
							type="button"
							class="gloskin-ui1-text-link gloskin-ui1-price-filter__reset"
							data-gloskin-shop-price-reset
							hidden><?php echo esc_html__( 'Reset harga', 'gloskin-site-core' ); ?></button>
					</div>
				</div>

				<?php /* ── KATEGORI ───────────────────────────────────────── */ ?>
				<div class="gloskin-ui1-shop-rail-section">
					<span class="gloskin-ui1-shop-rail-section__label"><?php echo esc_html__( 'Kategori', 'gloskin-site-core' ); ?></span>
					<nav class="gloskin-ui1-shop-categories" data-gloskin-shop-categories aria-label="<?php echo esc_attr__( 'Kategori produk', 'gloskin-site-core' ); ?>">
						<ul>
							<li><a href="<?php echo esc_url( $gloskin_shop_url ); ?>" data-gloskin-shop-category="" data-gloskin-no-transition aria-current="page"><?php echo esc_html__( 'Semua Produk', 'gloskin-site-core' ); ?></a></li>
							<?php foreach ( (array) $gloskin_context['mappings'] as $gloskin_mapping ) :
								$gloskin_mapping_slug = isset( $gloskin_mapping['woo_slug'] ) ? sanitize_title( (string) $gloskin_mapping['woo_slug'] ) : '';
								$gloskin_mapping_url  = isset( $gloskin_mapping['url'] ) ? (string) $gloskin_mapping['url'] : '';
								?>
								<li><a href="<?php echo esc_url( $gloskin_mapping_url ); ?>" data-gloskin-shop-category="<?php echo esc_attr( $gloskin_mapping_slug ); ?>" data-gloskin-no-transition><?php echo esc_html( (string) ( $gloskin_mapping['label'] ?? '' ) ); ?></a></li>
							<?php endforeach; ?>
						</ul>
					</nav>
				</div>

				<button type="button" class="gloskin-ui1-text-link gloskin-ui1-shop-filter__clear" data-gloskin-shop-clear-all hidden><?php echo esc_html__( 'Hapus semua filter', 'gloskin-site-core' ); ?></button>
			</aside>

			<div class="gloskin-ui1-shop-results-column">
				<div class="gloskin-ui1-shop-results" data-gloskin-shop-results aria-busy="false">
					<?php if ( is_readable( $gloskin_shop_results_partial ) ) { include $gloskin_shop_results_partial; } ?>
				</div>
				<span class="screen-reader-text" data-gloskin-shop-status-live aria-live="polite"></span>
			</div>

		</div>
	</div>
</section>