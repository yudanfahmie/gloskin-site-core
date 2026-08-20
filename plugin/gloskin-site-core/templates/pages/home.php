<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* Phase 2 client-approved Home structure:
 * Hero -> Why Gloskin -> Treatment Unggulan -> Testimoni -> Achievements -> Closing CTA.
 * Existing canonical data/render owners remain authoritative; Promo, product discovery
 * and the standalone brand-story composition are intentionally not rendered on Home
 * in this structure pass. The existing campaign hero behavior remains unchanged. */
gloskin_ui1_render_hero( $gloskin_context['hero'] );
?>
<?php require dirname( __DIR__ ) . '/parts/home-why-local-media.php'; ?>
<?php if ( $gloskin_context['treatments'] ) : ?>
<section class="gloskin-ui1-section gloskin-ui1-section--soft" data-gloskin-section="home-treatments"><div class="gloskin-ui1-container">
	<?php gloskin_ui1_render_section_heading( __( 'Treatment Unggulan', 'gloskin-site-core' ), __( 'Kenali ragam perawatan Gloskin dan temukan pilihan yang relevan untuk dibahas saat konsultasi.', 'gloskin-site-core' ) ); ?>
	<?php gloskin_ui1_render_card_grid( $gloskin_context['treatments'], 'treatment' ); ?>
	<p class="gloskin-ui1-section__action"><a class="gloskin-ui1-text-link" href="<?php echo esc_url( home_url( '/treatments/' ) ); ?>"><?php echo esc_html__( 'Jelajahi Perawatan', 'gloskin-site-core' ); ?> →</a></p>
</div></section>
<?php endif; ?>
<?php gloskin_ui1_render_testimonials( $gloskin_context['testimonials'] ); ?>
<?php gloskin_ui1_render_achievements( $gloskin_context['achievements'], 'compact' ); ?>
<section class="gloskin-ui1-section gloskin-ui1-section--cta" data-gloskin-section="home-closing"><div class="gloskin-ui1-container"><?php gloskin_ui1_render_closing_cta( __( 'Konsultasi', 'gloskin-site-core' ), __( 'Siap membicarakan kebutuhan kulit Anda?', 'gloskin-site-core' ), __( 'Pilih klinik Gloskin terdekat atau hubungi tim kami untuk menjadwalkan konsultasi.', 'gloskin-site-core' ), __( 'Pilih Klinik', 'gloskin-site-core' ), home_url( '/clinics/' ), __( 'Hubungi Kami', 'gloskin-site-core' ), home_url( '/contact/' ) ); ?></div></section>
