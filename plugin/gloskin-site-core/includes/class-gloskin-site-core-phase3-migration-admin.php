<?php
/**
 * Admin UI controller for Phase-3 migration (FB-989354 & FB-989360).
 *
 * Enforces: capability check + nonce + zero mutations from preflight/start.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Gloskin_Site_Core_Phase3_Migration_Admin {

	const CAPABILITY  = 'manage_options';
	const SLUG        = 'gloskin-phase3-migration';
	const AJAX_ACTION = 'gloskin_phase3_advance';
	const NONCE       = 'gloskin_phase3_nonce';

	/** @var Gloskin_Site_Core_Phase3_Migration */
	private $migration;

	public function __construct() {
		$this->migration = new Gloskin_Site_Core_Phase3_Migration();
	}

	/** @return void */
	public function register_menu() {
		add_submenu_page(
			'tools.php',
			__( 'Phase 3 Migration', 'gloskin-site-core' ),
			__( 'Phase 3 Migration', 'gloskin-site-core' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render_page' )
		);
	}

	/** @return void */
	public function render_pending_notice() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		$state = $this->migration->get_state();
		if ( in_array( $state['status'], array( 'pending', 'failed', 'running' ), true ) ) {
			$url = admin_url( 'tools.php?page=' . self::SLUG );
			printf(
				'<div class="notice notice-warning"><p><strong>Gloskin Phase 3 Migration</strong> %s <a href="%s">%s</a></p></div>',
				esc_html__( 'belum selesai. Status:', 'gloskin-site-core' ) . ' <code>' . esc_html( $state['status'] ) . '</code>.',
				esc_url( $url ),
				esc_html__( 'Buka halaman migrasi', 'gloskin-site-core' )
			);
		}
	}

	/** @return void */
	public function enqueue_assets() {
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( (string) $screen->id, self::SLUG ) ) {
			return;
		}
		wp_enqueue_script(
			'gloskin-phase3-admin',
			plugin_dir_url( dirname( __FILE__ ) ) . 'assets/phase3-admin.js',
			array( 'jquery' ),
			'1.0.0',
			true
		);
		wp_localize_script( 'gloskin-phase3-admin', 'GloskinPhase3', array(
			'ajax_url'    => admin_url( 'admin-ajax.php' ),
			'ajax_action' => self::AJAX_ACTION,
			'nonce'       => wp_create_nonce( self::NONCE ),
		) );
	}

	/** @return void */
	public function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Izin tidak cukup.', 'gloskin-site-core' ) );
		}
		$state = $this->migration->get_state();
		$is_complete = 'complete' === $state['status'];
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Gloskin Phase 3 Migration', 'gloskin-site-core' ); ?></h1>
			<p><?php esc_html_e( 'FB-989354 (Skincare) & FB-989360 (Treatment) — Rekonsiliasi aset dan data produk klien.', 'gloskin-site-core' ); ?></p>

			<table class="form-table" role="presentation">
				<tr>
					<th><?php esc_html_e( 'Status', 'gloskin-site-core' ); ?></th>
					<td><code><?php echo esc_html( $state['status'] ); ?></code></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Langkah Saat Ini', 'gloskin-site-core' ); ?></th>
					<td><?php echo esc_html( $state['current_step'] ); ?></td>
				</tr>
				<?php if ( ! empty( $state['last_error'] ) ) : ?>
				<tr>
					<th><?php esc_html_e( 'Error Terakhir', 'gloskin-site-core' ); ?></th>
					<td><code style="color:red;"><?php echo esc_html( $state['last_error'] ); ?></code></td>
				</tr>
				<?php endif; ?>
				<tr>
					<th><?php esc_html_e( 'Progres', 'gloskin-site-core' ); ?></th>
					<td>
						<div style="background:#ddd;border-radius:4px;height:20px;width:300px;overflow:hidden;">
							<div style="background:#0073aa;height:100%;width:<?php echo esc_attr( (int) ( $state['progress_percent'] ?? 0 ) ); ?>%;transition:width 0.3s;"></div>
						</div>
						<?php echo esc_html( ( $state['progress_percent'] ?? 0 ) . '%' ); ?>
					</td>
				</tr>
			</table>

			<?php if ( $is_complete ) : ?>
				<div class="notice notice-success inline"><p>
					<?php esc_html_e( 'Phase 3 migration selesai.', 'gloskin-site-core' ); ?>
				</p></div>
			<?php else : ?>
				<p>
					<?php if ( 'pending' === $state['status'] ) : ?>
						<button id="gloskin-p3-start" class="button button-primary"><?php esc_html_e( 'Mulai Phase 3', 'gloskin-site-core' ); ?></button>
					<?php elseif ( 'failed' === $state['status'] ) : ?>
						<button id="gloskin-p3-continue" class="button button-primary"><?php esc_html_e( 'Coba Lagi', 'gloskin-site-core' ); ?></button>
					<?php else : ?>
						<button id="gloskin-p3-continue" class="button button-primary"><?php esc_html_e( 'Lanjutkan', 'gloskin-site-core' ); ?></button>
					<?php endif; ?>
				</p>
				<div id="gloskin-p3-feedback" style="display:none;margin-top:12px;"></div>
			<?php endif; ?>

			<?php if ( ! empty( $state['audit'] ) ) : ?>
				<h2><?php esc_html_e( 'Audit', 'gloskin-site-core' ); ?></h2>
				<pre style="background:#f6f7f7;padding:12px;overflow:auto;max-height:400px;"><?php echo esc_html( wp_json_encode( $state['audit'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></pre>
			<?php endif; ?>
		</div>
		<script>
		(function($){
			function doAdvance(mode){
				$('#gloskin-p3-start,#gloskin-p3-continue').prop('disabled',true).text('<?php echo esc_js( __( 'Memproses…', 'gloskin-site-core' ) ); ?>');
				$('#gloskin-p3-feedback').show().html('<em><?php echo esc_js( __( 'Menjalankan langkah…', 'gloskin-site-core' ) ); ?></em>');
				$.post(<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,{
					action: <?php echo wp_json_encode( self::AJAX_ACTION ); ?>,
					_ajax_nonce: <?php echo wp_json_encode( wp_create_nonce( self::NONCE ) ); ?>,
					mode: mode
				},function(res){
					if(res.success){
						$('#gloskin-p3-feedback').html('<strong><?php echo esc_js( __( 'OK', 'gloskin-site-core' ) ); ?>:</strong> '+$('<span>').text(res.data.current_step||'').html());
						setTimeout(function(){ location.reload(); },800);
					} else {
						$('#gloskin-p3-feedback').html('<strong style="color:red"><?php echo esc_js( __( 'Error', 'gloskin-site-core' ) ); ?>:</strong> '+$('<span>').text(res.data||'').html());
						$('#gloskin-p3-start,#gloskin-p3-continue').prop('disabled',false).text('<?php echo esc_js( __( 'Coba Lagi', 'gloskin-site-core' ) ); ?>');
					}
				}).fail(function(){ location.reload(); });
			}
			$('#gloskin-p3-start').on('click',function(){ doAdvance('start'); });
			$('#gloskin-p3-continue').on('click',function(){ doAdvance('continue'); });
		})(jQuery);
		</script>
		<?php
	}

	/** @return void */
	public function ajax_advance() {
		check_ajax_referer( self::NONCE );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( __( 'Izin tidak cukup.', 'gloskin-site-core' ), 403 );
		}

		$mode = sanitize_key( (string) ( $_POST['mode'] ?? 'continue' ) );
		if ( ! in_array( $mode, array( 'start', 'continue' ), true ) ) {
			wp_send_json_error( __( 'Mode tidak valid.', 'gloskin-site-core' ), 400 );
		}

		try {
			$result = $this->migration->advance( $mode );
			wp_send_json_success( $result );
		} catch ( Throwable $error ) {
			wp_send_json_error( $error->getMessage(), 500 );
		}
	}

	/** @return void */
	public function handle_fallback() {
		/* Non-JS fallback via GET param — only for reference, not primary flow. */
		if ( ! isset( $_GET['gloskin_p3_action'] ) ) {
			return;
		}
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		check_admin_referer( self::NONCE );
		$mode = sanitize_key( (string) ( $_GET['gloskin_p3_action'] ) );
		try {
			$this->migration->advance( $mode );
		} catch ( Throwable $e ) {
			// State is already recorded in option, redirect back.
		}
		wp_safe_redirect( admin_url( 'tools.php?page=' . self::SLUG ) );
		exit;
	}
}
