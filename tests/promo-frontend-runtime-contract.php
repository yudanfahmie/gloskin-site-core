<?php
declare(strict_types=1);

$root      = dirname( __DIR__ );
$modal     = file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-promo-modal.php' );
$shell     = file_get_contents( $root . '/plugin/gloskin-site-core/templates/shell.php' );
$assets    = file_get_contents( $root . '/plugin/gloskin-site-core/config/assets.php' );
$js        = file_get_contents( $root . '/plugin/gloskin-site-core/assets/js/gloskin-ui1-promo-modal.js' );
$css       = file_get_contents( $root . '/plugin/gloskin-site-core/assets/css/gloskin-ui1-promo-modal.css' );
$reference = file_get_contents( $root . '/docs/2026-08-18-prototype-refresh/promo-modal-reference.html' );

foreach ( compact( 'modal', 'shell', 'assets', 'js', 'css', 'reference' ) as $name => $source ) {
	if ( false === $source ) {
		fwrite( STDERR, "Unable to read Promo runtime source: {$name}\n" );
		exit( 1 );
	}
}

$fail = static function ( string $message ): void {
	fwrite( STDERR, $message . "\n" );
	exit( 1 );
};

if ( false === strpos( $modal, "add_action( 'gloskin_site_core_shell_footer', array( \$this, 'render' ), 10 )" ) ) {
	$fail( 'Promo markup must render from the shell footer hook before wp_footer scripts.' );
}
if ( false !== strpos( $modal, "add_action( 'wp_footer', array( \$this, 'render' )" ) ) {
	$fail( 'Promo renderer must not return to late wp_footer ownership.' );
}
$shell_hook = strpos( $shell, "do_action( 'gloskin_site_core_shell_footer' )" );
$wp_footer  = strpos( $shell, 'wp_footer();' );
if ( false === $shell_hook || false === $wp_footer || $shell_hook >= $wp_footer ) {
	$fail( 'Shell Promo markup hook must execute before wp_footer().' );
}
if ( false === strpos( $modal, 'VISIBILITY_HOMEPAGE === $visibility' ) || false === strpos( $modal, 'return is_front_page();' ) ) {
	$fail( 'Homepage Promo visibility must resolve through the canonical WordPress front page.' );
}
if ( false === strpos( $modal, 'VISIBILITY_SPECIFIC === $visibility' ) ) {
	$fail( 'Specific Pages must remain a canonical placement.' );
}
if ( false === strpos( $modal, 'private function destination_url( $promo_id )' ) || false === strpos( $modal, "'clickable'  => '' !== \$url" ) ) {
	$fail( 'Promo click routing must be derived independently from optional URL metadata.' );
}
if ( false !== strpos( $modal, "return self::sanitize_destination_url( home_url( '/' ) );" ) ) {
	$fail( 'Homepage must not invent an implicit click target.' );
}
if ( false === strpos( $modal, '<div class="<?php echo esc_attr( $slide_class ); ?>" data-gloskin-promo-slide' ) ) {
	$fail( 'Display-only Promo slides must render without an anchor when URL is empty.' );
}
if ( false === strpos( $assets, "'gloskin-ui1-promo-modal'" ) || false === strpos( $assets, 'assets/js/gloskin-ui1-promo-modal.js' ) || false === strpos( $assets, 'assets/css/gloskin-ui1-promo-modal.css' ) ) {
	$fail( 'Promo frontend CSS/JS must remain in the canonical asset registry.' );
}
if ( false === strpos( $js, "window.addEventListener('scroll', onScrollTrigger" ) || false === strpos( $js, 'if (percent < 30)' ) || false === strpos( $js, 'hasShown = true;' ) ) {
	$fail( 'Promo controller must use the reference 30% scroll, once-per-page trigger.' );
}
if ( false === strpos( $js, "if (slide.matches('a[href]'))" ) || false === strpos( $js, "slide.removeAttribute('tabindex')" ) ) {
	$fail( 'Display-only slides must remain non-interactive in keyboard flow.' );
}
foreach ( array( 'localStorage', 'sessionStorage', 'initialShowTimer', 'dotsNode', "document.createElement('span')" ) as $forbidden ) {
	if ( false !== strpos( $js, $forbidden ) ) {
		$fail( "Promo controller must not restore deprecated state/navigation runtime: {$forbidden}." );
	}
}
if ( false !== strpos( $modal, 'data-gloskin-promo-never' ) || false !== strpos( $js, 'data-gloskin-promo-never' ) ) {
	$fail( 'Reference popup has no persistent never-show state.' );
}
if ( false === strpos( $reference, 'scrollPercent >= 30' ) ) {
	$fail( 'Promo reference no longer exposes the expected 30% trigger.' );
}

foreach ( array(
	'background:color-mix(in srgb,var(--gloskin-refresh-black) 5%,transparent);',
	'-webkit-backdrop-filter:blur(6px) saturate(106%);',
	'backdrop-filter:blur(1px) saturate(100%);',
	'background:var(--gloskin-accent);',
	'border:1px solid var(--gloskin-accent);',
	'right:15px;',
	'bottom:15px;',
	'padding:0;',
	'border:0;',
	'background:transparent;',
	'font-size:0;',
	'--gloskin-promo-chevron:url(',
	'M388.418,240.923L153.751,6.256',
	'-webkit-mask:var(--gloskin-promo-chevron) center/contain no-repeat;',
	'.gloskin-promo-modal__nav--prev::before{transform:rotate(180deg)}',
	'.gloskin-promo-modal__dots{display:none!important}',
	'top:-19px;',
	'right:-19px;',
	'width:38px;',
	'height:38px;'
) as $presentation ) {
	if ( false === strpos( $css, $presentation ) ) {
		$fail( "Promo presentation contract missing: {$presentation}." );
	}
}

if ( false !== strpos( $css, 'background:color-mix(in srgb,var(--gloskin-refresh-black) 22%,transparent);' ) ) {
	$fail( 'Promo navigation must not restore the old pill/background container.' );
}

echo "promo-frontend-runtime-contract.php: OK (placement/routing separated, reference trigger, accent SVG controls, light backdrop)\n";
