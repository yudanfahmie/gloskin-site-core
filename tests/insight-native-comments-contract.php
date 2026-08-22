<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$plugin = $root . '/plugin/gloskin-site-core';
$template = (string) file_get_contents( $plugin . '/templates/pages/insight-single.php' );
$css = (string) file_get_contents( $plugin . '/assets/css/gloskin-ui1-editorial.css' );
$footer = (string) file_get_contents( $plugin . '/templates/parts/footer.php' );

function insight_comments_fail( string $message ): void {
	fwrite( STDERR, "insight-native-comments-contract.php: FAIL: {$message}\n" );
	exit( 1 );
}
function insight_comments_must( bool $condition, string $message ): void {
	if ( ! $condition ) {
		insight_comments_fail( $message );
	}
}

/* Header: one real center axis, no manual offsets or line-break hacks. */
insight_comments_must( false !== strpos( $css, '.gloskin-ui1-insight-single__header-inner{display:grid;width:100%;justify-items:center}' ), 'Insight header uses grid center-axis geometry' );
insight_comments_must( false !== strpos( $css, '.gloskin-ui1-insight-single__dek{width:min(100%,660px);text-align:center;text-wrap:balance}' ), 'Insight dek has balanced bounded centered measure' );
insight_comments_must( false !== strpos( $css, '.gloskin-ui1-insight-single__meta{width:100%}' ), 'Insight meta occupies the same center axis' );
insight_comments_must( false === strpos( $template, '<br' ) && false === strpos( $css, 'translateX(' ), 'Insight centering uses no manual break/translate hack' );

/* Diskusi means native WordPress comments, not the consultation CTA. */
insight_comments_must( 1 === substr_count( $template, '<section class="gloskin-ui1-insight-single__discussion"' ), 'Insight owns one native discussion section' );
insight_comments_must( 1 === substr_count( $template, 'comments_template();' ), 'Insight invokes the native WordPress comments template exactly once' );
insight_comments_must( false !== strpos( $template, "post_type_supports( \$gloskin_post->post_type, 'comments' )" ), 'Discussion follows native post-type comments support' );
insight_comments_must( false !== strpos( $template, "wp_enqueue_script( 'comment-reply' );" ), 'Threaded native replies enqueue the WordPress comment-reply script' );
insight_comments_must( false !== strpos( $template, "get_option( 'thread_comments' )" ), 'comment-reply is gated by the native threaded-comments option' );
insight_comments_must( false !== strpos( $css, '.gloskin-ui1-insight-single__native-comments .comment-list' ), 'Editorial CSS styles the native comment list' );
insight_comments_must( false !== strpos( $css, '.gloskin-ui1-insight-single__native-comments .comment-form' ), 'Editorial CSS styles the native comment form' );

$back = strpos( $template, 'gloskin-ui1-insight-single__back' );
$discussion = strpos( $template, '<section class="gloskin-ui1-insight-single__discussion"' );
$related = strpos( $template, 'gloskin-ui1-insight-single__related' );
insight_comments_must( false !== $back && false !== $discussion && false !== $related && $back < $discussion && $discussion < $related, 'Visible journey is article/back -> native discussion -> related articles' );

/* Consultation CTA stays a separate single global footer owner. */
insight_comments_must( false === strpos( $template, 'gloskin-ui1-dark-consultation' ), 'Insight does not duplicate the global consultation CTA' );
insight_comments_must( 1 === substr_count( $footer, '<section class="gloskin-ui1-dark-consultation gloskin-ui1-footer__cta">' ), 'Footer still owns exactly one consultation CTA' );

echo "insight-native-comments-contract.php: OK (optical center axis + native WordPress discussion + related/footer ownership)\n";
