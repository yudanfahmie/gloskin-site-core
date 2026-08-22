<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$gloskin_post        = $gloskin_context['post'];
$gloskin_title       = get_the_title( $gloskin_post );
$gloskin_image_id    = absint( $gloskin_context['image_id'] ?? 0 );
$gloskin_related     = isset( $gloskin_context['related_treatments'] ) && is_array( $gloskin_context['related_treatments'] ) ? $gloskin_context['related_treatments'] : array();
$gloskin_booking_url = ! empty( $gloskin_context['booking_target'] ) ? (string) $gloskin_context['booking_target'] : home_url( '/contact/' );
?>
<section class="gloskin-ui1-detail-hero gloskin-treatment-single__hero" data-gloskin-section="treatment-hero">
	<div class="gloskin-ui1-container gloskin-ui1-detail-hero__grid">
		<div class="gloskin-ui1-detail-copy">
			<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Perawatan', 'gloskin-site-core' ); ?></p>
			<h1><?php echo esc_html( $gloskin_title ); ?></h1>
		</div>
		<div class="gloskin-treatment-single__hero-media">
			<?php if ( $gloskin_image_id ) : ?>
				<?php echo wp_get_attachment_image( $gloskin_image_id, 'large', false, array( 'class' => 'gloskin-treatment-single__hero-image', 'fetchpriority' => 'high', 'decoding' => 'async' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- canonical WordPress attachment markup. ?>
			<?php else : ?>
				<?php gloskin_ui1_render_editorial_media( 'treatment', (string) $gloskin_post->post_name, 'gloskin-treatment-single__hero-image', true ); ?>
			<?php endif; ?>
		</div>
	</div>
</section>

<section class="gloskin-ui1-section gloskin-treatment-single__consideration" data-gloskin-section="treatment-consideration">
	<div class="gloskin-ui1-container gloskin-ui1-editorial-split">
		<div class="gloskin-ui1-editorial-split__copy">
			<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Untuk dipertimbangkan', 'gloskin-site-core' ); ?></p>
			<h2><?php echo esc_html__( 'Gunakan informasi yang tersedia sebagai bahan sebelum berbicara dengan klinik.', 'gloskin-site-core' ); ?></h2>
			<p><?php echo esc_html__( 'Catat pertanyaan yang ingin Anda bahas dan gunakan kanal konsultasi Gloskin untuk mendapatkan informasi lebih lanjut sesuai kebutuhan Anda.', 'gloskin-site-core' ); ?></p>
			<a class="gloskin-ui1-button gloskin-ui1-button--primary" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php echo esc_html__( 'Buka Kontak', 'gloskin-site-core' ); ?></a>
		</div>
		<div class="gloskin-treatment-single__consideration-media">
			<?php gloskin_ui1_render_editorial_media( 'treatment', 'treatment_clinical', 'gloskin-treatment-single__consideration-image' ); ?>
		</div>
	</div>
</section>

<?php if ( $gloskin_related ) : ?>
<section class="gloskin-ui1-section gloskin-treatment-single__related" data-gloskin-section="treatment-related">
	<div class="gloskin-ui1-container">
		<div class="gloskin-ui1-section-heading">
			<h2><?php echo esc_html__( 'INFORMASI PERAWATAN LAIN', 'gloskin-site-core' ); ?></h2>
			<p><?php echo esc_html__( 'Buka halaman lain bila Anda masih menimbang informasi yang tersedia.', 'gloskin-site-core' ); ?></p>
		</div>
		<div class="gloskin-ui1-grid gloskin-ui1-grid--three gloskin-treatment-single__related-grid">
			<?php foreach ( $gloskin_related as $gloskin_related_treatment ) :
				$gloskin_related_title = trim( (string) ( $gloskin_related_treatment['title'] ?? '' ) );
				$gloskin_related_url   = trim( (string) ( $gloskin_related_treatment['url'] ?? '' ) );
				$gloskin_related_image = absint( $gloskin_related_treatment['image_id'] ?? 0 );
			?>
				<article class="gloskin-treatment-single__related-card">
					<?php if ( '' !== $gloskin_related_url ) : ?><a href="<?php echo esc_url( $gloskin_related_url ); ?>" class="gloskin-treatment-single__related-media" tabindex="-1" aria-hidden="true"><?php endif; ?>
						<?php if ( $gloskin_related_image ) : ?>
							<?php echo wp_get_attachment_image( $gloskin_related_image, 'medium_large', false, array( 'class' => 'gloskin-treatment-single__related-image', 'loading' => 'lazy', 'decoding' => 'async', 'alt' => $gloskin_related_title ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- canonical WordPress attachment markup. ?>
						<?php else : ?>
							<?php gloskin_ui1_render_editorial_media( 'treatment', $gloskin_related_title, 'gloskin-treatment-single__related-image' ); ?>
						<?php endif; ?>
					<?php if ( '' !== $gloskin_related_url ) : ?></a><?php endif; ?>
					<div class="gloskin-treatment-single__related-body">
						<h3><?php echo esc_html( $gloskin_related_title ); ?></h3>
						<?php if ( '' !== $gloskin_related_url ) : ?><a class="gloskin-ui1-text-link" href="<?php echo esc_url( $gloskin_related_url ); ?>"><?php echo esc_html__( 'Lihat Detail', 'gloskin-site-core' ); ?><?php echo gloskin_ui1_arrow_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- canonical static SVG. ?></a><?php endif; ?>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<section class="gloskin-ui1-section gloskin-treatment-single__transition" data-gloskin-section="treatment-transition">
	<div class="gloskin-ui1-container gloskin-ui1-closing-cta">
		<div class="gloskin-ui1-closing-cta__copy">
			<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Langkah berikutnya', 'gloskin-site-core' ); ?></p>
			<h2><?php echo esc_html__( 'Bicarakan pertanyaan yang belum terjawab melalui kanal Gloskin.', 'gloskin-site-core' ); ?></h2>
			<p><?php echo esc_html__( 'Gunakan jalur konsultasi yang tersedia untuk perawatan ini, atau lanjutkan ke halaman kontak.', 'gloskin-site-core' ); ?></p>
		</div>
		<div class="gloskin-ui1-closing-cta__actions">
			<a class="gloskin-ui1-text-link" href="<?php echo esc_url( $gloskin_booking_url ); ?>"><?php echo esc_html__( 'Lanjutkan Konsultasi', 'gloskin-site-core' ); ?><?php echo gloskin_ui1_arrow_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- canonical static SVG. ?></a>
		</div>
	</div>
</section>
