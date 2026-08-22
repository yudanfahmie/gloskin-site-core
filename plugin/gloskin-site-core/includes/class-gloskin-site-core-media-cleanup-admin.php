<?php
/**
 * Exact-user admin/AJAX controller for Media Cleanup.
 *
 * Access: current_user_can('manage_options') AND user_login === 'namaste' (exact, case-sensitive).
 * Enforced independently on every surface: menu, notice, page, assets, AJAX, export, reset and optimization.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once __DIR__ . '/class-gloskin-site-core-media-cleanup-resolver.php';

final class Gloskin_Site_Core_Media_Cleanup_Admin {
	const CAPABILITY      = 'manage_options';
	const USER_LOGIN      = 'namaste';
	const SLUG            = 'gloskin-media-cleanup-resolver';
	const AJAX_ACTION     = 'gloskin_site_core_media_cleanup_resolver';
	const DOWNLOAD_ACTION = 'gloskin_site_core_media_cleanup_manifest';
	const NONCE           = 'gloskin_site_core_media_cleanup_20260820';

	/** @var Gloskin_Site_Core_Media_Cleanup_Resolver */
	private $resolver;
	/** @var Gloskin_Site_Core_Asset_Service */
	private $assets;
	/** @var string */
	private $hook = '';

	/** @param Gloskin_Site_Core_Asset_Service $assets Asset owner. */
	public function __construct( $assets ) {
		$this->assets   = $assets;
		$this->resolver = new Gloskin_Site_Core_Media_Cleanup_Resolver();
	}

	/** @return void */
	public function register() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 16 );
		add_action( 'admin_notices', array( $this, 'render_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 30 );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax' ) );
		add_action( 'admin_post_' . self::DOWNLOAD_ACTION, array( $this, 'download_manifest' ) );
	}

	/** @return void */
	public function register_menu() {
		if ( ! $this->current_user_is_owner() ) { return; }
		$hook = add_submenu_page(
			Gloskin_Site_Core_Content_Service::ADMIN_MENU_SLUG,
			__( 'Media Cleanup', 'gloskin-site-core' ),
			__( 'Media Cleanup', 'gloskin-site-core' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render' )
		);
		if ( is_string( $hook ) ) { $this->hook = $hook; }
	}

	/** @return void */
	public function render_notice() {
		if ( ! $this->current_user_is_owner() ) { return; }
		$state = $this->resolver->get_state();
		if ( ! in_array( (string) $state['status'], array( 'pending', 'indexing', 'failed' ), true ) ) { return; }
		echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'Media Cleanup', 'gloskin-site-core' ) . '</strong> '
			. esc_html__( 'Scan media library tersedia.', 'gloskin-site-core' )
			. ' <a href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ) . '">'
			. esc_html__( 'Buka Media Cleanup', 'gloskin-site-core' )
			. '</a></p></div>';
	}

	/** @param string $hook_suffix Current admin screen hook. @return void */
	public function enqueue_assets( $hook_suffix ) {
		if ( ! $this->current_user_is_owner() || '' === $this->hook || $hook_suffix !== $this->hook ) { return; }
		if ( is_object( $this->assets ) && method_exists( $this->assets, 'enqueue_admin_media_cleanup' ) ) {
			$this->assets->enqueue_admin_media_cleanup();
		}
	}

	/** @return void */
	public function render() {
		if ( ! $this->current_user_is_owner() ) { $this->deny(); }
		$state         = $this->resolver->summary();
		$status        = (string) $state['status'];
		$counts        = (array) $state['counts'];
		$is_complete   = 'complete' === $status;
		$failed_from   = (string) $state['failed_from'];
		$effective     = 'failed' === $status && '' !== $failed_from ? $failed_from : $status;
		$in_scan       = in_array( $effective, array( 'pending', 'indexing' ), true );
		$in_review     = in_array( $effective, array( 'review_ready', 'deleting', 'verifying' ), true );
		$optimization  = isset( $state['optimization'] ) && is_array( $state['optimization'] ) ? $state['optimization'] : array();
		$opt_status    = isset( $optimization['status'] ) ? (string) $optimization['status'] : 'pending';
		$download_base = admin_url( 'admin-post.php?action=' . self::DOWNLOAD_ACTION . '&_wpnonce=' . wp_create_nonce( self::NONCE ) );
		?>
		<div id="gloskin-admin-root" data-gloskin-media-cleanup
			data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
			data-action="<?php echo esc_attr( self::AJAX_ACTION ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( self::NONCE ) ); ?>"
			data-revision="<?php echo esc_attr( Gloskin_Site_Core_Media_Cleanup_Resolver::REVISION ); ?>"
			data-status="<?php echo esc_attr( $status ); ?>"
			data-failed-from="<?php echo esc_attr( $failed_from ); ?>"
			data-optimization-status="<?php echo esc_attr( $opt_status ); ?>"
			data-token="<?php echo esc_attr( (string) $state['manifest_token'] ); ?>"
			data-cursor="<?php echo esc_attr( (string) $state['deletion_cursor'] ); ?>">

			<div class="gloskin-admin-workspace">
				<h1><?php echo esc_html__( 'Media Cleanup', 'gloskin-site-core' ); ?></h1>

				<?php if ( $is_complete ) : ?>
				<div class="gloskin-admin-card">
					<h2><?php echo esc_html__( 'Cleanup selesai', 'gloskin-site-core' ); ?></h2>
					<ul>
						<li><?php echo esc_html__( 'Dihapus:', 'gloskin-site-core' ); ?> <strong><?php echo esc_html( count( (array) $state['deleted'] ) ); ?></strong></li>
						<li><?php echo esc_html__( 'Dilewati setelah re-check:', 'gloskin-site-core' ); ?> <strong><?php echo esc_html( count( (array) $state['skipped'] ) ); ?></strong></li>
						<li><?php echo esc_html__( 'Gagal:', 'gloskin-site-core' ); ?> <strong><?php echo esc_html( count( (array) $state['failed'] ) ); ?></strong></li>
						<li><?php echo esc_html__( 'Ruang dibebaskan:', 'gloskin-site-core' ); ?> <strong><?php echo esc_html( size_format( (int) $state['actual_bytes'] ) ); ?></strong></li>
					</ul>
					<p><?php echo esc_html__( 'Item yang dilewati dipertahankan karena keamanan tidak lagi dapat dibuktikan saat re-check.', 'gloskin-site-core' ); ?></p>
					<p><button type="button" class="button button-primary" data-media-cleanup-reset><?php echo esc_html__( 'Mulai Scan Baru', 'gloskin-site-core' ); ?></button></p>
				</div>

				<?php elseif ( $in_review ) : ?>
				<div class="gloskin-admin-card">
					<h2><?php
						if ( 'review_ready' === $effective ) { echo esc_html__( 'Scan selesai — tinjau kandidat', 'gloskin-site-core' ); }
						elseif ( 'deleting' === $effective ) { echo esc_html__( 'Menghapus kandidat', 'gloskin-site-core' ); }
						else { echo esc_html__( 'Memverifikasi hasil', 'gloskin-site-core' ); }
					?></h2>
					<ul>
						<li><?php echo esc_html__( 'Total dipindai:', 'gloskin-site-core' ); ?> <strong><?php echo esc_html( (string) $state['total'] ); ?></strong></li>
						<li><?php echo esc_html__( 'Digunakan:', 'gloskin-site-core' ); ?> <strong data-count-used><?php echo esc_html( (string) $counts['used'] ); ?></strong></li>
						<li><?php echo esc_html__( 'Dilindungi:', 'gloskin-site-core' ); ?> <strong data-count-protected><?php echo esc_html( (string) $counts['protected'] ); ?></strong></li>
						<li><?php echo esc_html__( 'Ambigu (dipertahankan):', 'gloskin-site-core' ); ?> <strong data-count-ambiguous><?php echo esc_html( (string) $counts['ambiguous'] ); ?></strong></li>
						<li><?php echo esc_html__( 'Kandidat terkonfirmasi unused:', 'gloskin-site-core' ); ?> <strong data-count-unused><?php echo esc_html( (string) $counts['confirmed-unused'] ); ?></strong></li>
						<li><?php echo esc_html__( 'Estimasi ruang:', 'gloskin-site-core' ); ?> <strong><?php echo esc_html( size_format( (int) $state['estimated_bytes'] ) ); ?></strong></li>
					</ul>
					<?php if ( ! empty( $state['warnings'] ) ) : ?>
					<details><summary><?php echo esc_html__( 'Peringatan scanner', 'gloskin-site-core' ); ?></summary><ul>
						<?php foreach ( (array) $state['warnings'] as $w ) : ?><li><?php echo esc_html( (string) $w ); ?></li><?php endforeach; ?>
					</ul></details>
					<?php endif; ?>
				</div>

				<?php if ( 'review_ready' === $effective ) : ?>
				<div class="gloskin-admin-card">
					<h2><?php echo esc_html__( 'Kandidat terkonfirmasi unused', 'gloskin-site-core' ); ?></h2>
					<p><em><?php echo esc_html__( 'Label: "terkonfirmasi oleh scan saat ini" — bukan jaminan mutlak. Re-check JIT dilakukan sebelum setiap penghapusan.', 'gloskin-site-core' ); ?></em></p>
					<p>
						<a class="button" href="<?php echo esc_url( $download_base . '&format=json' ); ?>"><?php echo esc_html__( 'Unduh JSON', 'gloskin-site-core' ); ?></a>
						<a class="button" href="<?php echo esc_url( $download_base . '&format=csv' ); ?>"><?php echo esc_html__( 'Unduh CSV', 'gloskin-site-core' ); ?></a>
					</p>
					<div style="overflow-x:auto;"><table class="widefat striped">
						<thead><tr>
							<th><?php echo esc_html__( 'Thumbnail', 'gloskin-site-core' ); ?></th>
							<th><?php echo esc_html__( 'File', 'gloskin-site-core' ); ?></th>
							<th><?php echo esc_html__( 'Tanggal unggah / Usia', 'gloskin-site-core' ); ?></th>
							<th><?php echo esc_html__( 'Dimensi', 'gloskin-site-core' ); ?></th>
							<th><?php echo esc_html__( 'Ukuran', 'gloskin-site-core' ); ?></th>
							<th><?php echo esc_html__( 'Alasan', 'gloskin-site-core' ); ?></th>
						</tr></thead>
						<tbody data-media-cleanup-table></tbody>
					</table></div>
					<p data-media-cleanup-pagination></p>
				</div>
				<div class="gloskin-admin-card">
					<label><input type="checkbox" data-media-cleanup-confirm> <?php echo esc_html__( 'Saya memiliki backup database dan uploads terkini, dan memahami penghapusan bersifat permanen.', 'gloskin-site-core' ); ?></label>
					<p><button type="button" class="button button-primary is-destructive" style="background:#c00;border-color:#c00;color:#fff;" data-media-cleanup-delete disabled><?php printf( esc_html__( 'Hapus %s gambar terkonfirmasi unused secara permanen', 'gloskin-site-core' ), esc_html( (string) $counts['confirmed-unused'] ) ); ?></button></p>
				</div>
				<?php endif; ?>

				<?php if ( 'deleting' === $effective || 'verifying' === $effective ) : ?>
				<div class="gloskin-admin-card">
					<p role="status" aria-live="polite" data-media-cleanup-stage><?php
						echo 'deleting' === $effective
							? esc_html__( 'Menghapus kandidat…', 'gloskin-site-core' )
							: esc_html__( 'Memverifikasi hasil…', 'gloskin-site-core' );
					?></p>
					<progress data-media-cleanup-progress value="<?php echo esc_attr( (string) $state['deletion_cursor'] ); ?>" max="<?php echo esc_attr( (string) max( 1, (int) $counts['confirmed-unused'] ) ); ?>"></progress>
					<p data-media-cleanup-current><?php echo esc_html( (string) $state['current_file'] ); ?></p>
					<p><button type="button" class="button button-primary" data-media-cleanup-delete-continue><?php echo esc_html__( 'Lanjutkan', 'gloskin-site-core' ); ?></button></p>
				</div>
				<?php endif; ?>

				<?php else : ?>
				<div class="gloskin-admin-card">
					<?php if ( 'pending' === $effective ) : ?>
					<p><?php echo esc_html__( 'Scan Media Library untuk gambar lama yang tidak direferensikan.', 'gloskin-site-core' ); ?></p>
					<ul>
						<li><?php echo esc_html__( 'Scan bersifat read-only — tidak ada penghapusan selama scan.', 'gloskin-site-core' ); ?></li>
						<li><?php echo esc_html__( 'Media terbaru, sistem, dan ambigu tetap terlindungi.', 'gloskin-site-core' ); ?></li>
						<li><?php echo esc_html__( 'Menutup tab menghentikan request — buka lagi untuk melanjutkan.', 'gloskin-site-core' ); ?></li>
					</ul>
					<p><button type="button" class="button button-primary" data-media-cleanup-index><?php echo esc_html__( 'Scan Media Library', 'gloskin-site-core' ); ?></button></p>
					<?php elseif ( 'indexing' === $effective ) : ?>
					<h2><?php echo esc_html__( 'Memindai Media Library', 'gloskin-site-core' ); ?></h2>
					<progress data-media-cleanup-progress value="<?php echo esc_attr( (string) $state['processed'] ); ?>" max="<?php echo esc_attr( (string) max( 1, (int) $state['total'] ) ); ?>"></progress>
					<p role="status" aria-live="polite" data-media-cleanup-stage><?php echo esc_html( (int) $state['processed'] . ' / ' . (int) $state['total'] . ' ' . __( 'dipindai', 'gloskin-site-core' ) ); ?></p>
					<p data-media-cleanup-current><?php echo esc_html( (string) $state['current_file'] ); ?></p>
					<p><strong><?php echo esc_html__( 'Kandidat aman:', 'gloskin-site-core' ); ?></strong> <span data-count-unused><?php echo esc_html( (string) $counts['confirmed-unused'] ); ?></span></p>
					<p><strong><?php echo esc_html__( 'Estimasi ruang:', 'gloskin-site-core' ); ?></strong> <?php echo esc_html( size_format( (int) $state['estimated_bytes'] ) ); ?></p>
					<p><button type="button" class="button button-primary" data-media-cleanup-index><?php echo esc_html__( 'Lanjutkan Scan', 'gloskin-site-core' ); ?></button></p>
					<?php else : ?>
					<div class="notice notice-error inline"><p><strong><?php echo esc_html__( 'Error:', 'gloskin-site-core' ); ?></strong> <?php echo esc_html( (string) $state['last_error'] ); ?></p></div>
					<p><button type="button" class="button button-primary" data-media-cleanup-index><?php echo esc_html__( 'Coba Lagi', 'gloskin-site-core' ); ?></button></p>
					<?php endif; ?>
				</div>

				<?php if ( 'indexing' === $effective || 'failed' === $status ) : ?>
				<div class="gloskin-admin-card"><ul data-media-cleanup-counts>
					<li><?php echo esc_html__( 'Dipindai:', 'gloskin-site-core' ); ?> <strong data-count-processed><?php echo esc_html( (string) $state['processed'] ); ?></strong> / <?php echo esc_html( (string) $state['total'] ); ?></li>
					<li><?php echo esc_html__( 'Digunakan:', 'gloskin-site-core' ); ?> <strong data-count-used><?php echo esc_html( (string) $counts['used'] ); ?></strong></li>
					<li><?php echo esc_html__( 'Dilindungi:', 'gloskin-site-core' ); ?> <strong data-count-protected><?php echo esc_html( (string) $counts['protected'] ); ?></strong></li>
					<li><?php echo esc_html__( 'Ambigu:', 'gloskin-site-core' ); ?> <strong data-count-ambiguous><?php echo esc_html( (string) $counts['ambiguous'] ); ?></strong></li>
					<li><?php echo esc_html__( 'Kandidat unused:', 'gloskin-site-core' ); ?> <strong data-count-unused><?php echo esc_html( (string) $counts['confirmed-unused'] ); ?></strong></li>
				</ul></div>
				<?php endif; ?>
				<?php endif; ?>

				<div class="gloskin-admin-card" data-media-optimization-card>
					<h2><?php echo esc_html__( 'Optimize Images', 'gloskin-site-core' ); ?></h2>
					<p><?php echo esc_html__( 'Optimize retained images in place setelah cleanup stabil. Attachment ID, nama file, URL, format, dimensi dan crop tidak diubah; optimizer tidak membuat backup image permanen.', 'gloskin-site-core' ); ?></p>
					<?php if ( ! $is_complete ) : ?>
					<p><?php echo esc_html__( 'Selesaikan cleanup sampai verifikasi akhir sebelum menjalankan image optimization.', 'gloskin-site-core' ); ?></p>
					<?php else : ?>
					<ul>
						<li><?php echo esc_html__( 'Processed:', 'gloskin-site-core' ); ?> <strong data-opt-processed><?php echo esc_html( (string) ( $optimization['processed'] ?? 0 ) ); ?></strong> / <span data-opt-total><?php echo esc_html( (string) ( $optimization['total'] ?? 0 ) ); ?></span></li>
						<li><?php echo esc_html__( 'Optimized:', 'gloskin-site-core' ); ?> <strong data-opt-optimized><?php echo esc_html( (string) ( $optimization['optimized'] ?? 0 ) ); ?></strong></li>
						<li><?php echo esc_html__( 'Skipped:', 'gloskin-site-core' ); ?> <strong data-opt-skipped><?php echo esc_html( (string) ( $optimization['skipped'] ?? 0 ) ); ?></strong></li>
						<li><?php echo esc_html__( 'Failed:', 'gloskin-site-core' ); ?> <strong data-opt-failed><?php echo esc_html( (string) ( $optimization['failed'] ?? 0 ) ); ?></strong></li>
						<li><?php echo esc_html__( 'Bytes before:', 'gloskin-site-core' ); ?> <strong data-opt-bytes-before><?php echo esc_html( size_format( (int) ( $optimization['bytes_before'] ?? 0 ) ) ); ?></strong></li>
						<li><?php echo esc_html__( 'Bytes after:', 'gloskin-site-core' ); ?> <strong data-opt-bytes-after><?php echo esc_html( size_format( (int) ( $optimization['bytes_after'] ?? 0 ) ) ); ?></strong></li>
						<li><?php echo esc_html__( 'Saved:', 'gloskin-site-core' ); ?> <strong data-opt-bytes-saved><?php echo esc_html( size_format( (int) ( $optimization['bytes_saved'] ?? 0 ) ) ); ?></strong></li>
					</ul>
					<progress data-media-optimization-progress value="<?php echo esc_attr( (string) ( $optimization['processed'] ?? 0 ) ); ?>" max="<?php echo esc_attr( (string) max( 1, (int) ( $optimization['total'] ?? 0 ) ) ); ?>"></progress>
					<p role="status" aria-live="polite" data-media-optimization-stage><?php
						if ( 'running' === $opt_status ) { echo esc_html__( 'Optimization siap dilanjutkan…', 'gloskin-site-core' ); }
						elseif ( 'complete' === $opt_status ) { echo esc_html__( 'Optimization selesai.', 'gloskin-site-core' ); }
						elseif ( 'failed' === $opt_status ) { echo esc_html__( 'Optimization berhenti secara aman dan dapat dilanjutkan.', 'gloskin-site-core' ); }
						else { echo esc_html__( 'Siap mengoptimalkan retained images.', 'gloskin-site-core' ); }
					?></p>
					<p data-media-optimization-current><?php echo esc_html( (string) ( $optimization['current_file'] ?? '' ) ); ?></p>
					<?php if ( 'failed' === $opt_status && ! empty( $optimization['last_error'] ) ) : ?>
					<div class="notice notice-error inline"><p><?php echo esc_html__( 'Optimizer berhenti secara aman; file asli pada kegagalan aktif dipertahankan.', 'gloskin-site-core' ); ?></p></div>
					<?php endif; ?>
					<p><button type="button" class="button button-primary" data-media-optimization-start data-restart="<?php echo 'complete' === $opt_status ? '1' : '0'; ?>"><?php
						if ( 'complete' === $opt_status ) { echo esc_html__( 'Optimize New / Changed Images', 'gloskin-site-core' ); }
						elseif ( in_array( $opt_status, array( 'running', 'failed' ), true ) ) { echo esc_html__( 'Lanjutkan Optimization', 'gloskin-site-core' ); }
						else { echo esc_html__( 'Optimize Images', 'gloskin-site-core' ); }
					?></button></p>
					<?php endif; ?>
				</div>

				<div data-media-cleanup-error class="notice notice-error inline" hidden><p></p></div>
			</div>
		</div>
		<?php
	}

	/** @return void */
	public function ajax() {
		if ( ! $this->current_user_is_owner() ) {
			wp_send_json_error( array( 'code' => 'unauthorized', 'message' => 'Access denied.', 'retryable' => false ), 403 );
		}
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'code' => 'nonce_expired', 'message' => 'Session/nonce expired. Refresh halaman ini sebelum melanjutkan.', 'retryable' => false ), 403 );
		}
		$revision = isset( $_POST['revision'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['revision'] ) ) : '';
		if ( Gloskin_Site_Core_Media_Cleanup_Resolver::REVISION !== $revision ) {
			wp_send_json_error( array( 'code' => 'revision_mismatch', 'message' => 'Revision mismatch — refresh halaman.', 'retryable' => false ), 409 );
		}
		$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( (string) $_POST['mode'] ) ) : 'index';
		try {
			switch ( $mode ) {
				case 'index':
					$result = $this->resolver->index_batch();
					break;
				case 'delete':
					$cursor    = isset( $_POST['cursor'] ) ? absint( $_POST['cursor'] ) : 0;
					$token     = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['token'] ) ) : '';
					$confirmed = isset( $_POST['backup_confirmed'] ) && '1' === (string) $_POST['backup_confirmed'];
					$result    = $this->resolver->delete_batch( $cursor, $token, $confirmed );
					break;
				case 'verify':
					$result = $this->resolver->verify_batch();
					break;
				case 'review':
					$result = $this->resolver->review_page( isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1 );
					break;
				case 'reset':
					$result = $this->resolver->reset_scan();
					break;
				case 'optimize':
					$restart = isset( $_POST['restart'] ) && '1' === (string) $_POST['restart'];
					$result  = $this->resolver->optimize_batch( $restart );
					break;
				default:
					throw new RuntimeException( 'invalid_mode: Mode tidak dikenal.' );
			}
			wp_send_json_success( $result );
		} catch ( Throwable $error ) {
			$code        = $this->error_code( $error->getMessage() );
			$retryable   = in_array( $code, array( 'resolver_locked' ), true );
			$is_conflict = in_array( $code, array( 'resolver_locked', 'invalid_state' ), true );
			wp_send_json_error(
				array( 'code' => $code, 'message' => $this->safe_error( $code ), 'retryable' => $retryable ),
				'unauthorized' === $code ? 403 : ( $is_conflict ? 409 : 500 )
			);
		}
	}

	/** @return void */
	public function download_manifest() {
		if ( ! $this->current_user_is_owner() ) { $this->deny(); }
		check_admin_referer( self::NONCE );
		$state           = $this->resolver->summary();
		$download_status = 'failed' === (string) $state['status'] ? (string) $state['failed_from'] : (string) $state['status'];
		if ( ! in_array( $download_status, array( 'review_ready', 'deleting', 'verifying', 'complete' ), true ) ) {
			wp_die( esc_html__( 'Manifest belum siap.', 'gloskin-site-core' ), '', array( 'response' => 409 ) );
		}
		$format = isset( $_GET['format'] ) ? sanitize_key( wp_unslash( (string) $_GET['format'] ) ) : 'json';
		$data   = $this->resolver->export_data();
		nocache_headers();
		header( 'X-Content-Type-Options: nosniff' );
		if ( 'csv' === $format ) {
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="gloskin-media-cleanup-manifest.csv"' );
			$out = fopen( 'php://output', 'w' );
			fputcsv( $out, array( 'id', 'filename', 'mime', 'date', 'dimensions', 'bytes', 'classification', 'reason', 'references', 'warnings' ) );
			foreach ( (array) $data['state']['results'] as $item ) {
				fputcsv( $out, array( $item['id'], $item['filename'], $item['mime'], $item['date'], $item['dimensions'], $item['bytes'], $item['classification'], $item['reason'], implode( ' | ', (array) ( $item['references'] ?? array() ) ), implode( ' | ', (array) ( $item['warnings'] ?? array() ) ) ) );
			}
			fclose( $out );
			exit;
		}
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="gloskin-media-cleanup-manifest.json"' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit;
	}

	/** @return bool */
	private function current_user_is_owner() {
		if ( ! current_user_can( self::CAPABILITY ) ) { return false; }
		$user = wp_get_current_user();
		return $user && $user->exists() && self::USER_LOGIN === (string) $user->user_login;
	}

	/** @return never */
	private function deny() {
		wp_die( esc_html__( 'Media Cleanup hanya tersedia untuk owner yang diizinkan.', 'gloskin-site-core' ), '', array( 'response' => 403 ) );
	}

	/** @param string $message @return string */
	private function error_code( $message ) {
		foreach ( array( 'unauthorized', 'resolver_locked', 'invalid_state', 'invalid_mode', 'revision_mismatch', 'confirmation_required', 'manifest_invalid', 'manifest_failed', 'stale_manifest', 'scan_failed', 'verification_failed', 'optimization_failed' ) as $code ) {
			if ( 0 === strpos( (string) $message, $code . ':' ) ) { return $code; }
		}
		return 'unexpected_error';
	}

	/** @param string $code @return string */
	private function safe_error( $code ) {
		$messages = array(
			'resolver_locked'       => __( 'Resolver sedang diproses oleh request atau tab lain; akan dicoba kembali.', 'gloskin-site-core' ),
			'invalid_state'         => __( 'State Media Cleanup belum mengizinkan operasi tersebut. Selesaikan operasi aktif terlebih dahulu.', 'gloskin-site-core' ),
			'confirmation_required' => __( 'Konfirmasi backup wajib sebelum penghapusan permanen.', 'gloskin-site-core' ),
			'manifest_invalid'      => __( 'Manifest kandidat tidak valid atau berubah; penghapusan dihentikan.', 'gloskin-site-core' ),
			'scan_failed'           => __( 'Pemindaian tidak lengkap; tidak ada kandidat yang boleh dihapus.', 'gloskin-site-core' ),
			'verification_failed'   => __( 'Verifikasi akhir menemukan perubahan yang tidak aman.', 'gloskin-site-core' ),
			'optimization_failed'   => __( 'Image optimization berhenti secara aman. File asli pada kegagalan aktif tetap dipertahankan.', 'gloskin-site-core' ),
			'unexpected_error'      => __( 'Resolver gagal secara aman tanpa melanjutkan mutasi.', 'gloskin-site-core' ),
		);
		return isset( $messages[ $code ] ) ? $messages[ $code ] : $messages['unexpected_error'];
	}
}
