<?php
/** Global Gloskin footer. @package GloskinSiteCore */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$clinic_links = isset( $gloskin_context['clinic_links'] ) && is_array( $gloskin_context['clinic_links'] ) ? $gloskin_context['clinic_links'] : array();
$show_footer_cta = ! in_array( isset( $gloskin_context['view'] ) ? $gloskin_context['view'] : '', array( 'home', 'contact' ), true );
?>
<footer class="gloskin-ui1-footer">
	<?php if ( $show_footer_cta ) : ?><div class="gloskin-ui1-footer__cta"><div class="gloskin-ui1-container gloskin-ui1-footer__cta-inner"><div><p class="gloskin-ui1-eyebrow">Gloskin</p><h2><?php echo esc_html__( 'Temukan klinik Gloskin yang ingin Anda kunjungi.', 'gloskin-site-core' ); ?></h2></div><a class="gloskin-ui1-button gloskin-ui1-button--light" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php echo esc_html__( 'Hubungi Kami', 'gloskin-site-core' ); ?></a></div></div><?php endif; ?>
	<div class="gloskin-ui1-container gloskin-ui1-footer__grid">
		<div class="gloskin-ui1-footer__brand"><a class="gloskin-ui1-brand gloskin-ui1-brand--footer" href="<?php echo esc_url( home_url( '/' ) ); ?>">Gloskin</a><p><?php echo esc_html__( 'Jelajahi klinik, informasi perawatan, skincare, dan insight Gloskin dalam satu tempat.', 'gloskin-site-core' ); ?></p></div>
		<div><h3><?php echo esc_html__( 'Jelajahi', 'gloskin-site-core' ); ?></h3><ul><li><a href="<?php echo esc_url( home_url( '/treatments/' ) ); ?>"><?php echo esc_html__( 'Perawatan', 'gloskin-site-core' ); ?></a></li><li><a href="<?php echo esc_url( home_url( '/clinics/' ) ); ?>"><?php echo esc_html__( 'Klinik', 'gloskin-site-core' ); ?></a></li><li><a href="<?php echo esc_url( home_url( '/doctors/' ) ); ?>"><?php echo esc_html__( 'Dokter', 'gloskin-site-core' ); ?></a></li><li><a href="<?php echo esc_url( home_url( '/skincare/' ) ); ?>">Skincare</a></li></ul></div>
		<div><h3><?php echo esc_html__( 'Informasi', 'gloskin-site-core' ); ?></h3><ul><li><a href="<?php echo esc_url( home_url( '/about/' ) ); ?>"><?php echo esc_html__( 'Tentang Gloskin', 'gloskin-site-core' ); ?></a></li><li><a href="<?php echo esc_url( home_url( '/insights/' ) ); ?>"><?php echo esc_html__( 'Insight', 'gloskin-site-core' ); ?></a></li><li><a href="<?php echo esc_url( home_url( '/shop/' ) ); ?>"><?php echo esc_html__( 'Belanja', 'gloskin-site-core' ); ?></a></li><li><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php echo esc_html__( 'Kontak', 'gloskin-site-core' ); ?></a></li></ul></div>
		<div><h3><?php echo esc_html__( 'Jaringan Klinik', 'gloskin-site-core' ); ?></h3><ul class="gloskin-ui1-footer__clinics"><?php foreach ( $clinic_links as $link ) : ?><li><a href="<?php echo esc_url( (string) $link['url'] ); ?>"><?php echo esc_html( (string) $link['label'] ); ?></a></li><?php endforeach; ?></ul></div>
	</div>
	<div class="gloskin-ui1-container gloskin-ui1-footer__bottom"><p>&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> Gloskin.</p></div>
</footer>
