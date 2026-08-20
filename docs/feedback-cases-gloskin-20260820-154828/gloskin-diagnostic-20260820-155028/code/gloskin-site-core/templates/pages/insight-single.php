<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$gloskin_post = isset( $gloskin_context['post'] ) && $gloskin_context['post'] instanceof WP_Post ? $gloskin_context['post'] : null;
if ( ! $gloskin_post ) {
	gloskin_ui1_render_empty_state( 'insight', __( 'Artikel tidak tersedia', 'gloskin-site-core' ), __( 'Artikel ini belum dapat ditampilkan.', 'gloskin-site-core' ), __( 'Kembali ke Insight', 'gloskin-site-core' ), home_url( '/insights/' ) );
	return;
}
?>
<article class="gloskin-ui1-insight-single">
	<header class="gloskin-ui1-insight-single__header">
		<div class="gloskin-ui1-container gloskin-ui1-insight-single__header-inner">
			<?php if ( ! empty( $gloskin_context['category'] ) ) : ?><span class="gloskin-ui1-insight-single__category"><?php echo esc_html( (string) $gloskin_context['category'] ); ?></span><?php endif; ?>
			<h1 class="gloskin-ui1-insight-single__title"><?php echo esc_html( get_the_title( $gloskin_post ) ); ?></h1>
			<?php if ( ! empty( $gloskin_context['excerpt'] ) ) : ?><p class="gloskin-ui1-insight-single__dek"><?php echo esc_html( (string) $gloskin_context['excerpt'] ); ?></p><?php endif; ?>
			<div class="gloskin-ui1-insight-single__meta">
				<time datetime="<?php echo esc_attr( (string) $gloskin_context['date_iso'] ); ?>"><?php echo esc_html( (string) $gloskin_context['date'] ); ?></time>
				<?php if ( ! empty( $gloskin_context['reading_time'] ) ) : ?><span><?php echo esc_html( (string) $gloskin_context['reading_time'] ); ?></span><?php endif; ?>
			</div>
		</div>
	</header>
	<?php if ( ! empty( $gloskin_context['image_id'] ) ) : ?>
		<figure class="gloskin-ui1-container gloskin-ui1-insight-single__hero">
			<?php echo wp_get_attachment_image( absint( $gloskin_context['image_id'] ), 'large', false, array( 'class' => 'gloskin-ui1-insight-single__hero-image', 'fetchpriority' => 'high', 'decoding' => 'async' ) ); ?>
		</figure>
	<?php endif; ?>
	<div class="gloskin-ui1-container gloskin-ui1-insight-single__reading">
		<div class="gloskin-ui1-insight-single__content">
			<?php
			// WordPress core's canonical post-content filter remains the body renderer; no custom shadow field/pipeline is introduced.
			echo apply_filters( 'the_content', $gloskin_post->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted editor content through canonical WordPress filter.
			?>
		</div>
		<p class="gloskin-ui1-insight-single__back"><a href="<?php echo esc_url( home_url( '/insights/' ) ); ?>">← <?php echo esc_html__( 'Kembali ke Insight', 'gloskin-site-core' ); ?></a></p>
	</div>
	<?php if ( ! empty( $gloskin_context['related'] ) ) : ?>
		<section class="gloskin-ui1-insight-single__related" aria-labelledby="gloskin-related-insights">
			<div class="gloskin-ui1-container">
				<div class="gloskin-ui1-insight-single__related-head"><p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Lanjut membaca', 'gloskin-site-core' ); ?></p><h2 id="gloskin-related-insights"><?php echo esc_html__( 'Artikel terkait', 'gloskin-site-core' ); ?></h2></div>
				<div class="gloskin-ui1-insights-archive__grid">
					<?php foreach ( $gloskin_context['related'] as $gloskin_insight_card ) : ?>
						<?php $gloskin_insight_lead = false; require __DIR__ . '/../parts/insight-card.php'; ?>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
	<?php endif; ?>
</article>
