<?php
declare(strict_types=1);

$root      = dirname( __DIR__ );
$ia        = file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-final-ia-normalizer.php' );
$modal     = file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-promo-modal.php' );
$modalJs   = file_get_contents( $root . '/plugin/gloskin-site-core/assets/js/gloskin-ui1-promo-modal.js' );
$modalCss  = file_get_contents( $root . '/plugin/gloskin-site-core/assets/css/gloskin-ui1-promo-modal.css' );

function promo_frontend_fail( string $message ): void {
	fwrite( STDERR, "promo-popup-frontend-contract.php: FAIL: {$message}\n" );
	exit( 1 );
}

function promo_frontend_must( bool $condition, string $message ): void {
	if ( ! $condition ) {
		promo_frontend_fail( $message );
	}
}

promo_frontend_must( is_string( $ia ) && is_string( $modal ) && is_string( $modalJs ) && is_string( $modalCss ), 'required Homepage/Promo frontend owners must be readable' );

/* Canonical Homepage owner: the final IA normalizer makes Beranda the static WordPress front page. */
promo_frontend_must( false !== strpos( $ia, "'home' => 'Beranda'" ), 'canonical Homepage must remain the Beranda page' );
promo_frontend_must( false !== strpos( $ia, "update_option( 'show_on_front', 'page' );" ), 'Homepage must remain a static WordPress page' );
promo_frontend_must( false !== strpos( $ia, "update_option( 'page_on_front', \$home_id );" ), 'canonical Beranda ID must own page_on_front' );

/* Promo Homepage placement must use WordPress front-page semantics, not a guessed slug. */
promo_frontend_must( false !== strpos( $modal, 'if ( self::VISIBILITY_HOMEPAGE === $visibility ) {' ), 'Promo resolver must have an explicit Homepage branch' );
promo_frontend_must( false !== strpos( $modal, 'return is_front_page();' ), 'Homepage Promo visibility must use is_front_page()' );
promo_frontend_must( false !== strpos( $modal, 'private function destination_for_visibility' ), 'Promo resolver must own destination semantics by visibility' );
promo_frontend_must( false !== strpos( $modal, "return self::sanitize_destination_url( home_url( '/' ) );" ), 'Homepage Promo must own an implicit canonical site-root target' );
promo_frontend_must( false !== strpos( $modal, "get_post_meta( \$promo_id, self::DESTINATION_META, true )" ), 'non-Homepage placements must retain explicit destination metadata' );
promo_frontend_must( false !== strpos( $modal, "'visibility' => \$visibility" ) && false !== strpos( $modal, "'page_ids'   => \$page_ids" ), 'campaign payload must include placement state' );
promo_frontend_must( false !== strpos( $modal, "(string) \$promo['visibility']" ) && false !== strpos( $modal, "implode( ',', array_map( 'absint', (array) \$promo['page_ids'] ) )" ), 'campaign signature must change when placement changes' );

/* Popup On means visible without an undocumented engagement gate. */
promo_frontend_must( false === strpos( $modalJs, 'gloskinPromoDismissedSession' ), 'ordinary close must not suppress every popup for the rest of the browser session' );
foreach ( array( 'onScrollEngagement', 'armEngagementTrigger', 'interactionSeen', '6500' ) as $forbidden ) {
	promo_frontend_must( false === strpos( $modalJs, $forbidden ), 'frontend popup must not retain engagement gate: ' . $forbidden );
}
promo_frontend_must( false !== strpos( $modalJs, "var persistentKey = 'gloskinPromoDismissedCampaign';" ), 'explicit never-show preference must remain campaign-scoped' );
promo_frontend_must( false !== strpos( $modalJs, 'if (persistent && campaign) { storageSet(window.localStorage, persistentKey, campaign); }' ), 'only persistent dismissal may write the campaign preference' );
promo_frontend_must( false !== strpos( $modalJs, 'initialShowTimer = window.setTimeout(show, reducedMotion ? 0 : 450);' ), 'eligible popup must open deterministically after first paint' );

/* Production artwork is cropped at 1648x928 in admin; frontend must preserve that composition. */
promo_frontend_must( false !== strpos( $modalCss, 'aspect-ratio:1648/928;' ), 'popup poster must use the production Promo artwork ratio' );
promo_frontend_must( false !== strpos( $modalCss, 'object-fit:cover;' ), 'popup image must fill the production poster frame' );
promo_frontend_must( false !== strpos( $modalCss, 'object-position:var(--gloskin-promo-focus-x,50%) var(--gloskin-promo-focus-y,50%);' ), 'frontend must reuse saved crop focus' );
promo_frontend_must( false !== strpos( $modalCss, 'transform:scale(var(--gloskin-promo-scale,1));' ), 'frontend must reuse saved crop zoom' );

echo "promo-popup-frontend-contract.php: OK (canonical Beranda, deterministic popup, campaign dismissal, 1648x928 artwork)\n";
