<?php
declare(strict_types=1);

$root       = dirname( __DIR__ );
$managerPhp = file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-editorial-manager.php' );
$managerJs  = file_get_contents( $root . '/plugin/gloskin-site-core/assets/js/gloskin-editorial-manager.js' );
$promoJs    = file_get_contents( $root . '/plugin/gloskin-site-core/assets/js/gloskin-editorial-promo-settings.js' );
$managerCss = file_get_contents( $root . '/plugin/gloskin-site-core/assets/css/gloskin-editorial-manager.css' );
$promoCss   = file_get_contents( $root . '/plugin/gloskin-site-core/assets/css/gloskin-editorial-promo-settings.css' );

function promo_admin_fail( string $message ): void {
	fwrite( STDERR, "promo-admin-regression-contract.php: FAIL: {$message}\n" );
	exit( 1 );
}
function promo_admin_must( bool $condition, string $message ): void {
	if ( ! $condition ) {
		promo_admin_fail( $message );
	}
}

promo_admin_must( is_string( $managerPhp ) && is_string( $managerJs ) && is_string( $promoJs ) && is_string( $managerCss ) && is_string( $promoCss ), 'Promo admin owners must be readable' );

/* List controls: PHP owns first paint; reconciled JS rows reproduce the same wrapper. */
promo_admin_must( false !== strpos( $managerPhp, 'class="gloskin-editorial-status-actions"' ), 'PHP must render the canonical Active / Popup wrapper' );
promo_admin_must( false !== strpos( $managerPhp, 'data-gloskin-editorial-toggle' ) && false !== strpos( $managerPhp, 'data-gloskin-promo-popup-toggle' ), 'Active and Popup controls must remain siblings' );
promo_admin_must( false !== strpos( $managerCss, '.gloskin-editorial-status-actions{display:inline-flex;flex-wrap:nowrap;' ), 'status actions must remain one compact row' );
promo_admin_must( false !== strpos( $managerJs, "actions.className = 'gloskin-editorial-status-actions'" ), 'dynamic rows must use the same status wrapper' );

/* Modal lifecycle: nothing may auto-open during list initialization. */
promo_admin_must( false !== strpos( $managerPhp, 'data-gloskin-editorial-modal hidden' ), 'server first paint must be hidden' );
promo_admin_must( false === strpos( $managerJs, 'function initializeModalIntent(' ), 'startup modal-intent controller must not exist' );
promo_admin_must( false === strpos( $managerJs, 'initializeModalIntent();' ), 'page boot must not invoke modal opening' );
promo_admin_must( false !== strpos( $managerJs, 'if (modal) { modal.hidden = true; }' ), 'manager boot must normalize the modal to hidden' );
promo_admin_must( false !== strpos( $managerJs, "var edit = event.target.closest('[data-gloskin-editorial-edit]" ), 'Edit click remains an explicit open owner' );
promo_admin_must( false !== strpos( $managerJs, "var add = event.target.closest('.page-title-action" ), 'Add click remains an explicit open owner' );

/* Popup OFF is a hard disclosure boundary. Guidance never reveals hidden fields. */
promo_admin_must( false === strpos( $promoJs, 'guidanceMode' ), 'guidanceMode must stay removed' );
promo_admin_must( false === strpos( $promoJs, 'revealOptions' ), 'no secondary reveal state may bypass Popup OFF' );
promo_admin_must( false !== strpos( $promoJs, 'popupOptions.hidden = !enabled;' ), 'Popup OFF must always hide popup options' );
promo_admin_must( false !== strpos( $promoJs, "strong.textContent = 'Popup is still off';" ), 'incomplete list toggle must explain canonical OFF state' );
promo_admin_must( false !== strpos( $promoJs, 'popupField.focus()' ), 'guided completion must focus the Popup checkbox, not a hidden field' );
promo_admin_must( false === strpos( $promoJs, 'popupField.checked = true' ), 'client must never invent a pending Popup ON state' );

/* Visibility matrix: Homepage has no custom URL UI; other popup scopes do. */
promo_admin_must( false !== strpos( $promoJs, "var homepage = visibility === 'homepage';" ), 'Homepage visibility must be an explicit UI state' );
promo_admin_must( false !== strpos( $promoJs, 'var needsDestination = enabled && !homepage;' ), 'destination is relevant only to enabled non-Homepage popup modes' );
promo_admin_must( false !== strpos( $promoJs, 'destinationWrap.hidden = !needsDestination;' ), 'Homepage must hide the custom destination field' );
promo_admin_must( false !== strpos( $promoJs, 'destinationField.required = needsDestination;' ), 'hidden Homepage destination must not be required' );
promo_admin_must( false !== strpos( $promoJs, "destinationField.value = '/';" ), 'Homepage must persist the canonical internal root target without operator input' );
promo_admin_must( false !== strpos( $promoJs, "var specific = enabled && visibility === 'specific_pages';" ), 'Specific Pages must be gated by Popup ON' );
promo_admin_must( false !== strpos( $promoJs, 'pageSelect.required = specific;' ), 'Specific Pages must require at least one page only in that mode' );
promo_admin_must( false !== strpos( $managerPhp, "if ( '' !== \$destination_raw && '' === \$destination_url )" ), 'non-empty destination values must still be sanitized by the backend' );

/* Popup toggling remains one existing endpoint with backend authority. */
promo_admin_must( false !== strpos( $promoJs, "data.append('action', 'gloskin_editorial_toggle')" ), 'Popup must use the canonical toggle endpoint' );
promo_admin_must( false !== strpos( $promoJs, "data.append('field', 'popup')" ), 'Popup must use the canonical toggle field' );
promo_admin_must( false !== strpos( $promoJs, "error.code === 'popup_incomplete'" ), 'structured incomplete response must route to guided edit' );
$offPos   = strpos( $managerPhp, "if ( ! \$active )" );
$issuePos = strpos( $managerPhp, '$popup_issue = $this->promo_popup_validation_issue', false === $offPos ? 0 : $offPos );
promo_admin_must( false !== $offPos && false !== $issuePos && $offPos < $issuePos, 'Popup OFF must persist before prerequisite validation' );

/* Crop regression: saved geometry is visible and measurable without arbitrary delays. */
promo_admin_must( false !== strpos( $managerJs, 'function refreshCropLayout()' ), 'canonical crop refresh path must remain' );
promo_admin_must( false !== strpos( $managerJs, 'if (modal && modal.hidden) { return false; }' ), 'crop must not calculate against hidden modal geometry' );
promo_admin_must( false !== strpos( $managerJs, 'if (!rect.width || !rect.height) { return null; }' ), 'crop must reject zero-size viewport geometry' );
promo_admin_must( false !== strpos( $managerJs, 'new ResizeObserver(function () { refreshCropLayout(); })' ), 'resize must reuse canonical crop refresh' );
promo_admin_must( false === strpos( $managerJs, 'setTimeout(' ), 'modal/crop synchronization must not use arbitrary delay polling' );
promo_admin_must( false !== strpos( $managerCss, 'border:2px solid #2271b1' ), 'crop selection must remain visibly bounded' );
promo_admin_must( false !== strpos( $managerCss, '.gloskin-editorial-crop__handle--nw{top:6px;left:6px;' ), 'crop handles must stay inside the viewport' );

echo "promo-admin-regression-contract.php: OK (closed init, strict Popup OFF disclosure, Homepage no-custom-URL state, visible crop)\n";