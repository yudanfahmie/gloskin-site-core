<?php
/**
 * Gloskin template resolution and page-context owner.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Gloskin_Site_Core_Template_Service {
	/** @var string */
	private $plugin_root;

	/** @var Gloskin_Site_Core_Navigation_Service */
	private $navigation;

	/** @var Gloskin_Site_Core_WooCommerce_Adapter */
	private $woocommerce;

	/** @var Gloskin_Site_Core_Form_Adapter */
	private $form;

	/**
	 * @param string                                 $plugin_root Plugin directory.
	 * @param Gloskin_Site_Core_Navigation_Service  $navigation Navigation service.
	 * @param Gloskin_Site_Core_WooCommerce_Adapter $woocommerce Woo adapter.
	 * @param Gloskin_Site_Core_Form_Adapter        $form Form adapter.
	 */
	public function __construct( $plugin_root, $navigation, $woocommerce, $form ) {
		$this->plugin_root = untrailingslashit( $plugin_root );
		$this->navigation  = $navigation;
		$this->woocommerce = $woocommerce;
		$this->form        = $form;
	}

	/** @return void */
	public function register() {
		add_filter( 'template_include', array( $this, 'resolve_template' ), 99 );
		add_filter( 'document_title_parts', array( $this, 'localize_document_title' ), 20 );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'wp_head', array( $this, 'render_favicon_fallback' ) );
	}

	/** @return void */
	public function render_favicon_fallback() {
		remove_action( 'wp_head', 'wp_site_icon', 99 );
		$png_sizes = array(
			array( 'favicon-16x16.png', '16x16' ),
			array( 'favicon-32x32.png', '32x32' ),
			array( 'icon-192.png', '192x192' ),
			array( 'icon-512.png', '512x512' ),
		);
		echo '<link rel="icon" href="' . esc_url( $this->image_url( 'favicon.ico' ) ) . '" sizes="any">' . "\n";
		foreach ( $png_sizes as $size ) {
			echo '<link rel="icon" type="image/png" sizes="' . esc_attr( $size[1] ) . '" href="' . esc_url( $this->image_url( $size[0] ) ) . '">' . "\n";
		}
		echo '<link rel="apple-touch-icon" sizes="180x180" href="' . esc_url( $this->image_url( 'apple-touch-icon.png' ) ) . '">' . "\n";
	}

	/** @param string $relative Filename within assets/images. @return string */
	private function image_url( $relative ) {
		return plugins_url( 'assets/images/' . ltrim( $relative, '/' ), $this->plugin_root . '/gloskin-site-core.php' );
	}

	/** @param array<string,string> $parts WordPress title parts. @return array<string,string> */
	public function localize_document_title( $parts ) {
		$view = $this->identify_view();
		$titles = array(
			'home'       => 'Gloskin',
			'about'      => 'Tentang Gloskin',
			'treatments' => 'Perawatan',
			'promo'      => 'Promo',
			'skincare'   => 'Skincare',
			'clinics'    => 'Klinik',
			'doctors'    => 'Dokter',
			'contact'    => 'Kontak',
			'insights'   => 'Insight',
			'shop'       => 'Belanja',
		);
		if ( 'insight-single' === $view ) {
			$post = get_queried_object();
			if ( $post instanceof WP_Post ) {
				$parts['title'] = get_the_title( $post );
			}
		} elseif ( 'not-found' === $view ) {
			$parts['title'] = __( 'Halaman tidak ditemukan', 'gloskin-site-core' );
		} elseif ( isset( $titles[ $view ] ) ) {
			$parts['title'] = $titles[ $view ];
		}
		return $parts;
	}

	/** @param string $template Theme-resolved template. @return string */
	public function resolve_template( $template ) {
		$native_commerce = $this->woocommerce->is_commerce_request() && ! $this->woocommerce->is_shop_request();
		$view            = $native_commerce ? '' : $this->identify_view();

		if ( '' === $view && ! $native_commerce ) {
			set_query_var( 'gloskin_context', array() );
			return $template;
		}

		if ( $native_commerce ) {
			$view    = 'commerce-native';
			$context = array(
				'commerce_native'      => true,
				'commerce_render_mode' => $this->native_commerce_render_mode(),
			);
		} else {
			$context = $this->build_context( $view );
		}

		$context['view']           = $view;
		$context['navigation']     = $this->navigation->tree();
		$context['design_variant'] = $this->design_variant();
		$context['header_variant'] = $this->header_variant();
		$context['clinic_links']   = $this->static_clinic_links();
		$context['site_name']      = 'Gloskin';
		$context['commerce']       = $this->commerce_header_context();
		$context['logo_url']       = $this->image_url( 'gloskin-logotext.svg' );
		set_query_var( 'gloskin_context', $context );

		$shell = $this->plugin_root . '/templates/shell.php';
		return is_readable( $shell ) ? $shell : $template;
	}

	/** @return string */
	private function native_commerce_render_mode() {
		if ( function_exists( 'is_woocommerce' ) && is_woocommerce() ) {
			return 'woocommerce';
		}
		return 'page';
	}

	/** @return string */
	private function identify_view() {
		if ( is_404() ) {
			return 'not-found';
		}
		if ( is_singular( 'post' ) ) {
			return 'insight-single';
		}
		if ( is_singular( Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE ) ) {
			return 'treatment';
		}
		if ( is_singular( Gloskin_Site_Core_Content_Service::CLINIC_POST_TYPE ) ) {
			return 'clinic';
		}
		if ( is_singular( Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE ) ) {
			return 'doctor';
		}
		if ( is_front_page() ) {
			return 'home';
		}
		if ( is_page() ) {
			$post = get_queried_object();
			if ( $post instanceof WP_Post ) {
				if ( $this->is_skincare_child( $post ) ) {
					return 'skincare-category';
				}
				$views = array(
					'home' => 'home', 'about' => 'about', 'treatments' => 'treatments',
					'promo' => 'promo', 'skincare' => 'skincare', 'clinics' => 'clinics',
					'contact' => 'contact', 'insights' => 'insights', 'shop' => 'shop',
					'doctors' => 'doctors',
				);
				if ( isset( $views[ $post->post_name ] ) ) {
					return $views[ $post->post_name ];
				}
			}
		}
		if ( $this->woocommerce->is_shop_request() ) {
			return 'shop';
		}
		return '';
	}

	/** @param WP_Post $post Page object. @return bool */
	private function is_skincare_child( $post ) {
		if ( '' !== (string) get_post_meta( $post->ID, 'gloskin_woo_category_slug', true ) ) {
			return true;
		}
		if ( ! $post->post_parent ) {
			return false;
		}
		$parent = get_post( $post->post_parent );
		return $parent instanceof WP_Post && 'skincare' === $parent->post_name;
	}

	/** @param string $view View key. @return array<string,mixed> */
	private function build_context( $view ) {
		switch ( $view ) {
			case 'home': return $this->home_context();
			case 'about': return $this->about_context();
			case 'treatments': return $this->treatments_context();
			case 'promo': return $this->promo_context();
			case 'treatment': return $this->treatment_context();
			case 'skincare': return $this->skincare_context();
			case 'skincare-category': return $this->skincare_category_context();
			case 'clinics': return $this->clinics_context();
			case 'clinic': return $this->clinic_context();
			case 'doctors': return $this->doctors_context();
			case 'doctor': return $this->doctor_context();
			case 'contact': return $this->contact_context();
			case 'insights': return $this->insights_context();
			case 'insight-single': return $this->insight_single_context();
			case 'not-found': return $this->not_found_context();
			case 'shop': return $this->shop_context();
			default: return array();
		}
	}

	/** @return array<string,mixed> */
	private function home_context() {
		$page = $this->content_page( 'home' );
		$hero = $this->hero_context(
			$page,
			__( 'Perawatan kulit, anti-aging, dan rambut yang dimulai dari konsultasi.', 'gloskin-site-core' ),
			__( 'Gloskin adalah klinik estetika, anti-aging, dan perawatan rambut yang mengutamakan pemeriksaan bersama dokter sebelum menentukan langkah perawatan untuk kulit Anda.', 'gloskin-site-core' ),
			__( 'Jelajahi Perawatan', 'gloskin-site-core' ),
			home_url( '/treatments/' )
		);
		$hero = array_merge( $hero, $this->hero_background_video() );
		$hero['mode'] = 'campaign';
		return array(
			'page'          => $page,
			'hero'          => $hero,
			'treatments'    => $this->post_cards( Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE, 8 ),
			'promo'         => $this->managed_promo_records( 5 ),
			'skincare'      => $this->skincare_mappings(),
			'products'      => $this->woocommerce->products( 4 ),
			'testimonials'  => $this->published_managed_records( Gloskin_Site_Core_Content_Service::TESTIMONIAL_POST_TYPE, 'gloskin_testimonial_active', 6 ),
			'achievements'  => $this->published_managed_records( Gloskin_Site_Core_Content_Service::ACHIEVEMENT_POST_TYPE, 'gloskin_achievement_active', 8, 'gloskin_achievement_feature_on_home' ),
			'woo_ready'     => $this->woocommerce->available(),
		);
	}

	/** @return array<string,mixed> */
	private function about_context() {
		$page = $this->content_page( 'about' );
		$founder = $this->about_founder_context( $page );
		return array(
			'page'         => $page,
			'hero'         => $this->hero_context( $page, __( 'Tentang Gloskin', 'gloskin-site-core' ), __( 'Kenali Gloskin melalui jaringan klinik, informasi perawatan, skincare, dan kanal kontak yang tersedia.', 'gloskin-site-core' ) ),
			'vision'       => $page ? (string) get_post_meta( $page->ID, 'gloskin_about_vision', true ) : '',
			'mission'      => $page ? (string) get_post_meta( $page->ID, 'gloskin_about_mission', true ) : '',
			'values'       => $page ? (string) get_post_meta( $page->ID, 'gloskin_about_values', true ) : '',
			'founder'      => $founder,
			'clinics'      => $this->clinic_cards(),
			'doctors'      => $this->all_published_doctor_cards(),
			'achievements' => $this->published_managed_records( Gloskin_Site_Core_Content_Service::ACHIEVEMENT_POST_TYPE, 'gloskin_achievement_active', 20 ),
		);
	}

	/**
	 * Optional founder block for About page. Renders only when required fields exist.
	 *
	 * @param WP_Post|null $about_page About page.
	 * @return array<string,mixed>|null Founder data, or null if required fields missing.
	 */
	private function about_founder_context( $about_page ) {
		if ( ! ( $about_page instanceof WP_Post ) ) {
			return null;
		}
		$name  = trim( (string) get_post_meta( $about_page->ID, 'gloskin_about_founder_name', true ) );
		$role  = trim( (string) get_post_meta( $about_page->ID, 'gloskin_about_founder_role', true ) );
		$story = trim( (string) get_post_meta( $about_page->ID, 'gloskin_about_founder_story', true ) );
		/* Render only when both name and role are populated — prevents a blank
		 * "founder" block from appearing before the editor fills in the fields. */
		if ( '' === $name || '' === $role ) {
			return null;
		}
		return array(
			'name'     => $name,
			'role'     => $role,
			'story'    => $story,
			'media_id' => absint( get_post_meta( $about_page->ID, 'gloskin_about_founder_media_id', true ) ),
		);
	}

	/** @return array<string,mixed> */
	private function treatments_context() {
		$page = $this->content_page( 'treatments' );
		return array(
			'page'         => $page,
			'hero'         => $this->hero_context( $page, __( 'Perawatan', 'gloskin-site-core' ), __( 'Pelajari informasi perawatan Gloskin sebelum menentukan langkah konsultasi.', 'gloskin-site-core' ) ),
			'consultation' => $this->consultation_context(),
			'paths'        => $this->treatment_discovery_paths(),
		);
	}

	/**
	 * Treatment discovery paths for the editorial band presentation.
	 * Returns the same canonical path data as the consultation widget but
	 * shaped for large alternating editorial bands — does not duplicate the engine.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function treatment_discovery_paths() {
		if ( ! taxonomy_exists( Gloskin_Site_Core_Content_Service::CONSULTATION_TAXONOMY ) ) {
			return array();
		}
		$path_terms = get_terms( array(
			'taxonomy'   => Gloskin_Site_Core_Content_Service::CONSULTATION_TAXONOMY,
			'hide_empty' => false,
		) );
		if ( is_wp_error( $path_terms ) || count( $path_terms ) < 1 ) {
			return array();
		}
		usort( $path_terms, static function ( $a, $b ) {
			return absint( get_term_meta( $a->term_id, Gloskin_Site_Core_Content_Service::PATH_META_ORDER, true ) )
				<=> absint( get_term_meta( $b->term_id, Gloskin_Site_Core_Content_Service::PATH_META_ORDER, true ) );
		} );

		$paths = array();
		foreach ( array_slice( $path_terms, 0, 4 ) as $term ) {
			$paths[] = array(
				'id'       => (int) $term->term_id,
				'label'    => (string) $term->name,
				'image_id' => absint( get_term_meta( $term->term_id, Gloskin_Site_Core_Content_Service::PATH_META_IMAGE_ID, true ) ),
			);
		}
		return $paths;
	}

	/** @return array<string,mixed> */
	private function promo_context() {
		$page = $this->content_page( 'promo' );
		return array(
			'page'   => $page,
			'promos' => $this->managed_promo_records( 10 ),
		);
	}

	/**
	 * Fetch active published gloskin_promo records for the carousel.
	 * Falls back to empty array — caller handles empty state gracefully.
	 *
	 * @param int $limit Max records.
	 * @return array<int,array<string,mixed>>
	 */
	private function managed_promo_records( $limit = 5 ) {
		if ( ! post_type_exists( Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE ) ) {
			return array();
		}
		$posts = get_posts( array(
			'post_type'      => Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
			'meta_query'     => array(
				array(
					'key'     => 'gloskin_promo_active',
					'value'   => '1',
					'compare' => '=',
				),
			),
		) );

		$records = array();
		foreach ( $posts as $post ) {
			$records[] = array(
				'id'         => (int) $post->ID,
				'title'      => (string) get_the_title( $post ),
				'eyebrow'    => (string) get_post_meta( $post->ID, 'gloskin_promo_eyebrow', true ),
				'summary'    => (string) get_post_meta( $post->ID, 'gloskin_promo_summary', true ),
				'cta_label'  => (string) get_post_meta( $post->ID, 'gloskin_promo_cta_label', true ),
				'cta_url'    => (string) get_post_meta( $post->ID, 'gloskin_promo_cta_url', true ),
				'image_id'   => absint( get_post_thumbnail_id( $post->ID ) ),
				'excerpt'    => (string) get_the_excerpt( $post ),
			);
		}
		return $records;
	}

	/**
	 * Fetch active published records from a private managed CPT.
	 *
	 * @param string      $post_type    CPT slug.
	 * @param string      $active_meta  Meta key for the active flag.
	 * @param int         $limit        Max records.
	 * @param string|null $feature_meta Optional meta key to filter by (e.g. feature_on_home).
	 * @return array<int,array<string,mixed>>
	 */
	private function published_managed_records( $post_type, $active_meta, $limit = 10, $feature_meta = null ) {
		if ( ! post_type_exists( $post_type ) ) {
			return array();
		}
		$meta_query = array(
			array(
				'key'     => $active_meta,
				'value'   => '1',
				'compare' => '=',
			),
		);
		if ( $feature_meta ) {
			$meta_query[] = array(
				'key'     => $feature_meta,
				'value'   => '1',
				'compare' => '=',
			);
		}
		$posts = get_posts( array(
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
			'meta_query'     => $meta_query,
		) );

		$records = array();
		foreach ( $posts as $post ) {
			$records[] = array(
				'id'       => (int) $post->ID,
				'title'    => (string) get_the_title( $post ),
				'excerpt'  => (string) get_the_excerpt( $post ),
				'image_id' => absint( get_post_thumbnail_id( $post->ID ) ),
				'meta'     => array(
					'attribution' => (string) get_post_meta( $post->ID, 'gloskin_testimonial_attribution', true ),
					'subtitle'    => (string) get_post_meta( $post->ID, 'gloskin_testimonial_subtitle', true ),
					'issuer'      => (string) get_post_meta( $post->ID, 'gloskin_achievement_issuer', true ),
					'year'        => (string) get_post_meta( $post->ID, 'gloskin_achievement_year', true ),
				),
			);
		}
		return $records;
	}

	/** @return array{paths:array<int,array<string,mixed>>,products:array<int,array<string,mixed>>,disclaimer:string} */
	private function consultation_context() {
		$empty = array( 'paths' => array(), 'products' => array(), 'disclaimer' => '' );
		if ( ! taxonomy_exists( Gloskin_Site_Core_Content_Service::CONSULTATION_TAXONOMY )
			|| ! taxonomy_exists( Gloskin_Site_Core_Content_Service::CONCERN_TAXONOMY ) ) {
			return $empty;
		}

		$path_terms = get_terms( array( 'taxonomy' => Gloskin_Site_Core_Content_Service::CONSULTATION_TAXONOMY, 'hide_empty' => false ) );
		$path_terms = is_wp_error( $path_terms ) ? array() : $path_terms;
		if ( count( $path_terms ) < Gloskin_Site_Core_Content_Service::PATH_MIN_VALID ) {
			return $empty;
		}
		usort( $path_terms, static function ( $a, $b ) {
			return absint( get_term_meta( $a->term_id, Gloskin_Site_Core_Content_Service::PATH_META_ORDER, true ) ) <=> absint( get_term_meta( $b->term_id, Gloskin_Site_Core_Content_Service::PATH_META_ORDER, true ) );
		} );

		$concern_terms = get_terms( array( 'taxonomy' => Gloskin_Site_Core_Content_Service::CONCERN_TAXONOMY, 'hide_empty' => false ) );
		$concern_terms = is_wp_error( $concern_terms ) ? array() : $concern_terms;
		$concerns_by_id = array();
		foreach ( $concern_terms as $concern_term ) {
			$concerns_by_id[ (int) $concern_term->term_id ] = (string) $concern_term->name;
		}

		$paths = array();
		foreach ( $path_terms as $term ) {
			$baseline_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) get_term_meta( $term->term_id, Gloskin_Site_Core_Content_Service::PATH_META_BASELINE, true ) ) ) ) );
			$path_concerns = array();
			foreach ( $baseline_ids as $concern_id ) {
				if ( isset( $concerns_by_id[ $concern_id ] ) ) {
					$path_concerns[] = array( 'id' => $concern_id, 'label' => $concerns_by_id[ $concern_id ] );
				}
			}
			if ( ! $path_concerns ) {
				continue;
			}
			$paths[] = array(
				'id'       => (int) $term->term_id,
				'label'    => $term->name,
				'image_id' => absint( get_term_meta( $term->term_id, Gloskin_Site_Core_Content_Service::PATH_META_IMAGE_ID, true ) ),
				'concerns'  => $path_concerns,
			);
			if ( 4 === count( $paths ) ) {
				break;
			}
		}
		if ( 4 !== count( $paths ) ) {
			return $empty;
		}
		$products = array_values( array_filter( $this->woocommerce->treatment_products_with_concerns(), static function ( $product ) {
			return ! empty( $product['concern_ids'] );
		} ) );
		if ( ! $products ) {
			return $empty;
		}
		return array(
			'paths'      => $paths,
			'products'   => $products,
			'disclaimer' => __( 'Hasil ini membantu eksplorasi pilihan dan bukan diagnosis medis.', 'gloskin-site-core' ),
		);
	}

	/** @return array<string,mixed> */
	private function treatment_context() {
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return array();
		}
		$clinic_ids = $this->id_meta( $post->ID, 'gloskin_clinic_ids' );
		$doctor_ids = $this->id_meta( $post->ID, 'gloskin_doctor_ids' );
		return array(
			'post' => $post,
			'summary' => (string) get_post_meta( $post->ID, 'gloskin_summary', true ),
			'benefits' => (string) get_post_meta( $post->ID, 'gloskin_benefits', true ),
			'contraindications' => (string) get_post_meta( $post->ID, 'gloskin_contraindications', true ),
			'booking_target' => (string) get_post_meta( $post->ID, 'gloskin_booking_target', true ),
			'image_id' => absint( get_post_thumbnail_id( $post->ID ) ),
			'clinics' => $this->cards_by_ids( $clinic_ids, Gloskin_Site_Core_Content_Service::CLINIC_POST_TYPE ),
			'doctors' => $this->cards_by_ids( $doctor_ids, Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE ),
			'related_treatments' => $this->post_cards_except( Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE, 3, $post->ID ),
		);
	}

	/** @return array<string,mixed> */
	private function skincare_context() {
		$page = $this->content_page( 'skincare' );
		return array(
			'page' => $page,
			'hero' => $this->hero_context( $page, __( 'Skincare', 'gloskin-site-core' ), __( 'Jelajahi kategori skincare Gloskin untuk perawatan harian.', 'gloskin-site-core' ) ),
			'mappings' => $this->skincare_mappings(),
			'products' => $this->woocommerce->products( 8 ),
			'woo_ready' => $this->woocommerce->available(),
		);
	}

	/** @return array<string,mixed> */
	private function skincare_category_context() {
		$page = get_queried_object();
		if ( ! $page instanceof WP_Post ) {
			return array();
		}
		$mapping = (string) get_post_meta( $page->ID, 'gloskin_woo_category_slug', true );
		if ( '' === $mapping ) {
			$mapping = sanitize_title( $page->post_name );
		}
		$all_mappings = $this->skincare_mappings();
		$related = array_values( array_filter( $all_mappings, static function ( $item ) use ( $page ) {
			return isset( $item['slug'] ) && $item['slug'] !== $page->post_name;
		} ) );
		return array(
			'page' => $page,
			'hero' => $this->hero_context( $page, get_the_title( $page ), '' ),
			'category_slug' => $mapping,
			'category_exists' => $this->woocommerce->category_exists( $mapping ),
			'products' => $this->woocommerce->products_for_category( $mapping, 20 ),
			'related_mappings' => array_slice( $related, 0, 3 ),
			'woo_ready' => $this->woocommerce->available(),
		);
	}

	/** @return array<string,mixed> */
	private function clinics_context() {
		$page = $this->content_page( 'clinics' );
		return array(
			'page' => $page,
			'hero' => $this->hero_context( $page, __( 'Klinik Gloskin', 'gloskin-site-core' ), __( 'Temukan lokasi Gloskin yang ingin Anda kunjungi dan lihat informasi yang tersedia untuk setiap klinik.', 'gloskin-site-core' ) ),
			'clinics' => $this->clinic_cards(),
			'doctors' => $this->post_cards( Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE, 3 ),
			'treatments' => $this->post_cards( Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE, 3 ),
		);
	}

	/** @return array<string,mixed> */
	private function clinic_context() {
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return array();
		}
		$gallery = $this->id_meta( $post->ID, 'gloskin_gallery_image_ids' );
		$thumb = absint( get_post_thumbnail_id( $post->ID ) );
		if ( ! $gallery && $thumb ) {
			$gallery = array( $thumb );
		}
		return array(
			'post' => $post,
			'address' => (string) get_post_meta( $post->ID, 'gloskin_address', true ),
			'phone_display' => (string) get_post_meta( $post->ID, 'gloskin_phone_display', true ),
			'phone_uri' => (string) get_post_meta( $post->ID, 'gloskin_phone_uri', true ),
			'whatsapp_number' => (string) get_post_meta( $post->ID, 'gloskin_whatsapp_number', true ),
			'whatsapp_message' => (string) get_post_meta( $post->ID, 'gloskin_whatsapp_message', true ),
			'operating_hours' => (string) get_post_meta( $post->ID, 'gloskin_operating_hours', true ),
			'map_url' => (string) get_post_meta( $post->ID, 'gloskin_map_url', true ),
			'map_embed' => (string) get_post_meta( $post->ID, 'gloskin_map_embed', true ),
			'short_location' => (string) get_post_meta( $post->ID, 'gloskin_short_location', true ),
			'gallery_ids' => $gallery,
			'doctors' => $this->reverse_cards( Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE, 'gloskin_branch_ids', $post->ID ),
			'treatments' => $this->reverse_cards( Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE, 'gloskin_clinic_ids', $post->ID ),
			'whatsapp_url' => $this->form->whatsapp_url( (string) get_post_meta( $post->ID, 'gloskin_whatsapp_number', true ), (string) get_post_meta( $post->ID, 'gloskin_whatsapp_message', true ) ),
			'phone_url' => $this->form->phone_url( (string) get_post_meta( $post->ID, 'gloskin_phone_uri', true ) ),
		);
	}

	/** @return array<string,mixed> */
	private function doctors_context() {
		$page = $this->content_page( 'doctors' );
		return array(
			'page' => $page,
			'hero' => $this->hero_context( $page, __( 'Dokter Gloskin', 'gloskin-site-core' ), __( 'Gunakan halaman ini untuk mengenali profil dokter dan lokasi praktik yang dipublikasikan Gloskin.', 'gloskin-site-core' ) ),
			'doctors' => $this->all_published_doctor_cards(),
			/* Readiness target only; never a directory display ceiling. */
			'target' => Gloskin_Site_Core_Content_Service::DOCTOR_TARGET_COUNT,
		);
	}

	/** @return array<string,mixed> */
	private function doctor_context() {
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return array();
		}
		$branch_ids = $this->id_meta( $post->ID, 'gloskin_branch_ids' );
		return array(
			'post' => $post,
			'degree_title' => (string) get_post_meta( $post->ID, 'gloskin_degree_title', true ),
			'specialization' => (string) get_post_meta( $post->ID, 'gloskin_specialization', true ),
			'sip_number' => (string) get_post_meta( $post->ID, 'gloskin_sip_number', true ),
			'credentials' => (string) get_post_meta( $post->ID, 'gloskin_credentials', true ),
			'profile' => (string) get_post_meta( $post->ID, 'gloskin_profile', true ),
			'schedule' => (string) get_post_meta( $post->ID, 'gloskin_schedule', true ),
			'booking_target' => (string) get_post_meta( $post->ID, 'gloskin_booking_target', true ),
			'image_id' => absint( get_post_thumbnail_id( $post->ID ) ),
			'branches' => $this->cards_by_ids( $branch_ids, Gloskin_Site_Core_Content_Service::CLINIC_POST_TYPE ),
			'treatments' => $this->reverse_cards( Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE, 'gloskin_doctor_ids', $post->ID ),
		);
	}

	/** @return array<string,mixed> */
	private function contact_context() {
		$page = $this->content_page( 'contact' );
		return array(
			'page' => $page,
			'hero' => $this->hero_context( $page, __( 'Kontak Gloskin', 'gloskin-site-core' ), __( 'Pilih klinik Gloskin untuk melihat detail lokasi dan kanal kontak yang tersedia.', 'gloskin-site-core' ) ),
			'clinics' => $this->clinic_cards(),
			'form_html' => $this->form->render(),
		);
	}

	/** @return array<string,mixed> */
	private function insights_context() {
		$page = $this->content_page( 'insights' );
		$paged = max( 1, absint( get_query_var( 'paged' ) ), absint( get_query_var( 'page' ) ) );
		$query = new WP_Query( array(
			'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 9,
			'paged' => $paged, 'ignore_sticky_posts' => true,
		) );
		$posts = array();
		foreach ( $query->posts as $post ) {
			$posts[] = $this->insight_card( $post );
		}
		return array(
			'page' => $page,
			'hero' => $this->hero_context( $page, __( 'Insight', 'gloskin-site-core' ), __( 'Jelajahi informasi dan pembaruan yang dipublikasikan Gloskin.', 'gloskin-site-core' ) ),
			'insights' => $posts,
			'current_page' => $paged,
			'total_pages' => max( 1, absint( $query->max_num_pages ) ),
		);
	}

	/** @return array<string,mixed> */
	private function insight_single_context() {
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post || 'post' !== $post->post_type ) {
			return array();
		}
		$categories = get_the_category( $post->ID );
		$category = $categories ? (string) $categories[0]->name : '';
		return array(
			'post' => $post,
			'category' => $category,
			'excerpt' => has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 32 ),
			'date' => get_the_date( 'j F Y', $post ),
			'date_iso' => get_the_date( DATE_W3C, $post ),
			'reading_time' => $this->reading_time_label( $post->post_content ),
			'image_id' => absint( get_post_thumbnail_id( $post->ID ) ),
			'related' => $this->related_insight_cards( $post, 3 ),
		);
	}

	/** @return array<string,mixed> */
	private function not_found_context() {
		return array();
	}

	/** @param WP_Post $post Post. @return array<string,mixed> */
	private function insight_card( $post ) {
		if ( ! $post instanceof WP_Post ) {
			return array();
		}
		$categories = get_the_category( $post->ID );
		return array(
			'id' => (int) $post->ID,
			'title' => get_the_title( $post ),
			'url' => (string) get_permalink( $post ),
			'excerpt' => has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 28 ),
			'image_id' => absint( get_post_thumbnail_id( $post->ID ) ),
			'category' => $categories ? (string) $categories[0]->name : '',
			'date' => get_the_date( 'j F Y', $post ),
			'date_iso' => get_the_date( DATE_W3C, $post ),
			'reading_time' => $this->reading_time_label( $post->post_content ),
		);
	}

	/** @param string $content Content. @return string */
	private function reading_time_label( $content ) {
		$text = trim( wp_strip_all_tags( strip_shortcodes( (string) $content ) ) );
		$words = '' === $text ? 0 : count( preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY ) );
		$minutes = max( 1, (int) ceil( $words / 220 ) );
		return sprintf(
			/* translators: %d: estimated reading time in minutes. */
			_n( '%d menit baca', '%d menit baca', $minutes, 'gloskin-site-core' ),
			$minutes
		);
	}

	/** @param WP_Post $post Post. @param int $limit Limit. @return array<int,array<string,mixed>> */
	private function related_insight_cards( $post, $limit ) {
		$limit = max( 1, min( 3, absint( $limit ) ) );
		$related_posts = array();
		$exclude = array( (int) $post->ID );
		$categories = wp_get_post_categories( $post->ID );
		if ( $categories ) {
			$query = new WP_Query( array(
				'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => $limit,
				'post__not_in' => $exclude, 'cat' => (int) $categories[0],
				'ignore_sticky_posts' => true, 'no_found_rows' => true,
			) );
			$related_posts = $query->posts;
			foreach ( $related_posts as $related_post ) {
				$exclude[] = (int) $related_post->ID;
			}
		}
		$remaining = $limit - count( $related_posts );
		if ( $remaining > 0 ) {
			$fallback = new WP_Query( array(
				'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => $remaining,
				'post__not_in' => $exclude, 'orderby' => 'date', 'order' => 'DESC',
				'ignore_sticky_posts' => true, 'no_found_rows' => true,
			) );
			$related_posts = array_merge( $related_posts, $fallback->posts );
		}
		$cards = array();
		foreach ( array_slice( $related_posts, 0, $limit ) as $related_post ) {
			$cards[] = $this->insight_card( $related_post );
		}
		return $cards;
	}

	/** @return array<string,mixed> */
	private function shop_context() {
		$page  = $this->content_page( 'shop' );
		$paged = max( 1, absint( get_query_var( 'paged' ) ), absint( get_query_var( 'page' ) ) );
		$catalog = $this->woocommerce->products_paginated( $paged, 12 );
		return array(
			'page'           => $page,
			'hero'           => $this->hero_context( $page, __( 'Belanja', 'gloskin-site-core' ), __( 'Jelajahi seluruh skincare Gloskin.', 'gloskin-site-core' ) ),
			'mappings'       => $this->skincare_mappings(),
			'products'       => $catalog['products'],
			'products_total' => $catalog['total'],
			'current_page'   => $catalog['page'],
			'total_pages'    => $catalog['max_pages'],
			'woo_ready'      => $this->woocommerce->available(),
			'price_bounds'   => $this->shop_price_bounds(),
		);
	}

	/** @return array{min:float,max:float} */
	private function shop_price_bounds() {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! ( $wpdb instanceof wpdb ) ) {
			return array( 'min' => 0.0, 'max' => 5000000.0 );
		}
		$lookup = $wpdb->prefix . 'wc_product_meta_lookup';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			"SELECT MIN(l.min_price) AS avail_min, MAX(l.max_price) AS avail_max
			 FROM {$lookup} l
			 INNER JOIN {$wpdb->posts} p ON l.product_id = p.ID
			 WHERE p.post_type = 'product' AND p.post_status = 'publish'",
			ARRAY_A
		);
		// phpcs:enable
		$min = ( $row && null !== $row['avail_min'] ) ? (float) $row['avail_min'] : 0.0;
		$max = ( $row && null !== $row['avail_max'] ) ? (float) $row['avail_max'] : 5000000.0;
		if ( $max <= $min || $max <= 0.0 ) {
			$max = 5000000.0;
		}
		return array( 'min' => $min, 'max' => $max );
	}

	/** @return array<string,mixed> */
	private function commerce_header_context() {
		return array(
			'available'            => $this->woocommerce->available(),
			'account_url'          => $this->woocommerce->account_url(),
			'cart_url'             => $this->woocommerce->cart_url(),
			'checkout_url'         => $this->woocommerce->checkout_url(),
			'cart_count'           => $this->woocommerce->cart_count(),
			'mini_cart'            => $this->woocommerce->render_mini_cart_body(),
			'quick_auth'           => $this->woocommerce->should_render_quick_auth(),
			'add_to_cart_ajax_url' => $this->woocommerce->add_to_cart_ajax_url(),
			'cart_cta_label'       => $this->woocommerce->direct_cart_cta_label(),
		);
	}

	/** @return void */
	public function register_rest_routes() {
		register_rest_route( 'gloskin/v1', '/search', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'rest_search' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'q' => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		) );
		register_rest_route( 'gloskin/v1', '/shop/catalog', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'rest_shop_catalog' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'page' => array(
					'required'          => false,
					'type'              => 'integer',
					'default'           => 1,
					'sanitize_callback' => 'absint',
				),
				'category' => array(
					'required' => false,
					'type'     => 'string',
					'default'  => '',
				),
			),
		) );
	}

	/** @param WP_REST_Request $request Request. @return WP_REST_Response|WP_Error */
	public function rest_search( $request ) {
		$query = (string) $request->get_param( 'q' );
		if ( mb_strlen( $query ) < 2 || mb_strlen( $query ) > 100 ) {
			return rest_ensure_response( array( 'groups' => array() ) );
		}
		$groups = array();
		$treatments = $this->search_posts( $query, Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE, 3 );
		if ( $treatments ) { $groups[] = array( 'type' => 'perawatan', 'label' => 'Perawatan', 'items' => $treatments ); }
		$clinics = $this->search_posts( $query, Gloskin_Site_Core_Content_Service::CLINIC_POST_TYPE, 3 );
		if ( $clinics ) { $groups[] = array( 'type' => 'klinik', 'label' => 'Klinik', 'items' => $clinics ); }
		$doctors = $this->search_posts( $query, Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE, 3 );
		if ( $doctors ) { $groups[] = array( 'type' => 'dokter', 'label' => 'Dokter', 'items' => $doctors ); }
		$products = $this->woocommerce->search_products( $query, 3 );
		if ( $products ) { $groups[] = array( 'type' => 'produk', 'label' => 'Produk', 'items' => $products ); }
		$insights = $this->search_posts( $query, 'post', 2 );
		if ( $insights ) { $groups[] = array( 'type' => 'insight', 'label' => 'Insight', 'items' => $insights ); }
		return rest_ensure_response( array( 'groups' => $groups ) );
	}

	/** @param WP_REST_Request $request Request. @return WP_REST_Response|WP_Error */
	public function rest_shop_catalog( $request ) {
		$page     = max( 1, min( 1000, absint( $request->get_param( 'page' ) ) ) );
		$category = sanitize_title( (string) $request->get_param( 'category' ) );
		$mappings = $this->skincare_mappings();
		$mapping  = null;
		if ( '' !== $category ) {
			foreach ( $mappings as $candidate ) {
				$candidate_slug = isset( $candidate['woo_slug'] ) ? sanitize_title( (string) $candidate['woo_slug'] ) : '';
				if ( $candidate_slug === $category ) {
					$mapping = $candidate;
					break;
				}
			}
			if ( null === $mapping ) {
				return new WP_Error( 'gloskin_shop_category', __( 'Kategori produk tidak tersedia.', 'gloskin-site-core' ), array( 'status' => 400 ) );
			}
		}
		$catalog = $this->woocommerce->products_paginated( $page, 12, $category );
		$results = array(
			'products'       => $catalog['products'],
			'total'          => $catalog['total'],
			'page'           => $catalog['page'],
			'max_pages'      => $catalog['max_pages'],
			'category'       => $category,
			'category_label' => is_array( $mapping ) && isset( $mapping['label'] ) ? (string) $mapping['label'] : '',
			'woo_ready'      => $this->woocommerce->available(),
		);
		$html = $this->render_shop_results( $results );
		if ( '' === $html ) {
			return new WP_Error( 'gloskin_shop_render', __( 'Katalog belum dapat dirender.', 'gloskin-site-core' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response( array(
			'html'      => $html,
			'category'  => $category,
			'page'      => (int) $catalog['page'],
			'total'     => (int) $catalog['total'],
			'max_pages' => (int) $catalog['max_pages'],
		) );
	}

	/** @param array<string,mixed> $results Shop result context. @return string */
	private function render_shop_results( $results ) {
		$partial = $this->plugin_root . '/templates/parts/shop-results.php';
		if ( ! is_readable( $partial ) ) {
			return '';
		}
		$gloskin_shop_results = $results;
		ob_start();
		include $partial;
		return trim( (string) ob_get_clean() );
	}

	/** @param string $query Search. @param string $post_type Type. @param int $limit Limit. @return array<int,array<string,mixed>> */
	private function search_posts( $query, $post_type, $limit ) {
		$posts = get_posts( array(
			'post_type' => $post_type, 'post_status' => 'publish',
			'posts_per_page' => max( 1, min( absint( $limit ), 6 ) ), 's' => $query,
		) );
		$results = array();
		foreach ( $posts as $post ) {
			$results[] = array(
				'id' => (int) $post->ID, 'title' => get_the_title( $post ), 'url' => (string) get_permalink( $post ),
				'excerpt' => wp_trim_words( has_excerpt( $post ) ? get_the_excerpt( $post ) : $post->post_content, 12 ),
				'image_id' => absint( get_post_thumbnail_id( $post->ID ) ), 'type' => $post_type,
			);
		}
		return $results;
	}

	/** @param string $slug Slug. @return WP_Post|null */
	private function content_page( $slug ) {
		$current = get_queried_object();
		if ( $current instanceof WP_Post && 'page' === $current->post_type && $slug === $current->post_name ) {
			return $current;
		}
		$page = get_page_by_path( $slug, OBJECT, 'page' );
		return $page instanceof WP_Post ? $page : null;
	}

	/** @return array<string,mixed> */
	private function hero_context( $page, $default_heading, $default_copy, $default_cta_label = '', $default_cta_url = '' ) {
		$heading = $page ? trim( (string) get_post_meta( $page->ID, 'gloskin_hero_heading', true ) ) : '';
		$copy = $page ? trim( (string) get_post_meta( $page->ID, 'gloskin_hero_copy', true ) ) : '';
		return array(
			'heading' => '' !== $heading ? $heading : $default_heading,
			'copy' => '' !== $copy ? $copy : $default_copy,
			'cta_label' => $page && '' !== trim( (string) get_post_meta( $page->ID, 'gloskin_hero_cta_label', true ) ) ? (string) get_post_meta( $page->ID, 'gloskin_hero_cta_label', true ) : $default_cta_label,
			'cta_url' => $page && '' !== trim( (string) get_post_meta( $page->ID, 'gloskin_hero_cta_url', true ) ) ? (string) get_post_meta( $page->ID, 'gloskin_hero_cta_url', true ) : $default_cta_url,
			'media_id' => $page ? absint( get_post_meta( $page->ID, 'gloskin_hero_media_id', true ) ) : 0,
		);
	}

	/** @return array<int,array<string,mixed>> */
	private function post_cards( $post_type, $limit ) {
		$posts = get_posts( array(
			'post_type' => $post_type, 'post_status' => 'publish',
			'posts_per_page' => max( 1, absint( $limit ) ), 'orderby' => 'menu_order title', 'order' => 'ASC',
		) );
		$cards = array();
		foreach ( $posts as $post ) { $cards[] = $this->post_card( $post, $post_type ); }
		return $cards;
	}

	/**
	 * Factual Doctor directory/team collection. Unlike readiness targets and
	 * preview helpers, this collection has no display ceiling: every published
	 * Gloskin doctor is projected in deterministic menu-order/title order.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function all_published_doctor_cards() {
		$posts = get_posts( array(
			'post_type'      => Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
		) );
		$cards = array();
		foreach ( $posts as $post ) {
			$cards[] = $this->post_card( $post, Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE );
		}
		return $cards;
	}

	/** @return array<int,array<string,mixed>> */
	private function post_cards_except( $post_type, $limit, $exclude_id ) {
		$cards = $this->post_cards( $post_type, max( 1, absint( $limit ) + 1 ) );
		$cards = array_values( array_filter( $cards, static function ( $card ) use ( $exclude_id ) {
			return (int) $card['id'] !== absint( $exclude_id );
		} ) );
		return array_slice( $cards, 0, max( 1, absint( $limit ) ) );
	}

	/** @param array<int,array<string,mixed>> $cards Cards. @return array<string,mixed>|null */
	private function featured_factual_card( $cards ) {
		foreach ( $cards as $card ) {
			if ( ! empty( $card['image_id'] ) || ! empty( $card['summary'] ) || ! empty( $card['excerpt'] ) ) {
				return $card;
			}
		}
		return null;
	}

	/** @return array<int,array<string,mixed>> */
	private function clinic_cards() {
		$published = get_posts( array(
			'post_type' => Gloskin_Site_Core_Content_Service::CLINIC_POST_TYPE,
			'post_status' => 'publish', 'posts_per_page' => Gloskin_Site_Core_Content_Service::CLINIC_TARGET_COUNT,
			'orderby' => 'title', 'order' => 'ASC',
		) );
		$by_slug = array();
		foreach ( $published as $post ) { $by_slug[ $post->post_name ] = $post; }
		$cards = array();
		foreach ( Gloskin_Site_Core_Content_Service::clinic_definitions() as $slug => $title ) {
			if ( isset( $by_slug[ $slug ] ) ) {
				$cards[] = $this->post_card( $by_slug[ $slug ], Gloskin_Site_Core_Content_Service::CLINIC_POST_TYPE );
				continue;
			}
			$cards[] = array(
				'id' => 0, 'title' => $title, 'url' => home_url( '/clinics/' . $slug . '/' ),
				'excerpt' => '', 'image_id' => 0, 'short_location' => '', 'hours' => '',
				'degree_title' => '', 'specialization' => '', 'summary' => '', 'phone_display' => '', 'whatsapp_url' => '',
			);
		}
		return $cards;
	}

	/** @return array<string,mixed> */
	private function post_card( $post, $type ) {
		$excerpt = has_excerpt( $post ) ? get_the_excerpt( $post ) : '';
		$card = array(
			'id' => (int) $post->ID, 'title' => get_the_title( $post ), 'url' => get_permalink( $post ),
			'excerpt' => $excerpt, 'image_id' => absint( get_post_thumbnail_id( $post->ID ) ),
			'short_location' => '', 'hours' => '', 'degree_title' => '', 'specialization' => '',
			'summary' => '', 'phone_display' => '', 'whatsapp_url' => '',
		);
		if ( Gloskin_Site_Core_Content_Service::CLINIC_POST_TYPE === $type ) {
			$card['short_location'] = (string) get_post_meta( $post->ID, 'gloskin_short_location', true );
			$card['hours'] = (string) get_post_meta( $post->ID, 'gloskin_operating_hours', true );
			$card['phone_display'] = (string) get_post_meta( $post->ID, 'gloskin_phone_display', true );
			$card['whatsapp_url'] = $this->form->whatsapp_url( (string) get_post_meta( $post->ID, 'gloskin_whatsapp_number', true ), (string) get_post_meta( $post->ID, 'gloskin_whatsapp_message', true ) );
		} elseif ( Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE === $type ) {
			$card['degree_title'] = (string) get_post_meta( $post->ID, 'gloskin_degree_title', true );
			$card['specialization'] = (string) get_post_meta( $post->ID, 'gloskin_specialization', true );
		} elseif ( Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE === $type ) {
			$card['summary'] = (string) get_post_meta( $post->ID, 'gloskin_summary', true );
		}
		return $card;
	}

	/** @return array<int,array<string,mixed>> */
	private function cards_by_ids( $ids, $post_type ) {
		$cards = array();
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( $post instanceof WP_Post && $post_type === $post->post_type && 'publish' === $post->post_status ) {
				$cards[] = $this->post_card( $post, $post_type );
			}
		}
		return $cards;
	}

	/** @return array<int,array<string,mixed>> */
	private function reverse_cards( $post_type, $meta_key, $target_id ) {
		$posts = get_posts( array(
			'post_type' => $post_type, 'post_status' => 'publish', 'posts_per_page' => -1,
			'orderby' => 'title', 'order' => 'ASC',
		) );
		$cards = array();
		foreach ( $posts as $post ) {
			$ids = $this->id_meta( $post->ID, $meta_key );
			if ( in_array( absint( $target_id ), $ids, true ) ) { $cards[] = $this->post_card( $post, $post_type ); }
		}
		return $cards;
	}

	/** @return array<int,int> */
	private function id_meta( $post_id, $key ) {
		$value = get_post_meta( $post_id, $key, true );
		if ( ! is_array( $value ) ) { return array(); }
		return array_values( array_filter( array_map( 'absint', $value ) ) );
	}

	/** @return array<int,array<string,mixed>> */
	private function skincare_mappings() {
		$mappings = array();
		foreach ( Gloskin_Site_Core_Content_Service::skincare_definitions() as $slug => $label ) {
			$page = get_page_by_path( 'skincare/' . $slug, OBJECT, 'page' );
			$woo_slug = $page instanceof WP_Post ? (string) get_post_meta( $page->ID, 'gloskin_woo_category_slug', true ) : '';
			if ( '' === $woo_slug ) { $woo_slug = $slug; }
			$mappings[] = array(
				'label' => $label, 'slug' => $slug,
				'url' => $page instanceof WP_Post ? get_permalink( $page ) : home_url( '/skincare/' . $slug . '/' ),
				'woo_slug' => $woo_slug,
				'category_exists' => $this->woocommerce->category_exists( $woo_slug ),
				'category_url' => $this->woocommerce->category_url( $woo_slug ),
			);
		}
		return $mappings;
	}

	/** @return array<int,array<string,mixed>> */
	private function insight_cards( $limit ) {
		$posts = get_posts( array(
			'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => max( 1, absint( $limit ) ),
			'orderby' => 'date', 'order' => 'DESC',
		) );
		$cards = array();
		foreach ( $posts as $post ) { $cards[] = $this->insight_card( $post ); }
		return $cards;
	}

	/** @return array<int,array<string,string>> */
	private function static_clinic_links() {
		$links = array();
		foreach ( Gloskin_Site_Core_Content_Service::clinic_definitions() as $slug => $label ) {
			$links[] = array( 'label' => $label, 'url' => home_url( '/clinics/' . $slug . '/' ) );
		}
		return $links;
	}

	/** @return string */
	private function design_variant() {
		$settings = get_option( Gloskin_Site_Core_Form_Adapter::SETTINGS_OPTION, array() );
		$value = isset( $settings['design_variant'] ) ? sanitize_key( $settings['design_variant'] ) : 'medical';
		return in_array( $value, array( 'medical', 'modern', 'luxury' ), true ) ? $value : 'medical';
	}

	/** @return string */
	private function header_variant() {
		$settings = get_option( Gloskin_Site_Core_Form_Adapter::SETTINGS_OPTION, array() );
		$value = isset( $settings['header_variant'] ) ? sanitize_key( $settings['header_variant'] ) : 'header-1';
		return in_array( $value, array( 'header-1', 'header-2' ), true ) ? $value : 'header-1';
	}

	/**
	 * Resolve Home's optional native campaign video from the existing shared
	 * settings owner. Only Media Library MP4/WebM attachments are accepted; an
	 * invalid/missing value simply returns no sources so the same visible hero
	 * keeps its WordPress/editorial fallback media.
	 *
	 * @return array{sources:array<int,array{src:string,type:string}>}
	 */
	private function hero_background_video() {
		if ( ! class_exists( 'Gloskin_Site_Core_Admin_Service' ) ) {
			require_once __DIR__ . '/class-gloskin-site-core-admin-service.php';
		}
		$defaults = class_exists( 'Gloskin_Site_Core_Admin_Service' ) ? Gloskin_Site_Core_Admin_Service::settings_defaults() : array( 'hero_video_media_id' => 0 );
		$settings = array_merge( $defaults, get_option( Gloskin_Site_Core_Form_Adapter::SETTINGS_OPTION, $defaults ) );
		$media_id = isset( $settings['hero_video_media_id'] ) ? absint( $settings['hero_video_media_id'] ) : 0;
		if ( ! $media_id ) { return array( 'sources' => array() ); }
		$url  = function_exists( 'wp_get_attachment_url' ) ? wp_get_attachment_url( $media_id ) : false;
		$mime = function_exists( 'get_post_mime_type' ) ? get_post_mime_type( $media_id ) : '';
		if ( ! $url || ! in_array( (string) $mime, array( 'video/mp4', 'video/webm' ), true ) ) {
			return array( 'sources' => array() );
		}
		return array( 'sources' => array( array( 'src' => $url, 'type' => (string) $mime ) ) );
	}
}
