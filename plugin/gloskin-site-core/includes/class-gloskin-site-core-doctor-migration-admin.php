<?php
/**
 * Admin surface for verified doctor one-shot migration.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Gloskin_Site_Core_Doctor_Migration_Admin {
	const SLUG       = 'gloskin-doctor-migration';
	const ACTION     = 'gloskin_doctor_migration_advance';
	const NONCE      = 'gloskin_doctor_migration_advance';
	const CAPABILITY = 'edit_others_posts';

	/** @var Gloskin_Site_Core_Doctor_Importer */
	private $importer;

	/** @param string $plugin_file Main plugin file. */
	public function __construct( $plugin_file ) {
		$this->importer = new Gloskin_Site_Core_Doctor_Importer( $plugin_file );
	}

	/** @return void */
	public function register() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 12 );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_advance' ) );
	}

	/** @return void */
	public function register_menu() {
		if ( ! current_user_can( self::CAPABILITY ) || ! $this->importer->should_show_menu() ) {
			return;
		}
		add_submenu_page(
			Gloskin_Site_Core_Content_Service::ADMIN_MENU_SLUG,
			__( 'Verified Doctor Migration', 'gloskin-site-core' ),
			__( 'Verified Doctor Migration', 'gloskin-site-core' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render_page' )
		);
	}

	/** @return void */
	public function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Anda tidak memiliki izin menjalankan doctor migration.', 'gloskin-site-core' ) );
		}
		$state     = $this->importer->state();
		$processed = absint( $state['index'] );
		$expected  = absint( $state['expected'] );
		$mode      = $processed > 0 ? 'continue' : 'start';
		?>
		<div class="wrap"><h1><?php echo esc_html__( 'Verified Doctor Migration', 'gloskin-site-core' ); ?></h1>
		<p><?php echo esc_html__( 'Impor deterministik 13 dokter yang terverifikasi pada source package. SIP, jadwal, cabang, dan foto tidak dibuat bila tidak didukung sumber.', 'gloskin-site-core' ); ?></p>
		<table class="widefat striped" style="max-width:780px"><tbody>
		<tr><th><?php echo esc_html__( 'Bundle', 'gloskin-site-core' ); ?></th><td>gloskin-doctors-v1</td></tr>
		<tr><th><?php echo esc_html__( 'Verified roster', 'gloskin-site-core' ); ?></th><td>13</td></tr>
		<tr><th><?php echo esc_html__( 'Media', 'gloskin-site-core' ); ?></th><td>0</td></tr>
		<tr><th><?php echo esc_html__( 'Status', 'gloskin-site-core' ); ?></th><td><?php echo esc_html( (string) $state['status'] ); ?></td></tr>
		<tr><th><?php echo esc_html__( 'Progress', 'gloskin-site-core' ); ?></th><td><?php echo esc_html( sprintf( '%d/%d', $processed, $expected ) ); ?></td></tr>
		</tbody></table>
		<?php if ( ! empty( $state['last_error'] ) ) : ?><div class="notice notice-error inline"><p><?php echo esc_html( (string) $state['last_error'] ); ?></p></div><?php endif; ?>
		<?php if ( 'consumed' !== $state['status'] ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:20px">
			<?php wp_nonce_field( self::NONCE ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
			<input type="hidden" name="mode" value="<?php echo esc_attr( $mode ); ?>" />
			<button class="button button-primary" type="submit"><?php echo esc_html( 'start' === $mode ? __( 'Start', 'gloskin-site-core' ) : __( 'Continue', 'gloskin-site-core' ) ); ?></button>
		</form>
		<p class="description"><?php echo esc_html__( 'Setiap continuation memproses maksimum satu dokter. Bundle divalidasi penuh sebelum mutation.', 'gloskin-site-core' ); ?></p>
		<?php endif; ?></div>
		<?php
	}

	/** @return void */
	public function handle_advance() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Capability tidak cukup.', 'gloskin-site-core' ) );
		}
		check_admin_referer( self::NONCE );
		$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';
		try {
			$state = $this->importer->advance( $mode );
			/* Final verification/cleanup performs no second doctor checkpoint; it
			 * only consumes the already-complete 13-record set. */
			if ( 'verifying' === $state['status'] ) {
				$state = $this->importer->advance( 'continue' );
			}
			if ( 'consumed' === $state['status'] ) {
				wp_safe_redirect( admin_url( 'admin.php?page=' . Gloskin_Site_Core_Content_Service::ADMIN_MENU_SLUG ) );
				exit;
			}
		} catch ( Throwable $error ) {
			// State already stores a bounded safe error summary; never expose internals here.
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG ) );
		exit;
	}
}
