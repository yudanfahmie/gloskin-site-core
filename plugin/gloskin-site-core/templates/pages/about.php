<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$gloskin_about_page           = isset( $gloskin_context['page'] ) && $gloskin_context['page'] instanceof WP_Post ? $gloskin_context['page'] : null;
$gloskin_founder              = isset( $gloskin_context['founder'] ) ? $gloskin_context['founder'] : null;
$gloskin_has_principles       = ! empty( $gloskin_context['vision'] ) || ! empty( $gloskin_context['mission'] ) || ! empty( $gloskin_context['values'] );
$gloskin_about_media_id       = $gloskin_about_page ? absint( get_post_thumbnail_id( $gloskin_about_page->ID ) ) : 0;
?>
<header class="gloskin-about-header" data-gloskin-section="about-header">
	<div class="gloskin-ui1-container gloskin-ui1-container--narrow">
		<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'ABOUT', 'gloskin-site-core' ); ?></p>
		<h1><?php echo esc_html__( 'Tentang Kami', 'gloskin-site-core' ); ?></h1>
	</div>
</header>

<section class="gloskin-ui1-section gloskin-about-story" data-gloskin-section="about-story">
	<?php if ( $gloskin_about_page && gloskin_ui1_has_content( $gloskin_about_page ) ) : ?>
	<div class="gloskin-ui1-container gloskin-about-story__grid">
		<div class="gloskin-about-story__media">
			<?php if ( $gloskin_about_media_id ) : ?>
				<?php echo wp_get_attachment_image( $gloskin_about_media_id, 'large', false, array( 'class' => 'gloskin-about-story__image', 'loading' => 'eager' ) ); ?>
			<?php else : ?>
				<?php gloskin_ui1_render_editorial_media( 'editorial', 'about_story', 'gloskin-about-story__image', true ); ?>
			<?php endif; ?>
		</div>
		<div class="gloskin-about-story__copy">
			<p class="gloskin-ui1-eyebrow">GLOSKIN</p>
			<h2><?php echo esc_html__( 'Tentang GLOSKIN', 'gloskin-site-core' ); ?></h2>
			<?php gloskin_ui1_render_page_content( $gloskin_about_page ); ?>
		</div>
	</div>
	<?php else : ?>
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_empty_state( 'generic', __( 'Tentang GLOSKIN', 'gloskin-site-core' ), __( 'Konten cerita Gloskin belum diisi. Operator: tambahkan konten pada halaman About di WordPress (post_content).', 'gloskin-site-core' ) ); ?>
	</div>
	<?php endif; ?>
</section>

<section class="gloskin-ui1-section gloskin-ui1-section--soft gloskin-about-founder" data-gloskin-section="about-founder">
	<?php if ( is_array( $gloskin_founder ) ) : ?>
	<div class="gloskin-ui1-container gloskin-about-founder__grid">
		<div class="gloskin-about-founder__copy">
			<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Founder', 'gloskin-site-core' ); ?></p>
			<h2><?php echo esc_html( (string) $gloskin_founder['name'] ); ?></h2>
			<?php if ( '' !== trim( (string) $gloskin_founder['role'] ) ) : ?><p class="gloskin-about-founder__role"><?php echo esc_html( (string) $gloskin_founder['role'] ); ?></p><?php endif; ?>
			<?php if ( '' !== trim( (string) $gloskin_founder['story'] ) ) : ?><div class="gloskin-ui1-prose"><?php echo wp_kses_post( wpautop( (string) $gloskin_founder['story'] ) ); ?></div><?php endif; ?>
		</div>
		<?php if ( ! empty( $gloskin_founder['media_id'] ) ) : ?>
		<div class="gloskin-about-founder__media">
			<?php echo wp_get_attachment_image( absint( $gloskin_founder['media_id'] ), 'large', false, array( 'class' => 'gloskin-about-founder__image', 'loading' => 'lazy' ) ); ?>
		</div>
		<?php endif; ?>
	</div>
	<?php else : ?>
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_empty_state( 'generic', __( 'Founder', 'gloskin-site-core' ), __( 'Data founder belum diisi. Operator: tambahkan gloskin_about_founder_name dan gloskin_about_founder_role pada halaman About.', 'gloskin-site-core' ) ); ?>
	</div>
	<?php endif; ?>
</section>

<section class="gloskin-ui1-section gloskin-about-principles" data-gloskin-section="about-principles">
	<div class="gloskin-ui1-container">
		<?php if ( $gloskin_has_principles ) : ?>
		<?php gloskin_ui1_render_section_heading( __( 'Visi · Misi · Nilai', 'gloskin-site-core' ) ); ?>
		<div class="gloskin-about-principles__grid">
			<?php if ( ! empty( $gloskin_context['vision'] ) ) : ?><article class="gloskin-about-principle"><h3><?php echo esc_html__( 'Visi', 'gloskin-site-core' ); ?></h3><div class="gloskin-ui1-prose"><?php echo wp_kses_post( $gloskin_context['vision'] ); ?></div></article><?php endif; ?>
			<?php if ( ! empty( $gloskin_context['mission'] ) ) : ?><article class="gloskin-about-principle"><h3><?php echo esc_html__( 'Misi', 'gloskin-site-core' ); ?></h3><div class="gloskin-ui1-prose"><?php echo wp_kses_post( $gloskin_context['mission'] ); ?></div></article><?php endif; ?>
			<?php if ( ! empty( $gloskin_context['values'] ) ) : ?><article class="gloskin-about-principle"><h3><?php echo esc_html__( 'Nilai', 'gloskin-site-core' ); ?></h3><div class="gloskin-ui1-prose"><?php echo wp_kses_post( $gloskin_context['values'] ); ?></div></article><?php endif; ?>
		</div>
		<?php else : ?>
		<?php gloskin_ui1_render_empty_state( 'generic', __( 'Visi · Misi · Nilai', 'gloskin-site-core' ), __( 'Data visi/misi/nilai belum diisi. Operator: tambahkan gloskin_about_vision, gloskin_about_mission, dan gloskin_about_values pada halaman About.', 'gloskin-site-core' ) ); ?>
		<?php endif; ?>
	</div>
</section>
