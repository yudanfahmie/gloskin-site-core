<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$gloskin_doctors_hero       = isset( $gloskin_context['hero'] ) && is_array( $gloskin_context['hero'] ) ? $gloskin_context['hero'] : array();
$gloskin_doctors_hero_copy  = trim( (string) ( $gloskin_doctors_hero['copy'] ?? '' ) );
$gloskin_doctors_hero_media = absint( $gloskin_doctors_hero['media_id'] ?? 0 );
$gloskin_doctors            = isset( $gloskin_context['doctors'] ) && is_array( $gloskin_context['doctors'] ) ? $gloskin_context['doctors'] : array();
?>
<div class="gloskin-doctors-page">
	<section class="gloskin-doctors-hero" data-gloskin-section="doctors-hero">
		<div class="gloskin-ui1-container gloskin-doctors-hero__grid">
			<div class="gloskin-doctors-hero__copy">
				<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Kami Hadir untuk Anda', 'gloskin-site-core' ); ?></p>
				<h1><?php echo esc_html__( 'Dokter Gloskin', 'gloskin-site-core' ); ?></h1>
				<p><?php echo esc_html( $gloskin_doctors_hero_copy ? $gloskin_doctors_hero_copy : __( 'Gunakan halaman ini untuk mengenali profil dokter dan lokasi praktik yang dipublikasikan Gloskin.', 'gloskin-site-core' ) ); ?></p>
			</div>
			<div class="gloskin-doctors-hero__media">
				<?php if ( $gloskin_doctors_hero_media ) : ?>
					<?php echo wp_get_attachment_image( $gloskin_doctors_hero_media, 'large', false, array( 'class' => 'gloskin-doctors-hero__image', 'fetchpriority' => 'high', 'decoding' => 'async' ) ); ?>
				<?php else : ?>
					<?php gloskin_ui1_render_editorial_media( 'editorial', 'doctors-hero', 'gloskin-doctors-hero__image', true ); ?>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<section class="gloskin-doctors-intro" data-gloskin-section="doctors-intro">
		<div class="gloskin-ui1-container">
			<div class="gloskin-doctors-intro__banner">
				<div class="gloskin-doctors-intro__media"><?php gloskin_ui1_render_editorial_media( 'editorial', 'doctors-intro', 'gloskin-doctors-intro__image' ); ?></div>
				<div class="gloskin-doctors-intro__copy">
					<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Ekspertise Kami', 'gloskin-site-core' ); ?></p>
					<h2><?php echo esc_html__( 'Kenali Dokter Gloskin Melalui Profil yang Tersedia.', 'gloskin-site-core' ); ?></h2>
					<p><?php echo esc_html__( 'Buka profil untuk melihat gelar, spesialisasi, lokasi praktik, dan informasi profesional yang tersedia.', 'gloskin-site-core' ); ?></p>
					<a class="gloskin-ui1-button gloskin-ui1-button--primary" href="#profil"><?php echo esc_html__( 'Jelajahi Profil', 'gloskin-site-core' ); ?></a>
				</div>
			</div>
		</div>
	</section>

	<section id="profil" class="gloskin-doctors-profiles" data-gloskin-section="doctors-grid">
		<div class="gloskin-ui1-container">
			<header class="gloskin-doctors-profiles__head">
				<h2><?php echo esc_html__( 'Profil Dokter', 'gloskin-site-core' ); ?></h2>
				<p><?php echo esc_html__( 'Buka profil untuk melihat informasi profesional dan lokasi praktik yang tersedia.', 'gloskin-site-core' ); ?></p>
			</header>
			<?php if ( $gloskin_doctors ) : ?>
				<?php gloskin_ui1_render_card_grid( $gloskin_doctors, 'doctor' ); ?>
			<?php else : ?>
				<?php gloskin_ui1_render_empty_state( 'doctor', __( 'Belum ada profil dokter yang dapat ditampilkan', 'gloskin-site-core' ), __( 'Profil dokter akan tampil di sini setelah dipublikasikan.', 'gloskin-site-core' ), __( 'Lihat Klinik', 'gloskin-site-core' ), home_url( '/clinics/' ) ); ?>
			<?php endif; ?>
		</div>
	</section>

	<section class="gloskin-doctors-wayfinding" data-gloskin-section="doctors-wayfinding">
		<div class="gloskin-ui1-container">
			<div class="gloskin-doctors-wayfinding__panel">
				<h2><?php echo esc_html__( 'Pilih Jalur Berdasarkan Profil atau Lokasi yang Tersedia.', 'gloskin-site-core' ); ?></h2>
				<div class="gloskin-doctors-wayfinding__paths">
					<div class="gloskin-doctors-wayfinding__path">
						<h3><?php echo esc_html__( 'Informasi Profil', 'gloskin-site-core' ); ?></h3>
						<p><?php echo esc_html__( 'Telusuri profil dokter yang sudah dipublikasikan untuk melihat informasi profesional yang tersedia.', 'gloskin-site-core' ); ?></p>
						<a href="#profil"><?php echo esc_html__( 'Lihat Profil', 'gloskin-site-core' ); ?> <span aria-hidden="true">→</span></a>
					</div>
					<div class="gloskin-doctors-wayfinding__path">
						<h3><?php echo esc_html__( 'Informasi Lokasi', 'gloskin-site-core' ); ?></h3>
						<p><?php echo esc_html__( 'Buka jaringan klinik untuk melihat lokasi Gloskin dan informasi cabang yang tersedia.', 'gloskin-site-core' ); ?></p>
						<a href="<?php echo esc_url( home_url( '/clinics/' ) ); ?>"><?php echo esc_html__( 'Cari Lokasi', 'gloskin-site-core' ); ?> <span aria-hidden="true">→</span></a>
					</div>
				</div>
			</div>
		</div>
	</section>

</div>
