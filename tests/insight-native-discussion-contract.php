<?php
declare(strict_types=1);

$root       = dirname( __DIR__ );
$plugin     = $root . '/plugin/gloskin-site-core';
$template   = (string) file_get_contents( $plugin . '/templates/pages/insight-single.php' );
$css        = (string) file_get_contents( $plugin . '/assets/css/gloskin-ui1-editorial.css' );
$footer     = (string) file_get_contents( $plugin . '/templates/parts/footer.php' );
$bootstrap  = (string) file_get_contents( $plugin . '/gloskin-site-core.php' );
$discussion = (string) file_get_contents( $plugin . '/includes/class-gloskin-site-core-insight-discussion-service.php' );

function insight_discussion_fail( string $message ): void {
	fwrite( STDERR, "insight-native-discussion-contract.php: FAIL: {$message}\n" );
	exit( 1 );
}
function insight_discussion_must( bool $condition, string $message ): void {
	if ( ! $condition ) {
		insight_discussion_fail( $message );
	}
}

/* Diskusi is the native WordPress comment thread/form, not the consultation CTA. */
insight_discussion_must( false !== strpos( $template, "post_type_supports( \$gloskin_post->post_type, 'comments' )" ), 'Insight uses WordPress comments support instead of a custom discussion backend' );
insight_discussion_must( 1 === substr_count( $template, 'comments_template();' ), 'Insight renders one native WordPress comments template' );
insight_discussion_must( false !== strpos( $template, "comments_open( \$gloskin_post->ID )" ) && false !== strpos( $template, "get_option( 'thread_comments' )" ), 'threaded reply asset follows native WordPress comment settings' );
insight_discussion_must( false !== strpos( $template, "wp_enqueue_script( 'comment-reply' );" ), 'threaded replies use the WordPress comment-reply script' );
insight_discussion_must( 1 === substr_count( $template, '<section class="gloskin-ui1-insight-single__discussion"' ), 'Insight owns one semantic native discussion section' );
insight_discussion_must( false !== strpos( $template, "esc_html__( 'Diskusi'" ), 'discussion section is visibly labelled Diskusi' );
insight_discussion_must( false !== strpos( $template, "esc_html__( 'Komentar & Pertanyaan'" ), 'discussion heading explicitly describes native comments/questions' );

/* Final article journey: content/back -> related -> native comments -> shell/footer CTA. */
$back_pos       = strpos( $template, 'gloskin-ui1-insight-single__back' );
$related_pos    = strpos( $template, '<section class="gloskin-ui1-insight-single__related"' );
$discussion_pos = strpos( $template, '<section class="gloskin-ui1-insight-single__discussion"' );
insight_discussion_must( false !== $back_pos && false !== $related_pos && false !== $discussion_pos && $back_pos < $related_pos && $related_pos < $discussion_pos, 'visible journey is article/back -> Artikel Terkait -> native Diskusi' );
insight_discussion_must( false === strpos( $template, 'gloskin-ui1-dark-consultation' ), 'Insight does not duplicate the global consultation CTA' );
insight_discussion_must( 1 === substr_count( $footer, '<section class="gloskin-ui1-dark-consultation gloskin-ui1-footer__cta">' ), 'global consultation CTA remains one separate footer owner after main content' );

/* Historical bundle comments are opened once, without hijacking future editor control. */
insight_discussion_must( false !== strpos( $bootstrap, 'class-gloskin-site-core-insight-discussion-service.php' ), 'bootstrap loads the semantic Insight discussion service' );
insight_discussion_must( false !== strpos( $bootstrap, '$insight_discussion->register();' ), 'Insight discussion service registers once' );
insight_discussion_must( false !== strpos( $discussion, "const BUNDLE_META = '_gloskin_insight_bundle_id';" ) && false !== strpos( $discussion, "const BUNDLE_ID = 'gloskin-insights-v1';" ), 'discussion reconciliation is bounded to the historical Insight bundle owner' );
insight_discussion_must( false !== strpos( $discussion, 'const EXPECTED_POSTS = 13;' ), 'discussion reconciliation is fail-closed on the exact 13 imported Insights' );
insight_discussion_must( false !== strpos( $discussion, "'comment_status' => 'open'" ), 'reconciliation persists native comment_status=open' );
insight_discussion_must( false !== strpos( $discussion, "add_filter( 'comments_open'" ), 'pending reconciliation has an immediate native comments_open bridge' );
insight_discussion_must( false !== strpos( $discussion, 'if ( $open || $this->is_reconciled() )' ), 'comments_open bridge retires after reconciliation so editors regain native per-post control' );
insight_discussion_must( false !== strpos( $discussion, "'complete' === (string)" ), 'reconciliation has a durable completion checkpoint' );

/* Native comments receive scoped editorial presentation, including list + form. */
insight_discussion_must( false !== strpos( $css, '.gloskin-ui1-insight-single__discussion{' ), 'editorial CSS owns discussion section presentation' );
insight_discussion_must( false !== strpos( $css, '.gloskin-ui1-insight-single__native-comments .comment-list' ), 'editorial CSS styles the native comment thread' );
insight_discussion_must( false !== strpos( $css, '.gloskin-ui1-insight-single__native-comments .comment-form' ), 'editorial CSS styles the native comment form' );
insight_discussion_must( false !== strpos( $css, '.gloskin-ui1-insight-single__native-comments .comment-list .children' ), 'nested native replies retain threaded hierarchy' );

/* Header optical composition shares one true center axis. */
insight_discussion_must( false !== strpos( $css, '.gloskin-ui1-insight-single__header-inner{display:grid;width:100%;justify-items:center}' ), 'Insight header uses semantic centered grid geometry' );
insight_discussion_must( false !== strpos( $css, '.gloskin-ui1-insight-single__dek{width:min(100%,660px);text-align:center;text-wrap:balance}' ), 'Insight dek has balanced optical measure' );
insight_discussion_must( false !== strpos( $css, '.gloskin-ui1-insight-single__meta{width:100%}' ), 'Insight meta spans the same centered axis' );
insight_discussion_must( false === strpos( $template, '<br' ) && false === strpos( $css, 'translateX(' ), 'Insight centering uses no manual line-break or translation hack' );

echo "insight-native-discussion-contract.php: OK (article -> related -> native comments -> footer CTA; imported comments open; optical center axis)\n";
