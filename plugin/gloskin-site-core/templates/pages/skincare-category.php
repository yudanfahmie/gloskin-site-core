<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$gloskin_category_hero     = isset( $gloskin_context['hero'] ) && is_array( $gloskin_context['hero'] ) ? $gloskin_context['hero'] : array();
$gloskin_category_title    = trim( (string) ( $gloskin_category_hero['heading'] ?? '' ) );
$gloskin_category_media_id = absint( $gloskin_category_hero['media_id'] ?? 0 );
$gloskin_category_products = isset( $gloskin_context['products'] ) && is_array( $gloskin_context['products'] ) ? $gloskin_context['products'] : array();
$gloskin_related_mappings  = array_slice( isset( $gloskin_context['related_mappings'] ) && is_array( $gloskin_context['related_mappings'] ) ? $gloskin_context['related_mappings'] : array(), 0, 3 );
?>
<div class="gloskin-skincare-category-page">
	<section class="gloskin-skincare-category-hero" data-gloskin-section="skincare-category-hero">
		<div class="gloskin-ui1-container gloskin-skincare-category-hero__grid">
			<div class="gloskin-skincare-category-hero__content">
				<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Gloskin', 'gloskin-site-core' ); ?></p>
				<?php if ( '' !== $gloskin_category_title ) : ?><h1><?php echo esc_html( $gloskin_category_title ); ?></h1><?php endif; ?>
			</div>
			<div class="gloskin-skincare-category-hero__media">
				<?php if ( $gloskin_category_media_id ) : ?>
					<?php echo wp_get_attachment_image( $gloskin_category_media_id, 'large', false, array( 'class' => 'gloskin-skincare-category-hero__image', 'fetchpriority' => 'high', 'decoding' => 'async' ) ); ?>
				<?php else : ?>
					<?php gloskin_ui1_render_editorial_media( 'skincare', $gloskin_category_title, 'gloskin-skincare-category-hero__image', true ); ?>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<section class="gloskin-skincare-category-products" data-gloskin-section="skincare-category-products">
		<div class="gloskin-ui1-container">
			<header class="gloskin-skincare-category-section-head">
				<h2><?php echo esc_html__( 'PRODUK DALAM KATEGORI INI', 'gloskin-site-core' ); ?></h2>
				<p><?php echo esc_html__( 'Buka produk untuk melihat detail dan cara membelinya.', 'gloskin-site-core' ); ?></p>
			</header>
			<?php if ( $gloskin_category_products ) : ?>
				<div class="gloskin-ui1-product-grid gloskin-skincare-category-products__grid">
					<?php foreach ( $gloskin_category_products as $gloskin_product ) : ?>
						<?php gloskin_ui1_render_product_card( $gloskin_product ); ?>
					<?php endforeach; ?>
				</div>
			<?php elseif ( empty( $gloskin_context['woo_ready'] ) ) : ?>
				<?php gloskin_ui1_render_empty_state( 'product', __( 'Katalog produk belum tersedia', 'gloskin-site-core' ), __( 'Katalog produk belum tersedia untuk kategori ini.', 'gloskin-site-core' ), __( 'Kembali ke Skincare', 'gloskin-site-core' ), home_url( '/skincare/' ) ); ?>
			<?php else : ?>
				<?php gloskin_ui1_render_empty_state( 'product', __( 'Belum ada produk pada kategori ini', 'gloskin-site-core' ), __( 'Coba kategori skincare lain atau lihat seluruh katalog yang tersedia.', 'gloskin-site-core' ), __( 'Kembali ke Skincare', 'gloskin-site-core' ), home_url( '/skincare/' ) ); ?>
			<?php endif; ?>
		</div>
	</section>

	<section class="gloskin-skincare-category-related" data-gloskin-section="skincare-category-related">
		<div class="gloskin-ui1-container">
			<header class="gloskin-skincare-category-section-head">
				<h2><?php echo esc_html__( 'KATEGORI LAIN UNTUK DILIHAT', 'gloskin-site-core' ); ?></h2>
				<p><?php echo esc_html__( 'Lihat kategori skincare lain yang tersedia.', 'gloskin-site-core' ); ?></p>
			</header>
			<?php if ( $gloskin_related_mappings ) : ?>
				<div class="gloskin-skincare-category-related__grid">
					<?php foreach ( $gloskin_related_mappings as $gloskin_mapping ) : ?>
						<?php gloskin_ui1_render_category_link( $gloskin_mapping ); ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<div class="gloskin-skincare-category-transition">
				<div class="gloskin-skincare-category-transition__copy">
					<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Skincare', 'gloskin-site-core' ); ?></p>
					<h3><?php echo esc_html__( 'LANJUTKAN KE KATEGORI LAIN ATAU', 'gloskin-site-core' ); ?><br><?php echo esc_html__( 'LIHAT SELURUH PRODUK.', 'gloskin-site-core' ); ?></h3>
					<p><?php echo esc_html__( 'Pilih alur yang paling sesuai dengan apa yang ingin Anda lihat berikutnya.', 'gloskin-site-core' ); ?></p>
				</div>
				<a class="gloskin-skincare-category-transition__link" href="<?php echo esc_url( home_url( '/skincare/' ) ); ?>"><?php echo esc_html__( 'Semua Kategori', 'gloskin-site-core' ); ?> <span aria-hidden="true">→</span></a>
			</div>
		</div>
	</section>

	<section class="gloskin-skincare-category-consultation" data-gloskin-section="skincare-category-consultation">
		<div class="gloskin-ui1-container">
			<div class="gloskin-skincare-category-consultation__inner">
				<div class="gloskin-skincare-category-consultation__copy">
					<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Konsultasi', 'gloskin-site-core' ); ?></p>
					<h2><?php echo esc_html__( 'SIAP', 'gloskin-site-core' ); ?><br><?php echo esc_html__( 'MEMBICARAKAN', 'gloskin-site-core' ); ?><br><?php echo esc_html__( 'KEBUTUHAN', 'gloskin-site-core' ); ?><br><?php echo esc_html__( 'KULIT ANDA?', 'gloskin-site-core' ); ?></h2>
					<p><?php echo esc_html__( 'Pilih klinik Gloskin terdekat atau hubungi tim kami untuk menjadwalkan konsultasi.', 'gloskin-site-core' ); ?></p>
				</div>
				<div class="gloskin-skincare-category-consultation__actions">
					<a class="gloskin-skincare-category-consultation__button gloskin-skincare-category-consultation__button--primary" href="<?php echo esc_url( home_url( '/clinics/' ) ); ?>"><?php echo esc_html__( 'Pilih Klinik', 'gloskin-site-core' ); ?></a>
					<a class="gloskin-skincare-category-consultation__button gloskin-skincare-category-consultation__button--secondary" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php echo esc_html__( 'Hubungi Kami', 'gloskin-site-core' ); ?></a>
				</div>
			</div>
		</div>
	</section>
</div>
