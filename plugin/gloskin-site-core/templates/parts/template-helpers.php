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
	 * Curated staging/editorial photography.
	 *
	 * These are fixed Unsplash image URLs, never random/query endpoints. They are
	 * decorative only and must never represent a factual Gloskin doctor, clinic,
	 * WooCommerce product or medical result. WordPress/Woo factual media always wins.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	function gloskin_ui1_editorial_media_catalog() {
		return array(
			'wellness-room' => array(
				'src'    => 'https://images.unsplash.com/photo-1600334089648-b0d9d3028eb2?auto=format&fit=crop&w=1600&h=1200&q=82',
				'width'  => 1600,
				'height' => 1200,
			),
			'skincare-still-life' => array(
				'src'    => 'https://images.unsplash.com/photo-1556228578-8c89e6adf883?auto=format&fit=crop&w=1400&h=1100&q=82',
				'width'  => 1400,
				'height' => 1100,
			),
			'skincare-interior' => array(
				'src'    => 'https://images.unsplash.com/photo-1778330804164-2f6d5d3b16ad?auto=format&fit=crop&w=1400&h=1100&q=82',
				'width'  => 1400,
				'height' => 1100,
			),
			'wellness-editorial' => array(
				'src'    => 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?auto=format&fit=crop&w=1400&h=1100&q=82',
				'width'  => 1400,
				'height' => 1100,
			),
		);
	}
}

if ( ! function_exists( 'gloskin_ui1_resolve_editorial_media' ) ) {
	/**
	 * Pick a deterministic generic editorial image without creating factual identity.
	 *
	 * @param string $kind Generic visual family.
	 * @param string $seed Stable variation seed.
	 * @return array<string,mixed>
	 */
	function gloskin_ui1_resolve_editorial_media( $kind = 'editorial', $seed = 'gloskin' ) {
		$catalog = gloskin_ui1_editorial_media_catalog();
		$pools   = array(
			'hero'      => array( 'wellness-room', 'skincare-interior' ),
			'skincare'  => array( 'skincare-still-life', 'skincare-interior' ),
			'treatment' => array( 'wellness-editorial', 'wellness-room' ),
			'editorial' => array( 'wellness-room', 'skincare-interior', 'skincare-still-life' ),
			'insight'   => array( 'skincare-interior', 'wellness-room' ),
		);
		$pool = isset( $pools[ $kind ] ) ? $pools[ $kind ] : $pools['editorial'];
		$hash = (int) sprintf( '%u', crc32( $kind . '|' . $seed ) );
		$key  = $pool[ $hash % count( $pool ) ];

		return $catalog[ $key ];
	}
}

if ( ! function_exists( 'gloskin_ui1_render_editorial_media' ) ) {
	/**
	 * Render curated decorative staging photography with stable intrinsic geometry.
	 *
	 * @param string $kind Generic visual family.
	 * @param string $seed Stable variation seed.
	 * @param string $class Additional class.
	 * @param bool   $eager Whether the image is above the fold.
	 * @return void
	 */
	function gloskin_ui1_render_editorial_media( $kind = 'editorial', $seed = 'gloskin', $class = '', $eager = false ) {
		$media   = gloskin_ui1_resolve_editorial_media( $kind, $seed );
		$classes = trim( 'gloskin-ui1-editorial-image gloskin-ui1-editorial-image--' . sanitize_html_class( $kind ) . ' ' . $class );
		?>
		<img
			class="<?php echo esc_attr( $classes ); ?>"
			src="<?php echo esc_url( $media['src'] ); ?>"
			width="<?php echo esc_attr( (string) $media['width'] ); ?>"
			height="<?php echo esc_attr( (string) $media['height'] ); ?>"
			alt=""
			aria-hidden="true"
			decoding="async"
			loading="<?php echo $eager ? 'eager' : 'lazy'; ?>"
			<?php if ( $eager ) : ?>fetchpriority="high"<?php endif; ?>
			data-gloskin-editorial="unsplash"
		>
		<?php
	}
}

if ( ! function_exists( 'gloskin_ui1_render_hero' ) ) {
	/**
	 * @param array<string,mixed> $hero Hero context.
	 * @return void
	 */
	function gloskin_ui1_render_hero( $hero ) {
		$heading  = isset( $hero['heading'] ) ? (string) $hero['heading'] : '';
		$copy     = isset( $hero['copy'] ) ? (string) $hero['copy'] : '';
		$label    = isset( $hero['cta_label'] ) ? (string) $hero['cta_label'] : '';
		$url      = isset( $hero['cta_url'] ) ? (string) $hero['cta_url'] : '';
		$media    = isset( $hero['media_id'] ) ? absint( $hero['media_id'] ) : 0;
		$video_id = ! empty( $hero['video_enabled'] ) && ! empty( $hero['video_id'] ) ? (string) $hero['video_id'] : '';
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
					<?php if ( '' !== $video_id ) : ?>
						<?php gloskin_ui1_render_hero_video( $video_id, $heading ); ?>
					<?php elseif ( $media ) : ?>
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
			</div>
		</section>
		<?php
	}
}

if ( ! function_exists( 'gloskin_ui1_render_hero_video' ) ) {
	/**
	 * Performance-first poster/facade for the Home hero video: NO iframe in
	 * the initial server-rendered HTML, only a stable aspect-ratio box, the
	 * YouTube thumbnail as a real <img> (so it can become the LCP candidate
	 * instead of a full YouTube player boot), and one real accessible
	 * <button>. gloskin-ui1-core.js progressively enhances this into a
	 * youtube-nocookie.com iframe -- see initHeroVideo(). $video_id is
	 * always already validated (11-char YouTube ID pattern) by
	 * Gloskin_Site_Core_Admin_Service::resolve_youtube_video_id() before
	 * this ever runs; never raw admin input.
	 *
	 * @param string $video_id Validated 11-character YouTube video ID.
	 * @param string $heading Hero heading, reused for the poster's accessible fallback context.
	 * @return void
	 */
	function gloskin_ui1_render_hero_video( $video_id, $heading = '' ) {
		if ( '' === trim( (string) $video_id ) ) {
			return;
		}
		$maxres     = 'https://i.ytimg.com/vi/' . rawurlencode( $video_id ) . '/maxresdefault.jpg';
		$hq         = 'https://i.ytimg.com/vi/' . rawurlencode( $video_id ) . '/hqdefault.jpg';
		$play_label = __( 'Play hero video', 'gloskin-site-core' );
		/* Meaningful iframe title once the video becomes interactive (see
		 * initHeroVideo() in gloskin-ui1-core.js), reusing the hero heading
		 * when one exists rather than a generic placeholder. */
		$video_title = '' !== trim( $heading ) ? $heading : __( 'Gloskin hero video', 'gloskin-site-core' );
		?>
		<div class="gloskin-ui1-hero-video gloskin-ui1-hero__image" data-gloskin-hero-video data-video-id="<?php echo esc_attr( $video_id ); ?>" data-video-title="<?php echo esc_attr( $video_title ); ?>">
			<img class="gloskin-ui1-hero-video__poster" src="<?php echo esc_url( $maxres ); ?>" data-gloskin-hero-video-fallback="<?php echo esc_url( $hq ); ?>" alt="" width="1280" height="720" fetchpriority="high" decoding="async" />
			<button type="button" class="gloskin-ui1-hero-video__play" data-gloskin-hero-video-play aria-label="<?php echo esc_attr( $play_label ); ?>">
				<svg width="22" height="22" viewBox="0 0 20 20" fill="none" aria-hidden="true" focusable="false"><path d="M6.5 4.5v11l9-5.5-9-5.5z" fill="currentColor"/></svg>
			</button>
		</div>
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
	 * @return void
	 */
	function gloskin_ui1_render_product_card( $product ) {
		$name     = isset( $product['name'] ) ? (string) $product['name'] : '';
		$url      = isset( $product['url'] ) ? (string) $product['url'] : '';
		$image_id = isset( $product['image_id'] ) ? absint( $product['image_id'] ) : 0;
		$id       = isset( $product['id'] ) ? absint( $product['id'] ) : 0;
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
