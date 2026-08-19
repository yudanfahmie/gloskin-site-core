<?php
/**
 * Side-effect-free Gloskin presentation helpers.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'gloskin_ui1_render_brand_logo' ) ) {
	/**
	 * Render the canonical Gloskin logo SVG (assets/images/gloskin-logotext.svg,
	 * untouched) as an <img> with explicit intrinsic dimensions matching its
	 * source aspect ratio (1600x520, scaled to 200x65) so the browser reserves
	 * layout space before the asset loads -- no CLS. CSS controls the actual
	 * displayed size per placement via the modifier class.
	 *
	 * @param string $url Logo asset URL.
	 * @param string $class Placement modifier class, e.g. 'gloskin-ui1-brand__image--footer'.
	 * @return void
	 */
	function gloskin_ui1_render_brand_logo( $url, $class = '' ) {
		if ( '' === trim( (string) $url ) ) {
			return;
		}
		$classes = trim( 'gloskin-ui1-brand__image ' . $class );
		echo '<img class="' . esc_attr( $classes ) . '" src="' . esc_url( $url ) . '" width="200" height="65" alt="Gloskin" decoding="async">';
	}
}

if ( ! function_exists( 'gloskin_ui1_render_presentation_media' ) ) {
	/**
	 * Render deterministic abstract Gloskin media for genuine factual empty states.
	 *
	 * This neutral composition must remain the fallback when a specific doctor,
	 * clinic or WooCommerce product has no factual WordPress-owned image.
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

if ( ! function_exists( 'gloskin_ui1_editorial_media_catalog' ) ) {
	/**
	 * Formerly returned Unsplash URLs. Now returns empty — all editorial media
	 * slots use the CSS-only abstract presentation fallback (gloskin_ui1_render_presentation_media)
	 * instead of runtime requests to images.unsplash.com. Zero external staging-image
	 * dependency after this change. WordPress/Woo factual media always wins.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	function gloskin_ui1_editorial_media_catalog() {
		return array(); /* No external image catalog — see gloskin_ui1_render_editorial_media(). */
	}
}

if ( ! function_exists( 'gloskin_ui1_resolve_editorial_media' ) ) {
	/**
	 * Formerly resolved Unsplash catalog entries. Now returns empty array — all
	 * editorial media uses the CSS-only abstract presentation fallback. Kept for
	 * backward API compatibility; callers should use gloskin_ui1_render_editorial_media().
	 *
	 * @param string $kind Generic visual family.
	 * @param string $seed Stable variation seed.
	 * @return array<string,mixed>
	 */
	function gloskin_ui1_resolve_editorial_media( $kind = 'editorial', $seed = 'gloskin' ) {
		unset( $kind, $seed );
		return array();
	}
}

if ( ! function_exists( 'gloskin_ui1_render_editorial_media' ) ) {
	/**
	 * Render a decorative editorial placeholder with no external network request.
	 * All slots previously served by Unsplash now use the CSS-only abstract
	 * presentation composition (gloskin_ui1_render_presentation_media). No runtime
	 * request to images.unsplash.com or any external host remains after this change.
	 *
	 * WordPress/Woo factual Media Library images always take precedence over this
	 * fallback; callers already check for a real attachment before calling here.
	 *
	 * @param string $kind  Generic visual family (hero|skincare|treatment|editorial…).
	 * @param string $seed  Stable variation seed.
	 * @param string $class Additional class applied to the wrapper.
	 * @param bool   $eager Unused — kept for call-site API compatibility.
	 * @return void
	 */
	function gloskin_ui1_render_editorial_media( $kind = 'editorial', $seed = 'gloskin', $class = '', $eager = false ) {
		unset( $eager ); /* No network I/O — eager/lazy loading no longer applies. */
		gloskin_ui1_render_presentation_media( $kind, $seed, $class );
	}
}

if ( ! function_exists( 'gloskin_ui1_render_hero' ) ) {
	/**
	 * One shared hero renderer for every editorial route. Home uses the
	 * `campaign` presentation mode: heading/copy/CTA remain visible while an
	 * optional native Media Library MP4/WebM enhances the same media column.
	 * The video is never a second hero or second data owner; on missing/failed
	 * media the existing factual attachment or deterministic editorial fallback
	 * remains visible behind it.
	 *
	 * @param array<string,mixed> $hero Hero context.
	 * @return void
	 */
	function gloskin_ui1_render_hero( $hero ) {
		$heading  = isset( $hero['heading'] ) ? (string) $hero['heading'] : '';
		$copy     = isset( $hero['copy'] ) ? (string) $hero['copy'] : '';
		$label    = isset( $hero['cta_label'] ) ? (string) $hero['cta_label'] : '';
		$url      = isset( $hero['cta_url'] ) ? (string) $hero['cta_url'] : '';
		$media    = isset( $hero['media_id'] ) ? absint( $hero['media_id'] ) : 0;
		$campaign = isset( $hero['mode'] ) && 'campaign' === $hero['mode'];
		$sources  = $campaign && isset( $hero['sources'] ) && is_array( $hero['sources'] ) ? array_values( array_filter( $hero['sources'], static function ( $source ) {
			return is_array( $source )
				&& ! empty( $source['src'] )
				&& isset( $source['type'] )
				&& in_array( (string) $source['type'], array( 'video/mp4', 'video/webm' ), true );
		} ) ) : array();
		$has_video = array() !== $sources;
		$classes   = 'gloskin-ui1-hero' . ( $campaign ? ' gloskin-ui1-hero--campaign' : '' ) . ( $has_video ? ' is-video-preparing' : '' );
		?>
		<section class="<?php echo esc_attr( $classes ); ?>"<?php echo $has_video ? ' data-gloskin-hero-bg-video-root' : ''; ?>>
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
				<div class="gloskin-ui1-hero__media<?php echo $campaign ? ' gloskin-ui1-hero__media--campaign' : ''; ?>">
					<div class="gloskin-ui1-hero__media-fallback">
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
							<?php gloskin_ui1_render_editorial_media( 'hero', $heading, 'gloskin-ui1-hero__image gloskin-ui1-hero__image--editorial', true ); ?>
						<?php endif; ?>
					</div>
					<?php if ( $has_video ) : ?>
						<div class="gloskin-ui1-hero-bg-video" data-gloskin-hero-bg-video-wrap>
							<video class="gloskin-ui1-hero-bg-video__media" data-gloskin-hero-bg-video muted autoplay loop playsinline preload="auto" aria-hidden="true" tabindex="-1">
								<?php foreach ( $sources as $source ) : ?>
									<source src="<?php echo esc_url( (string) $source['src'] ); ?>" type="<?php echo esc_attr( (string) $source['type'] ); ?>" />
								<?php endforeach; ?>
							</video>
							<div class="gloskin-ui1-hero-bg-video__loader" aria-hidden="true"><span class="gloskin-ui1-hero-bg-video__loader-dot"></span></div>
						</div>
					<?php endif; ?>
				</div>
			</div>
			<?php if ( $campaign ) : ?>
				<div class="gloskin-ui1-hero__fade" aria-hidden="true"></div>
				<button type="button" class="gloskin-ui1-hero__scroll-cue" data-gloskin-hero-scroll-cue aria-label="<?php echo esc_attr__( 'Gulir ke konten berikutnya', 'gloskin-site-core' ); ?>">
					<span class="gloskin-ui1-hero__scroll-cue-dot" aria-hidden="true"></span>
					<svg class="gloskin-ui1-hero__scroll-cue-chevron" width="18" height="10" viewBox="0 0 18 10" fill="none" aria-hidden="true" focusable="false"><path d="M1 1L9 8.5L17 1" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</button>
			<?php endif; ?>
		</section>
		<?php
	}
}

if ( ! function_exists( 'gloskin_ui1_render_promo_campaign' ) ) {
	/**
	 * Shared Promo composition for Home and /promo/. It renders only facts
	 * supplied by the native WordPress Page/Media owners. No discount, period,
	 * price or terms are synthesized when editors have not provided them.
	 *
	 * @param array<string,mixed> $campaign Promo context.
	 * @param string              $heading_tag h1 on /promo/, h2 on Home.
	 * @param bool                $compact Compact Home treatment.
	 * @return void
	 */
	function gloskin_ui1_render_promo_campaign( $campaign, $heading_tag = 'h2', $compact = false ) {
		$heading_tag = in_array( $heading_tag, array( 'h1', 'h2' ), true ) ? $heading_tag : 'h2';
		$title       = isset( $campaign['title'] ) ? trim( (string) $campaign['title'] ) : '';
		$copy        = isset( $campaign['copy'] ) ? trim( (string) $campaign['copy'] ) : '';
		$url         = isset( $campaign['url'] ) ? trim( (string) $campaign['url'] ) : '';
		$media_ids   = isset( $campaign['media_ids'] ) ? array_values( array_filter( array_map( 'absint', (array) $campaign['media_ids'] ) ) ) : array();
		$has_content = ! empty( $campaign['has_content'] );
		$has_facts   = '' !== $copy || $has_content || ! empty( $media_ids );
		$classes     = 'gloskin-ui1-promo-campaign' . ( $compact ? ' gloskin-ui1-promo-campaign--home' : ' gloskin-ui1-promo-campaign--page' );
		?>
		<section class="<?php echo esc_attr( $classes ); ?>" data-gloskin-promo-campaign>
			<div class="gloskin-ui1-container gloskin-ui1-promo-campaign__grid">
				<div class="gloskin-ui1-promo-campaign__copy">
					<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Promo', 'gloskin-site-core' ); ?></p>
					<<?php echo esc_attr( $heading_tag ); ?> class="gloskin-ui1-promo-campaign__title"><?php echo esc_html( '' !== $title ? $title : __( 'Promo Gloskin', 'gloskin-site-core' ) ); ?></<?php echo esc_attr( $heading_tag ); ?>>
					<?php if ( $has_facts ) : ?>
						<?php if ( '' !== $copy ) : ?><p class="gloskin-ui1-promo-campaign__summary"><?php echo esc_html( $copy ); ?></p><?php endif; ?>
						<?php if ( $compact && '' !== $url ) : ?><a class="gloskin-ui1-button gloskin-ui1-button--primary" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html__( 'Lihat Promo', 'gloskin-site-core' ); ?></a><?php endif; ?>
					<?php else : ?>
						<p class="gloskin-ui1-promo-campaign__empty"><?php echo esc_html__( 'Informasi promo belum tersedia.', 'gloskin-site-core' ); ?></p>
						<?php if ( $compact && '' !== $url ) : ?><a class="gloskin-ui1-text-link" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html__( 'Buka halaman Promo', 'gloskin-site-core' ); ?> →</a><?php endif; ?>
					<?php endif; ?>
				</div>
				<?php if ( $media_ids ) : ?>
					<div class="gloskin-ui1-promo-campaign__media" aria-label="<?php echo esc_attr__( 'Media promo', 'gloskin-site-core' ); ?>">
						<?php foreach ( array_slice( $media_ids, 0, 3 ) as $index => $media_id ) : ?>
							<div class="gloskin-ui1-promo-campaign__poster gloskin-ui1-promo-campaign__poster--<?php echo esc_attr( (string) ( $index + 1 ) ); ?>"><?php echo wp_get_attachment_image( $media_id, 'large', false, array( 'loading' => $compact ? 'lazy' : 'eager', 'class' => 'gloskin-ui1-promo-campaign__image' ) ); ?></div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
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
		?>
		<article class="gloskin-ui1-card gloskin-ui1-card--<?php echo esc_attr( $kind ); ?>">
			<?php if ( '' !== $url ) : ?><a class="gloskin-ui1-card__media" href="<?php echo esc_url( $url ); ?>" tabindex="-1" aria-hidden="true"><?php endif; ?>
				<?php if ( $image_id ) : ?>
					<?php echo wp_get_attachment_image( $image_id, 'medium_large', false, array( 'loading' => 'lazy', 'class' => 'gloskin-ui1-card__image' ) ); ?>
				<?php elseif ( in_array( $kind, array( 'clinic', 'doctor' ), true ) ) : ?>
					<?php gloskin_ui1_render_presentation_media( $kind, $title, 'gloskin-ui1-card__abstract' ); ?>
				<?php else : ?>
					<?php gloskin_ui1_render_editorial_media( 'treatment' === $kind ? 'treatment' : 'insight', $title, 'gloskin-ui1-card__image gloskin-ui1-card__image--editorial' ); ?>
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

if ( ! function_exists( 'gloskin_ui1_render_wishlist_toggle' ) ) {
	/**
	 * Wishlist toggle for a product card. Only ever invoked with genuine Woo
	 * product data (product cards only render when Woo supplied them), so no
	 * separate Woo-availability check is needed here.
	 *
	 * @param int    $product_id Woo product ID.
	 * @param string $name Product name.
	 * @return void
	 */
	function gloskin_ui1_render_wishlist_toggle( $product_id, $name ) {
		if ( ! $product_id ) {
			return;
		}
		/* translators: %s: product name, used in the wishlist "add" toggle's accessible label. */
		$add_label    = sprintf( __( 'Simpan %s ke favorit', 'gloskin-site-core' ), $name );
		/* translators: %s: product name, used in the wishlist "remove" toggle's accessible label. */
		$remove_label = sprintf( __( 'Hapus %s dari favorit', 'gloskin-site-core' ), $name );
		?>
		<button type="button" class="gloskin-ui1-wishlist-toggle" data-gloskin-wishlist-toggle="<?php echo esc_attr( $product_id ); ?>" aria-pressed="false" data-label-add="<?php echo esc_attr( $add_label ); ?>" data-label-remove="<?php echo esc_attr( $remove_label ); ?>" aria-label="<?php echo esc_attr( $add_label ); ?>">
			<svg width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden="true" focusable="false"><path d="M10 16.8C8.4 15.5 3 11.4 3 7.8 3 5.6 4.8 3.5 7.2 3.5c1.3 0 2.2.7 2.8 1.3.6-.6 1.5-1.3 2.8-1.3C15.2 3.5 17 5.6 17 7.8c0 3.6-5.4 7.7-7 9z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg>
		</button>
		<?php
	}
}

if ( ! function_exists( 'gloskin_ui1_render_product_card' ) ) {
	/**
	 * @param array<string,mixed> $product Product data.
	 * @param string              $variant Presentation variant; catalog remains the default.
	 * @return void
	 */
	function gloskin_ui1_render_product_card( $product, $variant = 'catalog' ) {
		$name     = isset( $product['name'] ) ? (string) $product['name'] : '';
		$url      = isset( $product['url'] ) ? (string) $product['url'] : '';
		$image_id = isset( $product['image_id'] ) ? absint( $product['image_id'] ) : 0;
		$id       = isset( $product['id'] ) ? absint( $product['id'] ) : 0;
		if ( 'consultation' === $variant ) {
			$description = ! empty( $product['short_description'] ) ? wp_trim_words( wp_strip_all_tags( (string) $product['short_description'] ), 42 ) : '';
			/* translators: %s: Treatment Product name. */
			$detail_label = sprintf( __( 'Lihat detail %s', 'gloskin-site-core' ), $name );
			?>
			<article class="gloskin-ui1-card gloskin-ui1-card--product gloskin-ui1-card--consultation">
				<a class="gloskin-ui1-consultation-card" href="<?php echo esc_url( $url ); ?>" aria-label="<?php echo esc_attr( $detail_label ); ?>">
					<span class="gloskin-ui1-consultation-card__main">
						<span class="gloskin-ui1-consultation-card__media">
							<?php if ( $image_id ) : ?>
								<?php echo wp_get_attachment_image( $image_id, 'woocommerce_thumbnail', false, array( 'loading' => 'lazy', 'class' => 'gloskin-ui1-consultation-card__image' ) ); ?>
							<?php else : ?>
								<?php gloskin_ui1_render_editorial_media( 'treatment', $name, 'gloskin-ui1-consultation-card__image gloskin-ui1-consultation-card__image--decorative' ); ?>
							<?php endif; ?>
						</span>
						<span class="gloskin-ui1-consultation-card__content">
							<span class="gloskin-ui1-consultation-card__title"><?php echo esc_html( $name ); ?></span>
							<?php if ( '' !== $description ) : ?><span class="gloskin-ui1-consultation-card__copy"><?php echo esc_html( $description ); ?></span><?php endif; ?>
						</span>
					</span>
					<span class="gloskin-ui1-consultation-card__footer">
						<?php if ( ! empty( $product['price_html'] ) ) : ?><span class="gloskin-ui1-consultation-card__price"><?php echo wp_kses_post( (string) $product['price_html'] ); ?></span><?php endif; ?>
					</span>
					<span class="gloskin-ui1-consultation-card__action" aria-hidden="true"><?php echo esc_html__( 'Lihat Detail', 'gloskin-site-core' ); ?></span>
				</a>
			</article>
			<?php
			return;
		}
		$type     = isset( $product['type'] ) ? (string) $product['type'] : '';
		$is_variable = 'variable' === $type;
		$can_purchase = ! empty( $product['purchasable'] ) && ! empty( $product['in_stock'] );
		$action_url = $is_variable ? $url : ( isset( $product['add_to_cart_url'] ) ? (string) $product['add_to_cart_url'] : '' );
		?>
		<article class="gloskin-ui1-card gloskin-ui1-card--product">
			<div class="gloskin-ui1-card__media-wrap">
				<a class="gloskin-ui1-card__media" href="<?php echo esc_url( $url ); ?>" tabindex="-1" aria-hidden="true">
					<?php if ( $image_id ) : ?>
						<?php echo wp_get_attachment_image( $image_id, 'woocommerce_thumbnail', false, array( 'loading' => 'lazy', 'class' => 'gloskin-ui1-card__image' ) ); ?>
					<?php else : ?>
						<?php gloskin_ui1_render_presentation_media( 'product', $name, 'gloskin-ui1-card__abstract' ); ?>
					<?php endif; ?>
				</a>
				<?php gloskin_ui1_render_wishlist_toggle( $id, $name ); ?>
			</div>
			<div class="gloskin-ui1-card__body">
				<h3 class="gloskin-ui1-card__title"><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $name ); ?></a></h3>
				<?php if ( ! empty( $product['price_html'] ) ) : ?><div class="gloskin-ui1-product-price"><?php echo wp_kses_post( (string) $product['price_html'] ); ?></div><?php endif; ?>
				<?php if ( ! empty( $product['short_description'] ) ) : ?><p class="gloskin-ui1-card__copy"><?php echo esc_html( wp_trim_words( (string) $product['short_description'], 24 ) ); ?></p><?php endif; ?>
				<div class="gloskin-ui1-card__actions">
					<?php if ( $can_purchase && '' !== $action_url ) :
						/* Mirror WooCommerce's own native loop add-to-cart contract
						 * (class/data-attribute composition) so Woo's own frontend
						 * scripts can bind exactly where Woo declares AJAX support.
						 * Variable products keep the canonical product URL as their
						 * no-JS fallback while Quick Add remains enhancement only. */
						$cart_classes = array( 'gloskin-ui1-button', 'gloskin-ui1-button--small', 'button', 'add_to_cart_button' );
						if ( '' !== $type ) {
							$cart_classes[] = 'product_type_' . sanitize_html_class( $type );
						}
						if ( ! empty( $product['ajax_add_to_cart'] ) ) {
							$cart_classes[] = 'ajax_add_to_cart';
						}
						if ( $is_variable ) {
							$cart_classes[] = 'gloskin-ui1-quickadd-trigger';
						}
						$cart_label = '' !== trim( (string) ( $product['add_to_cart_description'] ?? '' ) )
							? (string) $product['add_to_cart_description']
							: (string) ( $product['add_to_cart_text'] ?? '' );
						$cart_text = $is_variable ? __( 'Pilih Varian', 'gloskin-site-core' ) : (string) ( $product['add_to_cart_text'] ?? '' );
						?>
						<a href="<?php echo esc_url( $action_url ); ?>" data-quantity="1" class="<?php echo esc_attr( implode( ' ', $cart_classes ) ); ?>" data-product_id="<?php echo esc_attr( (string) $id ); ?>" data-product_sku="<?php echo esc_attr( (string) ( $product['sku'] ?? '' ) ); ?>"<?php echo $is_variable ? ' data-gloskin-quickadd-open data-gloskin-quickadd-product="' . esc_attr( (string) $id ) . '" aria-haspopup="dialog"' : ''; ?> aria-label="<?php echo esc_attr( $cart_label ); ?>" rel="nofollow"><?php echo esc_html( $cart_text ); ?></a>
					<?php elseif ( '' !== $url ) : ?>
						<a class="gloskin-ui1-button gloskin-ui1-button--small gloskin-ui1-button--ghost" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html__( 'Lihat Produk', 'gloskin-site-core' ); ?></a>
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
			<?php gloskin_ui1_render_editorial_media( 'skincare', $label, 'gloskin-ui1-category-card__media' ); ?>
			<span class="gloskin-ui1-category-card__label"><?php echo esc_html( $label ); ?></span>
			<span class="gloskin-ui1-category-card__arrow" aria-hidden="true">→</span>
		</a>
		<?php
	}
}

if ( ! function_exists( 'gloskin_ui1_render_category_rail' ) ) {
	/**
	 * Compact category discovery rail/chip navigation for /shop/. Pure
	 * navigation only -- each chip links to the existing branded
	 * /skincare/{slug}/ landing page; nothing here filters products in-page,
	 * issues AJAX, or invents category information architecture.
	 *
	 * @param array<int,array<string,mixed>> $mappings Canonical skincare category mappings.
	 * @return void
	 */
	function gloskin_ui1_render_category_rail( $mappings ) {
		?>
		<nav class="gloskin-ui1-catalog-rail" aria-label="<?php echo esc_attr__( 'Kategori skincare', 'gloskin-site-core' ); ?>">
			<ul class="gloskin-ui1-catalog-rail__list">
				<li><a class="gloskin-ui1-catalog-rail__chip is-active" href="<?php echo esc_url( home_url( '/shop/' ) ); ?>" aria-current="page"><?php echo esc_html__( 'Semua Produk', 'gloskin-site-core' ); ?></a></li>
				<?php foreach ( (array) $mappings as $mapping ) : ?>
					<li><a class="gloskin-ui1-catalog-rail__chip" href="<?php echo esc_url( (string) ( $mapping['url'] ?? '' ) ); ?>"><?php echo esc_html( (string) ( $mapping['label'] ?? '' ) ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</nav>
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
	 * Stock imagery here remains generic/decorative even when the destination is
	 * a doctor or clinic hub; it never presents a stock subject as that entity.
	 *
	 * @param string $eyebrow Eyebrow.
	 * @param string $title Heading.
	 * @param string $copy Copy.
	 * @param string $label CTA label.
	 * @param string $url CTA URL.
	 * @param string $kind Generic editorial family hint.
	 * @param bool   $reverse Reverse layout.
	 * @return void
	 */
	function gloskin_ui1_render_editorial_split( $eyebrow, $title, $copy, $label, $url, $kind = 'editorial', $reverse = false ) {
		$editorial_kind = in_array( $kind, array( 'treatment', 'skincare' ), true ) ? $kind : 'editorial';
		?>
		<div class="gloskin-ui1-editorial-split<?php echo $reverse ? ' gloskin-ui1-editorial-split--reverse' : ''; ?>">
			<div class="gloskin-ui1-editorial-split__copy">
				<?php if ( '' !== $eyebrow ) : ?><p class="gloskin-ui1-eyebrow"><?php echo esc_html( $eyebrow ); ?></p><?php endif; ?>
				<h2><?php echo esc_html( $title ); ?></h2>
				<p><?php echo esc_html( $copy ); ?></p>
				<?php if ( '' !== $label && '' !== $url ) : ?><a class="gloskin-ui1-button gloskin-ui1-button--primary" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a><?php endif; ?>
			</div>
			<?php gloskin_ui1_render_editorial_media( $editorial_kind, $title, 'gloskin-ui1-editorial-split__media' ); ?>
		</div>
		<?php
	}
}

if ( ! function_exists( 'gloskin_ui1_render_page_content' ) ) {
	/** @param WP_Post|null $post Page/post. */
	function gloskin_ui1_render_page_content( $post ) {
		if ( ! $post instanceof WP_Post || '' === trim( (string) $post->post_content ) ) { return; }
		echo '<div class="gloskin-ui1-prose">';
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- this is WordPress core's own `the_content` filter, invoked deliberately to render canonical page/post content through the normal content pipeline; it must not be renamed or duplicated.
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

if ( ! function_exists( 'gloskin_ui1_render_treatment_bands' ) ) {
	/**
	 * Render configured consultation paths as large alternating editorial bands.
	 * The consultation/recommendation engine remains authoritative; this is
	 * discovery presentation only.
	 *
	 * @param array<int,array<string,mixed>> $paths Consultation paths.
	 * @return void
	 */
	function gloskin_ui1_render_treatment_bands( $paths ) {
		if ( ! $paths ) {
			return;
		}
		?>
		<div class="gloskin-ui1-treatment-bands">
			<?php foreach ( $paths as $index => $path ) :
				$label    = isset( $path['label'] ) ? (string) $path['label'] : '';
				$image_id = isset( $path['image_id'] ) ? absint( $path['image_id'] ) : 0;
				$path_id  = isset( $path['id'] ) ? absint( $path['id'] ) : 0;
				$reverse  = 1 === ( $index % 2 );
				?>
				<div class="gloskin-ui1-treatment-band<?php echo $reverse ? ' gloskin-ui1-treatment-band--reverse' : ''; ?>" data-gloskin-treatment-band="<?php echo esc_attr( (string) $path_id ); ?>">
					<div class="gloskin-ui1-treatment-band__media">
						<?php if ( $image_id ) : ?>
							<?php echo wp_get_attachment_image( $image_id, 'large', false, array( 'loading' => 0 === $index ? 'eager' : 'lazy', 'class' => 'gloskin-ui1-treatment-band__image' ) ); ?>
						<?php else : ?>
							<?php gloskin_ui1_render_editorial_media( 'treatment', $label, 'gloskin-ui1-treatment-band__image gloskin-ui1-treatment-band__image--editorial', 0 === $index ); ?>
						<?php endif; ?>
					</div>
					<div class="gloskin-ui1-treatment-band__content">
						<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Perawatan', 'gloskin-site-core' ); ?></p>
						<h2 class="gloskin-ui1-treatment-band__title"><?php echo esc_html( $label ); ?></h2>
						<p class="gloskin-ui1-treatment-band__copy"><?php echo esc_html__( 'Temukan pilihan perawatan yang relevan dan diskusikan dengan dokter Gloskin saat konsultasi.', 'gloskin-site-core' ); ?></p>
						<a class="gloskin-ui1-button gloskin-ui1-button--primary" href="<?php echo esc_url( home_url( '/treatments/?path=' . $path_id . '#consultation' ) ); ?>" data-gloskin-band-path="<?php echo esc_attr( (string) $path_id ); ?>">
							<?php echo esc_html__( 'Jelajahi Solusi', 'gloskin-site-core' ); ?>
						</a>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}
}

if ( ! function_exists( 'gloskin_ui1_render_managed_promo_carousel' ) ) {
	/**
	 * Multi-campaign Promo carousel from managed gloskin_promo records.
	 * Keyboard-accessible, reduced-motion safe, clean empty/one-record state.
	 *
	 * @param array<int,array<string,mixed>> $promos  Active promo records.
	 * @param string                         $heading_tag h1 on /promo/ page, h2 on Home.
	 * @param bool                           $compact True when embedded on Home.
	 * @return void
	 */
	function gloskin_ui1_render_managed_promo_carousel( $promos, $heading_tag = 'h2', $compact = false ) {
		$heading_tag = in_array( $heading_tag, array( 'h1', 'h2' ), true ) ? $heading_tag : 'h2';
		$count       = count( $promos );
		$classes     = 'gloskin-ui1-promo-carousel' . ( $compact ? ' gloskin-ui1-promo-carousel--compact' : ' gloskin-ui1-promo-carousel--page' );

		if ( 0 === $count ) {
			/* No published promo records — render editorial fallback */
			?>
			<section class="<?php echo esc_attr( $classes ); ?>" data-gloskin-promo-carousel>
				<div class="gloskin-ui1-container gloskin-ui1-promo-carousel__empty">
					<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Promo', 'gloskin-site-core' ); ?></p>
					<<?php echo esc_attr( $heading_tag ); ?> class="gloskin-ui1-promo-carousel__empty-heading"><?php echo esc_html__( 'Promo Gloskin', 'gloskin-site-core' ); ?></<?php echo esc_attr( $heading_tag ); ?>>
					<p><?php echo esc_html__( 'Informasi promo belum tersedia.', 'gloskin-site-core' ); ?></p>
				</div>
			</section>
			<?php
			return;
		}

		$first = $promos[0];
		?>
		<section class="<?php echo esc_attr( $classes ); ?>" data-gloskin-promo-carousel aria-label="<?php echo esc_attr__( 'Promo Gloskin', 'gloskin-site-core' ); ?>">
			<div class="gloskin-ui1-container">
				<!-- main panel -->
				<div class="gloskin-ui1-promo-carousel__stage" role="region" aria-live="polite" aria-atomic="true">
					<?php foreach ( $promos as $promo_index => $promo ) :
						$is_first = 0 === $promo_index;
						$title     = (string) $promo['title'];
						$eyebrow   = (string) $promo['eyebrow'];
						$summary   = '' !== (string) $promo['summary'] ? (string) $promo['summary'] : (string) $promo['excerpt'];
						$cta_label = (string) $promo['cta_label'];
						$cta_url   = (string) $promo['cta_url'];
						$image_id  = absint( $promo['image_id'] );
						?>
						<div class="gloskin-ui1-promo-carousel__slide<?php echo $is_first ? ' is-active' : ''; ?>" data-gloskin-promo-slide="<?php echo esc_attr( (string) $promo_index ); ?>" <?php echo $is_first ? '' : 'hidden'; ?> aria-label="<?php /* translators: %1$d: slide number; %2$d: total slides. */ echo esc_attr( sprintf( __( 'Promo %1$d dari %2$d', 'gloskin-site-core' ), $promo_index + 1, $count ) ); ?>">
							<div class="gloskin-ui1-promo-carousel__slide-inner">
								<div class="gloskin-ui1-promo-carousel__copy">
									<?php if ( '' !== $eyebrow ) : ?><p class="gloskin-ui1-eyebrow"><?php echo esc_html( $eyebrow ); ?></p><?php endif; ?>
									<<?php echo esc_attr( $heading_tag ); ?> class="gloskin-ui1-promo-carousel__title"><?php echo esc_html( $title ); ?></<?php echo esc_attr( $heading_tag ); ?>>
									<?php if ( '' !== $summary ) : ?><p class="gloskin-ui1-promo-carousel__summary"><?php echo esc_html( wp_trim_words( $summary, 40 ) ); ?></p><?php endif; ?>
									<?php if ( '' !== $cta_label && '' !== $cta_url ) : ?>
										<a class="gloskin-ui1-button gloskin-ui1-button--primary" href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( $cta_label ); ?></a>
									<?php endif; ?>
								</div>
								<?php if ( $image_id ) : ?>
									<div class="gloskin-ui1-promo-carousel__media">
										<?php echo wp_get_attachment_image( $image_id, 'large', false, array( 'loading' => $is_first ? 'eager' : 'lazy', 'class' => 'gloskin-ui1-promo-carousel__image' ) ); ?>
									</div>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>

				<?php if ( $count > 1 ) : ?>
					<!-- controls -->
					<div class="gloskin-ui1-promo-carousel__controls" role="group" aria-label="<?php echo esc_attr__( 'Navigasi promo', 'gloskin-site-core' ); ?>">
						<button type="button" class="gloskin-ui1-promo-carousel__prev" data-gloskin-promo-prev aria-label="<?php echo esc_attr__( 'Promo sebelumnya', 'gloskin-site-core' ); ?>">
							<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true" focusable="false"><path d="M13 4L7 10L13 16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
						</button>
						<div class="gloskin-ui1-promo-carousel__dots" role="tablist" aria-label="<?php echo esc_attr__( 'Pilih promo', 'gloskin-site-core' ); ?>">
							<?php foreach ( $promos as $dot_index => $dot_promo ) : ?>
								<button type="button" class="gloskin-ui1-promo-carousel__dot<?php echo 0 === $dot_index ? ' is-active' : ''; ?>" role="tab" data-gloskin-promo-dot="<?php echo esc_attr( (string) $dot_index ); ?>" aria-selected="<?php echo 0 === $dot_index ? 'true' : 'false'; ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Promo %d', 'gloskin-site-core' ), $dot_index + 1 ) ); ?>"></button>
							<?php endforeach; ?>
						</div>
						<button type="button" class="gloskin-ui1-promo-carousel__next" data-gloskin-promo-next aria-label="<?php echo esc_attr__( 'Promo berikutnya', 'gloskin-site-core' ); ?>">
							<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true" focusable="false"><path d="M7 4L13 10L7 16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
						</button>
					</div>
				<?php endif; ?>
			</div>
		</section>
		<?php
	}
}

if ( ! function_exists( 'gloskin_ui1_render_why_gloskin' ) ) {
	/**
	 * Why Gloskin — dominant primary value block + supporting cards.
	 *
	 * Editor-manageable: when the home page has gloskin_why_heading, gloskin_why_lead,
	 * gloskin_why_primary_title, or gloskin_why_primary_copy meta fields set, those
	 * values override the defaults below. Copy must be factual/generic only; no
	 * numbers, guarantees, statistics, or medical claims.
	 *
	 * @param WP_Post|null $home_page Home page for optional editor override.
	 * @return void
	 */
	function gloskin_ui1_render_why_gloskin( $home_page = null ) {
		/* Read optional editor overrides — fall back to factual copy if blank */
		$why_heading       = '';
		$why_lead          = '';
		$why_primary_title = '';
		$why_primary_copy  = '';
		if ( $home_page instanceof WP_Post ) {
			$why_heading       = trim( (string) get_post_meta( $home_page->ID, 'gloskin_why_heading', true ) );
			$why_lead          = trim( (string) get_post_meta( $home_page->ID, 'gloskin_why_lead', true ) );
			$why_primary_title = trim( (string) get_post_meta( $home_page->ID, 'gloskin_why_primary_title', true ) );
			$why_primary_copy  = trim( (string) get_post_meta( $home_page->ID, 'gloskin_why_primary_copy', true ) );
		}
		if ( '' === $why_heading ) {
			$why_heading = __( 'Konsultasi dulu, baru menentukan perawatan', 'gloskin-site-core' );
		}
		if ( '' === $why_lead ) {
			$why_lead = __( 'Setiap perjalanan dimulai dari pemeriksaan dan diskusi bersama dokter — bukan dari katalog pilihan instan.', 'gloskin-site-core' );
		}
		if ( '' === $why_primary_title ) {
			$why_primary_title = __( 'Perjalanan yang Dipandu', 'gloskin-site-core' );
		}
		if ( '' === $why_primary_copy ) {
			$why_primary_copy = __( 'Gloskin menggabungkan konsultasi medis, pilihan perawatan, dan produk skincare dalam satu ekosistem yang terhubung — sehingga setiap rekomendasi didasari pemeriksaan kondisi kulit Anda secara langsung.', 'gloskin-site-core' );
		}
		?>
		<section class="gloskin-ui1-section gloskin-ui1-section--why" data-gloskin-section="why-gloskin">
			<div class="gloskin-ui1-container">
				<div class="gloskin-ui1-why__intro">
					<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Mengapa Gloskin', 'gloskin-site-core' ); ?></p>
					<h2 class="gloskin-ui1-why__heading"><?php echo esc_html( $why_heading ); ?></h2>
					<p class="gloskin-ui1-why__lead"><?php echo esc_html( $why_lead ); ?></p>
				</div>
				<div class="gloskin-ui1-why__primary">
					<div class="gloskin-ui1-why__primary-copy">
						<h3><?php echo esc_html( $why_primary_title ); ?></h3>
						<p><?php echo esc_html( $why_primary_copy ); ?></p>
						<a class="gloskin-ui1-button gloskin-ui1-button--primary" href="<?php echo esc_url( home_url( '/treatments/' ) ); ?>"><?php echo esc_html__( 'Jelajahi Perawatan', 'gloskin-site-core' ); ?></a>
					</div>
					<div class="gloskin-ui1-why__primary-media" aria-hidden="true">
						<?php gloskin_ui1_render_presentation_media( 'treatment', 'why-gloskin-primary', 'gloskin-ui1-why__primary-image' ); ?>
					</div>
				</div>
				<div class="gloskin-ui1-why__cards">
					<div class="gloskin-ui1-why__card">
						<div class="gloskin-ui1-why__card-icon" aria-hidden="true">
							<svg width="32" height="32" viewBox="0 0 32 32" fill="none"><circle cx="16" cy="16" r="13" stroke="currentColor" stroke-width="1.5"/><path d="M10 16.5c1.5 2 3.5 3.5 6 3.5s4.5-1.5 6-3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><circle cx="12" cy="13" r="1.2" fill="currentColor"/><circle cx="20" cy="13" r="1.2" fill="currentColor"/></svg>
						</div>
						<h3 class="gloskin-ui1-why__card-title"><?php echo esc_html__( 'Penemuan berdasarkan kebutuhan', 'gloskin-site-core' ); ?></h3>
						<p class="gloskin-ui1-why__card-copy"><?php echo esc_html__( 'Temukan pilihan perawatan berdasarkan keluhan dan kondisi kulit — bukan label generik.', 'gloskin-site-core' ); ?></p>
					</div>
					<div class="gloskin-ui1-why__card">
						<div class="gloskin-ui1-why__card-icon" aria-hidden="true">
							<svg width="32" height="32" viewBox="0 0 32 32" fill="none"><path d="M8 24V12l8-6 8 6v12H8z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><rect x="13" y="17" width="6" height="7" rx="1" stroke="currentColor" stroke-width="1.5"/></svg>
						</div>
						<h3 class="gloskin-ui1-why__card-title"><?php echo esc_html__( 'Klinik dan produk dalam satu jaringan', 'gloskin-site-core' ); ?></h3>
						<p class="gloskin-ui1-why__card-copy"><?php echo esc_html__( 'Perawatan klinik dan produk skincare Gloskin dirancang dalam satu ekosistem yang saling melengkapi.', 'gloskin-site-core' ); ?></p>
					</div>
					<div class="gloskin-ui1-why__card">
						<div class="gloskin-ui1-why__card-icon" aria-hidden="true">
							<svg width="32" height="32" viewBox="0 0 32 32" fill="none"><path d="M16 4C9.373 4 4 9.373 4 16s5.373 12 12 12 12-5.373 12-12S22.627 4 16 4z" stroke="currentColor" stroke-width="1.5"/><path d="M16 10v6l4 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
						</div>
						<h3 class="gloskin-ui1-why__card-title"><?php echo esc_html__( 'Dukungan dokter yang tersedia', 'gloskin-site-core' ); ?></h3>
						<p class="gloskin-ui1-why__card-copy"><?php echo esc_html__( 'Tim dokter Gloskin tersedia di jaringan klinik untuk konsultasi dan perencanaan perawatan.', 'gloskin-site-core' ); ?></p>
					</div>
				</div>
			</div>
		</section>
		<?php
	}
}

if ( ! function_exists( 'gloskin_ui1_render_testimonials' ) ) {
	/**
	 * Testimonials section — renders only from valid published records.
	 * Static for one record, slider/dots for multiple. No forced auto-rotation.
	 *
	 * @param array<int,array<string,mixed>> $testimonials Published testimonial records.
	 * @return void
	 */
	function gloskin_ui1_render_testimonials( $testimonials ) {
		if ( ! $testimonials ) {
			return; /* Omit section entirely when no published factual records */
		}
		$count = count( $testimonials );
		?>
		<section class="gloskin-ui1-section gloskin-ui1-section--testimonials" data-gloskin-section="testimonials">
			<div class="gloskin-ui1-container">
				<div class="gloskin-ui1-section-heading">
					<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Testimoni', 'gloskin-site-core' ); ?></p>
					<h2><?php echo esc_html__( 'Pengalaman Pelanggan Gloskin', 'gloskin-site-core' ); ?></h2>
				</div>
				<div class="gloskin-ui1-testimonials" data-gloskin-testimonials aria-label="<?php echo esc_attr__( 'Testimoni pelanggan', 'gloskin-site-core' ); ?>">
					<?php foreach ( $testimonials as $t_index => $testimonial ) :
						$is_first    = 0 === $t_index;
						$attribution = (string) $testimonial['meta']['attribution'];
						$subtitle    = (string) $testimonial['meta']['subtitle'];
						$quote       = '' !== (string) $testimonial['excerpt']
							? (string) $testimonial['excerpt']
							: (string) $testimonial['title'];
						?>
						<figure class="gloskin-ui1-testimonial<?php echo $is_first ? ' is-active' : ''; ?>" data-gloskin-testimonial="<?php echo esc_attr( (string) $t_index ); ?>"<?php echo $is_first ? '' : ' hidden'; ?> aria-label="<?php echo esc_attr( sprintf( __( 'Testimoni %d dari %d', 'gloskin-site-core' ), $t_index + 1, $count ) ); ?>">
							<blockquote class="gloskin-ui1-testimonial__quote">
								<p>"<?php echo esc_html( wp_trim_words( $quote, 60 ) ); ?>"</p>
							</blockquote>
							<figcaption class="gloskin-ui1-testimonial__attribution">
								<?php if ( $testimonial['image_id'] ) : ?>
									<?php echo wp_get_attachment_image( $testimonial['image_id'], 'thumbnail', false, array( 'class' => 'gloskin-ui1-testimonial__avatar', 'loading' => 'lazy' ) ); ?>
								<?php endif; ?>
								<div class="gloskin-ui1-testimonial__identity">
									<?php if ( '' !== $attribution ) : ?><strong><?php echo esc_html( $attribution ); ?></strong><?php endif; ?>
									<?php if ( '' !== $subtitle ) : ?><span><?php echo esc_html( $subtitle ); ?></span><?php endif; ?>
								</div>
							</figcaption>
						</figure>
					<?php endforeach; ?>
					<?php if ( $count > 1 ) : ?>
						<div class="gloskin-ui1-testimonials__controls" role="group" aria-label="<?php echo esc_attr__( 'Navigasi testimoni', 'gloskin-site-core' ); ?>">
							<div class="gloskin-ui1-testimonials__dots" role="tablist">
								<?php foreach ( $testimonials as $d_index => $_ ) : ?>
									<button type="button" class="gloskin-ui1-testimonials__dot<?php echo 0 === $d_index ? ' is-active' : ''; ?>" role="tab" data-gloskin-testimonial-dot="<?php echo esc_attr( (string) $d_index ); ?>" aria-selected="<?php echo 0 === $d_index ? 'true' : 'false'; ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Testimoni %d', 'gloskin-site-core' ), $d_index + 1 ) ); ?>"></button>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</section>
		<?php
	}
}

if ( ! function_exists( 'gloskin_ui1_render_achievements' ) ) {
	/**
	 * Achievements/Piagam section — renders only from valid published records.
	 *
	 * @param array<int,array<string,mixed>> $achievements Published achievement records.
	 * @param string                         $variant      'compact' for Home, 'full' for About.
	 * @return void
	 */
	function gloskin_ui1_render_achievements( $achievements, $variant = 'compact' ) {
		if ( ! $achievements ) {
			return; /* Omit section entirely when no published factual records */
		}
		$classes = 'gloskin-ui1-section gloskin-ui1-achievements' . ( 'full' === $variant ? ' gloskin-ui1-achievements--full' : ' gloskin-ui1-achievements--compact' );
		?>
		<section class="<?php echo esc_attr( $classes ); ?>" data-gloskin-section="achievements">
			<div class="gloskin-ui1-container">
				<div class="gloskin-ui1-section-heading">
					<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Penghargaan', 'gloskin-site-core' ); ?></p>
					<h2><?php echo 'full' === $variant ? esc_html__( 'Pencapaian dan Penghargaan', 'gloskin-site-core' ) : esc_html__( 'Diakui', 'gloskin-site-core' ); ?></h2>
				</div>
				<div class="gloskin-ui1-achievements__grid">
					<?php foreach ( $achievements as $achievement ) :
						$title   = (string) $achievement['title'];
						$excerpt = (string) $achievement['excerpt'];
						$issuer  = (string) $achievement['meta']['issuer'];
						$year    = (string) $achievement['meta']['year'];
						$img_id  = absint( $achievement['image_id'] );
						?>
						<div class="gloskin-ui1-achievement">
							<?php if ( $img_id ) : ?>
								<div class="gloskin-ui1-achievement__media">
									<?php echo wp_get_attachment_image( $img_id, 'medium', false, array( 'loading' => 'lazy', 'class' => 'gloskin-ui1-achievement__image' ) ); ?>
								</div>
							<?php endif; ?>
							<div class="gloskin-ui1-achievement__body">
								<h3 class="gloskin-ui1-achievement__title"><?php echo esc_html( $title ); ?></h3>
								<?php if ( '' !== $issuer || '' !== $year ) : ?>
									<p class="gloskin-ui1-achievement__meta"><?php echo esc_html( trim( $issuer . ( $issuer && $year ? ', ' : '' ) . $year ) ); ?></p>
								<?php endif; ?>
								<?php if ( '' !== $excerpt && 'full' === $variant ) : ?>
									<p class="gloskin-ui1-achievement__copy"><?php echo esc_html( wp_trim_words( $excerpt, 30 ) ); ?></p>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
		<?php
	}
}
