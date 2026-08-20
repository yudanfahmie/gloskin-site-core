<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
status_header( 404 );
nocache_headers();
?>
<section class="gloskin-ui1-not-found" aria-labelledby="gloskin-not-found-title">
	<div class="gloskin-ui1-container gloskin-ui1-not-found__scene">
		<div class="gloskin-ui1-not-found__ambient" aria-hidden="true">
			<span class="gloskin-ui1-not-found__halo"></span>
			<span class="gloskin-ui1-not-found__orbit"></span>
			<span class="gloskin-ui1-not-found__point"></span>
		</div>
		<div class="gloskin-ui1-not-found__content">
			<p class="gloskin-ui1-not-found__code" aria-hidden="true">404</p>
			<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Gloskin', 'gloskin-site-core' ); ?></p>
			<h1 id="gloskin-not-found-title"><?php echo esc_html__( 'Halaman ini tidak ditemukan', 'gloskin-site-core' ); ?></h1>
			<p class="gloskin-ui1-not-found__copy"><?php echo esc_html__( 'Tautan mungkin sudah berubah atau alamatnya belum tepat. Anda tetap dapat melanjutkan dari halaman utama atau membaca Insight terbaru.', 'gloskin-site-core' ); ?></p>
			<div class="gloskin-ui1-not-found__actions">
				<a class="gloskin-ui1-button gloskin-ui1-button--primary" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html__( 'Kembali ke Beranda', 'gloskin-site-core' ); ?></a>
				<a class="gloskin-ui1-button gloskin-ui1-button--ghost" href="<?php echo esc_url( home_url( '/insights/' ) ); ?>"><?php echo esc_html__( 'Buka Insight', 'gloskin-site-core' ); ?></a>
			</div>
			<nav class="gloskin-ui1-not-found__links" aria-label="<?php echo esc_attr__( 'Pilihan halaman Gloskin', 'gloskin-site-core' ); ?>">
				<a href="<?php echo esc_url( home_url( '/treatments/' ) ); ?>"><?php echo esc_html__( 'Perawatan', 'gloskin-site-core' ); ?></a>
				<a href="<?php echo esc_url( home_url( '/skincare/' ) ); ?>"><?php echo esc_html__( 'Skincare', 'gloskin-site-core' ); ?></a>
				<a href="<?php echo esc_url( home_url( '/clinics/' ) ); ?>"><?php echo esc_html__( 'Klinik', 'gloskin-site-core' ); ?></a>
			</nav>
		</div>
	</div>
</section>
