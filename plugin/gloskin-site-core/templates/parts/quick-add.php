<?php
/**
 * SP-004 Gloskin Quick Add modal shell.
 *
 * Structural markup only -- content is fetched lazily (SP-004 "do not
 * preload full variation payloads for every product card") and injected
 * by gloskin-ui1-core.js once opened. Uses the same overlay contract
 * (data-gloskin-overlay/data-gloskin-overlay-close) as Search/Auth/Cart/
 * Wishlist, so the existing single-overlay controller owns focus/scroll
 * lock without any new plumbing.
 *
 * @package GloskinSiteCore
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="gloskin-ui1-quickadd" id="gloskin-quickadd-overlay" data-gloskin-overlay="quickadd" aria-hidden="true" hidden>
	<button class="gloskin-ui1-quickadd__backdrop" type="button" data-gloskin-overlay-close aria-label="<?php echo esc_attr__( 'Tutup pilih varian', 'gloskin-site-core' ); ?>"></button>
	<div class="gloskin-ui1-quickadd__panel" role="dialog" aria-modal="true" aria-labelledby="gloskin-quickadd-title">
		<div class="gloskin-ui1-quickadd__head">
			<strong id="gloskin-quickadd-title"><?php echo esc_html__( 'Pilih Varian', 'gloskin-site-core' ); ?></strong>
			<button class="gloskin-ui1-quickadd__close" type="button" data-gloskin-overlay-close aria-label="<?php echo esc_attr__( 'Tutup pilih varian', 'gloskin-site-core' ); ?>"><svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true" focusable="false"><path d="M4 4l10 10M14 4 4 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></button>
		</div>
		<div class="gloskin-ui1-quickadd__body" data-gloskin-quickadd-body aria-live="polite">
			<div class="gloskin-ui1-quickadd__loading"><span><?php echo esc_html__( 'Memuat…', 'gloskin-site-core' ); ?></span></div>
		</div>
	</div>
</div>
