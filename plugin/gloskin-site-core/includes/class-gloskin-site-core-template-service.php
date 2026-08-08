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
	 * @param string                                $plugin_root Plugin directory.
	 * @param Gloskin_Site_Core_Navigation_Service $navigation Navigation service.
	 * @param Gloskin_Site_Core_WooCommerce_Adapter $woocommerce Woo adapter.
	 * @param Gloskin_Site_Core_Form_Adapter       $form Form adapter.
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
	}

	/**
	 * @param array<string,string> $parts WordPress title parts.
	 * @return array<string,string>
	 */
	public function localize_document_title( $parts ) {
		$view = $this->identify_view();
		$titles = array(
			'home'       => 'Gloskin',
			'about'      => 'Tentang Gloskin',
			'treatments' => 'Perawatan',
			'skincare'   => 'Skincare',
			'clinics'    => 'Klinik',
			'doctors'    => 'Dokter',
			'contact'    => 'Kontak',
			'insights'   => 'Insight',
			'shop'       => 'Belanja',
		);
		if ( isset( $titles[ $view ] ) ) {
			$parts['title'] = $titles[ $view ];
		}
		return $parts;
	}

	/**
	 * @param string $template Theme-resolved template.
	 * @return string
	 */
	public function resolve_template( $template ) {
		$view = $this->identify_view();
		if ( '' === $view ) {
			return $template;
		}

		$context                   = $this->build_context( $view );
		$context['view']           = $view;
		$context['navigation']     = $this->navigation->tree();
		$context['design_variant'] = $this->design_variant();
		$context['clinic_links']   = $this->static_clinic_links();
		$context['site_name']      = 'Gloskin';
		set_query_var( 'gloskin_context', $context );

		$shell = $this->plugin_root . '/templates/shell.php';
		return is_readable( $shell ) ? $shell : $template;
	}

	/** @return string */
	private function identify_view() {
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
					'skincare' => 'skincare', 'clinics' => 'clinics', 'contact' => 'contact',
					'insights' => 'insights', 'shop' => 'shop', 'doctors' => 'doctors',
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
			case 'treatment': return $this->treatment_context();
			case 'skincare': return $this->skincare_context();
			case 'skincare-category': return $this->skincare_category_context();
			case 'clinics': return $this->clinics_context();
			case 'clinic': return $this->clinic_context();
			case 'doctors': return $this->doctors_context();
			case 'doctor': return $this->doctor_context();
			case 'contact': return $this->contact_context();
			case 'insights': return $this->insights_context();
			case 'shop': return $this->shop_context();
			default: return array();
		}
	}

	/** @return array<string,mixed> */
	private function home_context() {
		$page = $this->content_page( 'home' );
		return array(
			'page' => $page,
			'hero' => $this->hero_context( $page, __( 'Perawatan kulit, anti-aging, dan rambut yang dimulai dari konsultasi.', 'gloskin-site-core' ), __( 'Gloskin adalah klinik estetika, anti-aging, dan perawatan rambut yang mengutamakan pemeriksaan bersama dokter sebelum menentukan langkah perawatan untuk kulit Anda.', 'gloskin-site-core' ), __( 'Cari Klinik Terdekat', 'gloskin-site-core' ), home_url( '/clinics/' ) ),
			'treatments' => $this->post_cards( Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE, 8 ),
			'clinics' => $this->clinic_cards(),
			'doctors' => $this->post_cards( Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE, 4 ),
			'skincare' => $this->skincare_mappings(),
			'products' => $this->woocommerce->products( 4 ),
			'insights' => $this->insight_cards( 3 ),
			'woo_ready' => $this->woocommerce->available(),
		);
	}

	/** @return array<string,mixed> */
	private function about_context() {
		$page = $this->content_page( 'about' );
		return array(
			'page' => $page,
			'hero' => $this->hero_context( $page, __( 'Tentang Gloskin', 'gloskin-site-core' ), __( 'Kenali Gloskin melalui jaringan klinik, informasi perawatan, skincare, dan kanal kontak yang tersedia.', 'gloskin-site-core' ) ),
			'vision' => $page ? (string) get_post_meta( $page->ID, 'gloskin_about_vision', true ) : '',
			'mission' => $page ? (string) get_post_meta( $page->ID, 'gloskin_about_mission', true ) : '',
			'values' => $page ? (string) get_post_meta( $page->ID, 'gloskin_about_values', true ) : '',
			'clinics' => $this->clinic_cards(),
			'doctors' => $this->post_cards( Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE, 4 ),
		);
	}

	/** @return array<string,mixed> */
	private function treatments_context() {
		$page = $this->content_page( 'treatments' );
		$cards = $this->post_cards( Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE, 8 );
		$featured = $this->featured_factual_card( $cards );
		$remaining = $featured ? array_values( array_filter( $cards, static function ( $card ) use ( $featured ) {
			return (int) $card['id'] !== (int) $featured['id'];
		} ) ) : $cards;
		return array(
			'page' => $page,
			'hero' => $this->hero_context( $page, __( 'Perawatan', 'gloskin-site-core' ), __( 'Pelajari informasi perawatan Gloskin sebelum menentukan langkah konsultasi.', 'gloskin-site-core' ) ),
			'featured_treatment' => $featured,
			'treatments' => $remaining,
			'doctors' => $this->post_cards( Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE, 3 ),
			'target' => Gloskin_Site_Core_Content_Service::TREATMENT_TARGET_COUNT,
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
			'doctors' => $this->post_cards( Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE, 13 ),
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
			$posts[] = $this->post_card( $post, 'insight' );
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
	private function shop_context() {
		$page = $this->content_page( 'shop' );
		return array(
			'page' => $page,
			'hero' => $this->hero_context( $page, __( 'Belanja', 'gloskin-site-core' ), __( 'Jelajahi skincare Gloskin melalui kategori dan produk yang ditampilkan di situs.', 'gloskin-site-core' ) ),
			'mappings' => $this->skincare_mappings(),
			'products' => $this->woocommerce->products( 20 ),
			'woo_ready' => $this->woocommerce->available(),
		);
	}

	/** @param string $slug Page slug. @return WP_Post|null */
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
		foreach ( $posts as $post ) {
			$cards[] = $this->post_card( $post, $post_type );
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
		foreach ( $published as $post ) {
			$by_slug[ $post->post_name ] = $post;
		}
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
			if ( in_array( absint( $target_id ), $ids, true ) ) {
				$cards[] = $this->post_card( $post, $post_type );
			}
		}
		return $cards;
	}

	/** @return array<int,int> */
	private function id_meta( $post_id, $key ) {
		$value = get_post_meta( $post_id, $key, true );
		if ( ! is_array( $value ) ) {
			return array();
		}
		return array_values( array_filter( array_map( 'absint', $value ) ) );
	}

	/** @return array<int,array<string,mixed>> */
	private function skincare_mappings() {
		$mappings = array();
		foreach ( Gloskin_Site_Core_Content_Service::skincare_definitions() as $slug => $label ) {
			$page = get_page_by_path( 'skincare/' . $slug, OBJECT, 'page' );
			$woo_slug = $page instanceof WP_Post ? (string) get_post_meta( $page->ID, 'gloskin_woo_category_slug', true ) : '';
			if ( '' === $woo_slug ) {
				$woo_slug = $slug;
			}
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
		foreach ( $posts as $post ) {
			$cards[] = $this->post_card( $post, 'insight' );
		}
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
}
