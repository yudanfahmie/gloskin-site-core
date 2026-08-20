<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
gloskin_ui1_render_hero( $gloskin_context['hero'] );
?>
<section class="gloskin-ui1-section gloskin-ui1-section--tight gloskin-ui1-section--intro-only" data-gloskin-section="skincare-intro"><div class="gloskin-ui1-container">
	<?php if ( gloskin_ui1_has_content( $gloskin_context['page'] ) ) : ?><div class="gloskin-ui1-container--narrow gloskin-ui1-section__intro"><?php gloskin_ui1_render_page_content( $gloskin_context['page'] ); ?></div><?php else : ?><div class="gloskin-ui1-container--narrow gloskin-ui1-section__intro"><p class="gloskin-ui1-body"><?php echo esc_html__( 'Produk skincare Gloskin dirancang untuk mendukung perawatan kulit sehari-hari. Pilih kategori di bawah untuk mempersempit pilihan, atau lihat semua produk yang tersedia.', 'gloskin-site-core' ); ?></p></div><?php endif; ?>
</div></section>
<section class="gloskin-ui1-section gloskin-ui1-section--tight" data-gloskin-section="skincare-shop-gateway" aria-labelledby="gloskin-skincare-shop-gateway-title"><div class="gloskin-ui1-container">
	<div class="gloskin-ui1-skincare-shop-gateway">
		<div class="gloskin-ui1-skincare-shop-gateway__copy">
			<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'BELANJA GLOSKIN', 'gloskin-site-core' ); ?></p>
			<h2 id="gloskin-skincare-shop-gateway-title"><?php echo esc_html__( 'Lengkapi rutinitas skincare Anda.', 'gloskin-site-core' ); ?></h2>
			<p><?php echo esc_html__( 'Jelajahi seluruh koleksi, lihat detail produk, harga, dan pilihan yang tersedia di halaman Belanja.', 'gloskin-site-core' ); ?></p>
		</div>
		<div class="gloskin-ui1-skincare-shop-gateway__action">
			<a class="gloskin-ui1-button gloskin-ui1-button--primary" href="<?php echo esc_url( home_url( '/shop/' ) ); ?>"><?php echo esc_html__( 'Lihat Semua Produk', 'gloskin-site-core' ); ?></a>
		</div>
	</div>
</div></section>
<?php if ( $gloskin_context['products'] ) :
	/* Build chip list: only include categories that actually have products in our set */
	$gloskin_active_slugs = array();
	foreach ( $gloskin_context['products'] as $gloskin_product ) {
		if ( ! empty( $gloskin_product['category_slugs'] ) ) {
			foreach ( explode( ' ', (string) $gloskin_product['category_slugs'] ) as $gloskin_cs ) {
				$gloskin_cs = trim( $gloskin_cs );
				if ( '' !== $gloskin_cs ) { $gloskin_active_slugs[ $gloskin_cs ] = true; }
			}
		}
	}
	/* Build ordered chip list matching mappings order */
	$gloskin_chips = array();
	foreach ( $gloskin_context['mappings'] as $gloskin_mapping ) {
		if ( isset( $gloskin_active_slugs[ $gloskin_mapping['slug'] ] ) ) {
			$gloskin_chips[] = $gloskin_mapping;
		}
	}
	$gloskin_show_chips = count( $gloskin_chips ) > 0;
?>
<section class="gloskin-ui1-section" data-gloskin-section="skincare-products"><div class="gloskin-ui1-container">
	<?php gloskin_ui1_render_section_heading( __( 'Produk yang Tersedia', 'gloskin-site-core' ), __( 'Lihat detail, harga, dan cara membeli setiap produk.', 'gloskin-site-core' ) ); ?>
	<?php if ( $gloskin_show_chips ) : ?>
	<div class="gloskin-ui1-chip-filter" role="tablist" aria-label="<?php echo esc_attr__( 'Filter produk berdasarkan kategori', 'gloskin-site-core' ); ?>" data-gloskin-chip-filter>
		<button type="button" class="gloskin-ui1-chip is-active" role="tab" data-gloskin-chip="" aria-selected="true" tabindex="0"><?php echo esc_html__( 'Semua', 'gloskin-site-core' ); ?></button>
		<?php foreach ( $gloskin_chips as $gloskin_chip ) : ?>
		<button type="button" class="gloskin-ui1-chip" role="tab" data-gloskin-chip="<?php echo esc_attr( $gloskin_chip['slug'] ); ?>" aria-selected="false" tabindex="-1"><?php echo esc_html( $gloskin_chip['label'] ); ?></button>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>
	<div class="gloskin-ui1-grid gloskin-ui1-grid--cards gloskin-ui1-product-grid" data-gloskin-product-grid>
		<?php foreach ( $gloskin_context['products'] as $gloskin_product ) :
			$gloskin_cat_slugs = isset( $gloskin_product['category_slugs'] ) ? (string) $gloskin_product['category_slugs'] : '';
		?>
		<div data-gloskin-product-card data-category-slugs="<?php echo esc_attr( $gloskin_cat_slugs ); ?>">
			<?php gloskin_ui1_render_product_card( $gloskin_product ); ?>
		</div>
		<?php endforeach; ?>
	</div>
</div></section>
<?php endif; ?>
<section class="gloskin-ui1-section gloskin-ui1-section--soft" data-gloskin-section="skincare-categories"><div class="gloskin-ui1-container"><?php gloskin_ui1_render_section_heading( __( 'Kategori Skincare', 'gloskin-site-core' ), __( 'Jelajahi halaman kategori untuk produk detail dan konteks perawatan per kebutuhan kulit.', 'gloskin-site-core' ) ); ?><div class="gloskin-ui1-grid gloskin-ui1-grid--categories"><?php foreach ( $gloskin_context['mappings'] as $gloskin_mapping ) { gloskin_ui1_render_category_link( $gloskin_mapping ); } ?></div></div></section>
<section class="gloskin-ui1-section" data-gloskin-section="skincare-pathways"><div class="gloskin-ui1-container"><?php gloskin_ui1_render_pathway_grid( array( array( 'eyebrow' => __( 'Klinik', 'gloskin-site-core' ), 'title' => __( 'Ingin bertanya lebih lanjut?', 'gloskin-site-core' ), 'copy' => __( 'Pilih lokasi Gloskin dan lihat kanal kontak yang tersedia.', 'gloskin-site-core' ), 'label' => __( 'Lihat Klinik', 'gloskin-site-core' ), 'url' => home_url( '/clinics/' ) ) ) ); ?></div></section>
