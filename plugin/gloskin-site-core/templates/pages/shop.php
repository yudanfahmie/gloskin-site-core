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
);
$gloskin_shop_results_partial = dirname( __DIR__ ) . '/parts/shop-results.php';
?>
<section class="gloskin-ui1-section gloskin-ui1-section--tight" data-gloskin-section="shop-intro">
	<div class="gloskin-ui1-container">
		<?php if ( gloskin_ui1_has_content( $gloskin_context['page'] ) ) : ?>
			<div class="gloskin-ui1-container--narrow gloskin-ui1-section__intro"><?php gloskin_ui1_render_page_content( $gloskin_context['page'] ); ?></div>
		<?php endif; ?>
	</div>
</section>
<section class="gloskin-ui1-section gloskin-ui1-section--soft" data-gloskin-section="shop-products" data-gloskin-shop-catalog data-gloskin-shop-url="<?php echo esc_url( $gloskin_shop_url ); ?>" data-gloskin-shop-initial-page="<?php echo esc_attr( (string) $gloskin_shop_results['page'] ); ?>">
	<div class="gloskin-ui1-container">
		<div class="gloskin-ui1-shop-catalog">
			<nav class="gloskin-ui1-shop-categories" data-gloskin-shop-categories aria-label="<?php echo esc_attr__( 'Kategori produk', 'gloskin-site-core' ); ?>">
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
			<div class="gloskin-ui1-shop-results-column">
				<div class="gloskin-ui1-shop-results" data-gloskin-shop-results aria-busy="false">
					<?php if ( is_readable( $gloskin_shop_results_partial ) ) { include $gloskin_shop_results_partial; } ?>
				</div>
			</div>
		</div>
	</div>
</section>
