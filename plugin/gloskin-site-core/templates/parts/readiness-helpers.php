<?php
/**
 * Gloskin readiness presentation helpers: breadcrumbs and meaningful zero states.
 *
 * No metadata, schema, commerce data, or authentication ownership lives here.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'gloskin_ui1_empty_state_icon' ) ) {
	/**
	 * Decorative inline SVG for the shared empty-state visual language.
	 *
	 * @param string $kind Empty-state kind.
	 * @return string
	 */
	function gloskin_ui1_empty_state_icon( $kind ) {
		$kind = sanitize_key( $kind );
		$paths = array(
			'search'    => '<circle cx="24" cy="24" r="10"/><path d="m31.5 31.5 8 8"/><path d="M18 24h12"/>',
			'cart'      => '<path d="M16 20h24l2 24H14l2-24Z"/><path d="M21 20v-3a7 7 0 0 1 14 0v3"/>',
			'wishlist'  => '<path d="M28 43S12 33 12 21.5C12 15.7 16.4 12 21 12c3.1 0 5.4 1.6 7 3.7 1.6-2.1 3.9-3.7 7-3.7 4.6 0 9 3.7 9 9.5C44 33 28 43 28 43Z"/>',
			'treatment' => '<circle cx="28" cy="28" r="15"/><path d="M28 20v16M20 28h16"/><path d="M12 15l3 3M41 38l3 3"/>',
			'clinic'    => '<path d="M15 44V17h26v27"/><path d="M22 44V34h12v10M22 23h3M31 23h3M22 29h3M31 29h3"/><path d="M12 44h32"/>',
			'doctor'    => '<circle cx="28" cy="20" r="8"/><path d="M14 45c1.2-9 6.7-14 14-14s12.8 5 14 14"/><path d="M21 36c2 2 4.3 3 7 3s5-1 7-3"/>',
			'insight'   => '<path d="M17 11h18l7 7v27H17V11Z"/><path d="M35 11v8h7M22 27h14M22 33h14M22 39h9"/>',
			'product'   => '<path d="M21 15h14v7l5 6v17H16V28l5-6v-7Z"/><path d="M21 22h14M21 33h14"/>',
			'account'   => '<circle cx="28" cy="19" r="8"/><path d="M13 45c1.5-9.2 7-14 15-14s13.5 4.8 15 14"/>',
			'generic'   => '<circle cx="28" cy="28" r="16"/><path d="M22 28h12M28 22v12"/>',
		);
		$path = isset( $paths[ $kind ] ) ? $paths[ $kind ] : $paths['generic'];
		return '<svg viewBox="0 0 56 56" width="56" height="56" fill="none" aria-hidden="true" focusable="false"><g stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">' . $path . '</g><circle class="gloskin-ui1-empty-state__accent" cx="44" cy="13" r="3" fill="currentColor" stroke="none"/></svg>';
	}
}

if ( ! function_exists( 'gloskin_ui1_empty_state_html' ) ) {
	/**
	 * Build the shared empty-state component markup.
	 *
	 * @param string $kind Empty-state kind.
	 * @param string $title State title.
	 * @param string $copy Supporting copy.
	 * @param string $action_label Optional action label.
	 * @param string $action_url Optional action URL.
	 * @return string
	 */
	function gloskin_ui1_empty_state_html( $kind, $title, $copy = '', $action_label = '', $action_url = '' ) {
		$kind = sanitize_key( $kind );
		ob_start();
		?>
		<div class="gloskin-ui1-empty-state gloskin-ui1-empty-state--<?php echo esc_attr( $kind ? $kind : 'generic' ); ?>" data-gloskin-empty-state>
			<div class="gloskin-ui1-empty-state__visual"><?php echo gloskin_ui1_empty_state_icon( $kind ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG map above. ?></div>
			<div class="gloskin-ui1-empty-state__body">
				<h3 class="gloskin-ui1-empty-state__title"><?php echo esc_html( $title ); ?></h3>
				<?php if ( '' !== trim( (string) $copy ) ) : ?><p class="gloskin-ui1-empty-state__copy"><?php echo esc_html( $copy ); ?></p><?php endif; ?>
				<?php if ( '' !== trim( (string) $action_label ) && '' !== trim( (string) $action_url ) ) : ?><a class="gloskin-ui1-button gloskin-ui1-button--ghost gloskin-ui1-empty-state__action" href="<?php echo esc_url( $action_url ); ?>"><?php echo esc_html( $action_label ); ?></a><?php endif; ?>
			</div>
		</div>
		<?php
		return trim( (string) ob_get_clean() );
	}
}

if ( ! function_exists( 'gloskin_ui1_render_empty_state' ) ) {
	/** @see gloskin_ui1_empty_state_html() */
	function gloskin_ui1_render_empty_state( $kind, $title, $copy = '', $action_label = '', $action_url = '' ) {
		echo gloskin_ui1_empty_state_html( $kind, $title, $copy, $action_label, $action_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes dynamic values.
	}
}

if ( ! function_exists( 'gloskin_ui1_real_cards' ) ) {
	/**
	 * Keep collection UI tied to published WordPress records rather than route placeholders.
	 *
	 * @param array<int,array<string,mixed>> $cards Cards.
	 * @return array<int,array<string,mixed>>
	 */
	function gloskin_ui1_real_cards( $cards ) {
		return array_values(
			array_filter(
				(array) $cards,
				static function ( $card ) {
					return is_array( $card ) && ! empty( $card['id'] );
				}
			)
		);
	}
}

if ( ! function_exists( 'gloskin_ui1_breadcrumb_current_title' ) ) {
	/** @return string */
	function gloskin_ui1_breadcrumb_current_title() {
		$title = function_exists( 'get_queried_object_id' ) ? get_the_title( get_queried_object_id() ) : '';
		return '' !== trim( (string) $title ) ? (string) $title : __( 'Halaman', 'gloskin-site-core' );
	}
}

if ( ! function_exists( 'gloskin_ui1_fallback_breadcrumb_items' ) ) {
	/**
	 * Build visible-only fallback breadcrumbs from real route relationships.
	 * No JSON-LD, canonical, metadata, or crawler-only output is produced.
	 *
	 * @param array<string,mixed> $context Gloskin shell context.
	 * @return array<int,array{label:string,url:string,current:bool}>
	 */
	function gloskin_ui1_fallback_breadcrumb_items( $context ) {
		$view = isset( $context['view'] ) ? sanitize_key( $context['view'] ) : '';
		$items = array(
			array( 'label' => 'Home', 'url' => home_url( '/' ), 'current' => false ),
		);
		$hub = static function ( $label, $path ) use ( &$items ) {
			$items[] = array( 'label' => $label, 'url' => home_url( $path ), 'current' => false );
		};
		$current = static function ( $label ) use ( &$items ) {
			$items[] = array( 'label' => $label, 'url' => '', 'current' => true );
		};

		switch ( $view ) {
			case 'about': $current( __( 'Tentang Gloskin', 'gloskin-site-core' ) ); break;
			case 'treatments': $current( __( 'Perawatan', 'gloskin-site-core' ) ); break;
			case 'treatment':
				$hub( __( 'Perawatan', 'gloskin-site-core' ), '/treatments/' );
				$current( gloskin_ui1_breadcrumb_current_title() );
				break;
			case 'skincare': $current( __( 'Skincare', 'gloskin-site-core' ) ); break;
			case 'skincare-category':
				$hub( __( 'Skincare', 'gloskin-site-core' ), '/skincare/' );
				$current( gloskin_ui1_breadcrumb_current_title() );
				break;
			case 'clinics': $current( __( 'Klinik', 'gloskin-site-core' ) ); break;
			case 'clinic':
				$hub( __( 'Klinik', 'gloskin-site-core' ), '/clinics/' );
				$current( gloskin_ui1_breadcrumb_current_title() );
				break;
			case 'doctors': $current( __( 'Dokter', 'gloskin-site-core' ) ); break;
			case 'doctor':
				$hub( __( 'Dokter', 'gloskin-site-core' ), '/doctors/' );
				$current( gloskin_ui1_breadcrumb_current_title() );
				break;
			case 'insights': $current( __( 'Insight', 'gloskin-site-core' ) ); break;
			case 'shop': $current( __( 'Belanja', 'gloskin-site-core' ) ); break;
			case 'contact': $current( __( 'Kontak', 'gloskin-site-core' ) ); break;
			case 'commerce-native':
				if ( function_exists( 'is_product' ) && is_product() ) {
					$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
					$items[] = array( 'label' => __( 'Belanja', 'gloskin-site-core' ), 'url' => $shop_url ? (string) $shop_url : home_url( '/shop/' ), 'current' => false );
					$current( gloskin_ui1_breadcrumb_current_title() );
				} elseif ( function_exists( 'is_cart' ) && is_cart() ) {
					$current( __( 'Keranjang', 'gloskin-site-core' ) );
				} elseif ( function_exists( 'is_checkout' ) && is_checkout() ) {
					$current( __( 'Checkout', 'gloskin-site-core' ) );
				} elseif ( function_exists( 'is_account_page' ) && is_account_page() ) {
					$current( __( 'Akun', 'gloskin-site-core' ) );
				} else {
					$current( gloskin_ui1_breadcrumb_current_title() );
				}
				break;
			default:
				$current( gloskin_ui1_breadcrumb_current_title() );
				break;
		}
		return $items;
	}
}

if ( ! function_exists( 'gloskin_ui1_render_breadcrumbs' ) ) {
	/**
	 * Render exactly one breadcrumb owner on non-home Gloskin shell pages.
	 * Rank Math owns visible breadcrumbs when its documented function exists;
	 * otherwise a visible semantic Gloskin fallback is used without schema.
	 *
	 * @param array<string,mixed> $context Gloskin shell context.
	 * @return void
	 */
	function gloskin_ui1_render_breadcrumbs( $context ) {
		$view = isset( $context['view'] ) ? sanitize_key( $context['view'] ) : '';
		if ( 'home' === $view || ( function_exists( 'is_front_page' ) && is_front_page() ) ) {
			return;
		}
		echo '<div class="gloskin-ui1-breadcrumb-slot"><div class="gloskin-ui1-container">';
		if ( function_exists( 'rank_math_the_breadcrumbs' ) ) {
			echo '<div class="gloskin-ui1-breadcrumb gloskin-ui1-breadcrumb--provider" data-gloskin-breadcrumb-owner="rank-math">';
			rank_math_the_breadcrumbs();
			echo '</div>';
		} else {
			$items = gloskin_ui1_fallback_breadcrumb_items( $context );
			echo '<nav class="gloskin-ui1-breadcrumb" aria-label="Breadcrumb" data-gloskin-breadcrumb-owner="gloskin"><ol>';
			foreach ( $items as $index => $item ) {
				if ( $index > 0 ) {
					echo '<li class="gloskin-ui1-breadcrumb__separator" aria-hidden="true"><svg viewBox="0 0 12 12" width="12" height="12" focusable="false"><path d="m4.5 2 4 4-4 4" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg></li>';
				}
				echo '<li class="gloskin-ui1-breadcrumb__item">';
				if ( ! empty( $item['current'] ) ) {
					echo '<span aria-current="page">' . esc_html( $item['label'] ) . '</span>';
				} else {
					echo '<a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['label'] ) . '</a>';
				}
				echo '</li>';
			}
			echo '</ol></nav>';
		}
		echo '</div></div>';
	}
}

if ( ! function_exists( 'gloskin_ui1_render_commerce_page_heading' ) ) {
	/** Render the single page H1 for cart/checkout/account shortcode or block routes. */
	function gloskin_ui1_render_commerce_page_heading() {
		$label = '';
		if ( function_exists( 'is_cart' ) && is_cart() ) {
			$label = __( 'Keranjang', 'gloskin-site-core' );
		} elseif ( function_exists( 'is_checkout' ) && is_checkout() ) {
			$label = __( 'Checkout', 'gloskin-site-core' );
		} elseif ( function_exists( 'is_account_page' ) && is_account_page() ) {
			$label = __( 'Akun', 'gloskin-site-core' );
		}
		if ( '' === $label ) {
			return;
		}
		echo '<header class="gloskin-ui1-commerce-heading"><div class="gloskin-ui1-container"><h1>' . esc_html( $label ) . '</h1></div></header>';
	}
}

if ( ! function_exists( 'gloskin_ui1_render_native_cart_empty_state' ) ) {
	/** Render the shared zero state from Woo's native empty-cart action. */
	function gloskin_ui1_render_native_cart_empty_state() {
		$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
		if ( ! $shop_url ) {
			$shop_url = home_url( '/shop/' );
		}
		gloskin_ui1_render_empty_state(
			'cart',
			__( 'Keranjang Anda masih kosong', 'gloskin-site-core' ),
			__( 'Jelajahi produk Gloskin dan tambahkan pilihan Anda sebelum melanjutkan ke checkout.', 'gloskin-site-core' ),
			__( 'Lihat Belanja', 'gloskin-site-core' ),
			$shop_url
		);
	}
}
