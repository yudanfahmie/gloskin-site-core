<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$gloskin_founder = isset( $gloskin_context['founder'] ) && is_array( $gloskin_context['founder'] )
	? $gloskin_context['founder']
	: array( 'name' => '', 'role' => '', 'story' => '', 'media_id' => 0 );
?>
<div class="gloskin-about-page">
	<header class="gloskin-about-header" data-gloskin-section="about-header">
		<div class="gloskin-ui1-container gloskin-ui1-container--narrow">
			<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'ABOUT', 'gloskin-site-core' ); ?></p>
			<h1><?php echo esc_html__( 'Tentang Kami', 'gloskin-site-core' ); ?></h1>
		</div>
	</header>

	<section class="gloskin-ui1-section gloskin-about-story" data-gloskin-section="about-story">
		<div class="gloskin-ui1-container gloskin-about-story__grid">
			<div class="gloskin-about-story__media">
				<?php gloskin_ui1_render_editorial_media( 'editorial', 'about_story', 'gloskin-about-story__image', true ); ?>
			</div>
			<div class="gloskin-about-story__copy">
				<p class="gloskin-ui1-eyebrow">GLOSKIN</p>
				<h2><?php echo esc_html__( 'Tentang GLOSKIN', 'gloskin-site-core' ); ?></h2>
				<div class="gloskin-ui1-prose"><?php echo wp_kses_post( wpautop( (string) $gloskin_context['story'] ) ); ?></div>
			</div>
		</div>
	</section>

	<section class="gloskin-ui1-section gloskin-about-founder" data-gloskin-section="about-founder">
		<div class="gloskin-ui1-container gloskin-about-founder__grid">
			<div class="gloskin-about-founder__copy">
				<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Founder', 'gloskin-site-core' ); ?></p>
				<h2><?php echo esc_html( (string) $gloskin_founder['name'] ); ?></h2>
				<p class="gloskin-about-founder__role"><?php echo esc_html( (string) $gloskin_founder['role'] ); ?></p>
				<div class="gloskin-ui1-prose"><?php echo wp_kses_post( wpautop( (string) $gloskin_founder['story'] ) ); ?></div>
			</div>
			<div class="gloskin-about-founder__media">
				<?php if ( ! empty( $gloskin_founder['media_id'] ) ) : ?>
					<?php echo wp_get_attachment_image( absint( $gloskin_founder['media_id'] ), 'large', false, array( 'class' => 'gloskin-about-founder__image', 'loading' => 'lazy' ) ); ?>
				<?php else : ?>
					<?php gloskin_ui1_render_editorial_media( 'editorial', 'about_founder', 'gloskin-about-founder__image' ); ?>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<section class="gloskin-ui1-section gloskin-about-principles" data-gloskin-section="about-principles">
		<div class="gloskin-ui1-container">
			<?php gloskin_ui1_render_section_heading( __( 'Visi · Misi · Nilai', 'gloskin-site-core' ) ); ?>
			<div class="gloskin-about-principles__grid">
				<article class="gloskin-about-principle"><h3><?php echo esc_html__( 'Visi', 'gloskin-site-core' ); ?></h3><div class="gloskin-ui1-prose"><?php echo wp_kses_post( (string) $gloskin_context['vision'] ); ?></div></article>
				<article class="gloskin-about-principle"><h3><?php echo esc_html__( 'Misi', 'gloskin-site-core' ); ?></h3><div class="gloskin-ui1-prose"><?php echo wp_kses_post( (string) $gloskin_context['mission'] ); ?></div></article>
				<article class="gloskin-about-principle"><h3><?php echo esc_html__( 'Nilai', 'gloskin-site-core' ); ?></h3><div class="gloskin-ui1-prose"><?php echo wp_kses_post( (string) $gloskin_context['values'] ); ?></div></article>
			</div>
		</div>
	</section>
</div>
