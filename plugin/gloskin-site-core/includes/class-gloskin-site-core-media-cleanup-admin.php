<?php
/**
 * Exact-user admin/AJAX controller for Media Cleanup Resolver.
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
	public function __construct( $assets ) { $this->assets = $assets; $this->resolver = new Gloskin_Site_Core_Media_Cleanup_Resolver(); }

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
		if ( ! $this->current_user_is_owner() || $this->resolver->is_consumed() ) { return; }
		$hook = add_submenu_page( Gloskin_Site_Core_Content_Service::ADMIN_MENU_SLUG, __( 'Media Cleanup Resolver', 'gloskin-site-core' ), __( 'Media Cleanup Resolver', 'gloskin-site-core' ), self::CAPABILITY, self::SLUG, array( $this, 'render' ) );
		if ( is_string( $hook ) ) { $this->hook = $hook; }
	}

	/** @return void */
	public function render_notice() {
		if ( ! $this->current_user_is_owner() || $this->resolver->is_consumed() ) { return; }
		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Media Cleanup Resolver tersedia untuk dry-run.', 'gloskin-site-core' ) . '</strong> ' . esc_html__( 'Tahap pertama hanya mengindeks; penghapusan permanen memerlukan review dan konfirmasi backup.', 'gloskin-site-core' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ) . '">' . esc_html__( 'Buka resolver', 'gloskin-site-core' ) . '</a></p></div>';
	}

	/** @param string $hook_suffix Hook. @return void */
	public function enqueue_assets( $hook_suffix ) {
		if ( ! $this->current_user_is_owner() || '' === $this->hook || $hook_suffix !== $this->hook || $this->resolver->is_consumed() ) { return; }
		if ( is_object( $this->assets ) && method_exists( $this->assets, 'enqueue_admin_media_cleanup' ) ) { $this->assets->enqueue_admin_media_cleanup(); }
	}

	/** @return void */
	public function render() {
		if ( ! $this->current_user_is_owner() ) { $this->deny(); }
		$state = $this->resolver->summary();
		$counts = (array) $state['counts'];
		$download_base = admin_url( 'admin-post.php?action=' . self::DOWNLOAD_ACTION . '&_wpnonce=' . wp_create_nonce( self::NONCE ) );
		?>
		<div class="wrap" data-gloskin-media-cleanup data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-action="<?php echo esc_attr( self::AJAX_ACTION ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( self::NONCE ) ); ?>" data-revision="<?php echo esc_attr( Gloskin_Site_Core_Media_Cleanup_Resolver::REVISION ); ?>" data-status="<?php echo esc_attr( (string) $state['status'] ); ?>" data-resume-status="<?php echo esc_attr( (string) $state['resume_status'] ); ?>" data-token="<?php echo esc_attr( (string) $state['manifest_token'] ); ?>" data-cursor="<?php echo esc_attr( (string) $state['deletion_cursor'] ); ?>">
			<h1><?php echo esc_html__( 'Media Cleanup Resolver', 'gloskin-site-core' ); ?></h1>
			<p><?php echo esc_html__( 'Resolver satu kali yang fail-closed. Unattached tidak pernah otomatis berarti unused; hanya kandidat dari manifest immutable yang dapat dihapus.', 'gloskin-site-core' ); ?></p>
			<div class="notice notice-error inline"><p><strong><?php echo esc_html__( 'Permanen:', 'gloskin-site-core' ); ?></strong> <?php echo esc_html__( 'Tahap delete memakai WordPress untuk menghapus record, file asli, dan seluruh generated sizes.', 'gloskin-site-core' ); ?></p></div>
			<progress data-media-cleanup-progress value="<?php echo esc_attr( (string) $state['processed'] ); ?>" max="<?php echo esc_attr( (string) max( 1, (int) $state['total'] ) ); ?>"></progress>
			<p data-media-cleanup-stage role="status" aria-live="polite"><?php echo esc_html( (string) $state['status'] ); ?></p>
			<p data-media-cleanup-current><?php echo esc_html( (string) $state['current_file'] ); ?></p>
			<ul data-media-cleanup-counts>
				<li>Media: <strong data-count-total><?php echo esc_html( (string) $state['total'] ); ?></strong></li>
				<li>Used: <strong data-count-used><?php echo esc_html( (string) $counts['used'] ); ?></strong></li>
				<li>Protected: <strong data-count-protected><?php echo esc_html( (string) $counts['protected'] ); ?></strong></li>
				<li>Ambiguous: <strong data-count-ambiguous><?php echo esc_html( (string) $counts['ambiguous'] ); ?></strong></li>
				<li>Confirmed unused: <strong data-count-unused><?php echo esc_html( (string) $counts['confirmed-unused'] ); ?></strong></li>
				<li>Deleted / skipped / failed: <strong data-delete-counts><?php echo esc_html( count( (array) $state['deleted'] ) . ' / ' . count( (array) $state['skipped'] ) . ' / ' . count( (array) $state['failed'] ) ); ?></strong></li>
				<li>Estimated / actual bytes: <strong data-byte-counts><?php echo esc_html( size_format( (int) $state['estimated_bytes'] ) . ' / ' . size_format( (int) $state['actual_bytes'] ) ); ?></strong></li>
			</ul>
			<?php if ( ! empty( $state['warnings'] ) ) : ?><details><summary><?php echo esc_html__( 'Scanner warnings', 'gloskin-site-core' ); ?></summary><ul><?php foreach ( (array) $state['warnings'] as $warning ) : ?><li><?php echo esc_html( (string) $warning ); ?></li><?php endforeach; ?></ul></details><?php endif; ?>
			<div data-media-cleanup-error class="notice notice-error inline" hidden><p></p></div>
			<p class="submit">
				<button type="button" class="button button-primary" data-media-cleanup-index<?php echo in_array( (string) $state['status'], array( 'pending', 'indexing' ), true ) || ( 'failed' === (string) $state['status'] && in_array( (string) $state['resume_status'], array( 'pending', 'indexing' ), true ) ) ? '' : ' hidden'; ?>><?php echo esc_html__( 'Mulai / Lanjutkan Dry-run', 'gloskin-site-core' ); ?></button>
				<button type="button" class="button" data-media-cleanup-pause hidden><?php echo esc_html__( 'Pause', 'gloskin-site-core' ); ?></button>
			</p>
			<section data-media-cleanup-review<?php echo in_array( (string) $state['status'], array( 'review_ready', 'deleting', 'verifying' ), true ) || ( 'failed' === (string) $state['status'] && in_array( (string) $state['resume_status'], array( 'review_ready', 'deleting', 'verifying' ), true ) ) ? '' : ' hidden'; ?>>
				<h2><?php echo esc_html__( 'Review manifest', 'gloskin-site-core' ); ?></h2>
				<p><a class="button" href="<?php echo esc_url( $download_base . '&format=json' ); ?>"><?php echo esc_html__( 'Download JSON', 'gloskin-site-core' ); ?></a> <a class="button" href="<?php echo esc_url( $download_base . '&format=csv' ); ?>"><?php echo esc_html__( 'Download CSV', 'gloskin-site-core' ); ?></a></p>
				<table class="widefat striped"><thead><tr><th>ID</th><th>File</th><th>MIME</th><th>Date</th><th>Dimensions</th><th>Bytes</th><th>Class</th><th>Reason / references</th></tr></thead><tbody data-media-cleanup-table></tbody></table>
				<p data-media-cleanup-pagination></p>
				<label><input type="checkbox" data-media-cleanup-confirm> <?php echo esc_html__( 'Saya mengonfirmasi backup database dan uploads saat ini tersedia, dan memahami penghapusan bersifat permanen.', 'gloskin-site-core' ); ?></label>
				<p><button type="button" class="button button-primary" data-media-cleanup-delete disabled><?php echo esc_html__( 'Hapus kandidat manifest secara permanen', 'gloskin-site-core' ); ?></button></p>
			</section>
		</div>
		<?php
	}

	/** @return void */
	public function ajax() {
		if ( ! $this->current_user_is_owner() ) { wp_send_json_error( array( 'code' => 'unauthorized', 'message' => 'Access denied.', 'retryable' => false ), 403 ); }
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) { wp_send_json_error( array( 'code' => 'nonce_expired', 'message' => 'Session/nonce expired. Refresh this page before continuing.', 'retryable' => false ), 403 ); }
		$revision = isset( $_POST['revision'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['revision'] ) ) : '';
		if ( Gloskin_Site_Core_Media_Cleanup_Resolver::REVISION !== $revision ) { wp_send_json_error( array( 'code' => 'revision_mismatch', 'message' => 'Revision mismatch.', 'retryable' => false ), 409 ); }
		$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( (string) $_POST['mode'] ) ) : 'index';
		try {
			switch ( $mode ) {
				case 'index': $result = $this->resolver->index_batch(); break;
				case 'delete':
					$cursor = isset( $_POST['cursor'] ) ? absint( $_POST['cursor'] ) : 0;
					$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['token'] ) ) : '';
					$confirmed = isset( $_POST['backup_confirmed'] ) && '1' === (string) $_POST['backup_confirmed'];
					$result = $this->resolver->delete_batch( $cursor, $token, $confirmed );
					break;
				case 'verify': $result = $this->resolver->verify_batch(); break;
				case 'pause': $result = $this->resolver->pause(); break;
				case 'resume': $result = $this->resolver->resume(); break;
				case 'review': $result = $this->resolver->review_page( isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1 ); break;
				default: throw new RuntimeException( 'invalid_mode: Mode tidak dikenal.' );
			}
			wp_send_json_success( $result );
		} catch ( Throwable $error ) {
			$code = $this->error_code( $error->getMessage() );
			$retryable = in_array( $code, array( 'resolver_locked' ), true );
			wp_send_json_error( array( 'code' => $code, 'message' => $this->safe_error( $code ), 'retryable' => $retryable ), 'unauthorized' === $code ? 403 : ( $retryable ? 409 : 500 ) );
		}
	}

	/** @return void */
	public function download_manifest() {
		if ( ! $this->current_user_is_owner() ) { $this->deny(); }
		check_admin_referer( self::NONCE );
		$state = $this->resolver->summary();
		$download_status = 'failed' === (string) $state['status'] ? (string) $state['resume_status'] : (string) $state['status'];
		if ( ! in_array( $download_status, array( 'review_ready', 'deleting', 'verifying', 'consumed' ), true ) ) { wp_die( esc_html__( 'Manifest belum siap.', 'gloskin-site-core' ), '', array( 'response' => 409 ) ); }
		$format = isset( $_GET['format'] ) ? sanitize_key( wp_unslash( (string) $_GET['format'] ) ) : 'json';
		$data = $this->resolver->export_data();
		nocache_headers();
		header( 'X-Content-Type-Options: nosniff' );
		if ( 'csv' === $format ) {
			header( 'Content-Type: text/csv; charset=utf-8' ); header( 'Content-Disposition: attachment; filename="gloskin-media-cleanup-manifest.csv"' );
			$out = fopen( 'php://output', 'w' );
			fputcsv( $out, array( 'id', 'filename', 'mime', 'date', 'dimensions', 'bytes', 'classification', 'reason', 'references', 'warnings' ) );
			foreach ( (array) $data['state']['results'] as $item ) { fputcsv( $out, array( $item['id'], $item['filename'], $item['mime'], $item['date'], $item['dimensions'], $item['bytes'], $item['classification'], $item['reason'], implode( ' | ', (array) $item['references'] ), implode( ' | ', (array) $item['warnings'] ) ) ); }
			fclose( $out ); exit;
		}
		header( 'Content-Type: application/json; charset=utf-8' ); header( 'Content-Disposition: attachment; filename="gloskin-media-cleanup-manifest.json"' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ); exit;
	}

	/** @return bool */
	private function current_user_is_owner() {
		if ( ! current_user_can( self::CAPABILITY ) ) { return false; }
		$user = wp_get_current_user();
		return $user && $user->exists() && self::USER_LOGIN === (string) $user->user_login;
	}

	/** @return void */
	private function deny() { wp_die( esc_html__( 'Media Cleanup Resolver hanya tersedia untuk owner yang diizinkan.', 'gloskin-site-core' ), '', array( 'response' => 403 ) ); }

	/** @param string $message @return string */
	private function error_code( $message ) {
		foreach ( array( 'unauthorized', 'resolver_locked', 'invalid_state', 'invalid_mode', 'revision_mismatch', 'confirmation_required', 'manifest_invalid', 'manifest_failed', 'stale_manifest', 'scan_failed', 'verification_failed' ) as $code ) { if ( 0 === strpos( (string) $message, $code . ':' ) ) { return $code; } }
		return 'unexpected_error';
	}

	/** @param string $code @return string */
	private function safe_error( $code ) {
		$messages = array(
			'resolver_locked' => __( 'Resolver sedang diproses oleh request atau tab lain; akan dicoba kembali.', 'gloskin-site-core' ),
			'invalid_state' => __( 'State resolver tidak mengizinkan operasi tersebut.', 'gloskin-site-core' ),
			'confirmation_required' => __( 'Konfirmasi backup wajib sebelum penghapusan permanen.', 'gloskin-site-core' ),
			'manifest_invalid' => __( 'Manifest kandidat tidak valid atau berubah; penghapusan dihentikan.', 'gloskin-site-core' ),
			'scan_failed' => __( 'Pemindaian tidak lengkap; tidak ada kandidat yang boleh dihapus.', 'gloskin-site-core' ),
			'verification_failed' => __( 'Verifikasi akhir menemukan perubahan yang tidak aman.', 'gloskin-site-core' ),
			'unexpected_error' => __( 'Resolver gagal secara aman tanpa melanjutkan mutasi.', 'gloskin-site-core' ),
		);
		return isset( $messages[ $code ] ) ? $messages[ $code ] : $messages['unexpected_error'];
	}
}
