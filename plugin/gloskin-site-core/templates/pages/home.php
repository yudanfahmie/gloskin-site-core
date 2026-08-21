<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* Full-width video hero — mode is resolved in TemplateService::home_context(). */
gloskin_ui1_render_hero( isset( $gloskin_context['hero'] ) && is_array( $gloskin_context['hero'] ) ? $gloskin_context['hero'] : array() );

?>
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

<?php $gloskin_home_treatments = array_slice( isset( $gloskin_context['treatments'] ) && is_array( $gloskin_context['treatments'] ) ? $gloskin_context['treatments'] : array(), 0, 3 ); ?>
<section class="gloskin-ui1-section gloskin-ui1-section--soft gloskin-home-treatments" data-gloskin-section="home-treatments">
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_section_heading( __( 'Treatment Unggulan', 'gloskin-site-core' ), __( 'Rangkaian perawatan eksklusif yang dirancang secara personal dengan teknologi mutakhir untuk memancarkan kecantikan sejati kulit Anda.', 'gloskin-site-core' ) ); ?>
		<?php if ( $gloskin_home_treatments ) : ?>
			<div class="gloskin-ui1-grid gloskin-ui1-grid--cards gloskin-home-treatments__grid">
				<?php foreach ( $gloskin_home_treatments as $gloskin_home_treatment ) : ?>
					<?php
					/* Home prefers the managed Treatment summary, but a factual post excerpt
					 * is a safe short-description fallback when that summary is still blank. */
					$gloskin_home_treatment_card = $gloskin_home_treatment;
					if ( '' === trim( (string) ( $gloskin_home_treatment_card['summary'] ?? '' ) ) && '' !== trim( (string) ( $gloskin_home_treatment_card['excerpt'] ?? '' ) ) ) {
						$gloskin_home_treatment_card['summary'] = (string) $gloskin_home_treatment_card['excerpt'];
					}
					gloskin_ui1_render_card( $gloskin_home_treatment_card, 'treatment' );
					?>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<?php gloskin_ui1_render_empty_state( 'treatment', __( 'Treatment Unggulan', 'gloskin-site-core' ), __( 'Detail tambahan belum tersedia untuk ditampilkan.', 'gloskin-site-core' ) ); ?>
		<?php endif; ?>
	</div>
</section>

<?php
$gloskin_home_testimonials = isset( $gloskin_context['testimonials'] ) && is_array( $gloskin_context['testimonials'] ) ? $gloskin_context['testimonials'] : array();
$gloskin_home_testimonials = array_values( array_filter( $gloskin_home_testimonials, static function ( $gloskin_home_testimonial ) {
	return '' !== trim( (string) ( $gloskin_home_testimonial['excerpt'] ?? '' ) );
} ) );
$gloskin_home_testimonials = array_slice( $gloskin_home_testimonials, 0, 3 );
?>
<section class="gloskin-ui1-section gloskin-home-testimonials" data-gloskin-section="testimonials">
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_section_heading( __( 'Testimoni', 'gloskin-site-core' ), __( 'Pengalaman nyata dari mereka yang telah mempercayakan perjalanan kecantikannya dan merasakan transformasi luar biasa bersama GLOSKIN.', 'gloskin-site-core' ) ); ?>
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
						<button class="gloskin-home-testimonials__control" type="button" data-gloskin-testimonial-prev aria-label="<?php echo esc_attr__( 'Testimoni sebelumnya', 'gloskin-site-core' ); ?>"><svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m11 4-5 5 5 5"/></svg></button>
						<div class="gloskin-home-testimonials__dots" role="tablist" aria-label="<?php echo esc_attr__( 'Pilih testimoni', 'gloskin-site-core' ); ?>">
							<?php foreach ( $gloskin_home_testimonials as $gloskin_home_dot_index => $gloskin_home_dot ) : ?>
								<button type="button" class="gloskin-home-testimonials__dot<?php echo 0 === $gloskin_home_dot_index ? ' is-active' : ''; ?>" data-gloskin-testimonial-dot role="tab" aria-selected="<?php echo 0 === $gloskin_home_dot_index ? 'true' : 'false'; ?>" tabindex="<?php echo 0 === $gloskin_home_dot_index ? '0' : '-1'; ?>"><span class="screen-reader-text"><?php echo esc_html( sprintf( /* translators: %d: testimonial number. */ __( 'Testimoni %d', 'gloskin-site-core' ), $gloskin_home_dot_index + 1 ) ); ?></span></button>
							<?php endforeach; ?>
						</div>
						<button class="gloskin-home-testimonials__control" type="button" data-gloskin-testimonial-next aria-label="<?php echo esc_attr__( 'Testimoni berikutnya', 'gloskin-site-core' ); ?>"><svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m7 4 5 5-5 5"/></svg></button>
					</div>
				<?php endif; ?>
			</div>
		<?php else : ?>
			<?php gloskin_ui1_render_empty_state( 'generic', __( 'Testimoni', 'gloskin-site-core' ), __( 'Detail tambahan belum tersedia untuk ditampilkan.', 'gloskin-site-core' ) ); ?>
		<?php endif; ?>
	</div>
</section>

<?php $gloskin_home_piagam = array_slice( isset( $gloskin_context['achievements'] ) && is_array( $gloskin_context['achievements'] ) ? $gloskin_context['achievements'] : array(), 0, 5 ); ?>
<section class="gloskin-ui1-section gloskin-home-piagam" data-gloskin-section="achievements">
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_section_heading( __( 'Piagam & Penghargaan', 'gloskin-site-core' ), __( 'Bukti komitmen dan dedikasi tinggi kami dalam menjaga standar mutu pelayanan estetika dan inovasi medis terbaik di Indonesia.', 'gloskin-site-core' ) ); ?>
		<?php if ( $gloskin_home_piagam ) : ?>
			<div class="gloskin-home-piagam__rail" data-gloskin-piagam>
				<?php foreach ( $gloskin_home_piagam as $gloskin_home_piagam_index => $gloskin_home_achievement ) :
					$gloskin_home_achievement_title  = trim( (string) ( $gloskin_home_achievement['title'] ?? '' ) );
					$gloskin_home_achievement_issuer = trim( (string) ( $gloskin_home_achievement['meta']['issuer'] ?? '' ) );
					$gloskin_home_achievement_year   = trim( (string) ( $gloskin_home_achievement['meta']['year'] ?? '' ) );
					$gloskin_home_achievement_meta   = implode( ' · ', array_filter( array( $gloskin_home_achievement_issuer, $gloskin_home_achievement_year ) ) );
				?>
					<figure class="gloskin-home-piagam__card">
						<?php if ( '' !== $gloskin_home_achievement_title ) : ?><h3 class="gloskin-home-piagam__title"><?php echo esc_html( $gloskin_home_achievement_title ); ?></h3><?php endif; ?>
						<?php if ( ! empty( $gloskin_home_achievement['image_id'] ) ) : ?>
							<?php echo wp_get_attachment_image( absint( $gloskin_home_achievement['image_id'] ), 'medium_large', false, array( 'class' => 'gloskin-home-piagam__image', 'loading' => 'lazy', 'alt' => $gloskin_home_achievement_title ) ); ?>
						<?php else : ?>
							<?php gloskin_ui1_render_presentation_media( 'editorial', 'piagam-' . ( $gloskin_home_piagam_index + 1 ), 'gloskin-home-piagam__image' ); ?>
						<?php endif; ?>
						<?php if ( '' !== $gloskin_home_achievement_meta ) : ?><figcaption class="gloskin-home-piagam__meta"><?php echo esc_html( $gloskin_home_achievement_meta ); ?></figcaption><?php endif; ?>
					</figure>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<?php gloskin_ui1_render_empty_state( 'generic', __( 'Piagam & Penghargaan', 'gloskin-site-core' ), __( 'Detail tambahan belum tersedia untuk ditampilkan.', 'gloskin-site-core' ) ); ?>
		<?php endif; ?>
	</div>
</section>
