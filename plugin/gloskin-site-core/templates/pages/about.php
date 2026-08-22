<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$gloskin_founder = isset( $gloskin_context['founder'] ) && is_array( $gloskin_context['founder'] )
	? $gloskin_context['founder']
	: array( 'name' => '', 'role' => '', 'story' => '', 'media_id' => 0 );
$gloskin_founder_image_url = plugin_dir_url( dirname( __DIR__, 2 ) . '/gloskin-site-core.php' ) . 'assets/images/gloskin-founder.webp';
?>
<div class="gloskin-about-page">
	<header class="gloskin-about-header" data-gloskin-section="about-header">
		<div class="gloskin-ui1-container gloskin-ui1-container--narrow">
			<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'ABOUT', 'gloskin-site-core' ); ?></p>
			<h1><?php echo esc_html__( 'Tentang Kami', 'gloskin-site-core' ); ?></h1>
		</div>
	</header>

	<section class="gloskin-ui1-section gloskin-about-story" data-gloskin-section="about-story">
		<div class="gloskin-ui1-container gloskin-about-story__grid">
			<div class="gloskin-about-story__media">
				<?php gloskin_ui1_render_editorial_media( 'editorial', 'about_story', 'gloskin-about-story__image', true ); ?>
			</div>
			<div class="gloskin-about-story__copy">
				<p class="gloskin-ui1-eyebrow">GLOSKIN</p>
				<h2><?php echo esc_html__( 'Tentang GLOSKIN', 'gloskin-site-core' ); ?></h2>
				<div class="gloskin-ui1-prose"><?php echo wp_kses_post( wpautop( (string) $gloskin_context['story'] ) ); ?></div>
			</div>
		</div>
	</section>

	<section class="gloskin-ui1-section gloskin-about-founder" data-gloskin-section="about-founder">
		<div class="gloskin-ui1-container gloskin-about-founder__grid">
			<div class="gloskin-about-founder__copy">
				<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Founder', 'gloskin-site-core' ); ?></p>
				<h2><?php echo esc_html( (string) $gloskin_founder['name'] ); ?></h2>
				<p class="gloskin-about-founder__role"><?php echo esc_html( (string) $gloskin_founder['role'] ); ?></p>
				<div class="gloskin-ui1-prose"><?php echo wp_kses_post( wpautop( (string) $gloskin_founder['story'] ) ); ?></div>
			</div>
			<div class="gloskin-about-founder__media">
				<img class="gloskin-about-founder__image" src="<?php echo esc_url( $gloskin_founder_image_url ); ?>" alt="<?php echo esc_attr( (string) $gloskin_founder['name'] ); ?>" loading="lazy" decoding="async">
			</div>
		</div>
	</section>

	<section class="gloskin-ui1-section gloskin-about-principles" data-gloskin-section="about-principles">
		<div class="gloskin-ui1-container">
			<?php gloskin_ui1_render_section_heading( __( 'Visi · Misi · Nilai', 'gloskin-site-core' ) ); ?>
			<div class="gloskin-about-principles__grid">
				<article class="gloskin-about-principle">
					<div class="gloskin-about-principle__icon" aria-hidden="true">
						<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
							<path d="M24 36V18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
							<path d="M16 26l8-8 8 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
							<path d="M10 36h28" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
						</svg>
					</div>
					<h3><?php echo esc_html__( 'Visi', 'gloskin-site-core' ); ?></h3>
					<div class="gloskin-ui1-prose"><?php echo wp_kses_post( (string) $gloskin_context['vision'] ); ?></div>
				</article>
				<article class="gloskin-about-principle">
					<div class="gloskin-about-principle__icon" aria-hidden="true">
						<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
							<path d="M10 38c4 0 6-6 14-6 6 0 10-4 14-8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
							<circle cx="10" cy="38" r="3" stroke="currentColor" stroke-width="1.5"/>
							<circle cx="38" cy="24" r="3" stroke="currentColor" stroke-width="1.5"/>
						</svg>
					</div>
					<h3><?php echo esc_html__( 'Misi', 'gloskin-site-core' ); ?></h3>
					<div class="gloskin-ui1-prose"><?php echo wp_kses_post( (string) $gloskin_context['mission'] ); ?></div>
				</article>
				<article class="gloskin-about-principle">
					<div class="gloskin-about-principle__icon" aria-hidden="true">
						<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
							<circle cx="24" cy="13" r="4" stroke="currentColor" stroke-width="1.5"/>
							<circle cx="13" cy="33" r="4" stroke="currentColor" stroke-width="1.5"/>
							<circle cx="35" cy="33" r="4" stroke="currentColor" stroke-width="1.5"/>
							<path d="M21 17l-5 12M27 17l5 12M17 33h14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
						</svg>
					</div>
					<h3><?php echo esc_html__( 'Nilai', 'gloskin-site-core' ); ?></h3>
					<div class="gloskin-ui1-prose"><?php echo wp_kses_post( (string) $gloskin_context['values'] ); ?></div>
				</article>
			</div>
		</div>
	</section>

	<section class="gloskin-ui1-section gloskin-about-philosophy" data-gloskin-section="about-philosophy">
		<div class="gloskin-ui1-container gloskin-ui1-container--narrow">
			<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Prinsip Kami', 'gloskin-site-core' ); ?></p>
			<h2><?php echo esc_html__( 'Filosofi dalam Praktik', 'gloskin-site-core' ); ?></h2>
			<div class="gloskin-ui1-prose gloskin-about-philosophy__body">
				<p><?php echo esc_html__( 'Di Gloskin, filosofi kami diterapkan melalui layanan estetika medis berbasis bukti dengan fokus pada kualitas kulit dan rambut, sejalan dengan pendekatan Skin Barrier & Quality Xpert.', 'gloskin-site-core' ); ?></p>
				<p><?php echo esc_html__( 'Setiap layanan diarahkan untuk menghadirkan solusi yang aman, natural, dan berkelanjutan melalui pelayanan profesional yang konsisten dengan visi Gloskin.', 'gloskin-site-core' ); ?></p>
			</div>
		</div>
	</section>

	<section class="gloskin-ui1-section gloskin-about-explore" data-gloskin-section="about-explore">
		<div class="gloskin-ui1-container">
			<?php gloskin_ui1_render_section_heading( __( 'Jelajahi Gloskin', 'gloskin-site-core' ) ); ?>
			<?php gloskin_ui1_render_pathway_grid( array(
				array( 'title' => __( 'Perawatan', 'gloskin-site-core' ), 'eyebrow' => __( 'Layanan', 'gloskin-site-core' ), 'copy' => __( 'Rangkaian perawatan kulit profesional.', 'gloskin-site-core' ), 'url' => home_url( '/treatments/' ), 'label' => __( 'Lihat Perawatan', 'gloskin-site-core' ) ),
				array( 'title' => __( 'Produk Skincare', 'gloskin-site-core' ), 'eyebrow' => __( 'Produk', 'gloskin-site-core' ), 'copy' => __( 'Rangkaian produk perawatan kulit.', 'gloskin-site-core' ), 'url' => home_url( '/skincare/' ), 'label' => __( 'Lihat Produk', 'gloskin-site-core' ) ),
				array( 'title' => __( 'Klinik Kami', 'gloskin-site-core' ), 'eyebrow' => __( 'Lokasi', 'gloskin-site-core' ), 'copy' => __( 'Temukan klinik Gloskin terdekat.', 'gloskin-site-core' ), 'url' => home_url( '/clinics/' ), 'label' => __( 'Temukan Klinik', 'gloskin-site-core' ) ),
			) ); ?>
		</div>
	</section>
</div>
