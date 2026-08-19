<?php
/**
 * Preserve the approved Why Gloskin composition while replacing its historical
 * decorative treatment placeholder with the migration-owned local home_why
 * editorial asset. The generic editorial renderer retains catastrophic fallback.
 *
 * @package GloskinSiteCore
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

ob_start();
gloskin_ui1_render_why_gloskin( $gloskin_context['page'] );
$gloskin_why_html = (string) ob_get_clean();

ob_start();
gloskin_ui1_render_presentation_media( 'treatment', 'why-gloskin-primary', 'gloskin-ui1-why__primary-image' );
$gloskin_why_legacy_media = (string) ob_get_clean();

ob_start();
gloskin_ui1_render_editorial_media( 'editorial', 'home_why', 'gloskin-ui1-why__primary-image' );
$gloskin_why_local_media = (string) ob_get_clean();

$gloskin_why_replacements = 0;
if ( '' !== $gloskin_why_legacy_media && '' !== $gloskin_why_local_media ) {
	$gloskin_why_html = str_replace( $gloskin_why_legacy_media, $gloskin_why_local_media, $gloskin_why_html, $gloskin_why_replacements );
}
if ( 1 !== $gloskin_why_replacements ) {
	/* Fail closed to the approved existing composition if its exact internal
	 * marker ever changes; no alternate invented visual is introduced here. */
	ob_start();
	gloskin_ui1_render_why_gloskin( $gloskin_context['page'] );
	$gloskin_why_html = (string) ob_get_clean();
}

echo $gloskin_why_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- composed from existing escaped renderers.
unset( $gloskin_why_html, $gloskin_why_legacy_media, $gloskin_why_local_media, $gloskin_why_replacements );
