<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* Home uses the native managed video hero resolved by TemplateService. */
gloskin_ui1_render_hero( isset( $gloskin_context['hero'] ) && is_array( $gloskin_context['hero'] ) ? $gloskin_context['hero'] : array() );

?>
<section class="gloskin-home-trust" data-gloskin-section="home-trust" aria-label="<?php echo esc_attr__( 'Keunggulan Gloskin', 'gloskin-site-core' ); ?>">
	<div class="gloskin-ui1-container">
		<div class="gloskin-home-trust__bar">
			<article class="gloskin-home-trust__item">
				<div class="gloskin-home-trust__icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" focusable="false"><circle cx="9" cy="7" r="4"></circle><path d="M2.5 21v-2.5A6.5 6.5 0 0 1 9 12h2"></path><path d="m15 16 2 2 4-4"></path></svg>
				</div>
				<div class="gloskin-home-trust__text">
					<h3><?php echo esc_html__( 'Ditangani Ahli', 'gloskin-site-core' ); ?></h3>
					<p><?php echo esc_html__( 'Dokter bersertifikat & terapis profesional.', 'gloskin-site-core' ); ?></p>
				</div>
			</article>
			<span class="gloskin-home-trust__separator gloskin-home-trust__separator--1" aria-hidden="true"></span>
			<article class="gloskin-home-trust__item">
				<div class="gloskin-home-trust__icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" focusable="false"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><path d="M9 12l2 2 4-4"></path></svg>
				</div>
				<div class="gloskin-home-trust__text">
					<h3><?php echo esc_html__( 'Teruji Klinis', 'gloskin-site-core' ); ?></h3>
					<p><?php echo esc_html__( 'Metode aman berbasis', 'gloskin-site-core' ); ?> <em>evidence-based</em>.</p>
				</div>
			</article>
			<span class="gloskin-home-trust__separator gloskin-home-trust__separator--2" aria-hidden="true"></span>
			<article class="gloskin-home-trust__item">
				<div class="gloskin-home-trust__icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" focusable="false"><path d="M12 3l1.91 5.8a2 2 0 0 0 1.29 1.29L21 12l-5.8 1.91a2 2 0 0 0-1.29 1.29L12 21l-1.91-5.8a2 2 0 0 0-1.29-1.29L3 12l5.8-1.91a2 2 0 0 0 1.29-1.29L12 3z"></path></svg>
				</div>
				<div class="gloskin-home-trust__text">
					<h3><?php echo esc_html__( 'Hasil Natural', 'gloskin-site-core' ); ?></h3>
					<p><?php echo esc_html__( 'Meningkatkan kualitas tanpa merubah karakter.', 'gloskin-site-core' ); ?></p>
				</div>
			</article>
			<span class="gloskin-home-trust__separator gloskin-home-trust__separator--3" aria-hidden="true"></span>
			<article class="gloskin-home-trust__item">
				<div class="gloskin-home-trust__icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" focusable="false"><circle cx="12" cy="8" r="6"></circle><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"></path></svg>
				</div>
				<div class="gloskin-home-trust__text">
					<h3><?php echo esc_html__( 'Klinik Terpercaya', 'gloskin-site-core' ); ?></h3>
					<p><?php echo esc_html__( 'Meraih berbagai penghargaan prestisius.', 'gloskin-site-core' ); ?></p>
				</div>
			</article>
		</div>
	</div>
</section>

<section class="gloskin-ui1-section gloskin-home-why" data-gloskin-section="why-gloskin">
	<div class="gloskin-ui1-container gloskin-home-why__grid">
		<div class="gloskin-home-why__media" aria-hidden="true">
			<?php gloskin_ui1_render_editorial_media( 'editorial', 'home_why', 'gloskin-home-why__image' ); ?>
		</div>
		<div class="gloskin-home-why__copy">
			<h2><?php echo esc_html__( 'KENAPA MEMILIH', 'gloskin-site-core' ); ?><br><?php echo esc_html__( 'GLOSKIN', 'gloskin-site-core' ); ?></h2>
			<p class="gloskin-home-why__intro"><?php echo esc_html__( 'Kami hadir untuk memberikan solusi estetika terdepan yang tidak hanya merawat, tetapi juga menyehatkan kulit Anda dari dalam.', 'gloskin-site-core' ); ?></p>
			<ul>
				<li><?php echo esc_html__( 'Tersedia pilihan perawatan yang lengkap dan inovatif berdasarkan keilmuan estetik terkini.', 'gloskin-site-core' ); ?></li>
				<li><?php echo esc_html__( 'Ditangani oleh dokter dan terapis yang berpengalaman, tersertifikasi, dan terus mendapatkan update ilmu.', 'gloskin-site-core' ); ?></li>
				<li><?php echo esc_html__( 'Produk skincare dirancang khusus dan teruji klinis untuk menjawab berbagai masalah kulit masyarakat.', 'gloskin-site-core' ); ?></li>
			</ul>
		</div>
	</div>
</section>

<?php $gloskin_home_treatments = isset( $gloskin_context['treatments'] ) && is_array( $gloskin_context['treatments'] ) ? $gloskin_context['treatments'] : array(); ?>
<section class="gloskin-ui1-section gloskin-home-treatments" data-gloskin-section="home-treatments">
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_section_heading( __( 'TREATMENT UNGGULAN', 'gloskin-site-core' ), __( 'Rangkaian perawatan eksklusif yang dirancang secara personal dengan teknologi mutakhir untuk memancarkan kecantikan sejati kulit Anda.', 'gloskin-site-core' ) ); ?>
		<?php if ( $gloskin_home_treatments ) : ?>
			<div class="gloskin-ui1-grid gloskin-ui1-grid--cards gloskin-home-treatments__grid">
				<?php foreach ( $gloskin_home_treatments as $gloskin_home_treatment ) : ?>
					<?php
					/* Canonical Home cards are image + title + detail link only. */
					$gloskin_home_treatment_card            = $gloskin_home_treatment;
					$gloskin_home_treatment_card['summary'] = '';
					$gloskin_home_treatment_card['excerpt'] = '';
					gloskin_ui1_render_card( $gloskin_home_treatment_card, 'treatment' );
					?>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<?php gloskin_ui1_render_empty_state( 'treatment', __( 'Treatment Unggulan', 'gloskin-site-core' ), __( 'Detail tambahan belum tersedia untuk ditampilkan.', 'gloskin-site-core' ) ); ?>
		<?php endif; ?>
	</div>
</section>

<?php $gloskin_home_testimonials = isset( $gloskin_context['testimonials'] ) && is_array( $gloskin_context['testimonials'] ) ? $gloskin_context['testimonials'] : array(); ?>
<section class="gloskin-ui1-section gloskin-home-testimonials" data-gloskin-section="testimonials">
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_section_heading( __( 'TESTIMONI', 'gloskin-site-core' ), __( 'Pengalaman nyata dari mereka yang telah mempercayakan perjalanan kecantikannya dan merasakan transformasi luar biasa bersama GLOSKIN.', 'gloskin-site-core' ) ); ?>
		<?php if ( $gloskin_home_testimonials ) : ?>
			<div class="gloskin-home-testimonials__slider" data-gloskin-testimonials>
				<div class="gloskin-home-testimonials__stage">
					<?php foreach ( $gloskin_home_testimonials as $gloskin_home_testimonial_index => $gloskin_home_testimonial ) :
						$gloskin_home_quote       = trim( (string) ( $gloskin_home_testimonial['excerpt'] ?? '' ) );
						$gloskin_home_attribution = (string) ( $gloskin_home_testimonial['meta']['attribution'] ?? '' );
						$gloskin_home_subtitle    = (string) ( $gloskin_home_testimonial['meta']['subtitle'] ?? '' );
						$gloskin_home_has_avatar  = ! empty( $gloskin_home_testimonial['image_id'] );
					?>
					<figure class="gloskin-home-testimonial<?php echo $gloskin_home_has_avatar ? ' has-avatar' : ''; ?>" data-gloskin-testimonial aria-hidden="<?php echo 0 === $gloskin_home_testimonial_index ? 'false' : 'true'; ?>"<?php echo 0 === $gloskin_home_testimonial_index ? '' : ' hidden'; ?>>
						<?php if ( $gloskin_home_has_avatar ) : ?>
							<?php echo wp_get_attachment_image( absint( $gloskin_home_testimonial['image_id'] ), 'thumbnail', false, array( 'class' => 'gloskin-home-testimonial__avatar', 'loading' => 'lazy' ) ); ?>
						<?php endif; ?>
						<div class="gloskin-home-testimonial__body">
							<span class="gloskin-home-testimonial__quote" aria-hidden="true">“</span>
							<blockquote><p>"<?php echo esc_html( wp_trim_words( wp_strip_all_tags( $gloskin_home_quote ), 48 ) ); ?>"</p></blockquote>
							<?php if ( '' !== $gloskin_home_attribution || '' !== $gloskin_home_subtitle ) : ?>
							<figcaption>
								<?php if ( '' !== $gloskin_home_attribution ) : ?><strong><?php echo esc_html( $gloskin_home_attribution ); ?></strong><?php endif; ?>
								<?php if ( '' !== $gloskin_home_subtitle ) : ?><span><?php echo esc_html( $gloskin_home_subtitle ); ?></span><?php endif; ?>
							</figcaption>
							<?php endif; ?>
						</div>
					</figure>
					<?php endforeach; ?>
				</div>
				<?php if ( count( $gloskin_home_testimonials ) > 1 ) : ?>
					<div class="gloskin-home-testimonials__nav" aria-label="<?php echo esc_attr__( 'Navigasi testimoni', 'gloskin-site-core' ); ?>">
						<button class="gloskin-home-testimonials__control" type="button" data-gloskin-testimonial-prev aria-label="<?php echo esc_attr__( 'Testimoni sebelumnya', 'gloskin-site-core' ); ?>"><?php echo gloskin_ui1_arrow_icon( 'prev' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static shared icon markup. ?></button>
						<div class="gloskin-home-testimonials__dots" role="tablist" aria-label="<?php echo esc_attr__( 'Pilih testimoni', 'gloskin-site-core' ); ?>">
							<?php foreach ( $gloskin_home_testimonials as $gloskin_home_dot_index => $gloskin_home_dot ) : ?>
								<button type="button" class="gloskin-home-testimonials__dot<?php echo 0 === $gloskin_home_dot_index ? ' is-active' : ''; ?>" data-gloskin-testimonial-dot role="tab" aria-selected="<?php echo 0 === $gloskin_home_dot_index ? 'true' : 'false'; ?>" tabindex="<?php echo 0 === $gloskin_home_dot_index ? '0' : '-1'; ?>"><span class="screen-reader-text"><?php echo esc_html( sprintf( /* translators: %d: testimonial number. */ __( 'Testimoni %d', 'gloskin-site-core' ), $gloskin_home_dot_index + 1 ) ); ?></span></button>
							<?php endforeach; ?>
						</div>
						<button class="gloskin-home-testimonials__control" type="button" data-gloskin-testimonial-next aria-label="<?php echo esc_attr__( 'Testimoni berikutnya', 'gloskin-site-core' ); ?>"><?php echo gloskin_ui1_arrow_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static shared icon markup. ?></button>
					</div>
				<?php endif; ?>
			</div>
		<?php else : ?>
			<?php gloskin_ui1_render_empty_state( 'generic', __( 'Testimoni', 'gloskin-site-core' ), __( 'Detail tambahan belum tersedia untuk ditampilkan.', 'gloskin-site-core' ) ); ?>
		<?php endif; ?>
	</div>
</section>

<?php $gloskin_home_piagam = isset( $gloskin_context['achievements'] ) && is_array( $gloskin_context['achievements'] ) ? $gloskin_context['achievements'] : array(); ?>
<section class="gloskin-ui1-section gloskin-home-piagam" data-gloskin-section="achievements">
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_section_heading( __( 'PIAGAM & PENGHARGAAN', 'gloskin-site-core' ), __( 'Bukti komitmen dan dedikasi tinggi kami dalam menjaga standar mutu pelayanan estetika dan inovasi medis terbaik di Indonesia.', 'gloskin-site-core' ) ); ?>
		<?php if ( $gloskin_home_piagam ) : ?>
			<div class="gloskin-home-piagam__marquee" data-gloskin-piagam aria-label="<?php echo esc_attr__( 'Piagam dan penghargaan Gloskin', 'gloskin-site-core' ); ?>">
				<div class="gloskin-home-piagam__track">
					<?php for ( $gloskin_home_piagam_loop = 0; $gloskin_home_piagam_loop < 2; $gloskin_home_piagam_loop++ ) : ?>
						<?php foreach ( $gloskin_home_piagam as $gloskin_home_achievement ) :
							$gloskin_home_achievement_image_id = absint( $gloskin_home_achievement['image_id'] ?? 0 );
						?>
							<figure class="gloskin-home-piagam__item" data-gloskin-piagam-item<?php echo 1 === $gloskin_home_piagam_loop ? ' aria-hidden="true"' : ''; ?>>
								<?php echo wp_get_attachment_image( $gloskin_home_achievement_image_id, 'medium_large', false, array( 'class' => 'gloskin-home-piagam__image', 'loading' => 'lazy', 'decoding' => 'async', 'alt' => '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- canonical WordPress attachment markup. ?>
							</figure>
						<?php endforeach; ?>
					<?php endfor; ?>
				</div>
			</div>
		<?php else : ?>
			<?php gloskin_ui1_render_empty_state( 'generic', __( 'Piagam & Penghargaan', 'gloskin-site-core' ), __( 'Detail tambahan belum tersedia untuk ditampilkan.', 'gloskin-site-core' ) ); ?>
		<?php endif; ?>
	</div>
</section>