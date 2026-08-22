<?php
declare(strict_types=1);

/**
 * UX / Content closure contract — v0.7.221
 *
 * Verifies the static-analysis side of every change shipped in the ABSOLUTE
 * FINAL UX / CONTENT CLOSURE directive:
 *
 *  1. Button cursor:pointer (core-base.css)
 *  2. Insights archive bottom rhythm (editorial.css)
 *  3. Insight single typographic hardening (editorial.css)
 *  4. Treatment single glint coverage (editorial.css)
 *  5. Home all treatments — no display cap
 *  6. Insights AJAX progressive pagination (core.js + REST route)
 *  7. Contact split blush hero + real form owner
 *  8. Version 0.7.221
 */

$root     = dirname( __DIR__ );
$plugin   = $root . '/plugin/gloskin-site-core';
$kernel   = $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php';
$boot     = $root . '/plugin/gloskin-site-core/gloskin-site-core.php';
$service  = $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-template-service.php';
$assets   = $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-asset-service.php';
$home_tpl = $root . '/plugin/gloskin-site-core/templates/pages/home.php';
$insights = $root . '/plugin/gloskin-site-core/templates/pages/insights.php';
$insight_single = $root . '/plugin/gloskin-site-core/templates/pages/insight-single.php';
$contact  = $root . '/plugin/gloskin-site-core/templates/pages/contact.php';
$partial  = $root . '/plugin/gloskin-site-core/templates/parts/insights-results.php';
$shell    = $root . '/plugin/gloskin-site-core/templates/shell.php';
$footer   = $root . '/plugin/gloskin-site-core/templates/parts/footer.php';
$core_css = $root . '/plugin/gloskin-site-core/assets/css/gloskin-ui1-core-base.css';
$edi_css  = $root . '/plugin/gloskin-site-core/assets/css/gloskin-ui1-editorial.css';
$con_css  = $root . '/plugin/gloskin-site-core/assets/css/gloskin-ui1-consultation.css';
$core_js  = $root . '/plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js';

function ux221_fail( string $message ): void {
	fwrite( STDERR, "ux-closure-0.7.221-contract.php: FAIL: {$message}\n" );
	exit( 1 );
}
function ux221_must( bool $cond, string $msg ): void {
	if ( ! $cond ) { ux221_fail( $msg ); }
}
function ux221_text( string $path ): string {
	$v = @file_get_contents( $path );
	if ( false === $v ) { ux221_fail( 'cannot read ' . $path ); }
	return $v;
}

$k  = ux221_text( $kernel );
$b  = ux221_text( $boot );
$svc = ux221_text( $service );
$ast = ux221_text( $assets );
$h  = ux221_text( $home_tpl );
$ig = ux221_text( $insights );
$is = ux221_text( $insight_single );
$ct = ux221_text( $contact );
$pt = ux221_text( $partial );
$sh = ux221_text( $shell );
$ft = ux221_text( $footer );
$cc = ux221_text( $core_css );
$ec = ux221_text( $edi_css );
$lc = ux221_text( $con_css );
$js = ux221_text( $core_js );

/* ── 1. Button cursor ──────────────────────────────────────────────── */
ux221_must( false !== strpos( $cc, 'cursor:pointer' ), 'core-base.css adds cursor:pointer to .gloskin-ui1-button' );
ux221_must(
	false !== strpos( $cc, '.gloskin-ui1-button:disabled,.gloskin-ui1-button[aria-disabled="true"]{cursor:not-allowed' ),
	'core-base.css adds cursor:not-allowed to disabled/aria-disabled buttons'
);
/* Keyframe owner lives only in consultation.css — editorial.css must not re-declare it. */
ux221_must( false !== strpos( $lc, '@keyframes gloskin-treatment-image-glint' ), 'glint @keyframes is defined in consultation.css' );
ux221_must( 0 === substr_count( $ec, '@keyframes gloskin-treatment-image-glint{' ), 'editorial.css does not re-declare the glint @keyframes (comment text ok, rule text not)' );

/* ── 2. Insights archive bottom rhythm ─────────────────────────────── */
ux221_must(
	false !== strpos( $ec, '.gloskin-ui1-insights-archive{background:var(--gloskin-bg);padding:clamp(56px,7vw,88px) 0 clamp(72px,9vw,112px)}' ),
	'insights archive section has both top and bottom padding'
);

/* ── 3. Insight single typographic hardening ───────────────────────── */
ux221_must( false !== strpos( $ec, 'max-width:26ch' ), 'insight single title max-width hardened to 26ch' );
ux221_must(
	false !== strpos( $ec, 'font-size:clamp(2rem,3.5vw,3.2rem)' ),
	'insight single title font-size max capped at 3.2rem'
);
ux221_must( false !== strpos( $ec, 'margin:28px auto 0' ), 'insight single dek top-margin increased to 28px' );
ux221_must( false !== strpos( $ec, 'margin-top:26px' ), 'insight single meta top-margin increased to 26px' );

/* Final micro-closure: the whole header uses one semantic center axis. */
ux221_must(
	false !== strpos( $ec, '.gloskin-ui1-insight-single__header-inner{max-width:920px;text-align:center}' ),
	'insight single header-inner is centered by its existing semantic owner'
);
ux221_must(
	false !== strpos( $ec, '.gloskin-ui1-insight-single__dek{max-width:720px;margin:28px auto 0;' ),
	'insight single dek is bounded and auto-centered'
);
ux221_must(
	false !== strpos( $ec, '.gloskin-ui1-insight-single__meta{display:flex;flex-wrap:wrap;justify-content:center;' ),
	'insight single meta shares the centered header axis'
);
ux221_must( false !== strpos( $cc, 'margin-inline:auto' ), 'shared container geometry remains centered' );

/* The reference discussion section stays the one global footer owner. */
ux221_must(
	1 === substr_count( $ft, '<section class="gloskin-ui1-dark-consultation gloskin-ui1-footer__cta">' ),
	'footer owns exactly one global dark consultation section'
);
ux221_must( false === strpos( $is, 'gloskin-ui1-dark-consultation' ), 'insight-single does not duplicate the global dark consultation section' );
$main_close = strpos( $sh, '</main>' );
$footer_include = strpos( $sh, "require __DIR__ . '/parts/footer.php'" );
ux221_must( false !== $main_close && false !== $footer_include && $main_close < $footer_include, 'shell renders footer after main/Insight content' );
$footer_cta = strpos( $ft, '<section class="gloskin-ui1-dark-consultation gloskin-ui1-footer__cta">' );
$footer_info = strpos( $ft, '<div class="gloskin-ui1-container gloskin-ui1-footer__grid">' );
ux221_must( false !== $footer_cta && false !== $footer_info && $footer_cta < $footer_info, 'global consultation renders before footer information/navigation' );
ux221_must( false !== strpos( $is, 'gloskin-ui1-insight-single__related' ), 'Insight keeps Artikel Terkait before the shell-owned global CTA' );

/* ── 4. Treatment single glint coverage ────────────────────────────── */
/* Hero media, consideration media, and related media must all have ::after rules
   referencing the shared keyframe. Keyframe itself lives once in consultation.css. */
ux221_must(
	false !== strpos( $ec, '.gloskin-treatment-single__hero-media::after' ),
	'editorial.css extends glint to treatment single hero-media'
);
ux221_must(
	false !== strpos( $ec, '.gloskin-treatment-single__consideration-media::after' ),
	'editorial.css extends glint to treatment single consideration-media'
);
ux221_must(
	false !== strpos( $ec, '.gloskin-treatment-single__related-media::after' ),
	'editorial.css extends glint to treatment single related-media'
);
/* All three ::after rules must reference the one shared keyframe by name. */
ux221_must(
	3 <= substr_count( $ec, 'animation:gloskin-treatment-image-glint' ),
	'all three treatment single ::after rules reference the shared keyframe'
);
/* prefers-reduced-motion disables all three. */
ux221_must(
	false !== strpos( $ec, '.gloskin-treatment-single__hero-media::after,.gloskin-treatment-single__consideration-media::after,.gloskin-treatment-single__related-media::after{animation:none}' ),
	'reduced-motion disables all treatment single glint surfaces'
);
/* isolation:isolate set on media containers to contain blend-mode. */
foreach ( array( '__hero-media{', '__consideration-media{', '__related-media{' ) as $surface ) {
	$pattern_in_ec = 'gloskin-treatment-single' . $surface;
	$needle_pos    = strpos( $ec, $pattern_in_ec );
	if ( false !== $needle_pos ) {
		$rule = substr( $ec, $needle_pos, 200 );
		ux221_must( false !== strpos( $rule, 'isolation:isolate' ), 'treatment single ' . $surface . ' has isolation:isolate' );
	}
}

/* Runtime asset graph: style on Hub + single; consultation JS on Hub only. */
ux221_must(
	1 === substr_count( $lc . $ec, '@keyframes gloskin-treatment-image-glint{' ),
	'exactly one gloskin-treatment-image-glint keyframe declaration remains'
);
$route_start = strpos( $ast, 'private function maybe_enqueue_treatment_consultation()' );
$route_end = false !== $route_start ? strpos( $ast, "\n\t/**", $route_start + 1 ) : false;
$route = false !== $route_start && false !== $route_end ? substr( $ast, $route_start, $route_end - $route_start ) : '';
ux221_must( false !== $route_start && '' !== $route, 'AssetService owns the Treatment consultation/glint route gate' );
ux221_must( false !== strpos( $route, "array( 'treatments', 'treatment' )" ), 'consultation stylesheet route includes Hub and single Treatment' );
ux221_must( 1 === substr_count( $route, "wp_enqueue_style( 'gloskin-ui1-consultation' );" ), 'consultation stylesheet has one conditional enqueue call' );
ux221_must( 1 === substr_count( $route, "wp_enqueue_script( 'gloskin-ui1-consultation' );" ), 'consultation controller has one conditional enqueue call' );
$hub_guard = strpos( $route, "if ( 'treatments' === \$view )" );
$hub_script = strpos( $route, "wp_enqueue_script( 'gloskin-ui1-consultation' );" );
ux221_must( false !== $hub_guard && false !== $hub_script && $hub_guard < $hub_script, 'single Treatment receives style only; consultation JS stays Hub-only' );

/* ── 5. Home all-treatments — no display cap ───────────────────────── */
ux221_must(
	false === strpos( $h, 'array_slice(' ),
	'home.php does not slice the treatments array to a cap'
);
/* curated_home_treatments uses -1 to fetch all. */
$curated_start = strpos( $svc, 'private function curated_home_treatments()' );
$curated_end   = false !== $curated_start ? strpos( $svc, 'private function skincare_category_context', $curated_start ) : false;
$curated       = false !== $curated_start && false !== $curated_end
	? substr( $svc, $curated_start, $curated_end - $curated_start )
	: '';
ux221_must( false !== $curated_start, 'curated_home_treatments() exists in TemplateService' );
ux221_must( false !== strpos( $curated, "'posts_per_page' => -1" ), 'curated_home_treatments queries all featured (-1)' );
ux221_must( false === strpos( $curated, 'array_slice( $cards' ), 'curated_home_treatments does not apply an array_slice cap' );
ux221_must( false === strpos( $curated, "'posts_per_page' => 3" ), 'curated_home_treatments does not use a hard 3-record limit' );

/* Documentation must describe the already-landed all-Treatment projection. */
ux221_must( false === strpos( $svc, 'Up to 3 curated published treatments for the Home page.' ), 'stale Home Treatment 3-card docblock is removed' );
ux221_must( false === strpos( $svc, 'up to 6 total' ), 'stale Home Treatment 6-card docblock is removed' );
ux221_must( false === strpos( $svc, '3 most-recently-published' ), 'stale capped fallback docblock is removed' );
ux221_must( false !== strpos( $svc, 'All published informational Treatments for the Home page.' ), 'Home Treatment docblock states all published informational Treatments' );
ux221_must( false !== strpos( $svc, 'only prioritizes ordering' ), 'Home Treatment feature flag is documented as ordering-only' );

/* ── 6. Insights AJAX progressive pagination ───────────────────────── */
/* JS: one initInsightsPagination(), uses publicRestGetOptions(), delegates click. */
ux221_must( 1 === substr_count( $js, 'function initInsightsPagination()' ), 'one initInsightsPagination() function owner in core.js' );
ux221_must( false !== strpos( $js, 'initInsightsPagination()' ) && 1 < substr_count( $js, 'initInsightsPagination' ), 'initInsightsPagination() is called from init()' );
ux221_must( false !== strpos( $js, "publicRestGetOptions()" ) && substr_count( $js, "publicRestGetOptions()" ) >= 2, 'initInsightsPagination reuses publicRestGetOptions()' );
ux221_must( false !== strpos( $js, "'insights?page='" ) || false !== strpos( $js, '"insights?page="' ) || false !== strpos( $js, 'insights' . "' + '?page='" ) || false !== strpos( $js, "restBase + '?page='" ), 'AJAX fetch targets the insights REST endpoint with a page param' );
ux221_must( false !== strpos( $js, 'window.history.pushState' ), 'pagination pushes history state' );
ux221_must( false !== strpos( $js, "popstate'" ) || false !== strpos( $js, '"popstate"' ), 'popstate listener handles back/forward' );
ux221_must( false !== strpos( $js, "window.location.href" ) && false !== strpos( $js, 'initInsightsPagination' ), 'insights pagination has a location.href fallback path' );

/* REST route: registered for GET gloskin/v1/insights. */
ux221_must(
	false !== strpos( $svc, "'gloskin/v1', '/insights'" ),
	'TemplateService registers REST route gloskin/v1/insights'
);
ux221_must(
	false !== strpos( $svc, 'public function rest_insights(' ),
	'TemplateService owns one rest_insights() public method'
);
ux221_must( 1 === substr_count( $svc, 'public function rest_insights(' ), 'rest_insights() is declared exactly once' );

/* Shared partial is the single server-rendered fragment owner. */
ux221_must( file_exists( $partial ), 'insights-results.php shared partial exists' );
ux221_must( false !== strpos( $pt, '$gloskin_insights_data' ), 'shared partial accepts $gloskin_insights_data context variable' );
ux221_must( false !== strpos( $pt, 'paginate_links(' ), 'shared partial owns the server-rendered pagination links' );

/* insights.php delegates to the partial — no duplicated card logic. */
ux221_must(
	false !== strpos( $ig, 'data-gloskin-insights-results' ),
	'insights.php wraps results in data-gloskin-insights-results container'
);
ux221_must(
	false !== strpos( $ig, 'data-gloskin-insights-status' ),
	'insights.php includes the accessible live status region'
);
ux221_must(
	false !== strpos( $ig, "require __DIR__ . '/../parts/insights-results.php'" ),
	'insights.php delegates rendering to the shared partial'
);
ux221_must(
	false === strpos( $ig, 'array_shift(' ),
	'insights.php no longer contains duplicated card rendering logic'
);

/* ── 7. Contact split blush hero + location cards + real form owner ─── */
ux221_must(
	false !== strpos( $ct, 'gloskin-contact-hero' ),
	'contact.php uses the canonical contact hero section class'
);
ux221_must(
	false !== strpos( $ct, 'gloskin-contact-hero__heading' ),
	'contact.php has a contact hero heading element'
);
ux221_must(
	false !== strpos( $ct, '$gloskin_context[\'form_html\']' ) || false !== strpos( $ct, '$gloskin_form_html' ),
	'contact.php preserves the real form_html provider — no static HTML replacement'
);
ux221_must(
	false === strpos( $ct, 'gloskin_ui1_render_hero(' ),
	'contact.php does not invoke the generic hero renderer (uses split blush hero instead)'
);
/* Location card component — simpler than gloskin-ui1-card, name+link only. */
ux221_must(
	false !== strpos( $ct, 'gloskin-contact-location-card' ),
	'contact.php uses the compact location card component'
);
ux221_must(
	false === strpos( $ct, 'gloskin-ui1-card--contact' ),
	'contact.php does not use the generic image-card for location directory'
);
ux221_must(
	false !== strpos( $ec, '.gloskin-contact-hero{' ),
	'editorial.css defines the contact hero layout'
);
ux221_must(
	false !== strpos( $ec, 'background:var(--gloskin-surface)' ),
	'contact hero uses surface token for distinctly visible blush background'
);
ux221_must(
	false !== strpos( $ec, '.gloskin-contact-hero__inner{display:grid;grid-template-columns:1fr 1fr' ),
	'contact hero uses a two-column grid desktop layout'
);
ux221_must(
	false !== strpos( $ec, '.gloskin-contact-hero__inner--no-media{' ),
	'contact hero has no-media single-column modifier'
);
ux221_must(
	false !== strpos( $ec, '.gloskin-contact-location-grid{' ),
	'editorial.css owns the location card grid layout'
);
ux221_must(
	false !== strpos( $ec, 'max-width:1100px' ),
	'location grid is max-width capped and centered'
);
ux221_must(
	false !== strpos( $ec, '.gloskin-contact-clinics .gloskin-ui1-section-heading{text-align:center' ),
	'clinics section heading is centered'
);
ux221_must(
	false !== strpos( $ec, '.gloskin-contact-location-card{' ),
	'editorial.css owns the location card base style'
);
ux221_must(
	false !== strpos( $ec, 'font-family:var(--gloskin-font-heading)' ) && false !== strpos( $ec, '.gloskin-contact-location-card__name{' ),
	'location card name uses global heading font token'
);
ux221_must(
	false !== strpos( $ec, '.gloskin-contact-location-card:hover{' ),
	'editorial.css owns the location card hover state'
);

/* ── 8. Version (0.7.221 or higher — bumped further after boot() fix) ── */
ux221_must(
	false !== strpos( $k, "const VERSION = '0.7.22" ),
	'Kernel VERSION is at or above 0.7.22x'
);
ux221_must(
	false !== strpos( $b, 'Version: 0.7.22' ),
	'Plugin header version is at or above 0.7.22x'
);

echo "ux-closure-0.7.221-contract.php: OK (button cursor, archive rhythm, single typography/CTA ownership, treatment glint asset graph, home all-treatments, insights AJAX, contact split blush hero + location cards, version ≥0.7.221)\n";
