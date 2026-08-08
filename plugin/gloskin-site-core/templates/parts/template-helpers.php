<?php
/**
 * Side-effect-free Gloskin presentation helpers.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'gloskin_ui1_render_presentation_media' ) ) {
	/**
	 * Render deterministic abstract Gloskin media when factual photography is
	 * unavailable. The composition is decorative and never implies a person,
	 * clinic interior, treatment result or product identity.
	 *
	 * @param string $kind Visual family.
	 * @param string $seed Stable variation seed.
	 * @param string $class Additional class.
	 * @return void
	 */
	function gloskin_ui1_render_presentation_media( $kind = 'editorial', $seed = 'gloskin', $class = '' ) {
		$allowed = array( 'hero', 'clinic', 'treatment', 'doctor', 'skincare', 'product', 'editorial' );
		$kind    = in_array( $kind, $allowed, true ) ? $kind : 'editorial';
		$hash    = sprintf( '%u', crc32( $kind . '|' . $seed ) );
		$variant = 1 + ( (int) $hash % 4 );
		$classes = trim( 'gloskin-ui1-media gloskin-ui1-media--' . $kind . ' gloskin-ui1-media--v' . $variant . ' ' . $class );
		?>
		<div class="<?php echo esc_attr( $classes ); ?>" aria-hidden="true">
			<span class="gloskin-ui1-media__halo gloskin-ui1-media__halo--one"></span>
			<span class="gloskin-ui1-media__halo gloskin-ui1-media__halo--two"></span>
			<span class="gloskin-ui1-media__arc"></span>
			<span class="gloskin-ui1-media__line"></span>
			<span class="gloskin-ui1-media__point"></span>
		</div>
		<?php
	}
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
					<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Gloskin', 'gloskin-site-core' ); ?></p>
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
				<div class="gloskin-ui1-hero__media">
					<?php if ( $media ) : ?>
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
					<?php else : ?>
						<?php gloskin_ui1_render_presentation_media( 'hero', $heading, 'gloskin-ui1-media--hero-frame' ); ?>
					<?php endif; ?>
				</div>
			</div>
		</section>
		<?php
	}
}

if ( ! function_exists( 'gloskin_ui1_render_section_heading' ) ) {
	/**
	 * @param string $title Title.
	 * @param string $copy Optional copy.
	 * @return void
	 */
	function gloskin_ui1_render_section_heading( $title, $copy = '' ) {
		?>
		<div class="gloskin-ui1-section-heading">
			<h2><?php echo esc_html( $title ); ?></h2>
			<?php if ( '' !== $copy ) : ?><p><?php echo esc_html( $copy ); ?></p><?php endif; ?>
		</div>
		<?php
	}
}

if ( ! function_exists( 'gloskin_ui1_render_card' ) ) {
	/**
	 * @param array<string,mixed> $card Card data.
	 * @param string              $kind Card kind.
	 * @return void
	 */
	function gloskin_ui1_render_card( $card, $kind ) {
		$title    = isset( $card['title'] ) ? (string) $card['title'] : '';
		$url      = isset( $card['url'] ) ? (string) $card['url'] : '';
		$image_id = isset( $card['image_id'] ) ? absint( $card['image_id'] ) : 0;
		$copy     = '';

		if ( 'clinic' === $kind ) {
			$copy = isset( $card['short_location'] ) && '' !== trim( (string) $card['short_location'] )
				? (string) $card['short_location']
				: ( isset( $card['hours'] ) ? (string) $card['hours'] : '' );
		} elseif ( 'doctor' === $kind ) {
			$degree = isset( $card['degree_title'] ) ? trim( (string) $card['degree_title'] ) : '';
			$spec   = isset( $card['specialization'] ) ? trim( (string) $card['specialization'] ) : '';
			$copy   = trim( $degree . ( $degree && $spec ? ' · ' : '' ) . $spec );
		} elseif ( 'treatment' === $kind ) {
			$copy = isset( $card['summary'] ) ? (string) $card['summary'] : '';
		} else {
			$copy = isset( $card['excerpt'] ) ? (string) $card['excerpt'] : '';
		}
		$media_kind = in_array( $kind, array( 'clinic', 'doctor', 'treatment' ), true ) ? $kind : 'editorial';
		?>
		<article class="gloskin-ui1-card gloskin-ui1-card--<?php echo esc_attr( $kind ); ?>">
			<?php if ( '' !== $url ) : ?><a class="gloskin-ui1-card__media" href="<?php echo esc_url( $url ); ?>" tabindex="-1" aria-hidden="true"><?php endif; ?>
				<?php if ( $image_id ) : ?>
					<?php echo wp_get_attachment_image( $image_id, 'medium_large', false, array( 'loading' => 'lazy', 'class' => 'gloskin-ui1-card__image' ) ); ?>
				<?php else : ?>
					<?php gloskin_ui1_render_presentation_media( $media_kind, $title, 'gloskin-ui1-card__abstract' ); ?>
				<?php endif; ?>
			<?php if ( '' !== $url ) : ?></a><?php endif; ?>
			<div class="gloskin-ui1-card__body">
				<h3 class="gloskin-ui1-card__title"><?php if ( '' !== $url ) : ?><a href="<?php echo esc_url( $url ); ?>"><?php endif; ?><?php echo esc_html( $title ); ?><?php if ( '' !== $url ) : ?></a><?php endif; ?></h3>
				<?php if ( '' !== trim( $copy ) ) : ?><p class="gloskin-ui1-card__copy"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $copy ), 28 ) ); ?></p><?php endif; ?>
				<?php if ( '' !== $url ) : ?><a class="gloskin-ui1-text-link" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html__( 'Lihat Detail', 'gloskin-site-core' ); ?><span aria-hidden="true"> →</span></a><?php endif; ?>
			</div>
		</article>
		<?php
	}
}

if ( ! function_exists( 'gloskin_ui1_render_product_card' ) ) {
	/**
	 * @param array<string,mixed> $product Product data.
	 * @return void
	 */
	function gloskin_ui1_render_product_card( $product ) {
		$name     = isset( $product['name'] ) ? (string) $product['name'] : '';
		$url      = isset( $product['url'] ) ? (string) $product['url'] : '';
		$image_id = isset( $product['image_id'] ) ? absint( $product['image_id'] ) : 0;
		?>
		<article class="gloskin-ui1-card gloskin-ui1-card--product">
			<a class="gloskin-ui1-card__media" href="<?php echo esc_url( $url ); ?>" tabindex="-1" aria-hidden="true">
				<?php if ( $image_id ) : ?>
					<?php echo wp_get_attachment_image( $image_id, 'woocommerce_thumbnail', false, array( 'loading' => 'lazy', 'class' => 'gloskin-ui1-card__image' ) ); ?>
				<?php else : ?>
					<?php gloskin_ui1_render_presentation_media( 'product', $name, 'gloskin-ui1-card__abstract' ); ?>
				<?php endif; ?>
			</a>
			<div class="gloskin-ui1-card__body">
				<h3 class="gloskin-ui1-card__title"><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $name ); ?></a></h3>
				<?php if ( ! empty( $product['price_html'] ) ) : ?><div class="gloskin-ui1-product-price"><?php echo wp_kses_post( (string) $product['price_html'] ); ?></div><?php endif; ?>
				<?php if ( ! empty( $product['short_description'] ) ) : ?><p class="gloskin-ui1-card__copy"><?php echo esc_html( wp_trim_words( (string) $product['short_description'], 24 ) ); ?></p><?php endif; ?>
				<div class="gloskin-ui1-card__actions">
					<a class="gloskin-ui1-text-link" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html__( 'Lihat Produk', 'gloskin-site-core' ); ?></a>
					<?php if ( ! empty( $product['purchasable'] ) && ! empty( $product['in_stock'] ) && ! empty( $product['add_to_cart_url'] ) ) : ?>
						<a class="gloskin-ui1-button gloskin-ui1-button--small" href="<?php echo esc_url( (string) $product['add_to_cart_url'] ); ?>"><?php echo esc_html( (string) $product['add_to_cart_text'] ); ?></a>
					<?php endif; ?>
				</div>
			</div>
		</article>
		<?php
	}
}

if ( ! function_exists( 'gloskin_ui1_render_category_link' ) ) {
	/**
	 * @param array<string,mixed> $mapping Skincare landing mapping.
	 * @return void
	 */
	function gloskin_ui1_render_category_link( $mapping ) {
		$label = isset( $mapping['label'] ) ? (string) $mapping['label'] : '';
		$url   = isset( $mapping['url'] ) ? (string) $mapping['url'] : '';
		?>
		<a class="gloskin-ui1-category-card" href="<?php echo esc_url( $url ); ?>">
			<?php gloskin_ui1_render_presentation_media( 'skincare', $label, 'gloskin-ui1-category-card__media' ); ?>
			<span class="gloskin-ui1-category-card__label"><?php echo esc_html( $label ); ?></span>
			<span class="gloskin-ui1-category-card__arrow" aria-hidden="true">→</span>
		</a>
		<?php
	}
}

if ( ! function_exists( 'gloskin_ui1_has_content' ) ) {
	/** @param mixed $post WordPress post. */
	function gloskin_ui1_has_content( $post ) {
		return $post instanceof WP_Post && '' !== trim( (string) $post->post_content );
	}
}

if ( ! function_exists( 'gloskin_ui1_render_discovery_panel' ) ) {
	/**
	 * @param string $title Panel title.
	 * @param string $copy Panel copy.
	 * @param string $label Action label.
	 * @param string $url Action URL.
	 * @return void
	 */
	function gloskin_ui1_render_discovery_panel( $title, $copy, $label, $url ) {
		?>
		<div class="gloskin-ui1-contact-panel gloskin-ui1-discovery-panel">
			<div><h2><?php echo esc_html( $title ); ?></h2><?php if ( '' !== trim( $copy ) ) : ?><p><?php echo esc_html( $copy ); ?></p><?php endif; ?></div>
			<?php if ( '' !== $label && '' !== $url ) : ?><a class="gloskin-ui1-button gloskin-ui1-button--primary" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a><?php endif; ?>
		</div>
		<?php
	}
}

if ( ! function_exists( 'gloskin_ui1_render_editorial_split' ) ) {
	/**
	 * Safe editorial/navigation composition for sparse factual states.
	 *
	 * @param string $eyebrow Eyebrow.
	 * @param string $title Heading.
	 * @param string $copy Copy.
	 * @param string $label CTA label.
	 * @param string $url CTA URL.
	 * @param string $kind Abstract media family.
	 * @param bool   $reverse Reverse layout.
	 * @return void
	 */
	function gloskin_ui1_render_editorial_split( $eyebrow, $title, $copy, $label, $url, $kind = 'editorial', $reverse = false ) {
		?>
		<div class="gloskin-ui1-editorial-split<?php echo $reverse ? ' gloskin-ui1-editorial-split--reverse' : ''; ?>">
			<div class="gloskin-ui1-editorial-split__copy">
				<?php if ( '' !== $eyebrow ) : ?><p class="gloskin-ui1-eyebrow"><?php echo esc_html( $eyebrow ); ?></p><?php endif; ?>
				<h2><?php echo esc_html( $title ); ?></h2>
				<p><?php echo esc_html( $copy ); ?></p>
				<?php if ( '' !== $label && '' !== $url ) : ?><a class="gloskin-ui1-button gloskin-ui1-button--primary" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a><?php endif; ?>
			</div>
			<?php gloskin_ui1_render_presentation_media( $kind, $title, 'gloskin-ui1-editorial-split__media' ); ?>
		</div>
		<?php
	}
}

if ( ! function_exists( 'gloskin_ui1_render_page_content' ) ) {
	/** @param WP_Post|null $post Page/post. */
	function gloskin_ui1_render_page_content( $post ) {
		if ( ! $post instanceof WP_Post || '' === trim( (string) $post->post_content ) ) { return; }
		echo '<div class="gloskin-ui1-prose">';
		echo apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted editor content.
		echo '</div>';
	}
}

if ( ! function_exists( 'gloskin_ui1_empty' ) ) {
	/** @param string $message Message. */
	function gloskin_ui1_empty( $message ) { echo '<div class="gloskin-ui1-empty">' . esc_html( $message ) . '</div>'; }
}

if ( ! function_exists( 'gloskin_ui1_render_card_grid' ) ) {
	/**
	 * @param array<int,array<string,mixed>> $cards Cards.
	 * @param string $kind Card kind.
	 */
	function gloskin_ui1_render_card_grid( $cards, $kind ) {
		if ( ! $cards ) { return; }
		echo '<div class="gloskin-ui1-grid gloskin-ui1-grid--cards">';
		foreach ( $cards as $card ) { gloskin_ui1_render_card( $card, $kind ); }
		echo '</div>';
	}
}
