<?php
/** Global Gloskin footer. @package GloskinSiteCore */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$gloskin_clinic_links = isset( $gloskin_context['clinic_links'] ) && is_array( $gloskin_context['clinic_links'] ) ? $gloskin_context['clinic_links'] : array();
$gloskin_logo_url = isset( $gloskin_context['logo_url'] ) ? (string) $gloskin_context['logo_url'] : '';
$gloskin_footer_view = isset( $gloskin_context['view'] ) ? (string) $gloskin_context['view'] : '';
$gloskin_footer_cta_excluded_views = array( 'home', 'contact', 'about', 'promo', 'skincare', 'skincare-category', 'treatments', 'clinic', 'doctors' );
$gloskin_show_footer_cta = ! in_array( $gloskin_footer_view, $gloskin_footer_cta_excluded_views, true );
?>
<footer class="gloskin-ui1-footer">
	<?php if ( $gloskin_show_footer_cta ) : ?>
	<div class="gloskin-ui1-footer__cta" style="position:relative;overflow:hidden;padding:clamp(28px,4vw,52px) 0;background:#080808;color:#fff;">
		<svg aria-hidden="true" focusable="false" viewBox="0 0 1440 520" preserveAspectRatio="none" style="position:absolute;inset:0;width:100%;height:100%;color:#fff;opacity:.075;pointer-events:none;">
			<path d="M-120 430C160 160 430 110 720 260s560 150 840-120" fill="none" stroke="currentColor" stroke-width="1.25"/>
			<path d="M-80 500C230 230 500 210 760 330s510 110 790-80" fill="none" stroke="currentColor" stroke-width=".8"/>
			<circle cx="1250" cy="90" r="170" fill="none" stroke="currentColor" stroke-width=".8"/>
			<circle cx="1250" cy="90" r="118" fill="none" stroke="currentColor" stroke-width=".55"/>
		</svg>
		<div class="gloskin-ui1-container gloskin-ui1-footer__cta-inner" style="position:relative;z-index:1;display:flex;align-items:center;justify-content:space-between;gap:clamp(28px,5vw,72px);padding:clamp(42px,5vw,72px);border:1px solid rgba(255,255,255,.16);border-radius:32px;">
			<div class="gloskin-ui1-footer__cta-copy">
				<p class="gloskin-ui1-eyebrow" style="margin-bottom:12px;color:#d1ae77;"><?php echo esc_html__( 'Konsultasi', 'gloskin-site-core' ); ?></p>
				<h2 style="max-width:15ch;margin:0;color:#fff;font-size:clamp(2.35rem,4vw,4.35rem);line-height:.98;letter-spacing:.01em;text-transform:uppercase;"><?php echo esc_html__( 'Siap Membicarakan Kebutuhan Kulit Anda?', 'gloskin-site-core' ); ?></h2>
				<p class="gloskin-ui1-footer__cta-description" style="max-width:58ch;margin:18px 0 0;color:rgba(255,255,255,.68);line-height:1.65;"><?php echo esc_html__( 'Pilih klinik Gloskin terdekat atau hubungi tim kami untuk menjadwalkan konsultasi.', 'gloskin-site-core' ); ?></p>
			</div>
			<div style="display:flex;flex:0 0 auto;flex-wrap:wrap;align-items:center;justify-content:flex-end;gap:12px;">
				<a class="gloskin-ui1-button gloskin-ui1-button--primary" style="min-width:124px;background:#d50018;color:#fff;" href="<?php echo esc_url( home_url( '/clinics/' ) ); ?>"><?php echo esc_html__( 'Pilih Klinik', 'gloskin-site-core' ); ?></a>
				<a class="gloskin-ui1-button gloskin-ui1-button--ghost" style="min-width:150px;border-color:rgba(255,255,255,.38);color:#fff;" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php echo esc_html__( 'Hubungi Kami', 'gloskin-site-core' ); ?></a>
			</div>
		</div>
	</div>
	<?php endif; ?>
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