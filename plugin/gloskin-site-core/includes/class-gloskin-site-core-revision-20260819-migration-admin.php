<?php
/**
 * Admin runner for the bounded 2026-08-19 content/data revision migration.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-gloskin-site-core-revision-20260819-migration.php';

final class Gloskin_Site_Core_Revision_20260819_Migration_Admin {
	const CAPABILITY  = 'manage_options';
	const SLUG        = 'gloskin-revision-20260819-migration';
	const AJAX_ACTION = 'gloskin_site_core_revision_20260819_migration';
	const POST_ACTION = 'gloskin_site_core_revision_20260819_migration_fallback';
	const NONCE       = 'gloskin_site_core_revision_20260819_migration';

	/** @var Gloskin_Site_Core_Revision_20260819_Migration */
	private $migration;

	/** @var Gloskin_Site_Core_Asset_Service */
	private $assets;

	/** @var string */
	private $hook = '';

	/** @param Gloskin_Site_Core_Asset_Service $assets Asset owner. */
	public function __construct( $assets, $plugin_file ) {
		$this->assets    = $assets;
		$this->migration = new Gloskin_Site_Core_Revision_20260819_Migration( $plugin_file );
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
			. esc_html__( 'Gloskin revision 2026-08-19 migration siap dijalankan.', 'gloskin-site-core' )
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
			__( 'Revision Migration 2026-08-19', 'gloskin-site-core' ),
			__( 'Revision Migration', 'gloskin-site-core' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render' )
		);
		if ( is_string( $hook ) ) {
			$this->hook = $hook;
		}
	}

	/** @param string $hook_suffix Admin hook. @return void */
	public function enqueue_assets( $hook_suffix ) {
		if ( '' === $this->hook || $hook_suffix !== $this->hook ) {
			return;
		}
		$this->assets->enqueue_admin_migration( self::AJAX_ACTION, wp_create_nonce( self::NONCE ) );
	}

	/** @return void */
	public function render() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Anda tidak memiliki izin untuk menjalankan migrasi ini.', 'gloskin-site-core' ) );
		}
		$state     = $this->migration->get_state();
		$processed = absint( $state['processed_steps'] );
		$total     = max( 1, absint( $state['total_steps'] ) );
		$button    = 'failed' === $state['status'] || $processed > 0
			? __( 'Lanjutkan Migrasi Otomatis', 'gloskin-site-core' )
			: __( 'Jalankan Migrasi Otomatis', 'gloskin-site-core' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Revision Migration 2026-08-19', 'gloskin-site-core' ); ?></h1>
			<p><?php echo esc_html__( 'Migrasi deterministik satu klik untuk revision 0.7.134. Checkpoints: Promo/Testimonial/Achievement CPT, demo data, foto dokter, verifikasi keamanan.', 'gloskin-site-core' ); ?></p>
			<?php if ( '' !== (string) $state['last_error'] ) : ?>
				<div class="notice notice-error inline"><p><?php echo esc_html( $state['last_error'] ); ?></p></div>
			<?php endif; ?>
			<div id="gloskin-migration-app" data-ajax="<?php echo esc_attr( admin_url( 'admin-ajax.php' ) ); ?>" data-action="<?php echo esc_attr( self::AJAX_ACTION ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( self::NONCE ) ); ?>" data-processed="<?php echo esc_attr( (string) $processed ); ?>" data-total="<?php echo esc_attr( (string) $total ); ?>" data-status="<?php echo esc_attr( (string) $state['status'] ); ?>">
				<progress class="gloskin-admin-migration__progress" value="<?php echo esc_attr( (string) $processed ); ?>" max="<?php echo esc_attr( (string) $total ); ?>"></progress>
				<p class="gloskin-admin-migration__step"><?php echo esc_html( $state['current_step'] ); ?></p>
				<?php if ( 'consumed' !== $state['status'] ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="<?php echo esc_attr( self::POST_ACTION ); ?>">
						<?php wp_nonce_field( self::NONCE, 'gloskin_migration_nonce' ); ?>
						<button type="submit" class="button button-primary"><?php echo esc_html( $button ); ?></button>
					</form>
				<?php else : ?>
					<div class="notice notice-success inline"><p><?php echo esc_html__( 'Migrasi selesai. Menu ini tidak akan tampil lagi setelah reload.', 'gloskin-site-core' ); ?></p></div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/** @return void */
	public function ajax_advance() {
		check_ajax_referer( self::NONCE );
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Izin tidak cukup.', 'gloskin-site-core' ) ), 403 );
		}
		$mode = isset( $_POST['mode'] ) ? sanitize_key( (string) $_POST['mode'] ) : 'start';
		try {
			$state = $this->migration->advance( $mode );
			wp_send_json_success( $state );
		} catch ( Throwable $error ) {
			wp_send_json_error( array( 'message' => $error->getMessage() ), 500 );
		}
	}

	/** @return void */
	public function handle_fallback() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Izin tidak cukup.', 'gloskin-site-core' ) );
		}
		check_admin_referer( self::NONCE, 'gloskin_migration_nonce' );
		try {
			$this->migration->run_to_completion();
			wp_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&migrated=1' ) );
		} catch ( Throwable $error ) {
			wp_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&migration_error=' . rawurlencode( $error->getMessage() ) ) );
		}
		exit;
	}
}
