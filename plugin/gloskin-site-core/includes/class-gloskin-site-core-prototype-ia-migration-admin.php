<?php
/**
 * Admin runner for the bounded 2026-08-18 prototype IA migration.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-gloskin-site-core-prototype-ia-migration.php';

final class Gloskin_Site_Core_Prototype_IA_Migration_Admin {
	const CAPABILITY   = 'manage_options';
	const SLUG         = 'gloskin-prototype-ia-migration';
	const AJAX_ACTION  = 'gloskin_site_core_prototype_ia_migration';
	const POST_ACTION  = 'gloskin_site_core_prototype_ia_migration_fallback';
	const NONCE        = 'gloskin_site_core_prototype_ia_migration';

	/** @var Gloskin_Site_Core_Prototype_IA_Migration */
	private $migration;

	/** @var Gloskin_Site_Core_Asset_Service */
	private $assets;

	/** @var string */
	private $hook = '';

	/** @param Gloskin_Site_Core_Asset_Service $assets Asset owner. */
	public function __construct( $assets ) {
		$this->assets    = $assets;
		$this->migration = new Gloskin_Site_Core_Prototype_IA_Migration();
	}

	/** @return void */
	public function register() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 13 );
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
		echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'Gloskin prototype IA migration siap dijalankan.', 'gloskin-site-core' ) . '</strong> ';
		echo esc_html__( 'Satu klik akan menjalankan seluruh checkpoint otomatis dengan progress loader; tidak perlu memproses item satu per satu.', 'gloskin-site-core' );
		echo ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Buka migrasi', 'gloskin-site-core' ) . '</a></p></div>';
	}

	/** @return void */
	public function register_menu() {
		if ( ! current_user_can( self::CAPABILITY ) || $this->migration->is_consumed() ) {
			return;
		}
		$hook = add_submenu_page(
			Gloskin_Site_Core_Content_Service::ADMIN_MENU_SLUG,
			__( 'Prototype IA Migration', 'gloskin-site-core' ),
			__( 'Prototype IA Migration', 'gloskin-site-core' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render' )
		);
		if ( is_string( $hook ) ) {
			$this->hook = $hook;
		}
	}

	/**
	 * Reuse the existing bounded AJAX migration asset owner. The shared
	 * controller now exposes a real WP spinner/native progress element for
	 * both sample import and this IA workflow.
	 *
	 * @param string $hook_suffix Admin hook.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( '' === $this->hook || $hook_suffix !== $this->hook ) {
			return;
		}
		$this->assets->enqueue_admin_migration( self::AJAX_ACTION, wp_create_nonce( self::NONCE ) );
	}

	/** @return void */
	public function render() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Anda tidak memiliki izin untuk menjalankan migrasi IA.', 'gloskin-site-core' ) );
		}
		$state     = $this->migration->get_state();
		$processed = absint( $state['processed_products'] );
		$expected  = max( 1, absint( $state['expected_products'] ) );
		$button    = 'failed' === $state['status'] || $processed > 0
			? __( 'Lanjutkan Migrasi Otomatis', 'gloskin-site-core' )
			: __( 'Jalankan Migrasi Otomatis', 'gloskin-site-core' );
		?>
		<div class="wrap" data-gloskin-ia-migration data-gloskin-no-redirect="1" aria-busy="false">
			<h1><?php echo esc_html__( 'Prototype IA Migration', 'gloskin-site-core' ); ?></h1>
			<p><?php echo esc_html__( 'Migrasi satu kali ini menerapkan revisi IA client 2026-08-18 tanpa menghapus data WordPress/WooCommerce yang authoritative.', 'gloskin-site-core' ); ?></p>
			<p><strong><?php echo esc_html__( 'Satu klik saja.', 'gloskin-site-core' ); ?></strong> <?php echo esc_html__( 'Loader akan menjalankan page provisioning, menu normalization, safety verification, lalu consumed/schema checkpoint secara otomatis sampai selesai.', 'gloskin-site-core' ); ?></p>

			<div class="card" style="max-width:780px">
				<table class="widefat striped">
					<tbody>
						<tr><th scope="row"><?php echo esc_html__( 'Revision', 'gloskin-site-core' ); ?></th><td><?php echo esc_html( Gloskin_Site_Core_Prototype_IA_Migration::REVISION ); ?></td></tr>
						<tr><th scope="row"><?php echo esc_html__( 'Status', 'gloskin-site-core' ); ?></th><td><strong data-gloskin-sample-status><?php echo esc_html( (string) $state['status'] ); ?></strong></td></tr>
						<tr><th scope="row"><?php echo esc_html__( 'Langkah aktif', 'gloskin-site-core' ); ?></th><td><span data-gloskin-migration-step><?php echo esc_html( (string) $state['current_step'] ); ?></span></td></tr>
						<tr><th scope="row"><?php echo esc_html__( 'Progress', 'gloskin-site-core' ); ?></th><td><span data-gloskin-sample-progress><?php echo esc_html( $processed . '/' . $expected ); ?></span></td></tr>
					</tbody>
				</table>

				<div style="display:flex;align-items:center;gap:12px;margin:18px 0 8px">
					<progress data-gloskin-migration-progressbar max="<?php echo esc_attr( (string) $expected ); ?>" value="<?php echo esc_attr( (string) $processed ); ?>" style="width:min(520px,100%);height:18px">
						<?php echo esc_html( (string) round( ( $processed / $expected ) * 100 ) ); ?>%
					</progress>
					<span class="spinner" data-gloskin-migration-loader aria-hidden="true"></span>
				</div>

				<div class="notice notice-error inline" data-gloskin-sample-error<?php echo empty( $state['last_error'] ) ? ' hidden' : ''; ?>>
					<p><?php echo esc_html( (string) $state['last_error'] ); ?></p>
				</div>

				<p>
					<button type="button" class="button button-primary button-hero" data-gloskin-sample-run><?php echo esc_html( $button ); ?></button>
				</p>
				<p class="description"><?php echo esc_html__( 'Jika browser terputus, checkpoint tersimpan dan tombol yang sama akan melanjutkan dari langkah terakhir. Engine memakai lock singkat untuk mencegah dua tab menulis menu bersamaan.', 'gloskin-site-core' ); ?></p>
			</div>

			<noscript>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::POST_ACTION ); ?>">
					<?php wp_nonce_field( self::NONCE ); ?>
					<?php submit_button( __( 'Jalankan Migrasi Tanpa JavaScript', 'gloskin-site-core' ), 'secondary', 'submit', false ); ?>
				</form>
			</noscript>
		</div>
		<?php
	}

	/** @return void */
	public function ajax_advance() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Anda tidak memiliki izin untuk menjalankan migrasi IA.', 'gloskin-site-core' ) ), 403 );
		}
		check_ajax_referer( self::NONCE, 'nonce' );
		$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';
		try {
			wp_send_json_success( $this->migration->advance( $mode ) );
		} catch ( Throwable $error ) {
			wp_send_json_error( array( 'message' => $error->getMessage() ), 500 );
		}
	}

	/** @return void */
	public function handle_fallback() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Anda tidak memiliki izin untuk menjalankan migrasi IA.', 'gloskin-site-core' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::NONCE );
		try {
			$result = $this->migration->run_to_completion();
		} catch ( Throwable $error ) {
			$result = $this->migration->get_state();
			// State already contains the escaped-at-output failure message.
		}
		$destination = isset( $result['status'] ) && 'consumed' === $result['status']
			? Gloskin_Site_Core_Content_Service::ADMIN_MENU_SLUG
			: self::SLUG;
		wp_safe_redirect( admin_url( 'admin.php?page=' . $destination ) );
		exit;
	}
}
