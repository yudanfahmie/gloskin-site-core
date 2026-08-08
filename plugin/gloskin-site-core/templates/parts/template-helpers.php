<?php
/**
 * Side-effect-free presentation helpers.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'gloskin_ui1_render_hero' ) ) {
	/**
	 * @param array<string,mixed> $hero Hero context.
	 * @return void
	 */
	function gloskin_ui1_render_hero( $hero ) {
		$heading = isset( $hero['heading'] ) ? (string) $hero['heading'] : '';
		$copy    = isset( $hero['copy'] ) ? (string) $hero['copy'] : '';
		$label   = isset( $hero['cta_label'] ) ? (string) $hero['cta_label'] : '';
		$url     = isset( $hero['cta_url'] ) ? (string) $hero['cta_url'] : '';
		$media   = isset( $hero['media_id'] ) ? absint( $hero['media_id'] ) : 0;
		?>
		<section class="gloskin-ui1-hero">
			<div class="gloskin-ui1-container gloskin-ui1-hero__grid">
				<div class="gloskin-ui1-hero__content">
					<?php if ( '' !== $heading ) : ?>
						<h1 class="gloskin-ui1-hero__title"><?php echo esc_html( $heading ); ?></h1>
					<?php endif; ?>
					<?php if ( '' !== $copy ) : ?>
						<p class="gloskin-ui1-hero__copy"><?php echo esc_html( $copy ); ?></p>
					<?php endif; ?>
					<?php if ( '' !== $label && '' !== $url ) : ?>
						<p class="gloskin-ui1-hero__actions">
							<a class="gloskin-ui1-button gloskin-ui1-button--primary" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
						</p>
					<?php endif; ?>
				</div>
				<?php if ( $media ) : ?>
					<div class="gloskin-ui1-hero__media">
						<?php
						echo wp_get_attachment_image(
							$media,
							'large',
							false,
							array(
								'class'         => 'gloskin-ui1-hero__image',
								'fetchpriority' => 'high',
								'decoding'      => 'async',
							)
						);
						?>
					</div>
				<?php endif; ?>
			</div>
		</section>
		<?php
	}
}

if ( ! function_exists( 'gloskin_ui1_render_section_heading' ) ) {
	function gloskin_ui1_render_section_heading( $title, $copy = '' ) {
		?><div class="gloskin-ui1-section-heading"><h2><?php echo esc_html( $title ); ?></h2><?php if ( '' !== $copy ) : ?><p><?php echo esc_html( $copy ); ?></p><?php endif; ?></div><?php
	}
}

if ( ! function_exists( 'gloskin_ui1_render_card' ) ) {
	function gloskin_ui1_render_card( $card, $kind ) {
		$title = isset( $card['title'] ) ? (string) $card['title'] : '';
		$url = isset( $card['url'] ) ? (string) $card['url'] : '';
		$image_id = isset( $card['image_id'] ) ? absint( $card['image_id'] ) : 0;
		$copy = '';
		if ( 'clinic' === $kind ) { $copy = isset( $card['short_location'] ) && '' !== trim( (string) $card['short_location'] ) ? (string) $card['short_location'] : ( isset( $card['hours'] ) ? (string) $card['hours'] : '' ); }
		elseif ( 'doctor' === $kind ) { $degree = isset( $card['degree_title'] ) ? trim( (string) $card['degree_title'] ) : ''; $spec = isset( $card['specialization'] ) ? trim( (string) $card['specialization'] ) : ''; $copy = trim( $degree . ( $degree && $spec ? ' · ' : '' ) . $spec ); }
		elseif ( 'treatment' === $kind ) { $copy = isset( $card['summary'] ) ? (string) $card['summary'] : ''; }
		else { $copy = isset( $card['excerpt'] ) ? (string) $card['excerpt'] : ''; }
		?><article class="gloskin-ui1-card gloskin-ui1-card--<?php echo esc_attr( $kind ); ?>"><?php if ( $image_id ) : ?><a class="gloskin-ui1-card__media" href="<?php echo esc_url( $url ); ?>" tabindex="-1" aria-hidden="true"><?php echo wp_get_attachment_image( $image_id, 'medium_large', false, array( 'loading' => 'lazy', 'class' => 'gloskin-ui1-card__image' ) ); ?></a><?php endif; ?><div class="gloskin-ui1-card__body"><h3 class="gloskin-ui1-card__title"><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $title ); ?></a></h3><?php if ( '' !== trim( $copy ) ) : ?><p class="gloskin-ui1-card__copy"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $copy ), 28 ) ); ?></p><?php endif; ?><a class="gloskin-ui1-text-link" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html__( 'View details', 'gloskin-site-core' ); ?><span aria-hidden="true"> →</span></a></div></article><?php
	}
}

if ( ! function_exists( 'gloskin_ui1_render_product_card' ) ) {
	function gloskin_ui1_render_product_card( $product ) {
		$name = isset( $product['name'] ) ? (string) $product['name'] : ''; $url = isset( $product['url'] ) ? (string) $product['url'] : ''; $image_id = isset( $product['image_id'] ) ? absint( $product['image_id'] ) : 0;
		?><article class="gloskin-ui1-card gloskin-ui1-card--product"><?php if ( $image_id ) : ?><a class="gloskin-ui1-card__media" href="<?php echo esc_url( $url ); ?>" tabindex="-1" aria-hidden="true"><?php echo wp_get_attachment_image( $image_id, 'woocommerce_thumbnail', false, array( 'loading' => 'lazy', 'class' => 'gloskin-ui1-card__image' ) ); ?></a><?php endif; ?><div class="gloskin-ui1-card__body"><h3 class="gloskin-ui1-card__title"><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $name ); ?></a></h3><?php if ( ! empty( $product['price_html'] ) ) : ?><div class="gloskin-ui1-product-price"><?php echo wp_kses_post( (string) $product['price_html'] ); ?></div><?php endif; ?><?php if ( ! empty( $product['short_description'] ) ) : ?><p class="gloskin-ui1-card__copy"><?php echo esc_html( wp_trim_words( (string) $product['short_description'], 24 ) ); ?></p><?php endif; ?><div class="gloskin-ui1-card__actions"><a class="gloskin-ui1-text-link" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html__( 'View product', 'gloskin-site-core' ); ?></a><?php if ( ! empty( $product['purchasable'] ) && ! empty( $product['in_stock'] ) && ! empty( $product['add_to_cart_url'] ) ) : ?><a class="gloskin-ui1-button gloskin-ui1-button--small" href="<?php echo esc_url( (string) $product['add_to_cart_url'] ); ?>"><?php echo esc_html( (string) $product['add_to_cart_text'] ); ?></a><?php endif; ?></div></div></article><?php
	}
}

if ( ! function_exists( 'gloskin_ui1_has_content' ) ) {
	function gloskin_ui1_has_content( $post ) { return $post instanceof WP_Post && '' !== trim( (string) $post->post_content ); }
}

if ( ! function_exists( 'gloskin_ui1_render_discovery_panel' ) ) {
	function gloskin_ui1_render_discovery_panel( $title, $copy, $label, $url ) {
		?><div class="gloskin-ui1-contact-panel gloskin-ui1-discovery-panel"><div><h2><?php echo esc_html( $title ); ?></h2><?php if ( '' !== trim( $copy ) ) : ?><p><?php echo esc_html( $copy ); ?></p><?php endif; ?></div><?php if ( '' !== $label && '' !== $url ) : ?><a class="gloskin-ui1-button gloskin-ui1-button--primary" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a><?php endif; ?></div><?php
	}
}

if ( ! function_exists( 'gloskin_ui1_render_page_content' ) ) {
	function gloskin_ui1_render_page_content( $post ) {
		if ( ! $post instanceof WP_Post || '' === trim( (string) $post->post_content ) ) { return; }
		echo '<div class="gloskin-ui1-prose">'; echo apply_filters( 'the_content', $post->post_content ); echo '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

if ( ! function_exists( 'gloskin_ui1_empty' ) ) {
	function gloskin_ui1_empty( $message ) { echo '<div class="gloskin-ui1-empty">' . esc_html( $message ) . '</div>'; }
}

if ( ! function_exists( 'gloskin_ui1_render_card_grid' ) ) {
	function gloskin_ui1_render_card_grid( $cards, $kind ) { if ( ! $cards ) { return; } echo '<div class="gloskin-ui1-grid gloskin-ui1-grid--cards">'; foreach ( $cards as $card ) { gloskin_ui1_render_card( $card, $kind ); } echo '</div>'; }
}
