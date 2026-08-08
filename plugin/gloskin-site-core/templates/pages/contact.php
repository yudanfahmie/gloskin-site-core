<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
gloskin_ui1_render_hero( $gloskin_context['hero'] );
$form_html = trim( (string) $gloskin_context['form_html'] );
$form_available = '' !== $form_html && false === strpos( $form_html, 'gloskin-ui1-empty--form' );
?>
<section class="gloskin-ui1-section">
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_page_content( $gloskin_context['page'] ); ?>
		<?php gloskin_ui1_render_section_heading( __( 'Find a clinic', 'gloskin-site-core' ) ); ?>
		<div class="gloskin-ui1-grid gloskin-ui1-grid--cards">
			<?php foreach ( $gloskin_context['clinics'] as $clinic ) : ?>
				<article class="gloskin-ui1-card gloskin-ui1-card--contact">
					<div class="gloskin-ui1-card__body">
						<h3 class="gloskin-ui1-card__title"><a href="<?php echo esc_url( (string) $clinic['url'] ); ?>"><?php echo esc_html( (string) $clinic['title'] ); ?></a></h3>
						<?php if ( ! empty( $clinic['phone_display'] ) ) : ?><p><?php echo esc_html( (string) $clinic['phone_display'] ); ?></p><?php endif; ?>
						<div class="gloskin-ui1-card__actions">
							<a class="gloskin-ui1-text-link" href="<?php echo esc_url( (string) $clinic['url'] ); ?>"><?php echo esc_html__( 'Clinic details', 'gloskin-site-core' ); ?></a>
							<?php if ( ! empty( $clinic['whatsapp_url'] ) ) : ?><a class="gloskin-ui1-button gloskin-ui1-button--small" href="<?php echo esc_url( (string) $clinic['whatsapp_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'WhatsApp', 'gloskin-site-core' ); ?></a><?php endif; ?>
						</div>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php if ( $form_available ) : ?>
<section class="gloskin-ui1-section gloskin-ui1-section--soft">
	<div class="gloskin-ui1-container gloskin-ui1-container--narrow">
		<?php gloskin_ui1_render_section_heading( __( 'Contact form', 'gloskin-site-core' ) ); ?>
		<div class="gloskin-ui1-form"><?php echo $form_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted configured shortcode provider output. ?></div>
	</div>
</section>
<?php endif; ?>
