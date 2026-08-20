<?php
/**
 * Admin runner for the bounded 2026-08-19-final closure migration.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-gloskin-site-core-revision-20260819-final-migration.php';

final class Gloskin_Site_Core_Revision_20260819_Final_Migration_Admin {
	const CAPABILITY  = 'manage_options';
	const SLUG        = 'gloskin-revision-20260819-final-migration';
	const AJAX_ACTION = 'gloskin_site_core_revision_20260819_final_migration';
	const POST_ACTION = 'gloskin_site_core_revision_20260819_final_migration_fallback';
	const NONCE       = 'gloskin_site_core_revision_20260819_final_migration';

	/** @var Gloskin_Site_Core_Revision_20260819_Final_Migration */
	private $migration;

	/** @var Gloskin_Site_Core_Asset_Service */
	private $assets;

	/** @var string */
	private $hook = '';

	/** @param Gloskin_Site_Core_Asset_Service $assets @param string $plugin_file */
	public function __construct( $assets, $plugin_file ) {
		$this->assets    = $assets;
		$this->migration = new Gloskin_Site_Core_Revision_20260819_Final_Migration( $plugin_file );
	}

	/** @return void */
	public function register() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 14 );
		add_action( 'admin_notices', array( $this, 'render_pending_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 30 );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_advance' ) );
		add_action( 'admin_post_' . self::POST_ACTION, array( $this, 'handle_fallback' ) );
	}

	/** @return void */
	public function render_pending_notice() {
		if ( ! current_user_can( self::CAPABILITY ) || $this->migration->is_consumed() ) {
			return;
		}
		$url = admin_url( 'admin.php?page=' . self::SLUG );
		echo '<div class="notice notice-info"><p><strong>'
			. esc_html__( 'Finalisasi Prototype & Data siap dijalankan.', 'gloskin-site-core' )
			. '</strong> '
			. esc_html__( 'Satu klik menjalankan semua checkpoint: konten terkelola, demo data, foto dokter, dan verifikasi keamanan.', 'gloskin-site-core' )
			. ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Buka migrasi', 'gloskin-site-core' ) . '</a></p></div>';
	}

	/** @return void */
	public function register_menu() {
		if ( ! current_user_can( self::CAPABILITY ) || $this->migration->is_consumed() ) {
			return;
		}
		$hook = add_submenu_page(
			Gloskin_Site_Core_Content_Service::ADMIN_MENU_SLUG,
			__( 'Finalisasi Prototype & Data', 'gloskin-site-core' ),
			__( 'Finalisasi Prototype', 'gloskin-site-core' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render' )
		);
		if ( is_string( $hook ) ) {
			$this->hook = $hook;
		}
	}

	/** @param string $hook_suffix @return void */
	public function enqueue_assets( $hook_suffix ) {
		if ( '' === $this->hook || $hook_suffix !== $this->hook ) {
			return;
		}
		$this->assets->enqueue_admin_final_migration( self::AJAX_ACTION, wp_create_nonce( self::NONCE ) );
	}

	/** @return void */
	public function render() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Anda tidak memiliki izin untuk menjalankan migrasi ini.', 'gloskin-site-core' ) );
		}

		$state       = $this->migration->get_state();
		$processed   = absint( $state['processed_steps'] );
		$total       = max( 1, absint( $state['total_steps'] ) );
		$state_error = (string) $state['last_error'];
		$query_code  = isset( $_GET['migration_error'] ) ? sanitize_key( wp_unslash( (string) $_GET['migration_error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$error_code  = '' !== $state_error ? $this->classify_error( $state_error ) : $query_code;
		$safe_error  = '' !== $error_code ? $this->safe_error_message( $error_code ) : '';
		$button      = 'failed' === $state['status'] || $processed > 0
			? __( 'Lanjutkan Finalisasi', 'gloskin-site-core' )
			: __( 'Jalankan Finalisasi', 'gloskin-site-core' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Finalisasi Prototype & Data', 'gloskin-site-core' ); ?></h1>
			<p><?php echo esc_html__( 'Migrasi penutup deterministik satu klik. Checkpoints: CPT terkelola, demo data, foto dokter, cleanup opsi usang, dan verifikasi thumbnail per-dokter.', 'gloskin-site-core' ); ?></p>
			<div id="gloskin-migration-app"
				data-gloskin-final-migration
				data-ajax="<?php echo esc_attr( admin_url( 'admin-ajax.php' ) ); ?>"
				data-action="<?php echo esc_attr( self::AJAX_ACTION ); ?>"
				data-nonce="<?php echo esc_attr( wp_create_nonce( self::NONCE ) ); ?>"
				data-processed="<?php echo esc_attr( (string) $processed ); ?>"
				data-total="<?php echo esc_attr( (string) $total ); ?>"
				data-status="<?php echo esc_attr( (string) $state['status'] ); ?>">
				<progress class="gloskin-admin-migration__progress" data-gloskin-migration-progressbar value="<?php echo esc_attr( (string) $processed ); ?>" max="<?php echo esc_attr( (string) $total ); ?>"></progress>
				<p class="gloskin-admin-migration__step" data-gloskin-migration-step><?php echo esc_html( $state['current_step'] ); ?></p>
				<?php if ( '' !== $safe_error ) : ?>
					<div data-gloskin-migration-error><p><?php echo esc_html( $safe_error ); ?></p></div>
				<?php else : ?>
					<div data-gloskin-migration-error hidden><p></p></div>
				<?php endif; ?>
				<?php if ( 'consumed' !== $state['status'] ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-gloskin-migration-form>
						<input type="hidden" name="action" value="<?php echo esc_attr( self::POST_ACTION ); ?>">
						<?php wp_nonce_field( self::NONCE, 'gloskin_migration_nonce' ); ?>
						<button type="submit" class="button button-primary" data-gloskin-migration-run><?php echo esc_html( $button ); ?></button>
					</form>
				<?php else : ?>
					<div class="notice notice-success inline"><p><?php echo esc_html__( 'Finalisasi selesai', 'gloskin-site-core' ); ?></p></div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/** @return void */
	public function ajax_advance() {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array(
				'code'      => 'unauthorized',
				'message'   => __( 'Izin tidak cukup.', 'gloskin-site-core' ),
				'step'      => '',
				'retryable' => false,
			), 403 );
		}

		$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( (string) $_POST['mode'] ) ) : 'start';

		try {
			$state = $this->migration->advance( $mode );
			wp_send_json_success( $state );
		} catch ( Throwable $error ) {
			$code  = $this->classify_error( $error->getMessage() );
			$state = $this->migration->get_state();
			wp_send_json_error( array(
				'code'      => $code,
				'message'   => $this->safe_error_message( $code ),
				'step'      => isset( $state['current_step'] ) ? (string) $state['current_step'] : '',
				'retryable' => $this->is_retryable_error( $code ),
			), 'migration_locked' === $code ? 409 : 500 );
		}
	}

	/** @param string $msg @return string */
	private function classify_error( $msg ) {
		foreach ( array(
			'bundle_unavailable',
			'bundle_invalid',
			'doctor_unmatched',
			'doctor_ambiguous',
			'upload_unavailable',
			'normalize_failed',
			'verification_failed',
			'migration_locked',
			'unexpected_error',
		) as $prefix ) {
			if ( 0 === strpos( (string) $msg, $prefix . ':' ) ) {
				return $prefix;
			}
		}
		return 'unexpected_error';
	}

	/** @param string $code @return bool */
	private function is_retryable_error( $code ) {
		return in_array( $code, array( 'bundle_unavailable', 'upload_unavailable', 'migration_locked' ), true );
	}

	/** @param string $code @return string */
	private function safe_error_message( $code ) {
		$messages = array(
			'bundle_unavailable'  => __( 'Paket foto dokter belum tersedia di instalasi plugin. Perbarui paket plugin lalu coba lagi.', 'gloskin-site-core' ),
			'bundle_invalid'      => __( 'Paket foto dokter tidak valid atau tidak lengkap. Perbarui paket plugin atau perbaiki datanya sebelum melanjutkan.', 'gloskin-site-core' ),
			'doctor_unmatched'    => __( 'Sebagian foto belum dapat dicocokkan dengan data dokter. Tidak ada perubahan foto yang dilakukan.', 'gloskin-site-core' ),
			'doctor_ambiguous'    => __( 'Sebagian foto cocok dengan lebih dari satu data dokter. Perbaiki data dokter sebelum melanjutkan.', 'gloskin-site-core' ),
			'upload_unavailable'  => __( 'Folder upload WordPress tidak tersedia atau tidak dapat ditulis. Periksa konfigurasi atau izin lalu coba lagi.', 'gloskin-site-core' ),
			'normalize_failed'    => __( 'Normalisasi struktur halaman atau menu gagal. Pastikan halaman kanonik (Beranda, Perawatan, Promo, Skincare, Tentang Gloskin) sudah dipublikasikan, lalu coba lagi.', 'gloskin-site-core' ),
			'verification_failed' => __( 'Verifikasi finalisasi gagal. Proses dihentikan agar data tidak dilanjutkan secara tidak aman.', 'gloskin-site-core' ),
			'migration_locked'    => __( 'Finalisasi sedang diproses oleh request lain. Tunggu sebentar lalu coba lagi.', 'gloskin-site-core' ),
			'unexpected_error'    => __( 'Terjadi kesalahan tak terduga saat finalisasi. Coba lagi atau periksa log server.', 'gloskin-site-core' ),
			'unauthorized'        => __( 'Izin tidak cukup.', 'gloskin-site-core' ),
		);
		return isset( $messages[ $code ] ) ? $messages[ $code ] : $messages['unexpected_error'];
	}

	/** @return void */
	public function handle_fallback() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Izin tidak cukup.', 'gloskin-site-core' ) );
		}
		check_admin_referer( self::NONCE, 'gloskin_migration_nonce' );

		try {
			$this->migration->run_to_completion();
			wp_safe_redirect( admin_url( 'admin.php?page=' . Gloskin_Site_Core_Content_Service::ADMIN_MENU_SLUG . '&migrated=1' ) );
		} catch ( Throwable $error ) {
			$code = $this->classify_error( $error->getMessage() );
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&migration_error=' . rawurlencode( $code ) ) );
		}
		exit;
	}
}
