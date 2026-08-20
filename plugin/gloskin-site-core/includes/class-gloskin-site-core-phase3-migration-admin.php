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

	/**
	 * Runner JS is rendered inline in render_page(); no external asset file needed.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		// Inline JS rendered in render_page() — no external file to enqueue.
	}

	/** @return void */
	public function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Izin tidak cukup.', 'gloskin-site-core' ) );
		}
		$state       = $this->migration->get_state();
		$is_complete = 'complete' === $state['status'];
		$ajax_url    = admin_url( 'admin-ajax.php' );
		$nonce       = wp_create_nonce( self::NONCE );
		$audit       = $state['audit'] ?? array();
		$media_audit = $audit['media'] ?? array();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Gloskin Phase 3 Migration', 'gloskin-site-core' ); ?></h1>
			<p><?php esc_html_e( 'FB-989354 (Skincare) & FB-989360 (Treatment) — Rekonsiliasi aset dan data produk klien.', 'gloskin-site-core' ); ?></p>

			<!-- Status table — updated in-place by the JS runner -->
			<table class="form-table" role="presentation">
				<tr>
					<th><?php esc_html_e( 'Status', 'gloskin-site-core' ); ?></th>
					<td><code id="p3-status"><?php echo esc_html( $state['status'] ); ?></code></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Langkah', 'gloskin-site-core' ); ?></th>
					<td>
						<span id="p3-step-counter"><?php echo esc_html( ( $state['step_number'] ?? 1 ) . '/' . ( $state['total_steps'] ?? '?' ) ); ?></span>
						&nbsp;&mdash;&nbsp;
						<span id="p3-current-step"><?php echo esc_html( $state['current_step'] ?? '' ); ?></span>
					</td>
				</tr>
				<tr id="p3-media-row" style="<?php echo ( 'media_reconcile' === ( $state['current_step'] ?? '' ) ) ? '' : 'display:none;'; ?>">
					<th><?php esc_html_e( 'Media', 'gloskin-site-core' ); ?></th>
					<td>
						<span id="p3-media-counter"><?php echo esc_html( ( $state['media_cursor'] ?? 0 ) . '/' . ( $state['media_total'] ?? 0 ) ); ?></span>
						&nbsp;|&nbsp;
						<?php esc_html_e( 'Imported', 'gloskin-site-core' ); ?>: <span id="p3-imported"><?php echo (int) ( $media_audit['imported'] ?? 0 ); ?></span>&nbsp;
						<?php esc_html_e( 'Reused', 'gloskin-site-core' ); ?>: <span id="p3-reused"><?php echo (int) ( $media_audit['reused'] ?? 0 ); ?></span>&nbsp;
						<?php esc_html_e( 'Recovered', 'gloskin-site-core' ); ?>: <span id="p3-recovered"><?php echo (int) ( $media_audit['recovered'] ?? 0 ); ?></span>&nbsp;
						<?php esc_html_e( 'Skipped', 'gloskin-site-core' ); ?>: <span id="p3-skipped"><?php echo (int) ( $media_audit['skipped'] ?? 0 ); ?></span>
						<br><em id="p3-last-action"><?php echo esc_html( $state['media_last_action'] ?? '' ); ?></em>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Progres', 'gloskin-site-core' ); ?></th>
					<td>
						<div style="background:#ddd;border-radius:4px;height:20px;width:300px;overflow:hidden;">
							<div id="p3-progress-bar" style="background:#0073aa;height:100%;width:<?php echo esc_attr( (int) ( $state['progress_percent'] ?? 0 ) ); ?>%;transition:width 0.3s;"></div>
						</div>
						<span id="p3-progress-pct"><?php echo esc_html( ( $state['progress_percent'] ?? 0 ) . '%' ); ?></span>
					</td>
				</tr>
			</table>

			<!-- Completion notice -->
			<div id="p3-complete" class="notice notice-success inline" style="<?php echo $is_complete ? '' : 'display:none;'; ?>">
				<p><?php esc_html_e( 'Phase 3 migration selesai.', 'gloskin-site-core' ); ?></p>
			</div>

			<!-- In-place error display (spec J) -->
			<div id="p3-error-wrap" style="display:none;margin-top:12px;">
				<div class="notice notice-error inline">
					<p><strong><?php esc_html_e( 'Error', 'gloskin-site-core' ); ?>:</strong> <span id="p3-error-message"></span></p>
					<p><?php esc_html_e( 'Migrasi dihentikan aman pada checkpoint terakhir.', 'gloskin-site-core' ); ?></p>
				</div>
				<button id="p3-retry" class="button button-secondary" style="margin-top:8px;"><?php esc_html_e( 'Coba Lagi', 'gloskin-site-core' ); ?></button>
			</div>

			<!-- Action buttons -->
			<div id="p3-btn-wrap" style="margin-top:16px;<?php echo $is_complete ? 'display:none;' : ''; ?>">
				<?php if ( 'pending' === $state['status'] ) : ?>
					<button id="p3-start" class="button button-primary"><?php esc_html_e( 'Mulai Phase 3', 'gloskin-site-core' ); ?></button>
				<?php else : ?>
					<button id="p3-continue" class="button button-primary"><?php esc_html_e( 'Lanjutkan', 'gloskin-site-core' ); ?></button>
				<?php endif; ?>
			</div>

			<!-- Audit JSON display -->
			<?php if ( ! empty( $audit ) ) : ?>
				<h2><?php esc_html_e( 'Audit', 'gloskin-site-core' ); ?></h2>
				<pre id="p3-audit-json" style="background:#f6f7f7;padding:12px;overflow:auto;max-height:400px;"><?php echo esc_html( wp_json_encode( $audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></pre>
			<?php else : ?>
				<pre id="p3-audit-json" style="display:none;background:#f6f7f7;padding:12px;overflow:auto;max-height:400px;"></pre>
			<?php endif; ?>
		</div>
		<script>
		/* Gloskin Phase 3 — serial auto-continuing runner (spec E/F/G/H/J/K) */
		(function () {
			'use strict';
			var AJAX_URL = <?php echo wp_json_encode( $ajax_url ); ?>;
			var ACTION   = <?php echo wp_json_encode( self::AJAX_ACTION ); ?>;
			var NONCE    = <?php echo wp_json_encode( $nonce ); ?>;
			var running  = false; /* single-flight guard (spec H) */
			var lastMode = 'continue';

			function el(id) { return document.getElementById(id); }

			/* Update every in-place UI element from the AJAX response data (spec G). */
			function updateUI(data) {
				if (!data) return;
				if (el('p3-status'))       el('p3-status').textContent       = data.status || '';
				if (el('p3-step-counter')) el('p3-step-counter').textContent  = (data.step_number || '') + '/' + (data.total_steps || '');
				if (el('p3-current-step')) el('p3-current-step').textContent  = data.current_step || '';
				if (el('p3-progress-bar')) el('p3-progress-bar').style.width  = (data.progress_percent || 0) + '%';
				if (el('p3-progress-pct')) el('p3-progress-pct').textContent  = (data.progress_percent || 0) + '%';

				var isMedia = (data.current_step === 'media_reconcile');
				var mediaRow = el('p3-media-row');
				if (mediaRow) mediaRow.style.display = isMedia ? '' : 'none';
				if (isMedia) {
					if (el('p3-media-counter')) el('p3-media-counter').textContent = (data.media_cursor || 0) + '/' + (data.media_total || 0);
					if (el('p3-last-action'))   el('p3-last-action').textContent   = data.media_last_action || '';
					var ma = (data.audit && data.audit.media) ? data.audit.media : {};
					if (el('p3-imported'))  el('p3-imported').textContent  = ma.imported  || 0;
					if (el('p3-reused'))    el('p3-reused').textContent    = ma.reused    || 0;
					if (el('p3-recovered')) el('p3-recovered').textContent = ma.recovered || 0;
					if (el('p3-skipped'))   el('p3-skipped').textContent   = ma.skipped   || 0;
				}

				if (data.audit && el('p3-audit-json')) {
					el('p3-audit-json').textContent  = JSON.stringify(data.audit, null, 2);
					el('p3-audit-json').style.display = '';
				}
			}

			/* Display in-place error and expose retry button (spec J). */
			function showError(httpStatus, stepName, mediaCursor, serverMsg) {
				running = false;
				var msgEl = el('p3-error-message');
				if (msgEl) {
					msgEl.textContent = 'HTTP ' + httpStatus
						+ ' | Langkah: ' + (stepName || '—')
						+ ' | Media #' + mediaCursor
						+ ' | ' + (serverMsg || '<?php echo esc_js( __( 'Kesalahan tidak diketahui.', 'gloskin-site-core' ) ); ?>');
				}
				if (el('p3-error-wrap')) el('p3-error-wrap').style.display = '';
				var btn = el('p3-start') || el('p3-continue');
				if (btn) { btn.disabled = false; btn.textContent = '<?php echo esc_js( __( 'Lanjutkan', 'gloskin-site-core' ) ); ?>'; }
			}

			/* Send one AJAX request; retry up to 2 times on 5xx or network error (spec H). */
			function doRequest(mode, attempt) {
				if (running) return; /* single-flight guard */
				running  = true;
				lastMode = mode;
				attempt  = attempt || 1;

				if (el('p3-error-wrap')) el('p3-error-wrap').style.display = 'none';
				var btn = el('p3-start') || el('p3-continue');
				if (btn) { btn.disabled = true; btn.textContent = '<?php echo esc_js( __( 'Memproses…', 'gloskin-site-core' ) ); ?>'; }

				var body = 'action='      + encodeURIComponent(ACTION)
				         + '&_ajax_nonce=' + encodeURIComponent(NONCE)
				         + '&mode='        + encodeURIComponent(mode);

				var xhr = new XMLHttpRequest();
				xhr.open('POST', AJAX_URL, true);
				xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
				xhr.timeout = 120000;

				xhr.onload = function () {
					if (xhr.status >= 500 || xhr.status === 0) {
						running = false;
						if (attempt < 3) {
							setTimeout(function () { doRequest(mode, attempt + 1); }, attempt === 1 ? 1000 : 3000);
							return;
						}
						var cs  = el('p3-current-step') ? el('p3-current-step').textContent : '';
						var mc  = el('p3-media-counter') ? el('p3-media-counter').textContent.split('/')[0] : '0';
						showError(xhr.status, cs, mc, '<?php echo esc_js( __( 'Server error setelah percobaan ulang.', 'gloskin-site-core' ) ); ?>');
						return;
					}

					var json;
					try { json = JSON.parse(xhr.responseText); } catch(e) { json = null; }

					if (!json || !json.success) {
						running = false;
						var errMsg = json && json.data ? String(json.data) : xhr.responseText.substring(0, 300);
						var cs2    = el('p3-current-step') ? el('p3-current-step').textContent : '';
						var mc2    = el('p3-media-counter') ? el('p3-media-counter').textContent.split('/')[0] : '0';
						showError(xhr.status, cs2, mc2, errMsg);
						return;
					}

					var data = json.data;
					updateUI(data);
					running = false;

					if (data.status === 'complete') {
						if (el('p3-complete')) el('p3-complete').style.display = '';
						if (el('p3-btn-wrap')) el('p3-btn-wrap').style.display = 'none';
						return;
					}
					if (data.status === 'failed') {
						showError(200, data.current_step || '', data.media_cursor || 0, data.last_error || '<?php echo esc_js( __( 'Kesalahan migrasi.', 'gloskin-site-core' ) ); ?>');
						return;
					}

					/* Auto-continue — 300 ms pause between requests (spec F). */
					setTimeout(function () { doRequest('continue', 1); }, 300);
				};

				xhr.onerror = xhr.ontimeout = function () {
					running = false;
					if (attempt < 3) {
						setTimeout(function () { doRequest(mode, attempt + 1); }, attempt === 1 ? 1000 : 3000);
						return;
					}
					var cs3 = el('p3-current-step') ? el('p3-current-step').textContent : '';
					var mc3 = el('p3-media-counter') ? el('p3-media-counter').textContent.split('/')[0] : '0';
					showError(0, cs3, mc3, '<?php echo esc_js( __( 'Gagal terhubung ke server.', 'gloskin-site-core' ) ); ?>');
				};

				xhr.send(body);
			}

			var startBtn    = el('p3-start');
			var continueBtn = el('p3-continue');
			var retryBtn    = el('p3-retry');
			if (startBtn)    startBtn.addEventListener('click',    function () { doRequest('start',    1); });
			if (continueBtn) continueBtn.addEventListener('click', function () { doRequest('continue', 1); });
			if (retryBtn)    retryBtn.addEventListener('click',    function () {
				if (el('p3-error-wrap')) el('p3-error-wrap').style.display = 'none';
				doRequest(lastMode, 1);
			});
		}());
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
