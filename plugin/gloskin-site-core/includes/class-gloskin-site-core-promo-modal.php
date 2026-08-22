<?php
/**
 * Production Promo modal schema, eligibility, and frontend renderer.
 *
 * This extends the existing gloskin_promo collection. Editorial CRUD remains
 * owned by Gloskin_Site_Core_Editorial_Manager; this service owns only the
 * modal-specific metadata contract and the single frontend eligibility path.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Gloskin_Site_Core_Promo_Modal {
	const POPUP_META       = 'gloskin_promo_popup_enabled';
	const VISIBILITY_META  = 'gloskin_promo_visibility';
	const PAGE_IDS_META    = 'gloskin_promo_visibility_page_ids';
	const DESTINATION_META = 'gloskin_promo_destination_url';

	const VISIBILITY_HOMEPAGE = 'homepage';
	const VISIBILITY_ALL      = 'all_pages';
	const VISIBILITY_SPECIFIC = 'specific_pages';

	/** @var string */
	private $plugin_file;

	/** @var string */
	private $version;

	/** @var array<int,array<string,mixed>>|null */
	private $eligible_cache = null;

	/**
	 * @param string $plugin_file Main plugin file.
	 * @param string $version Plugin version.
	 */
	public function __construct( $plugin_file, $version ) {
		$this->plugin_file = (string) $plugin_file;
		$this->version     = (string) $version;
	}

	/** @return void */
	public function register_schema() {
		add_action( 'init', array( $this, 'register_meta' ), 6 );
	}

	/** @return void */
	public function register_frontend() {
		add_action( 'wp_footer', array( $this, 'render' ), 35 );
	}

	/** @return void */
	public function register_meta() {
		$post_type = Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE;
		$auth      = static function ( $allowed, $meta_key, $post_id ) {
			unset( $allowed, $meta_key );
			return current_user_can( 'edit_post', $post_id );
		};

		register_post_meta( $post_type, self::POPUP_META, array(
			'type'              => 'string',
			'single'            => true,
			'default'           => '0',
			'show_in_rest'      => true,
			'sanitize_callback' => static function ( $value ) { return '1' === (string) $value ? '1' : '0'; },
			'auth_callback'     => $auth,
		) );
		register_post_meta( $post_type, self::VISIBILITY_META, array(
			'type'              => 'string',
			'single'            => true,
			'default'           => self::VISIBILITY_HOMEPAGE,
			'show_in_rest'      => true,
			'sanitize_callback' => array( __CLASS__, 'sanitize_visibility' ),
			'auth_callback'     => $auth,
		) );
		register_post_meta( $post_type, self::PAGE_IDS_META, array(
			'type'              => 'array',
			'single'            => true,
			'default'           => array(),
			'show_in_rest'      => array( 'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ), 'default' => array() ) ),
			'sanitize_callback' => array( __CLASS__, 'sanitize_page_ids' ),
			'auth_callback'     => $auth,
		) );
		register_post_meta( $post_type, self::DESTINATION_META, array(
			'type'              => 'string',
			'single'            => true,
			'default'           => '',
			'show_in_rest'      => true,
			'sanitize_callback' => array( __CLASS__, 'sanitize_destination_url' ),
			'auth_callback'     => $auth,
		) );
	}

	/** @param mixed $value Visibility value. @return string */
	public static function sanitize_visibility( $value ) {
		$value = sanitize_key( is_scalar( $value ) ? (string) $value : '' );
		return in_array( $value, array( self::VISIBILITY_HOMEPAGE, self::VISIBILITY_ALL, self::VISIBILITY_SPECIFIC ), true )
			? $value
			: self::VISIBILITY_HOMEPAGE;
	}

	/** @param mixed $value Page IDs. @return array<int,int> */
	public static function sanitize_page_ids( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$ids = array();
		foreach ( array_slice( $value, 0, 100 ) as $candidate ) {
			$id = absint( $candidate );
			if ( $id && 'page' === get_post_type( $id ) ) {
				$ids[] = $id;
			}
		}
		$ids = array_values( array_unique( $ids ) );
		sort( $ids, SORT_NUMERIC );
		return $ids;
	}

	/**
	 * Accept same-site HTTP/HTTPS URLs and arbitrary external HTTPS URLs.
	 * Root-relative same-site targets are normalized to an absolute home URL.
	 *
	 * @param mixed $value Candidate URL.
	 * @return string Empty when invalid.
	 */
	public static function sanitize_destination_url( $value ) {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		if ( '' === $value ) {
			return '';
		}
		if ( 0 === strpos( $value, '/' ) && 0 !== strpos( $value, '//' ) ) {
			$value = home_url( $value );
		}
		$url = esc_url_raw( $value, array( 'http', 'https' ) );
		if ( '' === $url ) {
			return '';
		}
		$scheme    = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		$host      = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$home_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		if ( '' === $host || '' === $scheme ) {
			return '';
		}
		if ( $host === $home_host && in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return $url;
		}
		return 'https' === $scheme ? $url : '';
	}

	/** @param mixed $value Percentage. @param float $fallback Fallback. @return float */
	private static function bounded_percent( $value, $fallback = 50.0 ) {
		$value = is_numeric( $value ) ? (float) $value : (float) $fallback;
		return max( 0.0, min( 100.0, $value ) );
	}

	/** @param mixed $value Zoom percentage. @return float */
	private static function bounded_zoom( $value ) {
		$value = is_numeric( $value ) ? (float) $value : 100.0;
		return max( 100.0, min( 300.0, $value ) );
	}

	/**
	 * The one production eligibility resolver for Promo Modal.
	 *
	 * published + Active + popup-enabled + current-page visibility + image + URL.
	 * Existing gloskin_promo_order remains the only ordering source.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function eligible_promos() {
		if ( null !== $this->eligible_cache ) {
			return $this->eligible_cache;
		}
		$this->eligible_cache = array();
		if ( is_admin() || is_404() || ! post_type_exists( Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE ) ) {
			return $this->eligible_cache;
		}

		$profile      = Gloskin_Site_Core_Content_Service::editorial_profile( Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE );
		$active_meta  = isset( $profile['active_meta'] ) ? (string) $profile['active_meta'] : 'gloskin_promo_active';
		$order_meta   = isset( $profile['order_meta'] ) ? (string) $profile['order_meta'] : 'gloskin_promo_order';
		$focus_x_meta = isset( $profile['focus_x_meta'] ) ? (string) $profile['focus_x_meta'] : 'gloskin_promo_focus_x';
		$focus_y_meta = isset( $profile['focus_y_meta'] ) ? (string) $profile['focus_y_meta'] : 'gloskin_promo_focus_y';
		$zoom_meta    = isset( $profile['zoom_meta'] ) ? (string) $profile['zoom_meta'] : 'gloskin_promo_crop_zoom';
		$posts        = get_posts( array(
			'post_type'      => Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'meta_query'     => array(
				'relation' => 'AND',
				array( 'key' => $active_meta, 'value' => '1', 'compare' => '=' ),
				array( 'key' => self::POPUP_META, 'value' => '1', 'compare' => '=' ),
			),
		) );

		foreach ( $posts as $post ) {
			if ( ! $post instanceof WP_Post || ! $this->visibility_matches( $post->ID ) ) {
				continue;
			}
			$image_id = absint( get_post_thumbnail_id( $post->ID ) );
			if ( ! $image_id || ! wp_get_attachment_image_url( $image_id, 'full' ) ) {
				continue;
			}
			$url = self::sanitize_destination_url( get_post_meta( $post->ID, self::DESTINATION_META, true ) );
			if ( '' === $url ) {
				continue;
			}
			$this->eligible_cache[] = array(
				'id'       => (int) $post->ID,
				'title'    => (string) get_the_title( $post ),
				'image_id' => $image_id,
				'url'      => $url,
				'order'    => (int) get_post_meta( $post->ID, $order_meta, true ),
				'modified' => (string) $post->post_modified_gmt,
				'focus_x'  => self::bounded_percent( get_post_meta( $post->ID, $focus_x_meta, true ) ),
				'focus_y'  => self::bounded_percent( get_post_meta( $post->ID, $focus_y_meta, true ) ),
				'zoom'     => self::bounded_zoom( get_post_meta( $post->ID, $zoom_meta, true ) ),
			);
		}

		usort( $this->eligible_cache, static function ( $left, $right ) {
			$lo = (int) $left['order'];
			$ro = (int) $right['order'];
			$lh = $lo > 0;
			$rh = $ro > 0;
			if ( $lh && ! $rh ) { return -1; }
			if ( ! $lh && $rh ) { return 1; }
			if ( $lo !== $ro ) { return $lo <=> $ro; }
			return (int) $left['id'] <=> (int) $right['id'];
		} );
		return $this->eligible_cache;
	}

	/** @param int $promo_id Promo ID. @return bool */
	private function visibility_matches( $promo_id ) {
		$visibility = self::sanitize_visibility( get_post_meta( $promo_id, self::VISIBILITY_META, true ) );
		if ( self::VISIBILITY_ALL === $visibility ) {
			return true;
		}
		if ( self::VISIBILITY_HOMEPAGE === $visibility ) {
			return is_front_page();
		}
		if ( ! is_page() ) {
			return false;
		}
		$page_ids = self::sanitize_page_ids( get_post_meta( $promo_id, self::PAGE_IDS_META, true ) );
		return in_array( absint( get_queried_object_id() ), $page_ids, true );
	}

	/** @param array<int,array<string,mixed>> $promos Eligible set. @return string */
	private function campaign_signature( $promos ) {
		$parts = array();
		foreach ( $promos as $promo ) {
			$parts[] = implode( ':', array(
				(int) $promo['id'],
				(int) $promo['image_id'],
				(string) $promo['modified'],
				(string) $promo['url'],
			) );
		}
		return substr( hash( 'sha256', implode( '|', $parts ) ), 0, 24 );
	}

	/** @return void */
	public function render() {
		$promos = $this->eligible_promos();
		if ( ! $promos ) {
			return;
		}
		$multiple  = count( $promos ) > 1;
		$signature = $this->campaign_signature( $promos );
		?>
		<div class="gloskin-promo-modal" data-gloskin-promo-modal data-campaign="<?php echo esc_attr( $signature ); ?>" data-slide-count="<?php echo esc_attr( (string) count( $promos ) ); ?>" hidden aria-hidden="true">
			<div class="gloskin-promo-modal__backdrop" data-gloskin-promo-close aria-hidden="true"></div>
			<div class="gloskin-promo-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="gloskin-promo-modal-title" tabindex="-1">
				<h2 id="gloskin-promo-modal-title" class="screen-reader-text"><?php echo esc_html__( 'Promo Gloskin', 'gloskin-site-core' ); ?></h2>
				<div class="gloskin-promo-modal__poster" data-gloskin-promo-slider>
					<div class="gloskin-promo-modal__viewport">
						<div class="gloskin-promo-modal__track" data-gloskin-promo-track>
							<?php foreach ( $promos as $index => $promo ) : ?>
							<a class="gloskin-promo-modal__slide" data-gloskin-promo-slide data-focus-x="<?php echo esc_attr( (string) $promo['focus_x'] ); ?>" data-focus-y="<?php echo esc_attr( (string) $promo['focus_y'] ); ?>" data-crop-zoom="<?php echo esc_attr( (string) $promo['zoom'] ); ?>" href="<?php echo esc_url( $promo['url'] ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Buka promo: %s', 'gloskin-site-core' ), $promo['title'] ) ); ?>"<?php echo 0 === $index ? '' : ' tabindex="-1"'; ?>>
								<?php echo wp_get_attachment_image( $promo['image_id'], 'full', false, array( 'class' => 'gloskin-promo-modal__image', 'alt' => (string) $promo['title'], 'loading' => 0 === $index ? 'eager' : 'lazy', 'decoding' => 'async' ) ); ?>
							</a>
							<?php endforeach; ?>
						</div>
					</div>
					<button type="button" class="gloskin-promo-modal__close" data-gloskin-promo-close aria-label="<?php echo esc_attr__( 'Tutup promo', 'gloskin-site-core' ); ?>">&times;</button>
					<?php if ( $multiple ) : ?>
					<div class="gloskin-promo-modal__controls" aria-label="<?php echo esc_attr__( 'Navigasi promo', 'gloskin-site-core' ); ?>">
						<button type="button" class="gloskin-promo-modal__nav gloskin-promo-modal__nav--prev" data-gloskin-promo-prev aria-label="<?php echo esc_attr__( 'Promo sebelumnya', 'gloskin-site-core' ); ?>">&#8249;</button>
						<div class="gloskin-promo-modal__dots" data-gloskin-promo-dots aria-hidden="true"></div>
						<button type="button" class="gloskin-promo-modal__nav gloskin-promo-modal__nav--next" data-gloskin-promo-next aria-label="<?php echo esc_attr__( 'Promo berikutnya', 'gloskin-site-core' ); ?>">&#8250;</button>
					</div>
					<?php endif; ?>
				</div>
				<button type="button" class="gloskin-promo-modal__never" data-gloskin-promo-never><?php echo esc_html__( 'Jangan tampilkan lagi', 'gloskin-site-core' ); ?></button>
			</div>
		</div>
		<?php
	}
}
