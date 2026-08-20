<?php
/**
 * Admin runner for Finalisasi Prototype Tahap 2.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once __DIR__ . '/class-gloskin-site-core-revision-20260820-promo-recovery.php';

final class Gloskin_Site_Core_Revision_20260820_Promo_Recovery_Admin {
	const CAPABILITY  = 'manage_options';
	const SLUG        = 'gloskin-revision-20260820-promo-recovery';
	const AJAX_ACTION = 'gloskin_site_core_revision_20260820_promo_recovery';
	const POST_ACTION = 'gloskin_site_core_revision_20260820_promo_recovery_fallback';
	const NONCE       = 'gloskin_site_core_revision_20260820_promo_recovery';

	/** @var Gloskin_Site_Core_Revision_20260820_Promo_Recovery */
	private $migration;
	/** @var Gloskin_Site_Core_Asset_Service */
	private $assets;
	/** @var string */
	private $hook = '';

	/** @param Gloskin_Site_Core_Asset_Service $assets */
	public function __construct( $assets ) { $this->assets = $assets; $this->migration = new Gloskin_Site_Core_Revision_20260820_Promo_Recovery(); }

	/** @return void */
	public function register() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 15 );
		add_action( 'admin_notices', array( $this, 'render_pending_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 30 );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_advance' ) );
		add_action( 'admin_post_' . self::POST_ACTION, array( $this, 'handle_fallback' ) );
	}

	/** @return void */
	public function register_menu() {
		if ( ! current_user_can( self::CAPABILITY ) || $this->migration->is_consumed() ) { return; }
		$hook = add_submenu_page( Gloskin_Site_Core_Content_Service::ADMIN_MENU_SLUG, __( 'Finalisasi Prototype Tahap 2', 'gloskin-site-core' ), __( 'Finalisasi Tahap 2', 'gloskin-site-core' ), self::CAPABILITY, self::SLUG, array( $this, 'render' ) );
		if ( is_string( $hook ) ) { $this->hook = $hook; }
	}

	/** @return void */
	public function render_pending_notice() {
		if ( ! current_user_can( self::CAPABILITY ) || $this->migration->is_consumed() ) { return; }
		echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'Finalisasi Prototype Tahap 2 siap dijalankan.', 'gloskin-site-core' ) . '</strong> ' . esc_html__( 'Recovery ini memulihkan Page Promo dan item navigasinya tanpa mengubah collision object atau Promo production.', 'gloskin-site-core' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ) . '">' . esc_html__( 'Buka recovery', 'gloskin-site-core' ) . '</a></p></div>';
	}

	/** @param string $hook_suffix Hook. @return void */
	public function enqueue_assets( $hook_suffix ) {
		if ( '' !== $this->hook && $hook_suffix === $this->hook ) { $this->assets->enqueue_admin_final_migration( self::AJAX_ACTION, wp_create_nonce( self::NONCE ) ); }
	}

	/** @return void */
	public function render() {
		if ( ! current_user_can( self::CAPABILITY ) ) { wp_die( esc_html__( 'Izin tidak cukup.', 'gloskin-site-core' ) ); }
		$state = $this->migration->get_state(); $processed = (int) $state['processed_steps']; $total = max( 1, (int) $state['total_steps'] );
		$error = '' !== (string) $state['last_error'] ? $this->safe_error_message( $this->classify_error( $state['last_error'] ) ) : '';
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Finalisasi Prototype Tahap 2', 'gloskin-site-core' ); ?></h1>
			<p><?php echo esc_html__( 'Recovery satu kali untuk Page /promo/, binding item menu yang sudah ada, dan verifikasi read-only Promo/WooCommerce.', 'gloskin-site-core' ); ?></p>
			<div id="gloskin-migration-app" data-gloskin-final-migration data-ajax="<?php echo esc_attr( admin_url( 'admin-ajax.php' ) ); ?>" data-action="<?php echo esc_attr( self::AJAX_ACTION ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( self::NONCE ) ); ?>" data-processed="<?php echo esc_attr( (string) $processed ); ?>" data-total="<?php echo esc_attr( (string) $total ); ?>" data-status="<?php echo esc_attr( (string) $state['status'] ); ?>">
				<progress class="gloskin-admin-migration__progress" data-gloskin-migration-progressbar value="<?php echo esc_attr( (string) $processed ); ?>" max="<?php echo esc_attr( (string) $total ); ?>"></progress>
				<p class="gloskin-admin-migration__step" data-gloskin-migration-step><?php echo esc_html( (string) $state['current_step'] ); ?></p>
				<div data-gloskin-migration-error<?php echo '' === $error ? ' hidden' : ''; ?>><p><?php echo esc_html( $error ); ?></p></div>
				<?php if ( 'consumed' !== (string) $state['status'] ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-gloskin-migration-form>
					<input type="hidden" name="action" value="<?php echo esc_attr( self::POST_ACTION ); ?>">
					<?php wp_nonce_field( self::NONCE, 'gloskin_migration_nonce' ); ?>
					<button type="submit" class="button button-primary" data-gloskin-migration-run><?php echo esc_html( 'failed' === (string) $state['status'] || $processed > 0 ? __( 'Lanjutkan Finalisasi Tahap 2', 'gloskin-site-core' ) : __( 'Jalankan Finalisasi Tahap 2', 'gloskin-site-core' ) ); ?></button>
				</form><?php else : ?><div class="notice notice-success inline"><p><?php echo esc_html__( 'Finalisasi Tahap 2 selesai.', 'gloskin-site-core' ); ?></p></div><?php endif; ?>
			</div>
		</div>
		<?php
	}

	/** @return void */
	public function ajax_advance() {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( self::CAPABILITY ) ) { wp_send_json_error( array( 'code' => 'unauthorized', 'message' => __( 'Izin tidak cukup.', 'gloskin-site-core' ), 'retryable' => false ), 403 ); }
		$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( (string) $_POST['mode'] ) ) : 'start';
		try { wp_send_json_success( $this->migration->advance( $mode ) ); }
		catch ( Throwable $error ) {
			$code = $this->classify_error( $error->getMessage() ); $state = $this->migration->get_state();
			wp_send_json_error( array( 'code' => $code, 'message' => $this->safe_error_message( $code ), 'step' => (string) $state['current_step'], 'retryable' => in_array( $code, array( 'migration_locked', 'verification_pending' ), true ) ), 'migration_locked' === $code ? 409 : 500 );
		}
	}

	/** @return void */
	public function handle_fallback() {
		if ( ! current_user_can( self::CAPABILITY ) ) { wp_die( esc_html__( 'Izin tidak cukup.', 'gloskin-site-core' ) ); }
		check_admin_referer( self::NONCE, 'gloskin_migration_nonce' );
		try { $this->migration->run_to_completion(); wp_safe_redirect( admin_url( 'admin.php?page=' . Gloskin_Site_Core_Content_Service::ADMIN_MENU_SLUG . '&migrated=1' ) ); }
		catch ( Throwable $error ) { wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&migration_error=' . rawurlencode( $this->classify_error( $error->getMessage() ) ) ) ); }
		exit;
	}

	/** @param string $message Message. @return string */
	private function classify_error( $message ) {
		foreach ( array( 'reconcile_failed', 'verification_failed', 'verification_pending', 'migration_locked', 'unexpected_error' ) as $code ) { if ( 0 === strpos( (string) $message, $code . ':' ) ) { return $code; } }
		return 'unexpected_error';
	}

	/** @param string $code Code. @return string */
	private function safe_error_message( $code ) {
		$messages = array(
			'reconcile_failed' => __( 'Page atau item menu Promo belum dapat direkonsiliasi dengan aman.', 'gloskin-site-core' ),
			'verification_failed' => __( 'Verifikasi Tahap 2 menemukan perubahan atau kondisi yang tidak aman.', 'gloskin-site-core' ),
			'verification_pending' => __( 'Data sudah direkonsiliasi, tetapi route/cache belum menampilkan hasil final. Tunggu cache segar lalu lanjutkan.', 'gloskin-site-core' ),
			'migration_locked' => __( 'Finalisasi Tahap 2 sedang diproses oleh request lain.', 'gloskin-site-core' ),
			'unexpected_error' => __( 'Terjadi kesalahan tak terduga saat Finalisasi Tahap 2.', 'gloskin-site-core' ),
			'unauthorized' => __( 'Izin tidak cukup.', 'gloskin-site-core' ),
		);
		return isset( $messages[ $code ] ) ? $messages[ $code ] : $messages['unexpected_error'];
	}
}
