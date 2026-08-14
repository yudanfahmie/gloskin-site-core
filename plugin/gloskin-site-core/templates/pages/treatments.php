<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
gloskin_ui1_render_hero( $gloskin_context['hero'] );
?>
<section class="gloskin-ui1-section" data-gloskin-section="treatments-orientation"><div class="gloskin-ui1-container">
	<?php if ( gloskin_ui1_has_content( $gloskin_context['page'] ) ) : ?><div class="gloskin-ui1-container--narrow gloskin-ui1-section__intro"><?php gloskin_ui1_render_page_content( $gloskin_context['page'] ); ?></div><?php endif; ?>
	<?php gloskin_ui1_render_editorial_split( __( 'Sebelum memilih', 'gloskin-site-core' ), __( 'Baca konteksnya, lalu tentukan apa yang ingin dikonsultasikan.', 'gloskin-site-core' ), __( 'Gunakan informasi perawatan sebagai awal untuk menyiapkan pertanyaan. Kebutuhan personal dapat dibicarakan lebih lanjut melalui kanal konsultasi Gloskin.', 'gloskin-site-core' ), __( 'Buka Kontak', 'gloskin-site-core' ), home_url( '/contact/' ), 'treatment', true ); ?>
</div></section>
<?php
/* Consultation discovery (docs/task-treatment-consultation-commerce-
   discovery.md section 6): additive, sits above the existing eight
   informational gloskin_treatment records below, which are never
   replaced/reduced/repurposed. Fails gracefully (empty 'paths') when
   fewer than 4 valid paths or fewer than 13 published questions exist --
   the rest of this page renders exactly as before either way. */
$gloskin_consultation = $gloskin_context['consultation'];
if ( ! empty( $gloskin_consultation['paths'] ) ) :
	?>
	<section class="gloskin-ui1-section" data-gloskin-section="treatments-consultation">
		<div class="gloskin-ui1-container">
			<?php gloskin_ui1_render_section_heading( __( 'Mulai dari Konsultasi', 'gloskin-site-core' ), __( 'Pilih fokus utama Anda, jawab beberapa pertanyaan singkat, dan lihat rekomendasi perawatan yang relevan.', 'gloskin-site-core' ) ); ?>
			<div class="gloskin-ui1-consultation" data-gloskin-consultation data-gloskin-consultation-data="<?php echo esc_attr( wp_json_encode( array( 'paths' => $gloskin_consultation['paths'], 'questions' => $gloskin_consultation['questions'] ) ) ); ?>">
				<div class="gloskin-ui1-consultation__paths" data-gloskin-consultation-paths role="group" aria-label="<?php echo esc_attr__( 'Pilih jalur konsultasi', 'gloskin-site-core' ); ?>">
					<?php foreach ( $gloskin_consultation['paths'] as $gloskin_path ) : ?>
						<button type="button" class="gloskin-ui1-consultation__path" data-gloskin-consultation-path="<?php echo esc_attr( (string) $gloskin_path['id'] ); ?>">
							<span class="gloskin-ui1-consultation__path-media">
								<?php if ( $gloskin_path['image_id'] ) : ?>
									<?php echo wp_get_attachment_image( $gloskin_path['image_id'], 'thumbnail', false, array( 'class' => 'gloskin-ui1-consultation__path-image', 'loading' => 'lazy' ) ); ?>
								<?php else : ?>
									<span class="gloskin-ui1-consultation__path-initial" aria-hidden="true"><?php echo esc_html( mb_substr( $gloskin_path['label'], 0, 1 ) ); ?></span>
								<?php endif; ?>
							</span>
							<span class="gloskin-ui1-consultation__path-label"><?php echo esc_html( $gloskin_path['label'] ); ?></span>
						</button>
					<?php endforeach; ?>
				</div>
				<div class="gloskin-ui1-consultation__questionnaire" data-gloskin-consultation-questionnaire hidden></div>
				<p class="gloskin-ui1-consultation__disclaimer"><?php echo esc_html( $gloskin_consultation['disclaimer'] ); ?></p>
				<div class="gloskin-ui1-consultation__results" data-gloskin-consultation-results hidden aria-live="polite">
					<h3 class="gloskin-ui1-consultation__results-heading"><?php echo esc_html__( 'Rekomendasi Perawatan', 'gloskin-site-core' ); ?></h3>
					<div class="gloskin-ui1-grid gloskin-ui1-grid--cards" data-gloskin-consultation-results-grid>
						<?php foreach ( $gloskin_consultation['products'] as $gloskin_treatment_product ) : ?>
							<div class="gloskin-ui1-consultation__result" data-gloskin-consultation-result data-gloskin-concern-ids="<?php echo esc_attr( implode( ',', $gloskin_treatment_product['concern_ids'] ) ); ?>" hidden>
								<?php gloskin_ui1_render_product_card( $gloskin_treatment_product ); ?>
							</div>
						<?php endforeach; ?>
					</div>
					<p class="gloskin-ui1-consultation__empty" data-gloskin-consultation-empty hidden><?php echo esc_html__( 'Belum ada produk yang cocok dengan jawaban Anda. Hubungi kami untuk konsultasi lebih lanjut.', 'gloskin-site-core' ); ?></p>
				</div>
			</div>
		</div>
	</section>
<?php endif; ?>
<?php if ( ! empty( $gloskin_context['featured_treatment'] ) ) : ?><section class="gloskin-ui1-section gloskin-ui1-section--soft" data-gloskin-section="treatments-featured"><div class="gloskin-ui1-container"><div class="gloskin-ui1-featured-entry"><div><p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Sorotan', 'gloskin-site-core' ); ?></p><h2><?php echo esc_html__( 'Perawatan untuk dilihat lebih dekat', 'gloskin-site-core' ); ?></h2><p><?php echo esc_html__( 'Buka halaman perawatan untuk membaca informasi yang tersedia.', 'gloskin-site-core' ); ?></p></div><div><?php gloskin_ui1_render_card( $gloskin_context['featured_treatment'], 'treatment' ); ?></div></div></div></section><?php endif; ?>
<?php if ( ! empty( $gloskin_context['treatments'] ) || empty( $gloskin_context['featured_treatment'] ) ) : ?>
<section class="gloskin-ui1-section" data-gloskin-section="treatments-discovery"><div class="gloskin-ui1-container"><?php gloskin_ui1_render_section_heading( __( 'Informasi Perawatan', 'gloskin-site-core' ), __( 'Buka setiap halaman untuk membaca informasi yang tersedia.', 'gloskin-site-core' ) ); ?><?php if ( $gloskin_context['treatments'] ) : ?><?php gloskin_ui1_render_card_grid( $gloskin_context['treatments'], 'treatment' ); ?><?php else : ?><?php gloskin_ui1_render_empty_state( 'treatment', __( 'Belum ada perawatan yang tersedia', 'gloskin-site-core' ), __( 'Informasi perawatan akan tampil di sini setelah dipublikasikan.', 'gloskin-site-core' ), __( 'Hubungi Gloskin', 'gloskin-site-core' ), home_url( '/contact/' ) ); ?><?php endif; ?></div></section>
<?php endif; ?>
<section class="gloskin-ui1-section gloskin-ui1-section--soft" data-gloskin-section="treatments-pathways"><div class="gloskin-ui1-container"><?php gloskin_ui1_render_section_heading( __( 'Lanjutkan dari konteks yang Anda perlukan', 'gloskin-site-core' ), __( 'Padukan informasi perawatan dengan lokasi klinik atau profil dokter yang tersedia.', 'gloskin-site-core' ) ); gloskin_ui1_render_pathway_grid( array_filter( array( array( 'eyebrow' => __( 'Lokasi', 'gloskin-site-core' ), 'title' => __( 'Lihat jaringan klinik', 'gloskin-site-core' ), 'copy' => __( 'Pilih cabang dan lihat kanal kontak yang tersedia.', 'gloskin-site-core' ), 'label' => __( 'Buka Klinik', 'gloskin-site-core' ), 'url' => home_url( '/clinics/' ) ), ! empty( $gloskin_context['doctors'] ) ? array( 'eyebrow' => __( 'Profil', 'gloskin-site-core' ), 'title' => __( 'Kenali dokter Gloskin', 'gloskin-site-core' ), 'copy' => __( 'Baca profil profesional dan lokasi praktik yang tersedia.', 'gloskin-site-core' ), 'label' => __( 'Buka Dokter', 'gloskin-site-core' ), 'url' => home_url( '/doctors/' ) ) : null, array( 'eyebrow' => __( 'Konsultasi', 'gloskin-site-core' ), 'title' => __( 'Sampaikan pertanyaan Anda', 'gloskin-site-core' ), 'copy' => __( 'Gunakan halaman kontak untuk menanyakan langkah berikutnya.', 'gloskin-site-core' ), 'label' => __( 'Buka Kontak', 'gloskin-site-core' ), 'url' => home_url( '/contact/' ) ) ) ) ); ?></div></section>
<section class="gloskin-ui1-section gloskin-ui1-section--cta" data-gloskin-section="treatments-closing"><div class="gloskin-ui1-container"><?php gloskin_ui1_render_closing_cta( __( 'Konsultasi', 'gloskin-site-core' ), __( 'Informasi di situs membantu menyiapkan pertanyaan sebelum konsultasi.', 'gloskin-site-core' ), __( 'Pilih klinik atau gunakan halaman kontak untuk melanjutkan melalui kanal yang tersedia.', 'gloskin-site-core' ), __( 'Pilih Klinik', 'gloskin-site-core' ), home_url( '/clinics/' ), __( 'Hubungi Gloskin', 'gloskin-site-core' ), home_url( '/contact/' ) ); ?></div></section>
