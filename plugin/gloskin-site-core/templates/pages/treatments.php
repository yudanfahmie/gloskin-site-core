<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$gloskin_hero = isset( $gloskin_context['hero'] ) && is_array( $gloskin_context['hero'] ) ? $gloskin_context['hero'] : array();
$gloskin_hero_heading = trim( (string) ( $gloskin_hero['heading'] ?? __( 'Perawatan', 'gloskin-site-core' ) ) );
$gloskin_hero_copy = trim( (string) ( $gloskin_hero['copy'] ?? '' ) );
$gloskin_hero_media_id = absint( $gloskin_hero['media_id'] ?? 0 );
// Stable band copy pool: keyed by path term ID so copy survives taxonomy reordering.
// First-seen order assigns each path its string from the pool; wraps if more than 4 paths.
$gloskin_band_copy_pool = array(
	__( 'Temukan pilihan perawatan yang berfokus pada jerawat aktif dan bekas jerawat untuk membantu menyiapkan diskusi konsultasi Anda.', 'gloskin-site-core' ),
	__( 'Pelajari pilihan perawatan untuk flek dan pigmentasi agar kebutuhan warna kulit yang tidak merata dapat dibahas lebih terarah saat konsultasi.', 'gloskin-site-core' ),
	__( 'Jelajahi pilihan perawatan untuk tanda penuaan dan kontur wajah sebelum menentukan pendekatan yang sesuai bersama dokter Gloskin.', 'gloskin-site-core' ),
	__( 'Kenali pilihan perawatan yang berfokus pada kualitas kulit dan skin barrier untuk membantu menjaga kulit tampak sehat dan terawat.', 'gloskin-site-core' ),
);
$gloskin_band_copy  = array(); // keyed by path term ID.
$gloskin_band_pool_n = count( $gloskin_band_copy_pool );
foreach ( array_values( $gloskin_context['paths'] ?? array() ) as $i => $gloskin_p ) {
	$gloskin_pid = absint( $gloskin_p['id'] ?? 0 );
	if ( $gloskin_pid ) {
		$gloskin_band_copy[ $gloskin_pid ] = $gloskin_band_copy_pool[ $i % $gloskin_band_pool_n ];
	}
}
?>
<div class="gloskin-treatments-page">
	<section class="gloskin-treatments-hero" data-gloskin-section="treatments-hero">
		<div class="gloskin-ui1-container gloskin-treatments-hero__grid">
			<div class="gloskin-treatments-hero__content">
				<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Treatment', 'gloskin-site-core' ); ?></p>
				<h1><?php echo esc_html( $gloskin_hero_heading ); ?></h1>
				<?php if ( '' !== $gloskin_hero_copy ) : ?><p class="gloskin-treatments-hero__copy"><?php echo esc_html( $gloskin_hero_copy ); ?></p><?php endif; ?>
			</div>
			<div class="gloskin-treatments-hero__media">
				<?php if ( $gloskin_hero_media_id ) : ?>
					<?php echo wp_get_attachment_image( $gloskin_hero_media_id, 'large', false, array( 'class' => 'gloskin-treatments-hero__image', 'fetchpriority' => 'high', 'decoding' => 'async' ) ); ?>
				<?php else : ?>
					<?php gloskin_ui1_render_editorial_media( 'treatment', 'treatment_discovery', 'gloskin-treatments-hero__image', true ); ?>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<?php if ( ! empty( $gloskin_context['paths'] ) ) : ?>
	<div class="gloskin-ui1-treatment-bands" data-gloskin-section="treatments-bands">
		<?php foreach ( array_values( $gloskin_context['paths'] ) as $gloskin_index => $gloskin_path ) :
			$gloskin_label = isset( $gloskin_path['label'] ) ? (string) $gloskin_path['label'] : '';
			$gloskin_image_id = isset( $gloskin_path['image_id'] ) ? absint( $gloskin_path['image_id'] ) : 0;
			$gloskin_path_id = isset( $gloskin_path['id'] ) ? absint( $gloskin_path['id'] ) : 0;
			$gloskin_reverse = 1 === ( $gloskin_index % 2 );
			$gloskin_copy = $gloskin_band_copy[ $gloskin_path_id ] ?? $gloskin_band_copy_pool[0];
			?>
			<article class="gloskin-ui1-treatment-band<?php echo $gloskin_reverse ? ' gloskin-ui1-treatment-band--reverse' : ''; ?>" data-gloskin-treatment-band="<?php echo esc_attr( (string) $gloskin_path_id ); ?>">
				<div class="gloskin-ui1-treatment-band__media">
					<?php if ( $gloskin_image_id ) : ?>
						<?php echo wp_get_attachment_image( $gloskin_image_id, 'large', false, array( 'loading' => 0 === $gloskin_index ? 'eager' : 'lazy', 'class' => 'gloskin-ui1-treatment-band__image' ) ); ?>
					<?php else : ?>
						<?php gloskin_ui1_render_editorial_media( 'treatment', $gloskin_label, 'gloskin-ui1-treatment-band__image', 0 === $gloskin_index ); ?>
					<?php endif; ?>
				</div>
				<div class="gloskin-ui1-treatment-band__content">
					<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Perawatan', 'gloskin-site-core' ); ?></p>
					<h2 class="gloskin-ui1-treatment-band__title"><?php echo esc_html( $gloskin_label ); ?></h2>
					<p class="gloskin-ui1-treatment-band__copy"><?php echo esc_html( $gloskin_copy ); ?></p>
					<button type="button" class="gloskin-ui1-button gloskin-ui1-button--primary gloskin-ui1-treatment-band__button" data-gloskin-band-path="<?php echo esc_attr( (string) $gloskin_path_id ); ?>"><?php echo esc_html__( 'Jelajahi Solusi', 'gloskin-site-core' ); ?></button>
				</div>
			</article>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	<?php
	$gloskin_consultation = isset( $gloskin_context['consultation'] ) && is_array( $gloskin_context['consultation'] ) ? $gloskin_context['consultation'] : array();
	if ( ! empty( $gloskin_consultation['paths'] ) ) :
		$gloskin_finder_note = trim( (string) ( $gloskin_consultation['disclaimer'] ?? '' ) );
		if ( '' === $gloskin_finder_note ) {
			$gloskin_finder_note = __( 'Pilihan perawatan ini dapat Anda diskusikan lebih lanjut saat konsultasi.', 'gloskin-site-core' );
		}
	?>
	<section class="gloskin-treatments-finder" id="consultation" data-gloskin-section="treatments-consultation">
		<div class="gloskin-ui1-container">
			<div class="gloskin-ui1-consultation" data-gloskin-consultation data-gloskin-consultation-data="<?php echo esc_attr( wp_json_encode( array( 'paths' => $gloskin_consultation['paths'] ) ) ); ?>">
				<div class="gloskin-ui1-consultation__panel">
					<div class="gloskin-ui1-consultation__intro">
						<h2><?php echo esc_html__( 'Temukan Perawatan yang Tepat', 'gloskin-site-core' ); ?></h2>
						<p><?php echo esc_html__( 'Informasi seputar masalah dan prosedur Anda untuk memandu Anda menemukan solusi yang relevan.', 'gloskin-site-core' ); ?></p>
					</div>
					<h3 class="gloskin-ui1-consultation__prompt"><?php echo esc_html__( 'Pilih Fokus Utama Anda', 'gloskin-site-core' ); ?></h3>
					<div class="gloskin-ui1-consultation__paths" data-gloskin-consultation-paths role="group" aria-label="<?php echo esc_attr__( 'Pilih fokus perawatan', 'gloskin-site-core' ); ?>">
						<?php foreach ( $gloskin_consultation['paths'] as $gloskin_path ) : ?>
							<button type="button" class="gloskin-ui1-consultation__path" data-gloskin-consultation-path="<?php echo esc_attr( (string) $gloskin_path['id'] ); ?>" aria-pressed="false">
								<span class="gloskin-ui1-consultation__path-media">
									<?php if ( $gloskin_path['image_id'] ) : ?>
										<?php echo wp_get_attachment_image( $gloskin_path['image_id'], 'medium', false, array( 'class' => 'gloskin-ui1-consultation__path-image', 'loading' => 'lazy' ) ); ?>
									<?php else : ?>
										<?php gloskin_ui1_render_editorial_media( 'treatment', $gloskin_path['label'], 'gloskin-ui1-consultation__path-image' ); ?>
									<?php endif; ?>
								</span>
								<span class="gloskin-ui1-consultation__path-label"><?php echo esc_html( $gloskin_path['label'] ); ?></span>
							</button>
						<?php endforeach; ?>
					</div>

					<div class="gloskin-ui1-consultation__concerns" data-gloskin-consultation-concerns hidden>
						<h3 class="gloskin-ui1-consultation__concerns-heading"><?php echo esc_html__( 'Apa yang paling ingin Anda perbaiki?', 'gloskin-site-core' ); ?></h3>
						<p class="gloskin-ui1-consultation__helper"><span class="gloskin-ui1-consultation__helper-icon" aria-hidden="true">+</span><?php echo esc_html__( 'Anda dapat memilih lebih dari satu keluhan.', 'gloskin-site-core' ); ?></p>
						<?php foreach ( $gloskin_consultation['paths'] as $gloskin_path ) : ?>
							<fieldset class="gloskin-ui1-consultation__concern-group" data-gloskin-consultation-concern-group="<?php echo esc_attr( (string) $gloskin_path['id'] ); ?>" hidden>
								<legend class="screen-reader-text"><?php echo esc_html( $gloskin_path['label'] ); ?></legend>
								<div class="gloskin-ui1-consultation__chips">
									<?php foreach ( $gloskin_path['concerns'] as $gloskin_concern ) :
										$gloskin_concern_input_id = 'gloskin-consultation-' . absint( $gloskin_path['id'] ) . '-' . absint( $gloskin_concern['id'] );
										?>
										<label class="gloskin-ui1-consultation__chip" for="<?php echo esc_attr( $gloskin_concern_input_id ); ?>">
											<input id="<?php echo esc_attr( $gloskin_concern_input_id ); ?>" type="checkbox" value="<?php echo esc_attr( (string) $gloskin_concern['id'] ); ?>" data-gloskin-consultation-concern />
											<span><?php echo esc_html( $gloskin_concern['label'] ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
							</fieldset>
						<?php endforeach; ?>
					</div>

					<div class="gloskin-ui1-consultation__actions">
						<button type="button" class="gloskin-ui1-consultation__submit" data-gloskin-consultation-submit disabled><?php echo esc_html__( 'Cari Perawatan yang Tepat', 'gloskin-site-core' ); ?></button>
					</div>
					<p class="gloskin-ui1-consultation__disclaimer"><?php echo esc_html( $gloskin_finder_note ); ?></p>
				</div>

				<div class="gloskin-ui1-consultation__results" data-gloskin-consultation-results hidden aria-live="polite">
					<h3 class="gloskin-ui1-consultation__results-heading"><?php echo esc_html__( 'Rekomendasi Perawatan', 'gloskin-site-core' ); ?></h3>
					<div class="gloskin-ui1-consultation__results-grid" data-gloskin-consultation-results-grid>
						<?php foreach ( (array) ( $gloskin_consultation['products'] ?? array() ) as $gloskin_treatment_product ) : ?>
							<div class="gloskin-ui1-consultation__result" data-gloskin-consultation-result data-gloskin-concern-ids="<?php echo esc_attr( implode( ',', $gloskin_treatment_product['concern_ids'] ) ); ?>" hidden>
								<?php gloskin_ui1_render_product_card( $gloskin_treatment_product, 'consultation' ); ?>
							</div>
					<?php endforeach; ?>
					</div>
					<p class="gloskin-ui1-consultation__empty" data-gloskin-consultation-empty hidden><?php echo esc_html__( 'Belum ada produk yang cocok dengan keluhan pilihan Anda. Hubungi kami untuk konsultasi lebih lanjut.', 'gloskin-site-core' ); ?></p>
				</div>
			</div>
		</div>
	</section>
	<?php endif; ?>

	<section class="gloskin-treatments-info" data-gloskin-section="treatments-information">
		<div class="gloskin-ui1-container gloskin-treatments-info__inner">
			<div class="gloskin-treatments-info__copy">
				<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Edukasi', 'gloskin-site-core' ); ?></p>
				<h2><?php echo esc_html__( 'Informasi di Situs Membantu Menyiapkan Pertanyaan Sebelum Konsultasi.', 'gloskin-site-core' ); ?></h2>
				<p><?php echo esc_html__( 'Gunakan informasi ini sebagai panduan awal, lalu diskusikan kebutuhan dan pilihan perawatan Anda secara langsung di klinik Gloskin.', 'gloskin-site-core' ); ?></p>
			</div>
			<a class="gloskin-treatments-info__link" href="<?php echo esc_url( home_url( '/insights/' ) ); ?>"><?php echo esc_html__( 'Insight Kami', 'gloskin-site-core' ); ?><span aria-hidden="true">→</span></a>
		</div>
	</section>
</div>