<?php
/**
 * Shared Shop catalog results renderer for SSR and the read-only AJAX projection.
 *
 * Expects $gloskin_shop_results with Woo-normalized products and pagination
 * metadata. Product cards remain owned by gloskin_ui1_render_product_card().
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* REST requests do not render templates/shell.php first. Keep this shared
 * renderer self-sufficient by loading the same existing helper owners; SSR
 * reaches these as require_once no-ops because the shell already loaded them. */
require_once __DIR__ . '/template-helpers.php';
require_once __DIR__ . '/readiness-helpers.php';

$gloskin_shop_products       = isset( $gloskin_shop_results['products'] ) && is_array( $gloskin_shop_results['products'] ) ? $gloskin_shop_results['products'] : array();
$gloskin_shop_total          = isset( $gloskin_shop_results['total'] ) ? absint( $gloskin_shop_results['total'] ) : 0;
$gloskin_shop_page           = isset( $gloskin_shop_results['page'] ) ? max( 1, absint( $gloskin_shop_results['page'] ) ) : 1;
$gloskin_shop_max_pages      = isset( $gloskin_shop_results['max_pages'] ) ? max( 1, absint( $gloskin_shop_results['max_pages'] ) ) : 1;
$gloskin_shop_category_label = isset( $gloskin_shop_results['category_label'] ) ? trim( (string) $gloskin_shop_results['category_label'] ) : '';
$gloskin_shop_woo_ready      = ! empty( $gloskin_shop_results['woo_ready'] );
$gloskin_shop_filtered       = ! empty( $gloskin_shop_results['filtered'] );
$gloskin_shop_q              = isset( $gloskin_shop_results['q'] ) ? trim( (string) $gloskin_shop_results['q'] ) : '';
$gloskin_shop_heading        = '' !== $gloskin_shop_category_label ? $gloskin_shop_category_label : __( 'Semua Produk', 'gloskin-site-core' );
$gloskin_shop_page_url       = static function ( $page ) {
	$page = max( 1, absint( $page ) );
	return 1 === $page ? home_url( '/shop/' ) : home_url( '/shop/page/' . $page . '/' );
};
/* translators: %d: total number of products in the current Shop result set. */
$gloskin_shop_total_label = sprintf( __( '%d produk', 'gloskin-site-core' ), $gloskin_shop_total );
?>
<div class="gloskin-ui1-catalog-header">
	<h2 id="gloskin-shop-results-heading" tabindex="-1" data-gloskin-shop-results-heading><?php echo esc_html( $gloskin_shop_heading ); ?></h2>
	<span class="gloskin-ui1-catalog-header__count" data-gloskin-shop-count aria-live="polite"><?php echo esc_html( $gloskin_shop_total_label ); ?></span>
</div>
<div class="gloskin-ui1-shop-status" data-gloskin-shop-status role="status" aria-live="polite"></div>
<?php if ( $gloskin_shop_products ) : ?>
	<div class="gloskin-ui1-grid gloskin-ui1-grid--cards gloskin-ui1-product-grid gloskin-ui1-shop-grid" data-gloskin-product-grid data-gloskin-shop-grid>
		<?php foreach ( $gloskin_shop_products as $gloskin_product ) { gloskin_ui1_render_product_card( $gloskin_product, 'skincare' ); } ?>
	</div>
	<?php if ( $gloskin_shop_max_pages > 1 ) : ?>
		<nav class="gloskin-ui1-pagination gloskin-ui1-shop-pagination" aria-label="<?php echo esc_attr__( 'Navigasi halaman produk', 'gloskin-site-core' ); ?>">
			<ul>
				<?php if ( $gloskin_shop_page > 1 ) : ?>
					<li><a class="prev page-numbers" href="<?php echo esc_url( $gloskin_shop_page_url( $gloskin_shop_page - 1 ) ); ?>" data-gloskin-shop-page="<?php echo esc_attr( (string) ( $gloskin_shop_page - 1 ) ); ?>" aria-label="<?php echo esc_attr__( 'Halaman produk sebelumnya', 'gloskin-site-core' ); ?>">&larr;</a></li>
				<?php endif; ?>
				<?php for ( $gloskin_shop_index = 1; $gloskin_shop_index <= $gloskin_shop_max_pages; $gloskin_shop_index++ ) : ?>
					<?php if ( $gloskin_shop_index === $gloskin_shop_page ) : ?>
						<li><span class="page-numbers current" aria-current="page"><?php echo esc_html( (string) $gloskin_shop_index ); ?></span></li>
					<?php else : ?>
						<li><a class="page-numbers" href="<?php echo esc_url( $gloskin_shop_page_url( $gloskin_shop_index ) ); ?>" data-gloskin-shop-page="<?php echo esc_attr( (string) $gloskin_shop_index ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: product catalog page number. */ __( 'Halaman produk %d', 'gloskin-site-core' ), $gloskin_shop_index ) ); ?>"><?php echo esc_html( (string) $gloskin_shop_index ); ?></a></li>
					<?php endif; ?>
				<?php endfor; ?>
				<?php if ( $gloskin_shop_page < $gloskin_shop_max_pages ) : ?>
					<li><a class="next page-numbers" href="<?php echo esc_url( $gloskin_shop_page_url( $gloskin_shop_page + 1 ) ); ?>" data-gloskin-shop-page="<?php echo esc_attr( (string) ( $gloskin_shop_page + 1 ) ); ?>" aria-label="<?php echo esc_attr__( 'Halaman produk berikutnya', 'gloskin-site-core' ); ?>">&rarr;</a></li>
				<?php endif; ?>
			</ul>
		</nav>
	<?php endif; ?>
<?php elseif ( ! $gloskin_shop_woo_ready ) : ?>
	<?php gloskin_ui1_render_empty_state( 'product', __( 'Belanja belum tersedia', 'gloskin-site-core' ), __( 'Katalog produk belum tersedia pada situs ini.', 'gloskin-site-core' ), __( 'Lihat Skincare', 'gloskin-site-core' ), home_url( '/skincare/' ) ); ?>
<?php elseif ( $gloskin_shop_filtered ) : ?>
	<div class="gloskin-ui1-shop-empty-search">
		<p class="gloskin-ui1-shop-empty-search__title">
			<?php
			if ( '' !== $gloskin_shop_q ) {
				echo esc_html( sprintf(
					/* translators: %s: search query. */
					__( 'Produk tidak ditemukan untuk "%s"', 'gloskin-site-core' ),
					$gloskin_shop_q
				) );
			} else {
				echo esc_html__( 'Produk tidak ditemukan dengan filter ini', 'gloskin-site-core' );
			}
			?>
		</p>
		<p class="gloskin-ui1-shop-empty-search__hint"><?php echo esc_html__( 'Coba kata lain atau perluas rentang harga.', 'gloskin-site-core' ); ?></p>
		<div class="gloskin-ui1-shop-empty-search__actions">
			<?php if ( '' !== $gloskin_shop_q ) : ?>
				<button type="button" class="gloskin-ui1-button gloskin-ui1-button--ghost gloskin-ui1-button--small" data-gloskin-shop-clear-search><?php echo esc_html__( 'Reset pencarian', 'gloskin-site-core' ); ?></button>
			<?php endif; ?>
			<button type="button" class="gloskin-ui1-text-link" data-gloskin-shop-clear-all><?php echo esc_html__( 'Hapus semua filter', 'gloskin-site-core' ); ?></button>
		</div>
	</div>
<?php else : ?>
	<?php gloskin_ui1_render_empty_state( 'product', __( 'Belum ada produk yang dapat ditampilkan', 'gloskin-site-core' ), __( 'Produk akan tampil di sini setelah item tersedia dalam katalog.', 'gloskin-site-core' ), __( 'Lihat Skincare', 'gloskin-site-core' ), home_url( '/skincare/' ) ); ?>
<?php endif; ?>
