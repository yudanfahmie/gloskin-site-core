<?php
/** Global Gloskin footer. @package GloskinSiteCore */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$gloskin_clinic_links = isset( $gloskin_context['clinic_links'] ) && is_array( $gloskin_context['clinic_links'] ) ? $gloskin_context['clinic_links'] : array();
$gloskin_logo_url = isset( $gloskin_context['logo_url'] ) ? (string) $gloskin_context['logo_url'] : '';
$gloskin_footer_view = isset( $gloskin_context['view'] ) ? (string) $gloskin_context['view'] : '';
?>
<footer class="gloskin-ui1-footer">
	<section class="gloskin-ui1-dark-consultation gloskin-ui1-footer__cta">
		<div class="gloskin-ui1-container">
			<div class="gloskin-ui1-dark-consultation__inner">
				<div class="gloskin-ui1-dark-consultation__copy">
					<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Konsultasi', 'gloskin-site-core' ); ?></p>
					<h2><?php echo esc_html__( 'Siap Membicarakan Kebutuhan Kulit Anda?', 'gloskin-site-core' ); ?></h2>
					<p><?php echo esc_html__( 'Pilih klinik Gloskin terdekat atau hubungi tim kami untuk menjadwalkan konsultasi.', 'gloskin-site-core' ); ?></p>
				</div>
				<div class="gloskin-ui1-dark-consultation__actions">
					<a class="gloskin-ui1-dark-consultation__button gloskin-ui1-dark-consultation__button--primary" href="<?php echo esc_url( home_url( '/clinics/' ) ); ?>"><?php echo esc_html__( 'Pilih Klinik', 'gloskin-site-core' ); ?></a>
					<a class="gloskin-ui1-dark-consultation__button gloskin-ui1-dark-consultation__button--secondary" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php echo esc_html__( 'Hubungi Kami', 'gloskin-site-core' ); ?></a>
				</div>
			</div>
		</div>
	</section>
	<div class="gloskin-ui1-container gloskin-ui1-footer__grid">
		<div class="gloskin-ui1-footer__brand"><div class="gloskin-ui1-footer__brand-mark"><a class="gloskin-ui1-brand--footer" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php gloskin_ui1_render_brand_logo( $gloskin_logo_url, 'gloskin-ui1-brand__image--footer' ); ?></a></div><p><?php echo esc_html__( 'Gloskin adalah klinik estetika, anti-aging, dan perawatan rambut yang mengedepankan konsultasi dan penanganan dokter di setiap kliniknya.', 'gloskin-site-core' ); ?></p></div>
		<div>
			<h3><?php echo esc_html__( 'Layanan', 'gloskin-site-core' ); ?></h3>
			<ul>
				<li><a href="<?php echo esc_url( home_url( '/treatments/' ) ); ?>"><?php echo esc_html__( 'Perawatan', 'gloskin-site-core' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/promo/' ) ); ?>"><?php echo esc_html__( 'Promo', 'gloskin-site-core' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/skincare/' ) ); ?>">Skincare</a></li>
				<li><a href="<?php echo esc_url( home_url( '/shop/' ) ); ?>"><?php echo esc_html__( 'Belanja', 'gloskin-site-core' ); ?></a></li>
			</ul>
		</div>
		<div>
			<h3><?php echo esc_html__( 'Informasi', 'gloskin-site-core' ); ?></h3>
			<ul>
				<li><a href="<?php echo esc_url( home_url( '/about/' ) ); ?>"><?php echo esc_html__( 'Tentang', 'gloskin-site-core' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/doctors/' ) ); ?>"><?php echo esc_html__( 'Dokter', 'gloskin-site-core' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/insights/' ) ); ?>"><?php echo esc_html__( 'Insight', 'gloskin-site-core' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php echo esc_html__( 'Kontak', 'gloskin-site-core' ); ?></a></li>
			</ul>
		</div>
		<div>
			<h3><?php echo esc_html__( 'Jaringan Klinik', 'gloskin-site-core' ); ?></h3>
			<ul class="gloskin-ui1-footer__clinics">
				<?php foreach ( $gloskin_clinic_links as $gloskin_link ) : ?>
					<li><a href="<?php echo esc_url( (string) $gloskin_link['url'] ); ?>"><?php echo esc_html( (string) $gloskin_link['label'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
	<div class="gloskin-ui1-container gloskin-ui1-footer__bottom"><p>&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> Gloskin.</p></div>
</footer>