<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* One Home hero only. TemplateService supplies the visible campaign H1/copy/CTA
 * and may enhance the same media column with the existing native Media Library
 * video controller. There is no second hero or second video service. */
gloskin_ui1_render_hero( $gloskin_context['hero'] );

/* Approved final prototype hierarchy:
 * Hero -> Why -> Featured Treatments -> Promo -> unified Skincare/Product
 * Discovery -> factual Testimonials -> editor Home brand story -> factual
 * Achievements -> Closing CTA. */
?>
<?php gloskin_ui1_render_why_gloskin( $gloskin_context['page'] ); ?>
<?php if ( $gloskin_context['treatments'] ) : ?>
<section class="gloskin-ui1-section gloskin-ui1-section--soft" data-gloskin-section="home-treatments"><div class="gloskin-ui1-container">
	<?php gloskin_ui1_render_section_heading( __( 'Pilihan Perawatan', 'gloskin-site-core' ), __( 'Kenali ragam perawatan Gloskin dan temukan pilihan yang relevan untuk dibahas saat konsultasi.', 'gloskin-site-core' ) ); ?>
	<?php gloskin_ui1_render_card_grid( $gloskin_context['treatments'], 'treatment' ); ?>
	<p class="gloskin-ui1-section__action"><a class="gloskin-ui1-text-link" href="<?php echo esc_url( home_url( '/treatments/' ) ); ?>"><?php echo esc_html__( 'Jelajahi Perawatan', 'gloskin-site-core' ); ?> →</a></p>
</div></section>
<?php endif; ?>
<?php gloskin_ui1_render_managed_promo_carousel( $gloskin_context['promo'], 'h2', true ); ?>
<section class="gloskin-ui1-section gloskin-ui1-section--soft gloskin-ui1-home-discovery" data-gloskin-section="home-discovery"><div class="gloskin-ui1-container">
	<?php gloskin_ui1_render_section_heading( __( 'Skincare & Produk Gloskin', 'gloskin-site-core' ), __( 'Jelajahi kategori skincare dan produk yang tersedia dalam satu alur discovery.', 'gloskin-site-core' ) ); ?>
	<div class="gloskin-ui1-home-discovery__categories gloskin-ui1-grid gloskin-ui1-grid--categories">
		<?php foreach ( $gloskin_context['skincare'] as $gloskin_mapping ) { gloskin_ui1_render_category_link( $gloskin_mapping ); } ?>
	</div>
	<?php if ( $gloskin_context['products'] ) : ?>
		<div class="gloskin-ui1-home-discovery__products gloskin-ui1-grid gloskin-ui1-grid--cards gloskin-ui1-product-grid" data-gloskin-product-grid>
			<?php foreach ( $gloskin_context['products'] as $gloskin_product ) { gloskin_ui1_render_product_card( $gloskin_product ); } ?>
		</div>
	<?php endif; ?>
	<p class="gloskin-ui1-section__action"><a class="gloskin-ui1-text-link" href="<?php echo esc_url( home_url( '/skincare/' ) ); ?>"><?php echo esc_html__( 'Jelajahi Skincare', 'gloskin-site-core' ); ?> →</a><?php if ( $gloskin_context['products'] ) : ?> <span aria-hidden="true">·</span> <a class="gloskin-ui1-text-link" href="<?php echo esc_url( home_url( '/shop/' ) ); ?>"><?php echo esc_html__( 'Lihat Semua Produk', 'gloskin-site-core' ); ?> →</a><?php endif; ?></p>
</div></section>
<?php gloskin_ui1_render_testimonials( $gloskin_context['testimonials'] ); ?>
<?php if ( gloskin_ui1_has_content( $gloskin_context['page'] ) ) : ?>
<section class="gloskin-ui1-section gloskin-ui1-home-brand-story" data-gloskin-section="home-brand-story"><div class="gloskin-ui1-container gloskin-ui1-home-brand-story__grid">
	<div class="gloskin-ui1-home-brand-story__content"><p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Tentang Gloskin', 'gloskin-site-core' ); ?></p><?php gloskin_ui1_render_page_content( $gloskin_context['page'] ); ?></div>
	<div class="gloskin-ui1-home-brand-story__media"><?php gloskin_ui1_render_editorial_media( 'editorial', 'home_brand_story', 'gloskin-ui1-home-brand-story__image' ); ?></div>
</div></section>
<?php endif; ?>
<?php gloskin_ui1_render_achievements( $gloskin_context['achievements'], 'compact' ); ?>
<section class="gloskin-ui1-section gloskin-ui1-section--cta" data-gloskin-section="home-closing"><div class="gloskin-ui1-container"><?php gloskin_ui1_render_closing_cta( __( 'Konsultasi', 'gloskin-site-core' ), __( 'Siap membicarakan kebutuhan kulit Anda?', 'gloskin-site-core' ), __( 'Pilih klinik Gloskin terdekat atau hubungi tim kami untuk menjadwalkan konsultasi.', 'gloskin-site-core' ), __( 'Pilih Klinik', 'gloskin-site-core' ), home_url( '/clinics/' ), __( 'Hubungi Kami', 'gloskin-site-core' ), home_url( '/contact/' ) ); ?></div></section>
