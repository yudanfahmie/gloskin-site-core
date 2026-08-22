<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$manager_path = $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-editorial-manager.php';
$js_path      = $root . '/plugin/gloskin-site-core/assets/js/gloskin-editorial-manager.js';
$css_path     = $root . '/plugin/gloskin-site-core/assets/css/gloskin-editorial-manager.css';
$kernel_path  = $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php';
$admin_path   = $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-admin-service.php';

function editorial_fail(string $message): void {
    fwrite(STDERR, "editorial-manager-contract.php: FAIL: {$message}\n");
    exit(1);
}
function editorial_must(bool $condition, string $message): void {
    if (!$condition) {
        editorial_fail($message);
    }
}
function editorial_text(string $path): string {
    $value = @file_get_contents($path);
    if (false === $value) {
        editorial_fail('cannot read ' . $path);
    }
    return $value;
}

$manager = editorial_text($manager_path);
$js      = editorial_text($js_path);
$css     = editorial_text($css_path);
$kernel  = editorial_text($kernel_path);
$admin   = editorial_text($admin_path);

editorial_must(1 === substr_count($kernel, 'new Gloskin_Site_Core_Editorial_Manager'), 'EditorialManager is instantiated exactly once');
editorial_must(1 === substr_count($kernel, '$editorial_manager->register();'), 'EditorialManager is registered exactly once');
editorial_must(1 === substr_count($manager, "add_action( 'admin_footer', array( \$this, 'render_modal' ) );"), 'render_modal has exactly one admin_footer owner');
editorial_must(false === strpos($manager, 'admin_footer-edit.php'), 'old admin_footer-edit.php owner is absent');
editorial_must(false !== strpos($manager, "'edit' !== \$screen->base") && false !== strpos($manager, "\$this->is_managed_type( (string) \$screen->post_type )"), 'modal/assets retain managed-list screen guard');

$managed_start = strpos($manager, 'private function is_managed_type');
$managed_end   = false !== $managed_start ? strpos($manager, 'private function active_meta_key', $managed_start) : false;
$managed       = false !== $managed_start && false !== $managed_end ? substr($manager, $managed_start, $managed_end - $managed_start) : '';
editorial_must(false !== strpos($managed, 'PROMO_POST_TYPE') && false !== strpos($managed, 'TESTIMONIAL_POST_TYPE'), 'managed profiles contain Promo and Testimonial');
foreach (array('ACHIEVEMENT_POST_TYPE', 'TREATMENT_POST_TYPE', 'CLINIC_POST_TYPE', 'DOCTOR_POST_TYPE', "'page'") as $forbidden_profile) {
    editorial_must(false === strpos($managed, $forbidden_profile), 'managed profiles stay bounded: ' . $forbidden_profile);
}

editorial_must(1 === substr_count($manager, 'wp_enqueue_media();'), 'WordPress Media Library is enqueued once');
editorial_must(false !== strpos($manager, "array( 'jquery', 'jquery-ui-sortable', 'media-editor' )"), 'EditorialManager explicitly depends on WordPress media-editor');
editorial_must(1 === substr_count($manager, "wp_enqueue_style( 'gloskin-editorial-manager'"), 'one shared EditorialManager CSS owner');
editorial_must(1 === substr_count($manager, "wp_enqueue_script( 'gloskin-editorial-manager'"), 'one shared EditorialManager JS owner');
editorial_must(1 === substr_count($js, 'var mediaFrame = null;') && false !== strpos($js, 'if (!mediaFrame)'), 'one lazy reusable media frame owner');
editorial_must(1 === substr_count($js, 'wp.media({'), 'one native wp.media frame constructor');
editorial_must(false !== strpos($manager, "'mediaUnavailable'") && false !== strpos($js, "label('mediaUnavailable'"), 'media initialization failure uses the shared live status region');
editorial_must(false === strpos($js, 'alert('), 'media failure does not create an alert owner');
editorial_must(1 === substr_count($js, 'var mediaFrameActive = false;'), 'one media foreground state owner');
editorial_must(false !== strpos($js, "mediaFrame.on('open'") && false !== strpos($js, 'mediaFrameActive = true;'), 'native media open owns foreground state');
editorial_must(false !== strpos($js, "mediaFrame.on('close'") && false !== strpos($js, 'mediaFrameActive = false;'), 'native media close releases foreground state');
editorial_must(false !== strpos($js, 'if (mediaFrameActive) { return; }'), 'editorial keyboard trap bypasses while WordPress media is foreground');
editorial_must(false !== strpos($js, 'mediaTrigger.focus()'), 'focus returns to the media trigger after native frame close');
foreach (array('gloskin_editorial_upload', 'customUploader', 'PromoMediaFrame', 'TestimonialMediaFrame') as $forbidden_media_owner) {
    editorial_must(false === strpos($manager . $js, $forbidden_media_owner), 'no parallel/custom media owner: ' . $forbidden_media_owner);
}

foreach (array('MutationObserver', 'setInterval(', 'setTimeout(') as $forbidden_lifecycle) {
    editorial_must(false === strpos($js, $forbidden_lifecycle), 'no DOM retry lifecycle: ' . $forbidden_lifecycle);
}
foreach (array('promo_list_columns', 'testimonial_list_columns', 'render_promo_meta_box', 'render_testimonial_meta_box') as $legacy_admin) {
    editorial_must(false === strpos($admin, $legacy_admin), 'AdminService has no legacy editorial owner: ' . $legacy_admin);
}

editorial_must(false !== strpos($manager, 'get_delete_post_link(') && false !== strpos($js, 'record.trash_url'), 'new AJAX rows use native WordPress Trash URL');
editorial_must(false === strpos($manager . $js, 'gloskin_editorial_delete'), 'no custom editorial delete AJAX owner');
editorial_must(false !== strpos($js, 'requestedId > 0 && !records[String(requestedId)]'), 'invalid Edit ID fails closed before populate');
editorial_must(false !== strpos($js, 'normalizeModalQuery();'), 'invalid/direct modal query can be normalized in-place');
editorial_must(false === strpos($js, 'window.location.reload'), 'successful save path never needs full-page reload');

editorial_must(false !== strpos($manager, "'canReorder' => \$can_reorder") && false !== strpos($manager, 'private function can_reorder_list('), 'server owns one canReorder decision');
editorial_must(false !== strpos($manager, 'private function canonical_reorder_ids('), 'server validates complete canonical reorder collection');
editorial_must(false !== strpos($js, '!config.canReorder'), 'client disables reorder when server says list is unsafe');
editorial_must(false !== strpos($js, "event.key !== 'Tab'") && false !== strpos($js, 'focusableElements('), 'modal has bounded Tab focus trap');
editorial_must(false !== strpos($manager, 'data-gloskin-editorial-status') && false !== strpos($manager, 'aria-live="polite"'), 'one shared status/live region exists');
editorial_must(false !== strpos($js, 'activeUpdated') && false !== strpos($js, 'activeFailed') && false !== strpos($js, 'reorderSaved') && false !== strpos($js, 'reorderFailed'), 'toggle/reorder success and failures surface status');
editorial_must(false !== strpos($css, '@media (max-width:782px)') && false !== strpos($css, '[aria-disabled="true"]'), 'shared CSS keeps mobile modal and disabled-reorder presentation');

foreach (array('PromoManager', 'TestimonialManager') as $forked_owner) {
    editorial_must(false === strpos($manager . $js, $forked_owner), 'no forked manager owner: ' . $forked_owner);
}

echo "editorial-manager-contract.php: OK (lifecycle + bounded profiles + native media dependency/frame/keyboard + native delete + fail-closed edit + safe reorder + accessibility/status)\n";
