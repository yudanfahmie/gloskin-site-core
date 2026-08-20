<?php
/**
 * Demo content reset tool.
 *
 * One-time utility for staging/presentation environments: hard-deletes all
 * existing demo posts (promo, testimonial, achievement) and replaces them with
 * realistic-looking content in published+active state, ready for front-end
 * presentation without further manual editing.
 *
 * This is intentionally separate from the Finalisasi Prototype migration.
 * The migration's demo_seed step follows engineering-fixture policy (draft,
 * inactive) and exists to validate layout structures. This tool creates
 * presentation-quality records for demos and client walkthroughs.
 *
 * Note: if Finalisasi Prototype is re-run after this tool, its quarantine
 * step will return records to draft. That is expected — this tool is for
 * pre-demo setup, not for production population.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Gloskin_Site_Core_Demo_Content_Reset {

	const SLUG       = 'gloskin-demo-reset';
	const POST_ACTION = 'gloskin_demo_reset';
	const NONCE_KEY  = 'gloskin_demo_reset_nonce';
	const CAPABILITY = 'manage_options';

	/**
	 * Meta key shared with the migration — used to identify and clean up
	 * all demo-seeded posts regardless of which tool created them.
	 */
	const DEMO_META      = '_gloskin_demo_identity';
	const COMPLETED_OPT  = 'gloskin_demo_reset_completed';

	/** @return void */
	public function register() {
		add_action( 'admin_menu',                      array( $this, 'register_page' ) );
		add_action( 'admin_post_' . self::POST_ACTION, array( $this, 'handle_reset' ) );
	}

	/** @return void */
	public function register_page() {
		$completed = (bool) get_option( self::COMPLETED_OPT );
		/*
		 * Once the reset has run, detach from the visible menu (parent_slug = null)
		 * so "Reset Demo" disappears and admins are not confused about re-running it.
		 * The page remains accessible directly via URL for any post-reset review.
		 */
		add_submenu_page(
			$completed ? null : Gloskin_Site_Core_Content_Service::ADMIN_MENU_SLUG,
			'Reset Konten Demo',
			'Reset Demo',
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render_page' )
		);
	}

	/** @return void */
	public function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		$completed = (bool) get_option( self::COMPLETED_OPT );
		$counts    = $this->current_demo_counts();
		$done      = isset( $_GET['reset_done'] ) ? absint( $_GET['reset_done'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap">
			<h1>Reset Konten Demo Gloskin</h1>

			<?php if ( $done ) : ?>
			<div class="notice notice-success inline"><p>
				<strong>Selesai.</strong> <?php echo $done; ?> konten demo realistis berhasil dibuat dan dipublikasikan.
				Menu <em>Reset Demo</em> telah dihapus dari navigasi — konten sudah siap.
			</p></div>
			<?php elseif ( $completed ) : ?>
			<div class="notice notice-info inline"><p>
				Reset telah dijalankan sebelumnya. Gunakan tombol di bawah hanya jika ingin menghapus dan membuat ulang konten demo.
			</p></div>
			<?php endif; ?>

			<p>Tool ini <strong>menghapus permanen</strong> semua konten demo lama dan membuat ulang data realistis dalam status <strong>published + active</strong> siap presentasi.</p>
			<p>Konten yang dibuat: 3 promo, 3 testimonial, 3 pencapaian.</p>

			<table class="widefat" style="max-width:360px;margin:16px 0">
				<thead><tr><th>Tipe</th><th>Demo post saat ini</th></tr></thead>
				<tbody>
				<?php foreach ( $counts as $label => $n ) : ?>
					<tr><td><?php echo esc_html( $label ); ?></td><td><?php echo absint( $n ); ?></td></tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::POST_ACTION ); ?>">
				<?php wp_nonce_field( self::POST_ACTION, self::NONCE_KEY ); ?>
				<button
					type="submit"
					class="button button-primary"
					onclick="return confirm('Ini akan menghapus permanen semua konten demo lama dan membuat ulang yang baru. Lanjutkan?')"
				>Reset &amp; Isi Ulang Konten Demo</button>
			</form>
		</div>
		<?php
	}

	/** @return void */
	public function handle_reset() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( 'Unauthorized', 403 );
		}
		check_admin_referer( self::POST_ACTION, self::NONCE_KEY );

		$this->delete_all_demo_posts();
		$count = $this->seed_realistic_content();

		/* Mark as completed — hides the menu item on next page load. */
		update_option( self::COMPLETED_OPT, time(), false );

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&reset_done=' . $count ) );
		exit;
	}

	/** @return void */
	private function delete_all_demo_posts() {
		$post_types = array(
			Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE,
			Gloskin_Site_Core_Content_Service::TESTIMONIAL_POST_TYPE,
			Gloskin_Site_Core_Content_Service::ACHIEVEMENT_POST_TYPE,
		);
		foreach ( $post_types as $post_type ) {
			$ids = get_posts( array(
				'post_type'      => $post_type,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( array( 'key' => self::DEMO_META, 'compare' => 'EXISTS' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			) );
			foreach ( $ids as $id ) {
				wp_delete_post( absint( $id ), true /* force delete, bypass trash */ );
			}
		}
	}

	/** @return int Number of posts created. */
	private function seed_realistic_content() {
		$count = 0;
		foreach ( $this->realistic_seeds() as $seed ) {
			$id = wp_insert_post( array(
				'post_type'    => $seed['post_type'],
				'post_status'  => 'publish',
				'post_title'   => $seed['title'],
				'post_excerpt' => $seed['excerpt'],
			) );
			if ( is_wp_error( $id ) || ! $id ) {
				continue;
			}
			$id = absint( $id );
			update_post_meta( $id, self::DEMO_META, $seed['identity'] );
			foreach ( $seed['meta'] as $key => $value ) {
				update_post_meta( $id, $key, $value );
			}
			$count++;
		}
		return $count;
	}

	/** @return array<string,int> */
	private function current_demo_counts() {
		$map = array(
			'Promo'       => Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE,
			'Testimonial' => Gloskin_Site_Core_Content_Service::TESTIMONIAL_POST_TYPE,
			'Pencapaian'  => Gloskin_Site_Core_Content_Service::ACHIEVEMENT_POST_TYPE,
		);
		$counts = array();
		foreach ( $map as $label => $post_type ) {
			$ids = get_posts( array(
				'post_type'      => $post_type,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( array( 'key' => self::DEMO_META, 'compare' => 'EXISTS' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			) );
			$counts[ $label ] = count( $ids );
		}
		return $counts;
	}

	/**
	 * Realistic demo content definitions.
	 *
	 * Content is published + active so it renders immediately on the front end
	 * for presentation purposes. Identity keys use the `-r2` suffix to distinguish
	 * them from migration engineering fixtures (which use the base keys).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function realistic_seeds() {
		$promo  = Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE;
		$testi  = Gloskin_Site_Core_Content_Service::TESTIMONIAL_POST_TYPE;
		$achiev = Gloskin_Site_Core_Content_Service::ACHIEVEMENT_POST_TYPE;

		return array(

			/* ── Promos ── */

			array(
				'post_type' => $promo,
				'identity'  => 'gloskin-demo-r2-promo-brightening',
				'title'     => 'Paket Brightening Intensif',
				'excerpt'   => 'Kombinasi eksfoliasi enzimatik, infus vitamin C, dan LED photo-therapy untuk kulit cerah merata dalam 4 sesi.',
				'meta'      => array(
					'gloskin_promo_eyebrow'   => 'Promo Bulan Ini',
					'gloskin_promo_summary'   => 'Kombinasi eksfoliasi enzimatik, infus vitamin C, dan LED photo-therapy untuk kulit cerah merata dalam 4 sesi.',
					'gloskin_promo_cta_label' => 'Cek Paket',
					'gloskin_promo_cta_url'   => '/treatments/',
					'gloskin_promo_active'    => '1',
					'gloskin_promo_order'     => '1',
				),
			),

			array(
				'post_type' => $promo,
				'identity'  => 'gloskin-demo-r2-promo-konsultasi-gratis',
				'title'     => 'Konsultasi Perdana Bebas Biaya',
				'excerpt'   => 'Periksa kondisi kulit bersama dokter spesialis dan dapatkan rekomendasi perawatan personal tanpa biaya untuk kunjungan pertama.',
				'meta'      => array(
					'gloskin_promo_eyebrow'   => 'Penawaran Pasien Baru',
					'gloskin_promo_summary'   => 'Periksa kondisi kulit bersama dokter spesialis dan dapatkan rekomendasi perawatan personal tanpa biaya untuk kunjungan pertama.',
					'gloskin_promo_cta_label' => 'Daftar Sekarang',
					'gloskin_promo_cta_url'   => '/treatments/',
					'gloskin_promo_active'    => '1',
					'gloskin_promo_order'     => '2',
				),
			),

			array(
				'post_type' => $promo,
				'identity'  => 'gloskin-demo-r2-promo-acne-program',
				'title'     => 'Acne Clear Program — 6 Minggu',
				'excerpt'   => 'Program terintegrasi: chemical peel, microneedling, dan homecare regimen selama 6 minggu untuk kulit bebas jerawat aktif.',
				'meta'      => array(
					'gloskin_promo_eyebrow'   => 'Program Unggulan',
					'gloskin_promo_summary'   => 'Program terintegrasi: chemical peel, microneedling, dan homecare regimen selama 6 minggu untuk kulit bebas jerawat aktif.',
					'gloskin_promo_cta_label' => 'Lihat Program',
					'gloskin_promo_cta_url'   => '/treatments/',
					'gloskin_promo_active'    => '1',
					'gloskin_promo_order'     => '3',
				),
			),

			/* ── Testimonials ── */

			array(
				'post_type' => $testi,
				'identity'  => 'gloskin-demo-r2-testi-anisa',
				'title'     => 'Jerawat aktif tuntas dalam 2 bulan',
				'excerpt'   => 'Sudah 3 tahun saya coba berbagai produk tapi jerawat selalu kembali. Di Gloskin baru 2 bulan dan hasilnya benar-benar berbeda — kulit saya bersih dan jauh lebih sehat.',
				'meta'      => array(
					'gloskin_testimonial_attribution' => 'Anisa R., 26 tahun',
					'gloskin_testimonial_subtitle'    => 'Perawatan Acne & Scarring',
					'gloskin_testimonial_active'      => '1',
					'gloskin_testimonial_order'       => '1',
				),
			),

			array(
				'post_type' => $testi,
				'identity'  => 'gloskin-demo-r2-testi-dewi',
				'title'     => 'Flek berkurang, kulit terlihat lebih muda',
				'excerpt'   => 'Kulit saya sensitif, tapi dokternya sabar menjelaskan setiap langkah. Flek di pipi sudah jauh berkurang dan banyak teman yang tanya saya pakai apa.',
				'meta'      => array(
					'gloskin_testimonial_attribution' => 'Dewi K., 34 tahun',
					'gloskin_testimonial_subtitle'    => 'Perawatan Brightening',
					'gloskin_testimonial_active'      => '1',
					'gloskin_testimonial_order'       => '2',
				),
			),

			array(
				'post_type' => $testi,
				'identity'  => 'gloskin-demo-r2-testi-bima',
				'title'     => 'Klinik yang jujur dan tidak memaksa',
				'excerpt'   => 'Yang saya suka dari Gloskin adalah dokternya tidak langsung menawarkan treatment mahal. Saya dikasih tahu kondisi kulit dulu, baru diberi rekomendasi yang sesuai kebutuhan.',
				'meta'      => array(
					'gloskin_testimonial_attribution' => 'Bima S., 29 tahun',
					'gloskin_testimonial_subtitle'    => 'Konsultasi Kulit',
					'gloskin_testimonial_active'      => '1',
					'gloskin_testimonial_order'       => '3',
				),
			),

			/* ── Achievements ── */

			array(
				'post_type' => $achiev,
				'identity'  => 'gloskin-demo-r2-achiev-hba-2024',
				'title'     => 'Klinik Kulit Terbaik Bandung 2024',
				'excerpt'   => 'Penghargaan atas komitmen pelayanan dermatologi dan estetika berkualitas tinggi di wilayah Bandung.',
				'meta'      => array(
					'gloskin_achievement_issuer'          => 'Health & Beauty Awards Indonesia',
					'gloskin_achievement_year'            => '2024',
					'gloskin_achievement_feature_on_home' => '1',
					'gloskin_achievement_active'          => '1',
					'gloskin_achievement_order'           => '1',
				),
			),

			array(
				'post_type' => $achiev,
				'identity'  => 'gloskin-demo-r2-achiev-iama-2023',
				'title'     => 'Top Aesthetic Clinic West Java 2023',
				'excerpt'   => 'Diakui sebagai klinik estetika terkemuka di Jawa Barat oleh Indonesian Aesthetic Medicine Association.',
				'meta'      => array(
					'gloskin_achievement_issuer'          => 'Indonesian Aesthetic Medicine Association',
					'gloskin_achievement_year'            => '2023',
					'gloskin_achievement_feature_on_home' => '1',
					'gloskin_achievement_active'          => '1',
					'gloskin_achievement_order'           => '2',
				),
			),

			array(
				'post_type' => $achiev,
				'identity'  => 'gloskin-demo-r2-achiev-kemenkes-2023',
				'title'     => 'Sertifikasi Standar Klinis Premium',
				'excerpt'   => 'Sertifikasi standar pelayanan medis tingkat premium dari Kementerian Kesehatan Republik Indonesia.',
				'meta'      => array(
					'gloskin_achievement_issuer'          => 'Kementerian Kesehatan Republik Indonesia',
					'gloskin_achievement_year'            => '2023',
					'gloskin_achievement_feature_on_home' => '1',
					'gloskin_achievement_active'          => '1',
					'gloskin_achievement_order'           => '3',
				),
			),

		);
	}
}
