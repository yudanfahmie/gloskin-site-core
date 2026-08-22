<?php
declare(strict_types=1);

$root        = dirname( __DIR__ );
$managerPhp  = file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-editorial-manager.php' );
$managerJs   = file_get_contents( $root . '/plugin/gloskin-site-core/assets/js/gloskin-editorial-manager.js' );
$promoJs     = file_get_contents( $root . '/plugin/gloskin-site-core/assets/js/gloskin-editorial-promo-settings.js' );
$managerCss  = file_get_contents( $root . '/plugin/gloskin-site-core/assets/css/gloskin-editorial-manager.css' );
$promoCss    = file_get_contents( $root . '/plugin/gloskin-site-core/assets/css/gloskin-editorial-promo-settings.css' );
$bootstrap   = file_get_contents( $root . '/plugin/gloskin-site-core/gloskin-site-core.php' );

function promo_admin_fail( string $message ): void {
	fwrite( STDERR, "promo-admin-regression-contract.php: FAIL: {$message}\n" );
	exit( 1 );
}
function promo_admin_must( bool $condition, string $message ): void {
	if ( ! $condition ) {
		promo_admin_fail( $message );
	}
}

promo_admin_must( is_string( $managerPhp ) && is_string( $managerJs ) && is_string( $promoJs ) && is_string( $managerCss ) && is_string( $promoCss ) && is_string( $bootstrap ), 'required Promo admin owners must be readable' );

/* Server markup is the first-paint authority for list controls. */
promo_admin_must( false !== strpos( $managerPhp, 'class="gloskin-editorial-status-actions"' ), 'EditorialManager PHP must render the canonical status wrapper' );
promo_admin_must( false !== strpos( $managerPhp, 'data-gloskin-editorial-toggle' ) && false !== strpos( $managerPhp, 'data-gloskin-promo-popup-toggle' ), 'canonical wrapper must contain Active and Popup controls' );
promo_admin_must( false !== strpos( $managerCss, '.gloskin-editorial-status-actions{display:inline-flex;flex-wrap:nowrap;' ), 'status controls must be inline-flex and nowrap' );
promo_admin_must( false === strpos( $promoCss, 'gloskin-editorial-status-actions' ), 'Promo field stylesheet must not own list-control geometry' );
promo_admin_must( false !== strpos( $managerJs, "actions.className = 'gloskin-editorial-status-actions'" ), 'dynamic rows must create the same canonical status wrapper' );

/* Popup extension is field/UX only: no post-paint DOM repair, duplicate readiness authority, or modal-init owner. */
foreach ( array( 'MutationObserver', 'statusActions(', 'consumeAutoOpenIntent', 'pendingSave', 'setTimeout(', 'function readinessIssue(' ) as $forbidden ) {
	promo_admin_must( false === strpos( $promoJs, $forbidden ), 'Promo extension must not retain duplicate owner: ' . $forbidden );
}
promo_admin_must( false === strpos( $bootstrap, 'gloskin_site_core_guard_editorial_modal_autostart' ), 'bootstrap corrective modal hide/open guard must stay removed' );
promo_admin_must( false !== strpos( $managerPhp, 'data-gloskin-editorial-modal hidden' ), 'normal list load must render the modal hidden at first paint' );
promo_admin_must( false !== strpos( $managerJs, 'function initializeModalIntent()' ) && false !== strpos( $managerJs, 'window.history.replaceState' ), 'EditorialManager must own one-shot deep-link opening and URL normalization' );

/* One canonical popup readiness rule is shared by Save and Toggle. */
promo_admin_must( 3 === substr_count( $managerPhp, 'promo_popup_validation_issue' ), 'popup readiness helper must have one definition and exactly two call sites' );
promo_admin_must( false !== strpos( $managerPhp, "'code'    => 'popup_incomplete'" ), 'incomplete popup state must be a structured backend validation response' );
foreach ( array( "'field'   => 'image'", "'field'   => 'destination'", "'field'   => 'pages'" ) as $needle ) {
	promo_admin_must( false !== strpos( $managerPhp, $needle ), 'backend must identify the first missing popup field: ' . $needle );
}
$offPos   = strpos( $managerPhp, "if ( ! \$active )" );
$issuePos = strpos( $managerPhp, '$popup_issue = $this->promo_popup_validation_issue', false === $offPos ? 0 : $offPos );
promo_admin_must( false !== $offPos && false !== $issuePos && $offPos < $issuePos, 'Popup Off must persist before any prerequisite validation' );
promo_admin_must( false !== strpos( $managerPhp, "update_post_meta( \$post_id, Gloskin_Site_Core_Promo_Modal::POPUP_META, '0' );" ), 'Popup Off must immediately persist canonical OFF state' );
promo_admin_must( false !== strpos( $promoJs, "data.append('action', 'gloskin_editorial_toggle')" ) && false !== strpos( $promoJs, "data.append('field', 'popup')" ), 'Popup client must use the existing canonical toggle endpoint/field' );
promo_admin_must( false !== strpos( $promoJs, "ajaxPopup(id, enable).then" ), 'Popup list toggle must always consult the backend before changing canonical state' );
promo_admin_must( false !== strpos( $promoJs, "error.code === 'popup_incomplete'" ), 'structured incomplete response must open guided editor instead of surfacing generic HTTP 400' );
promo_admin_must( false === strpos( $promoJs, 'if (popupField) { popupField.checked = true; }' ), 'guided editor must never invent a pending Popup On state' );

/* Destination URL is one field and is conditional on canonical popup state. */
promo_admin_must( 1 === substr_count( $managerPhp, 'name="destination_url"' ), 'there must be one Destination URL field' );
promo_admin_must( false === strpos( $managerPhp, 'name="destination_url" required' ), 'Destination URL must not be statically required in PHP' );
promo_admin_must( false !== strpos( $promoJs, 'destinationField.required = enabled;' ), 'Popup checkbox must immediately control the Destination URL required state' );
promo_admin_must( false !== strpos( $promoJs, 'popupField.checked = !!record.popup_enabled;' ), 'editor open must hydrate popup state from the canonical record' );
promo_admin_must( false !== strpos( $managerPhp, "if ( '' !== \$destination_raw && '' === \$destination_url )" ), 'optional non-empty Destination URL must still be validated' );

/* Crop geometry may only synchronize against a measurable visible viewport. */
promo_admin_must( false !== strpos( $managerJs, 'function refreshCropLayout()' ), 'one canonical crop layout refresh function must exist' );
promo_admin_must( false !== strpos( $managerJs, 'if (modal && modal.hidden) { return false; }' ), 'crop refresh must not calculate final geometry while modal is hidden' );
promo_admin_must( false !== strpos( $managerJs, 'if (!rect.width || !rect.height) { return null; }' ), 'crop refresh must reject zero-size viewport geometry' );
promo_admin_must( false !== strpos( $managerJs, 'promoCropSource.onload = imageReady;' ), 'image load must participate in the canonical refresh lifecycle' );
promo_admin_must( false !== strpos( $managerJs, 'new ResizeObserver(function () { refreshCropLayout(); })' ), 'viewport resize must reuse the same crop refresh path' );
promo_admin_must( false !== strpos( $managerJs, 'cropState.appliedX = cropState.draftX;' ) && false !== strpos( $managerJs, 'cropState.appliedZoom = cropState.draftZoom;' ), 'saved focus and zoom must hydrate as applied state' );
promo_admin_must( false === strpos( $managerJs, 'setTimeout(' ), 'crop/modal synchronization must not use arbitrary delay polling' );

echo "promo-admin-regression-contract.php: OK (server list owner, backend popup authority, canonical modal state, conditional popup URL, persisted crop sync, zero flicker)\n";
