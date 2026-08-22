<?php
declare(strict_types=1);

$root    = dirname( __DIR__ );
$plugin  = $root . '/plugin/gloskin-site-core';
$template = (string) file_get_contents( $plugin . '/templates/pages/insight-single.php' );
$css      = (string) file_get_contents( $plugin . '/assets/css/gloskin-ui1-editorial.css' );
$footer   = (string) file_get_contents( $plugin . '/templates/parts/footer.php' );

function insight_discussion_fail( string $message ): void {
	fwrite( STDERR, "insight-native-discussion-contract.php: FAIL: {$message}\n" );
	exit( 1 );
}
function insight_discussion_must( bool $condition, string $message ): void {
	if ( ! $condition ) {
		insight_discussion_fail( $message );
	}
}

insight_discussion_must( false !== strpos( $template, "post_type_supports( \$gloskin_post->post_type, 'comments' )" ), 'Insight uses WordPress comments support instead of a custom discussion backend' );
insight_discussion_must( false !== strpos( $template, 'comments_template();' ), 'Insight renders the native WordPress comments template' );
insight_discussion_must( false !== strpos( $template, "wp_enqueue_script( 'comment-reply' );" ), 'threaded replies use the WordPress comment-reply script' );
insight_discussion_must( false !== strpos( $template, 'gloskin-ui1-insight-single__discussion' ), 'Insight owns one semantic discussion section' );
insight_discussion_must( false !== strpos( $template, "esc_html__( 'Diskusi'" ), 'discussion section is visibly labelled Diskusi' );
insight_discussion_must( false !== strpos( $template, "esc_html__( 'Komentar & Pertanyaan'" ), 'discussion heading describes native comments/questions' );

$related_pos    = strpos( $template, '<section class="gloskin-ui1-insight-single__related"' );
$discussion_pos = strpos( $template, '<section class="gloskin-ui1-insight-single__discussion"' );
insight_discussion_must( false !== $related_pos && false !== $discussion_pos && $related_pos < $discussion_pos, 'Artikel Terkait renders before native Diskusi' );
insight_discussion_must( 1 === substr_count( $template, 'comments_template();' ), 'native comments renderer is unique' );
insight_discussion_must( 1 === substr_count( $template, 'gloskin-ui1-insight-single__discussion"' ), 'discussion section is unique' );
insight_discussion_must( false === strpos( $template, 'gloskin-ui1-dark-consultation' ), 'Insight does not duplicate the global consultation CTA' );
insight_discussion_must( 1 === substr_count( $footer, '<section class="gloskin-ui1-dark-consultation gloskin-ui1-footer__cta">' ), 'global consultation CTA remains one footer owner' );

insight_discussion_must( false !== strpos( $css, '.gloskin-ui1-insight-single__discussion{' ), 'editorial CSS owns discussion presentation' );
insight_discussion_must( false !== strpos( $css, '.gloskin-ui1-insight-single__native-comments{' ), 'editorial CSS scopes native comments presentation' );
insight_discussion_must( false !== strpos( $css, '.gloskin-ui1-insight-single__header-inner{display:grid;width:100%;justify-items:center}' ), 'Insight header uses semantic centered grid geometry' );
insight_discussion_must( false !== strpos( $css, '.gloskin-ui1-insight-single__dek{width:min(100%,660px);text-align:center;text-wrap:balance}' ), 'Insight dek has balanced optical measure' );
insight_discussion_must( false !== strpos( $css, '.gloskin-ui1-insight-single__meta{width:100%}' ), 'Insight meta spans the centered axis' );

echo "insight-native-discussion-contract.php: OK (related -> native Diskusi -> global CTA; centered Insight header)\n";
