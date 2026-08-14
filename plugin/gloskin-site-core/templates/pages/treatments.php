<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
gloskin_ui1_render_hero( $gloskin_context['hero'] );

/* The finder is a read-only presentation over canonical path/concern terms
 * and SSR Woo Treatment Product cards. Private questions remain admin data
 * and are intentionally absent from this public payload. */
$gloskin_consultation = $gloskin_context['consultation'];
if ( ! empty( $gloskin_consultation['paths'] ) ) :
	?>
	<section class="gloskin-ui1-section" data-gloskin-section="treatments-consultation">
		<div class="gloskin-ui1-container">
			<?php gloskin_ui1_render_section_heading( __( 'Temukan Perawatan yang Tepat', 'gloskin-site-core' ), __( 'Pilih fokus dan keluhan yang ingin Anda eksplorasi sebelum melanjutkan ke detail perawatan.', 'gloskin-site-core' ) ); ?>
			<div class="gloskin-ui1-consultation" data-gloskin-consultation data-gloskin-consultation-data="<?php echo esc_attr( wp_json_encode( array( 'paths' => $gloskin_consultation['paths'] ) ) ); ?>">
				<div class="gloskin-ui1-consultation__panel">
					<h3 class="gloskin-ui1-consultation__prompt"><?php echo esc_html__( 'Pilih fokus utama Anda', 'gloskin-site-core' ); ?></h3>
					<div class="gloskin-ui1-consultation__paths" data-gloskin-consultation-paths role="group" aria-label="<?php echo esc_attr__( 'Pilih fokus perawatan', 'gloskin-site-core' ); ?>">
						<?php foreach ( $gloskin_consultation['paths'] as $gloskin_path ) : ?>
							<button type="button" class="gloskin-ui1-consultation__path" data-gloskin-consultation-path="<?php echo esc_attr( (string) $gloskin_path['id'] ); ?>" aria-pressed="false">
								<span class="gloskin-ui1-consultation__path-media">
									<?php if ( $gloskin_path['image_id'] ) : ?>
										<?php echo wp_get_attachment_image( $gloskin_path['image_id'], 'medium', false, array( 'class' => 'gloskin-ui1-consultation__path-image', 'loading' => 'lazy' ) ); ?>
									<?php else : ?>
										<?php gloskin_ui1_render_editorial_media( 'treatment', $gloskin_path['label'], 'gloskin-ui1-consultation__path-image gloskin-ui1-consultation__path-image--decorative' ); ?>
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
						<button type="button" class="gloskin-ui1-button gloskin-ui1-button--primary gloskin-ui1-consultation__submit" data-gloskin-consultation-submit disabled><?php echo esc_html__( 'Cari Perawatan yang Tepat', 'gloskin-site-core' ); ?></button>
					</div>
					<p class="gloskin-ui1-consultation__disclaimer"><?php echo esc_html( $gloskin_consultation['disclaimer'] ); ?></p>
				</div>

				<div class="gloskin-ui1-consultation__results" data-gloskin-consultation-results hidden aria-live="polite">
					<h3 class="gloskin-ui1-consultation__results-heading"><?php echo esc_html__( 'Rekomendasi Perawatan', 'gloskin-site-core' ); ?></h3>
					<div class="gloskin-ui1-consultation__results-grid" data-gloskin-consultation-results-grid>
						<?php foreach ( $gloskin_consultation['products'] as $gloskin_treatment_product ) : ?>
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

<section class="gloskin-ui1-section gloskin-ui1-section--cta" data-gloskin-section="treatments-closing"><div class="gloskin-ui1-container"><?php gloskin_ui1_render_closing_cta( __( 'Konsultasi', 'gloskin-site-core' ), __( 'Informasi di situs membantu menyiapkan pertanyaan sebelum konsultasi.', 'gloskin-site-core' ), __( 'Pilih klinik atau gunakan halaman kontak untuk melanjutkan melalui kanal yang tersedia.', 'gloskin-site-core' ), __( 'Pilih Klinik', 'gloskin-site-core' ), home_url( '/clinics/' ), __( 'Hubungi Gloskin', 'gloskin-site-core' ), home_url( '/contact/' ) ); ?></div></section>
