<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

gloskin_ui1_render_hero( $gloskin_context['hero'] );
$gloskin_shop_url = home_url( '/shop/' );
$gloskin_shop_results = array(
	'products'       => isset( $gloskin_context['products'] ) ? $gloskin_context['products'] : array(),
	'total'          => isset( $gloskin_context['products_total'] ) ? $gloskin_context['products_total'] : 0,
	'page'           => isset( $gloskin_context['current_page'] ) ? $gloskin_context['current_page'] : 1,
	'max_pages'      => isset( $gloskin_context['total_pages'] ) ? $gloskin_context['total_pages'] : 1,
	'category'       => '',
	'category_label' => '',
	'woo_ready'      => ! empty( $gloskin_context['woo_ready'] ),
	'filtered'       => false,
	'q'              => '',
	'min_price'      => null,
	'max_price'      => null,
);
$gloskin_shop_results_partial = dirname( __DIR__ ) . '/parts/shop-results.php';
?>
<?php if ( gloskin_ui1_has_content( $gloskin_context['page'] ) ) : ?>
<section class="gloskin-ui1-section gloskin-ui1-section--tight" data-gloskin-section="shop-intro">
	<div class="gloskin-ui1-container">
		<div class="gloskin-ui1-container--narrow gloskin-ui1-section__intro"><?php gloskin_ui1_render_page_content( $gloskin_context['page'] ); ?></div>
	</div>
</section>
<?php endif; ?>
<section class="gloskin-ui1-section gloskin-ui1-section--soft" data-gloskin-section="shop-products" data-gloskin-shop-catalog-owner data-gloskin-shop-url="<?php echo esc_url( $gloskin_shop_url ); ?>" data-gloskin-shop-endpoint="<?php echo esc_url( rest_url( 'gloskin/v1/shop/catalog' ) ); ?>" data-gloskin-shop-initial-page="<?php echo esc_attr( (string) $gloskin_shop_results['page'] ); ?>">
	<div class="gloskin-ui1-container">
		<div class="gloskin-ui1-shop-catalog">
			<aside class="gloskin-ui1-shop-catalog__rail" aria-label="<?php echo esc_attr__( 'Penyaring produk', 'gloskin-site-core' ); ?>">
				<form class="gloskin-ui1-shop-filter" data-gloskin-shop-search-form role="search">
					<label for="gloskin-shop-search"><span><?php echo esc_html__( 'Cari Produk', 'gloskin-site-core' ); ?></span></label>
					<div class="gloskin-ui1-shop-filter__search-row">
						<input id="gloskin-shop-search" type="search" name="q" maxlength="100" placeholder="<?php echo esc_attr__( 'Cari skincare…', 'gloskin-site-core' ); ?>" autocomplete="off" data-gloskin-shop-search />
						<button type="button" class="gloskin-ui1-text-link" data-gloskin-shop-search-clear hidden><?php echo esc_html__( 'Hapus', 'gloskin-site-core' ); ?></button>
					</div>
				</form>
				<form class="gloskin-ui1-shop-filter" data-gloskin-shop-price-form>
					<strong class="gloskin-ui1-shop-filter__title"><?php echo esc_html__( 'Rentang Harga', 'gloskin-site-core' ); ?></strong>
					<div class="gloskin-ui1-shop-filter__price-grid">
						<label><span><?php echo esc_html__( 'Harga minimum', 'gloskin-site-core' ); ?></span><input type="number" inputmode="decimal" min="0" max="999999999.99" step="0.01" maxlength="16" data-gloskin-shop-min-price /></label>
						<label><span><?php echo esc_html__( 'Harga maksimum', 'gloskin-site-core' ); ?></span><input type="number" inputmode="decimal" min="0" max="999999999.99" step="0.01" maxlength="16" data-gloskin-shop-max-price /></label>
					</div>
					<p class="gloskin-ui1-shop-filter__validation" data-gloskin-shop-filter-validation role="alert" hidden></p>
					<div class="gloskin-ui1-shop-filter__actions">
						<button type="submit" class="gloskin-ui1-button gloskin-ui1-button--small"><?php echo esc_html__( 'Terapkan', 'gloskin-site-core' ); ?></button>
						<button type="button" class="gloskin-ui1-text-link" data-gloskin-shop-price-reset hidden><?php echo esc_html__( 'Reset harga', 'gloskin-site-core' ); ?></button>
					</div>
				</form>
				<nav class="gloskin-ui1-shop-categories" data-gloskin-shop-categories aria-label="<?php echo esc_attr__( 'Kategori produk', 'gloskin-site-core' ); ?>">
					<strong class="gloskin-ui1-shop-filter__title"><?php echo esc_html__( 'Kategori', 'gloskin-site-core' ); ?></strong>
					<ul>
						<li><a href="<?php echo esc_url( $gloskin_shop_url ); ?>" data-gloskin-shop-category="" aria-current="page"><?php echo esc_html__( 'Semua Produk', 'gloskin-site-core' ); ?></a></li>
						<?php foreach ( (array) $gloskin_context['mappings'] as $gloskin_mapping ) :
							$gloskin_mapping_slug = isset( $gloskin_mapping['woo_slug'] ) ? sanitize_title( (string) $gloskin_mapping['woo_slug'] ) : '';
							$gloskin_mapping_url  = isset( $gloskin_mapping['url'] ) ? (string) $gloskin_mapping['url'] : '';
							?>
							<li><a href="<?php echo esc_url( $gloskin_mapping_url ); ?>" data-gloskin-shop-category="<?php echo esc_attr( $gloskin_mapping_slug ); ?>"><?php echo esc_html( (string) ( $gloskin_mapping['label'] ?? '' ) ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</nav>
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
