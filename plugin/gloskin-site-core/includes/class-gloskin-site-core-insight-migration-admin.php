<?php
/**
 * Temporary one-shot admin surface for the Gloskin Insights v1 migration.
 *
 * @package GloskinSiteCore
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once __DIR__ . '/class-gloskin-site-core-insight-importer.php';

final class Gloskin_Site_Core_Insight_Migration_Admin {
	const CAPABILITY = 'manage_options';
	const SLUG = 'gloskin-insight-import-v1';
	const ACTION = 'gloskin_site_core_insight_import_v1';
	const NONCE = 'gloskin_site_core_insight_import_v1';

	/** @var Gloskin_Site_Core_Insight_Importer */
	private $importer;

	public function __construct( $plugin_file ) {
		$this->importer = new Gloskin_Site_Core_Insight_Importer( $plugin_file );
	}

	public function register() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 12 );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	public function register_menu() {
		if ( ! current_user_can( self::CAPABILITY ) || ! $this->importer->should_show_menu() ) { return; }
		add_submenu_page(
			Gloskin_Site_Core_Content_Service::ADMIN_MENU_SLUG,
			__( 'Insight Editorial Migration', 'gloskin-site-core' ),
			__( 'Insight Migration', 'gloskin-site-core' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render' )
		);
	}

	public function render() {
		if ( ! current_user_can( self::CAPABILITY ) ) { return; }
		$summary = $this->importer->get_summary();
		if ( 'consumed' === $summary['detection'] || 'none' === $summary['detection'] ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Insight Editorial Migration', 'gloskin-site-core' ) . '</h1><p>' . esc_html__( 'Bundle migrasi tidak lagi tersedia untuk dijalankan.', 'gloskin-site-core' ) . '</p></div>';
			return;
		}
		$mode = in_array( $summary['detection'], array( 'running','failed','verifying','validating' ), true ) && ! empty( $summary['bundle_id'] ) ? 'continue' : 'start';
		$label = 'start' === $mode ? __( 'Validasi dan Mulai', 'gloskin-site-core' ) : __( 'Lanjutkan Satu Artikel', 'gloskin-site-core' );
		$processed = isset( $summary['processed_posts'] ) ? absint( $summary['processed_posts'] ) : 0;
		$expected = isset( $summary['expected_posts'] ) ? absint( $summary['expected_posts'] ) : 13;
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Insight Editorial Migration', 'gloskin-site-core' ); ?></h1>
			<p><?php echo esc_html__( 'Migrasi satu kali ini mengimpor artikel sebagai WordPress Posts, kategori native, dan featured image lokal. Setiap kelanjutan memproses maksimal satu pasangan artikel/media.', 'gloskin-site-core' ); ?></p>
			<table class="widefat striped" style="max-width:760px">
				<tbody>
					<tr><th><?php echo esc_html__( 'Status', 'gloskin-site-core' ); ?></th><td><?php echo esc_html( (string) $summary['detection'] ); ?></td></tr>
					<tr><th><?php echo esc_html__( 'Progress', 'gloskin-site-core' ); ?></th><td><?php echo esc_html( $processed . ' / ' . $expected ); ?></td></tr>
					<tr><th><?php echo esc_html__( 'Featured media', 'gloskin-site-core' ); ?></th><td>13</td></tr>
					<tr><th><?php echo esc_html__( 'Kategori native', 'gloskin-site-core' ); ?></th><td>5</td></tr>
				</tbody>
			</table>
			<?php if ( ! empty( $summary['last_error'] ) ) : ?>
				<div class="notice notice-error inline"><p><?php echo esc_html( (string) $summary['last_error'] ); ?></p></div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:20px">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
				<input type="hidden" name="mode" value="<?php echo esc_attr( $mode ); ?>">
				<?php wp_nonce_field( self::NONCE ); ?>
				<?php submit_button( $label, 'primary', 'submit', false ); ?>
			</form>
			<p class="description"><?php echo esc_html__( 'Jangan menghapus atau mengubah file runtime saat migrasi berjalan. Setelah semua 13 record diverifikasi, status consumed disimpan sebelum cleanup runtime dilakukan.', 'gloskin-site-core' ); ?></p>
		</div>
		<?php
	}

	public function handle() {
		if ( ! current_user_can( self::CAPABILITY ) ) { wp_die( esc_html__( 'Anda tidak memiliki izin untuk menjalankan migrasi Insight.', 'gloskin-site-core' ), '', array( 'response' => 403 ) ); }
		check_admin_referer( self::NONCE );
		$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';
		$next_page = self::SLUG;
		try {
			$result = $this->importer->advance( $mode );
			if ( isset( $result['status'] ) && 'consumed' === $result['status'] ) {
				$next_page = Gloskin_Site_Core_Content_Service::ADMIN_MENU_SLUG;
			}
		} catch ( Throwable $error ) {
			// Importer persists the bounded error in its state; no raw exception is rendered here.
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . $next_page ) );
		exit;
	}
}
