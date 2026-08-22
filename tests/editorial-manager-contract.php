<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$manager_path = $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-editorial-manager.php';
$js_path      = $root . '/plugin/gloskin-site-core/assets/js/gloskin-editorial-manager.js';
$css_path     = $root . '/plugin/gloskin-site-core/assets/css/gloskin-editorial-manager.css';
$kernel_path  = $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php';
$admin_path   = $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-admin-service.php';
$content_path = $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-content-service.php';

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
$content = editorial_text($content_path);

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

/* Canonical editorial schema lives once in ContentService. */
editorial_must(1 === substr_count($content, 'public static function editorial_profile('), 'ContentService owns exactly one editorial display profile');
foreach (array(
    "'gloskin_promo_active'", "'gloskin_promo_order'", "'gloskin_promo_type'",
    "'gloskin_testimonial_active'", "'gloskin_testimonial_order'", "'post_excerpt'",
    "'gloskin_achievement_active'", "'gloskin_achievement_order'", "'gloskin_achievement_feature_on_home'",
) as $profile_field) {
    editorial_must(false !== strpos($content, $profile_field), 'editorial profile field missing: ' . $profile_field);
}
editorial_must(false !== strpos($manager, 'Gloskin_Site_Core_Content_Service::editorial_profile( $post_type )'), 'EditorialManager reads canonical profile for active/order/type fields');

/* Active Testimonial must always be genuinely displayable. */
editorial_must(false !== strpos($manager, '<textarea name="quote" rows="6" required>'), 'Testimonial modal marks quote required');
editorial_must(false !== strpos($manager, "'Testimonial quote is required.'") && false !== strpos($manager, "'' === trim( \$quote )"), 'save boundary rejects empty testimonial quote');
editorial_must(false !== strpos($manager, "'Add a testimonial quote before activating this record.'"), 'toggle boundary rejects activating an empty testimonial');
editorial_must(false !== strpos($manager, "'' === \$quote && '1' === (string) get_post_meta( \$post->ID, 'gloskin_testimonial_active', true )"), 'setup normalizes historical active empty testimonials to inactive');

/* Setup identity may own only its seeds; editor-created Promo must survive reruns. */
editorial_must(false === strpos($manager, '$all_promos'), 'setup has no broad all-Promo ownership loop');
editorial_must(false === strpos($manager, "! in_array( (int) \$post_id, \$seed_ids, true )"), 'setup never deactivates arbitrary non-seed Promo');
editorial_must(false !== strpos($manager, 'foreach ( $seed_ids as $post_id )'), 'seed cleanup is bounded to known seed IDs');
editorial_must(false !== strpos($manager, 'maybe_normalize_display_state') && false !== strpos($manager, 'normalize_display_state'), 'bounded historical display normalization exists');
editorial_must(false !== strpos($manager, 'Gloskin_Site_Core_Content_Service::DEMO_IDENTITY_META') && false !== strpos($manager, "\$this->set_meta_if_changed( \$post->ID, \$active_meta, '0' )"), 'legacy identity is translated once into explicit inactive state');
editorial_must(false !== strpos($manager, "'display_contract_v1'") && false !== strpos($manager, "'complete' !== (string) ( \$state['display_contract_v1'] ?? '' )"), 'normalization is idempotently gated in existing setup state');

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
/* Media state safety: ONE guarded accessor; no unguarded state().get() chaining. */
editorial_must(false !== strpos($js, 'function getMediaSelection()'), 'one safe media-selection accessor exists');
editorial_must(false === strpos($js, 'mediaFrame.state().get('), 'no unguarded mediaFrame.state().get() call outside safe accessor');
editorial_must(false !== strpos($js, "mediaFrame.on('open', function () {\n\t\t\t\tmediaFrameActive = true;\n\t\t\t\tresetMediaSelection();"), 'reset/preselection runs inside open handler after WordPress frame lifecycle starts');
editorial_must(false === strpos($js, "}\n\t\tresetMediaSelection();\n\t\tmediaFrame.open()"), 'resetMediaSelection is not called before mediaFrame.open()');
editorial_must(false !== strpos($js, 'var selection = getMediaSelection();') && false !== strpos($js, 'typeof selection.first'), 'select callback uses safe accessor');
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

/* Promo type admin column: explicit 3-way — limited/regular/invalid — no silent ternary. */
editorial_must(false === strpos($manager, "'limited' === \$type ? __( 'Promo Terbatas'"), 'promo column uses explicit 3-way: no ternary-only pattern that hides invalid state');
editorial_must(false !== strpos($manager, "if ( 'limited' === \$type )"), 'promo column handles limited explicitly');
editorial_must(false !== strpos($manager, "} elseif ( 'regular' === \$type )"), 'promo column handles regular explicitly');
editorial_must(false !== strpos($manager, "'Type not set'"), 'promo column exposes invalid state visibly');

/* Promo type normalization: idempotent gate exists, normalizes blank/invalid → regular. */
editorial_must(false !== strpos($manager, "'promo_type_v1'"), 'promo_type_v1 gate key exists in normalization');
editorial_must(false !== strpos($manager, 'private function normalize_promo_types()'), 'normalize_promo_types() method exists');
editorial_must(false !== strpos($manager, "'limited' !== \$type && 'regular' !== \$type"), 'normalization catches non-canonical promo types');
editorial_must(false !== strpos($manager, "\$this->set_meta_if_changed( \$post->ID, 'gloskin_promo_type', 'regular' )"), 'normalization writes regular as the corrected canonical value');

/* Promo Crop & Apply stays inside the existing profile/manager/media/save owners. */
editorial_must(1 === substr_count($content, "'focus_x_meta'     => 'gloskin_promo_focus_x'"), 'Promo profile owns focus_x exactly once');
editorial_must(1 === substr_count($content, "'focus_y_meta'     => 'gloskin_promo_focus_y'"), 'Promo profile owns focus_y exactly once');
editorial_must(1 === substr_count($content, "register_percent_meta( self::PROMO_POST_TYPE, 'gloskin_promo_focus_x' )"), 'focus_x registration exists exactly once');
editorial_must(1 === substr_count($content, "register_percent_meta( self::PROMO_POST_TYPE, 'gloskin_promo_focus_y' )"), 'focus_y registration exists exactly once');
editorial_must(false !== strpos($content, "'crop_width'       => 1648") && false !== strpos($content, "'crop_height'      => 928"), 'Promo profile owns canonical 1648x928 production target');
editorial_must(false !== strpos($content, "'default'           => 50") && false !== strpos($content, "'minimum' => 0, 'maximum' => 100, 'default' => 50"), 'focus meta defaults to 50 and REST schema is range-bounded');
editorial_must(false !== strpos($content, 'return max( 0.0, min( 100.0, $value ) );'), 'server focus sanitizer clamps 0..100');
editorial_must(false !== strpos($manager, 'data-gloskin-promo-crop') && false !== strpos($manager, 'data-gloskin-promo-crop-apply') && false !== strpos($manager, 'data-gloskin-promo-crop-reset'), 'existing Promo modal contains one bounded crop stage');
editorial_must(false !== strpos($manager, "'focus_x'        => \$this->promo_focus_value") && false !== strpos($manager, "'focus_y'        => \$this->promo_focus_value"), 'normalized Promo record response includes focal state');
editorial_must(false !== strpos($manager, "\$current_image_id !== \$image_id") && false !== strpos($manager, "'Promo image must be at least %1\$d × %2\$d pixels.'"), 'new Promo replacement image is dimension-validated without blocking unchanged legacy artwork');
editorial_must(false !== strpos($manager, "update_post_meta( \$saved_id, (string) ( \$profile['focus_x_meta']") && false !== strpos($manager, "update_post_meta( \$saved_id, (string) ( \$profile['focus_y_meta']"), 'existing AJAX save persists focal state');
editorial_must(false !== strpos($js, 'function applyPromoCrop()') && false !== strpos($js, 'pointerdown') && false !== strpos($js, 'ArrowLeft'), 'crop interaction supports pointer/touch and keyboard focal adjustment');
editorial_must(false !== strpos($js, "config.postType === 'gloskin_promo'"), 'client crop behavior is explicitly Promo-profile bounded');
editorial_must(false === strpos($manager . $js, 'PromoCropManager'), 'no second Promo crop manager exists');
editorial_must(false === strpos($manager . $js, 'media_handle_sideload') || false !== strpos($manager, 'seed_attachment('), 'Crop & Apply does not introduce derivative attachment generation');
editorial_must(1 === substr_count($css, 'aspect-ratio:1648 / 928'), 'admin crop viewport owns exactly one fixed canonical ratio declaration');
editorial_must(false !== strpos($css, 'object-fit:cover') && false !== strpos($css, 'object-position:var(--gloskin-promo-focus-x,50%) var(--gloskin-promo-focus-y,50%)'), 'admin crop preview uses production cover/focal geometry');

$testimonial_profile_start = strpos($content, 'self::TESTIMONIAL_POST_TYPE => array(');
$testimonial_profile_end   = false !== $testimonial_profile_start ? strpos($content, 'self::ACHIEVEMENT_POST_TYPE => array(', $testimonial_profile_start) : false;
$testimonial_profile       = false !== $testimonial_profile_start && false !== $testimonial_profile_end ? substr($content, $testimonial_profile_start, $testimonial_profile_end - $testimonial_profile_start) : '';
editorial_must(false !== strpos($testimonial_profile, "'crop_enabled'     => false") && false !== strpos($testimonial_profile, "'focus_x_meta'     => ''") && false !== strpos($testimonial_profile, "'focus_y_meta'     => ''"), 'Testimonial profile does not inherit Promo crop state');

echo "editorial-manager-contract.php: OK (canonical schema + native manager/media/save ownership + Promo non-destructive Crop & Apply + quality guard + safe reorder + accessibility/status)\n";
