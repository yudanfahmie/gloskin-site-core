<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$gloskin_hero = isset( $gloskin_context['hero'] ) && is_array( $gloskin_context['hero'] ) ? $gloskin_context['hero'] : array();
$gloskin_hero_heading = trim( (string) ( $gloskin_hero['heading'] ?? __( 'Skincare', 'gloskin-site-core' ) ) );
$gloskin_hero_copy = trim( (string) ( $gloskin_hero['copy'] ?? '' ) );
$gloskin_hero_media_id = absint( $gloskin_hero['media_id'] ?? 0 );

$gloskin_active_slugs = array();
foreach ( (array) $gloskin_context['products'] as $gloskin_product ) {
	if ( empty( $gloskin_product['category_slugs'] ) ) { continue; }
	foreach ( explode( ' ', (string) $gloskin_product['category_slugs'] ) as $gloskin_cs ) {
		$gloskin_cs = trim( $gloskin_cs );
		if ( '' !== $gloskin_cs ) { $gloskin_active_slugs[ $gloskin_cs ] = true; }
	}
}
$gloskin_chips = array();
foreach ( (array) $gloskin_context['mappings'] as $gloskin_mapping ) {
	if ( isset( $gloskin_active_slugs[ $gloskin_mapping['slug'] ] ) ) {
		$gloskin_chips[] = $gloskin_mapping;
	}
}
?>
<div class="gloskin-skincare-page">
	<section class="gloskin-skincare-hero" data-gloskin-section="skincare-hero">
		<div class="gloskin-ui1-container gloskin-skincare-hero__grid">
			<div class="gloskin-skincare-hero__content">
				<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Produk', 'gloskin-site-core' ); ?></p>
				<h1><?php echo esc_html( $gloskin_hero_heading ); ?></h1>
				<?php if ( '' !== $gloskin_hero_copy ) : ?><p class="gloskin-skincare-hero__copy"><?php echo esc_html( $gloskin_hero_copy ); ?></p><?php endif; ?>
			</div>
			<div class="gloskin-skincare-hero__media">
				<?php if ( $gloskin_hero_media_id ) : ?>
					<?php echo wp_get_attachment_image( $gloskin_hero_media_id, 'large', false, array( 'class' => 'gloskin-skincare-hero__image', 'fetchpriority' => 'high', 'decoding' => 'async' ) ); ?>
				<?php else : ?>
					<?php gloskin_ui1_render_editorial_media( 'skincare', 'skincare_editorial', 'gloskin-skincare-hero__image', true ); ?>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<section class="gloskin-skincare-products" data-gloskin-section="skincare-products">
		<div class="gloskin-ui1-container">
			<div class="gloskin-skincare-products__head">
				<div class="gloskin-skincare-products__intro">
					<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Lengkapi rutinitas skincare Anda.', 'gloskin-site-core' ); ?></p>
					<h2><?php echo esc_html__( 'Produk yang Tersedia', 'gloskin-site-core' ); ?></h2>
					<p><?php echo esc_html__( 'Temukan rangkaian produk perawatan kulit untuk Anda.', 'gloskin-site-core' ); ?></p>
				</div>
				<a class="gloskin-ui1-button gloskin-ui1-button--primary gloskin-skincare-products__shop" href="<?php echo esc_url( home_url( '/shop/' ) ); ?>"><?php echo esc_html__( 'Lihat Semua Produk', 'gloskin-site-core' ); ?></a>
			</div>

			<?php if ( $gloskin_chips ) : ?>
			<div class="gloskin-ui1-chip-filter" role="tablist" aria-label="<?php echo esc_attr__( 'Filter produk berdasarkan kategori', 'gloskin-site-core' ); ?>" data-gloskin-chip-filter>
				<button type="button" class="gloskin-ui1-chip is-active" role="tab" data-gloskin-chip="" aria-selected="true" tabindex="0"><?php echo esc_html__( 'Semua', 'gloskin-site-core' ); ?></button>
				<?php foreach ( $gloskin_chips as $gloskin_chip ) : ?>
				<button type="button" class="gloskin-ui1-chip" role="tab" data-gloskin-chip="<?php echo esc_attr( $gloskin_chip['slug'] ); ?>" aria-selected="false" tabindex="-1"><?php echo esc_html( $gloskin_chip['label'] ); ?></button>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>

			<?php if ( ! empty( $gloskin_context['products'] ) ) : ?>
			<div class="gloskin-ui1-product-grid" data-gloskin-product-grid>
				<?php foreach ( $gloskin_context['products'] as $gloskin_product ) :
					$gloskin_cat_slugs = isset( $gloskin_product['category_slugs'] ) ? (string) $gloskin_product['category_slugs'] : '';
				?>
				<div data-gloskin-product-card data-category-slugs="<?php echo esc_attr( $gloskin_cat_slugs ); ?>">
					<?php gloskin_ui1_render_product_card( $gloskin_product ); ?>
				</div>
				<?php endforeach; ?>
			</div>
			<?php else : ?>
				<?php gloskin_ui1_empty( __( 'Produk skincare belum tersedia.', 'gloskin-site-core' ) ); ?>
			<?php endif; ?>
		</div>
	</section>

	<section class="gloskin-skincare-categories" data-gloskin-section="skincare-categories">
		<div class="gloskin-ui1-container">
			<div class="gloskin-skincare-categories__head">
				<h2><?php echo esc_html__( 'Kategori Skincare', 'gloskin-site-core' ); ?></h2>
				<p><?php echo esc_html__( 'Jelajahi produk kami yang didedikasikan khusus untuk kebutuhan kulit Anda.', 'gloskin-site-core' ); ?></p>
			</div>
			<div class="gloskin-skincare-categories__grid">
				<?php foreach ( (array) $gloskin_context['mappings'] as $gloskin_mapping ) { gloskin_ui1_render_category_link( $gloskin_mapping ); } ?>
			</div>
		</div>
	</section>

	<section class="gloskin-skincare-clinic-cta" data-gloskin-section="skincare-closing">
		<div class="gloskin-ui1-container gloskin-skincare-clinic-cta__inner">
			<div class="gloskin-skincare-clinic-cta__copy">
				<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Lokasi', 'gloskin-site-core' ); ?></p>
				<h2><?php echo esc_html__( 'Pilih Klinik Gloskin Terdekat dan Mulai Konsultasi', 'gloskin-site-core' ); ?></h2>
				<p><?php echo esc_html__( 'Kunjungi klinik Gloskin yang tersedia untuk berdiskusi langsung mengenai kebutuhan kulit Anda.', 'gloskin-site-core' ); ?></p>
			</div>
			<a class="gloskin-skincare-clinic-cta__button" href="<?php echo esc_url( home_url( '/clinics/' ) ); ?>"><?php echo esc_html__( 'Cari Klinik', 'gloskin-site-core' ); ?></a>
		</div>
	</section>
</div>
