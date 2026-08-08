<?php
/**
 * Global Gloskin footer.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$clinic_links = isset( $gloskin_context['clinic_links'] ) && is_array( $gloskin_context['clinic_links'] )
	? $gloskin_context['clinic_links']
	: array();

$show_footer_cta = ! in_array( isset( $gloskin_context['view'] ) ? $gloskin_context['view'] : '', array( 'home', 'contact' ), true );
?>
<footer class="gloskin-ui1-footer">
	<?php if ( $show_footer_cta ) : ?><div class="gloskin-ui1-footer__cta">
		<div class="gloskin-ui1-container gloskin-ui1-footer__cta-inner">
			<div>
				<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Gloskin', 'gloskin-site-core' ); ?></p>
				<h2><?php echo esc_html__( 'Find a Gloskin clinic.', 'gloskin-site-core' ); ?></h2>
			</div>
			<a class="gloskin-ui1-button gloskin-ui1-button--light" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php echo esc_html__( 'Contact', 'gloskin-site-core' ); ?></a>
		</div>
	</div><?php endif; ?>
	<div class="gloskin-ui1-container gloskin-ui1-footer__grid">
		<div class="gloskin-ui1-footer__brand">
			<a class="gloskin-ui1-brand gloskin-ui1-brand--footer" href="<?php echo esc_url( home_url( '/' ) ); ?>">Gloskin</a>
			<p><?php echo esc_html__( 'Explore Gloskin clinics, treatments, doctors, skincare and insights.', 'gloskin-site-core' ); ?></p>
		</div>
		<div>
			<h3><?php echo esc_html__( 'Discover', 'gloskin-site-core' ); ?></h3>
			<ul>
				<li><a href="<?php echo esc_url( home_url( '/treatments/' ) ); ?>"><?php echo esc_html__( 'Treatments', 'gloskin-site-core' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/clinics/' ) ); ?>"><?php echo esc_html__( 'Clinics', 'gloskin-site-core' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/doctors/' ) ); ?>"><?php echo esc_html__( 'Doctors', 'gloskin-site-core' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/skincare/' ) ); ?>"><?php echo esc_html__( 'Skincare', 'gloskin-site-core' ); ?></a></li>
			</ul>
		</div>
		<div>
			<h3><?php echo esc_html__( 'More', 'gloskin-site-core' ); ?></h3>
			<ul>
				<li><a href="<?php echo esc_url( home_url( '/about/' ) ); ?>"><?php echo esc_html__( 'About', 'gloskin-site-core' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/shop/' ) ); ?>"><?php echo esc_html__( 'Shop', 'gloskin-site-core' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/insights/' ) ); ?>"><?php echo esc_html__( 'Insights', 'gloskin-site-core' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php echo esc_html__( 'Contact', 'gloskin-site-core' ); ?></a></li>
			</ul>
		</div>
		<div>
			<h3><?php echo esc_html__( 'Clinic network', 'gloskin-site-core' ); ?></h3>
			<ul class="gloskin-ui1-footer__clinics">
				<?php foreach ( $clinic_links as $link ) : ?>
					<li><a href="<?php echo esc_url( (string) $link['url'] ); ?>"><?php echo esc_html( (string) $link['label'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
	<div class="gloskin-ui1-container gloskin-ui1-footer__bottom">
		<p>&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> Gloskin.</p>
	</div>
</footer>
