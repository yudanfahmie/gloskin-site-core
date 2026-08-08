<?php
/**
 * Gloskin-owned WordPress content structures.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Gloskin_Site_Core_Content_Service {
	const TREATMENT_POST_TYPE = 'gloskin_treatment';
	const CLINIC_POST_TYPE    = 'gloskin_clinic';
	const DOCTOR_POST_TYPE    = 'gloskin_doctor';
	const ADMIN_MENU_SLUG     = 'gloskin-content';

	const TREATMENT_TARGET_COUNT = 8;
	const CLINIC_TARGET_COUNT    = 9;
	const DOCTOR_TARGET_COUNT    = 13;

	/** @return void */
	public function register() {
		add_action( 'init', array( $this, 'register_content' ), 5 );
	}

	/** @return void */
	public function register_content() {
		self::register_content_types();
		$this->register_meta();
	}

	/** @return void */
	public static function register_content_types() {
		register_post_type( self::TREATMENT_POST_TYPE, self::post_type_args( __( 'Treatments', 'gloskin-site-core' ), __( 'Treatment', 'gloskin-site-core' ), 'treatments' ) );
		register_post_type( self::CLINIC_POST_TYPE, self::post_type_args( __( 'Clinics', 'gloskin-site-core' ), __( 'Clinic', 'gloskin-site-core' ), 'clinics' ) );
		register_post_type( self::DOCTOR_POST_TYPE, self::post_type_args( __( 'Doctors', 'gloskin-site-core' ), __( 'Doctor', 'gloskin-site-core' ), 'doctors' ) );
	}

	/** @return array<string,int> */
	public static function record_targets() {
		return array(
			self::TREATMENT_POST_TYPE => self::TREATMENT_TARGET_COUNT,
			self::CLINIC_POST_TYPE => self::CLINIC_TARGET_COUNT,
			self::DOCTOR_POST_TYPE => self::DOCTOR_TARGET_COUNT,
		);
	}

	/** @return array<string,string> */
	public static function clinic_definitions() {
		return array(
			'kebayoran-baru' => 'Kebayoran Baru', 'tebet' => 'Tebet', 'bekasi' => 'Bekasi',
			'cibubur' => 'Cibubur', 'serpong' => 'Serpong', 'surabaya' => 'Surabaya',
			'banjarmasin' => 'Banjarmasin', 'balikpapan' => 'Balikpapan', 'denpasar' => 'Denpasar',
		);
	}

	/** @return array<string,string> */
	public static function skincare_definitions() {
		return array(
			'facial-wash' => 'Facial Wash',
			'day-cream-sunscreen' => 'Day Cream / Sunscreen',
			'toner' => 'Toner',
			'serum' => 'Serum',
			'acne-care' => 'Acne Care',
			'anti-aging' => 'Anti-Aging',
			'brightening-pigmentation-care' => 'Brightening & Pigmentation Care',
		);
	}

	/** @return array<string,mixed> */
	private static function post_type_args( $plural, $singular, $rewrite_slug ) {
		return array(
			'labels' => array(
				'name' => $plural,
				'singular_name' => $singular,
				'add_new_item' => sprintf( __( 'Add New %s', 'gloskin-site-core' ), $singular ),
				'edit_item' => sprintf( __( 'Edit %s', 'gloskin-site-core' ), $singular ),
				'view_item' => sprintf( __( 'View %s', 'gloskin-site-core' ), $singular ),
				'search_items' => sprintf( __( 'Search %s', 'gloskin-site-core' ), $plural ),
			),
			'public' => true,
			'show_ui' => true,
			'show_in_menu' => self::ADMIN_MENU_SLUG,
			'show_in_rest' => true,
			'has_archive' => false,
			'rewrite' => array( 'slug' => $rewrite_slug, 'with_front' => false ),
			'supports' => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields' ),
			'map_meta_cap' => true,
			'delete_with_user' => false,
			'publicly_queryable' => true,
		);
	}

	/** @return void */
	private function register_meta() {
		$this->register_string_meta( self::TREATMENT_POST_TYPE, 'gloskin_summary', 'textarea' );
		$this->register_string_meta( self::TREATMENT_POST_TYPE, 'gloskin_benefits', 'rich' );
		$this->register_string_meta( self::TREATMENT_POST_TYPE, 'gloskin_contraindications', 'rich' );
		$this->register_post_id_list_meta( self::TREATMENT_POST_TYPE, 'gloskin_clinic_ids', self::CLINIC_POST_TYPE );
		$this->register_post_id_list_meta( self::TREATMENT_POST_TYPE, 'gloskin_doctor_ids', self::DOCTOR_POST_TYPE );
		$this->register_string_meta( self::TREATMENT_POST_TYPE, 'gloskin_booking_target', 'action_url' );

		$this->register_string_meta( self::CLINIC_POST_TYPE, 'gloskin_address', 'textarea' );
		$this->register_string_meta( self::CLINIC_POST_TYPE, 'gloskin_phone_display', 'text' );
		$this->register_string_meta( self::CLINIC_POST_TYPE, 'gloskin_phone_uri', 'phone' );
		$this->register_string_meta( self::CLINIC_POST_TYPE, 'gloskin_whatsapp_number', 'phone' );
		$this->register_string_meta( self::CLINIC_POST_TYPE, 'gloskin_whatsapp_message', 'text' );
		$this->register_string_meta( self::CLINIC_POST_TYPE, 'gloskin_operating_hours', 'textarea' );
		$this->register_string_meta( self::CLINIC_POST_TYPE, 'gloskin_map_url', 'http_url' );
		$this->register_string_meta( self::CLINIC_POST_TYPE, 'gloskin_map_embed', 'map_embed_url' );
		$this->register_attachment_id_list_meta( self::CLINIC_POST_TYPE, 'gloskin_gallery_image_ids' );
		$this->register_string_meta( self::CLINIC_POST_TYPE, 'gloskin_short_location', 'text' );

		$this->register_string_meta( self::DOCTOR_POST_TYPE, 'gloskin_degree_title', 'text' );
		$this->register_string_meta( self::DOCTOR_POST_TYPE, 'gloskin_specialization', 'text' );
		$this->register_post_id_list_meta( self::DOCTOR_POST_TYPE, 'gloskin_branch_ids', self::CLINIC_POST_TYPE );
		$this->register_string_meta( self::DOCTOR_POST_TYPE, 'gloskin_sip_number', 'text' );
		$this->register_string_meta( self::DOCTOR_POST_TYPE, 'gloskin_credentials', 'rich' );
		$this->register_string_meta( self::DOCTOR_POST_TYPE, 'gloskin_profile', 'rich' );
		$this->register_string_meta( self::DOCTOR_POST_TYPE, 'gloskin_schedule', 'textarea' );
		$this->register_string_meta( self::DOCTOR_POST_TYPE, 'gloskin_booking_target', 'action_url' );

		$this->register_string_meta( 'page', 'gloskin_woo_category_slug', 'slug' );
		$this->register_string_meta( 'page', 'gloskin_about_vision', 'rich' );
		$this->register_string_meta( 'page', 'gloskin_about_mission', 'rich' );
		$this->register_string_meta( 'page', 'gloskin_about_values', 'rich' );
		$this->register_string_meta( 'page', 'gloskin_hero_heading', 'text' );
		$this->register_string_meta( 'page', 'gloskin_hero_copy', 'textarea' );
		$this->register_string_meta( 'page', 'gloskin_hero_cta_label', 'text' );
		$this->register_string_meta( 'page', 'gloskin_hero_cta_url', 'action_url' );
		$this->register_attachment_id_meta( 'page', 'gloskin_hero_media_id' );
	}

	private function register_string_meta( $post_type, $meta_key, $sanitizer ) {
		register_post_meta( $post_type, $meta_key, array(
			'type' => 'string', 'single' => true, 'default' => '', 'show_in_rest' => true,
			'sanitize_callback' => function ( $value ) use ( $sanitizer ) { return $this->sanitize_string( $value, $sanitizer ); },
			'auth_callback' => array( $this, 'authorize_meta' ),
		) );
	}

	private function register_post_id_list_meta( $post_type, $meta_key, $target_post_type ) {
		register_post_meta( $post_type, $meta_key, array(
			'type' => 'array', 'single' => true, 'default' => array(),
			'show_in_rest' => array( 'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ), 'default' => array() ) ),
			'sanitize_callback' => function ( $value ) use ( $target_post_type ) { return $this->sanitize_post_id_list( $value, $target_post_type ); },
			'auth_callback' => array( $this, 'authorize_meta' ),
		) );
	}

	private function register_attachment_id_list_meta( $post_type, $meta_key ) {
		register_post_meta( $post_type, $meta_key, array(
			'type' => 'array', 'single' => true, 'default' => array(),
			'show_in_rest' => array( 'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ), 'default' => array() ) ),
			'sanitize_callback' => array( $this, 'sanitize_attachment_id_list' ),
			'auth_callback' => array( $this, 'authorize_meta' ),
		) );
	}

	private function register_attachment_id_meta( $post_type, $meta_key ) {
		register_post_meta( $post_type, $meta_key, array(
			'type' => 'integer', 'single' => true, 'default' => 0, 'show_in_rest' => true,
			'sanitize_callback' => array( $this, 'sanitize_attachment_id' ),
			'auth_callback' => array( $this, 'authorize_meta' ),
		) );
	}

	public function authorize_meta( $allowed, $meta_key, $post_id ) {
		unset( $allowed, $meta_key );
		return current_user_can( 'edit_post', $post_id );
	}

	private function sanitize_string( $value, $sanitizer ) {
		$value = is_scalar( $value ) ? (string) $value : '';
		switch ( $sanitizer ) {
			case 'textarea': return sanitize_textarea_field( $value );
			case 'rich': return wp_kses_post( $value );
			case 'http_url': return esc_url_raw( $value, array( 'http', 'https' ) );
			case 'action_url': return esc_url_raw( $value, array( 'http', 'https', 'tel', 'mailto' ) );
			case 'phone': return $this->sanitize_phone( $value );
			case 'map_embed_url': return $this->sanitize_map_embed_url( $value );
			case 'slug': return sanitize_title( $value );
			case 'text':
			default: return sanitize_text_field( $value );
		}
	}

	private function sanitize_phone( $value ) {
		$value = preg_replace( '/[^0-9+]/', '', $value );
		if ( ! is_string( $value ) ) { return ''; }
		$value = preg_replace( '/(?!^)\+/', '', $value );
		return is_string( $value ) ? $value : '';
	}

	private function sanitize_map_embed_url( $value ) {
		$url = esc_url_raw( $value, array( 'https' ) );
		if ( '' === $url ) { return ''; }
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$allowed_hosts = array( 'google.com', 'www.google.com', 'google.co.id', 'www.google.co.id', 'maps.google.com' );
		if ( ! in_array( $host, $allowed_hosts, true ) || 0 !== strpos( $path, '/maps/embed' ) ) { return ''; }
		return $url;
	}

	private function sanitize_post_id_list( $value, $target_post_type ) {
		if ( ! is_array( $value ) ) { return array(); }
		$ids = array();
		foreach ( array_slice( $value, 0, 50 ) as $candidate ) {
			$id = absint( $candidate );
			if ( $id && $target_post_type === get_post_type( $id ) ) { $ids[] = $id; }
		}
		return array_values( array_unique( $ids ) );
	}

	public function sanitize_attachment_id( $value ) {
		$id = absint( $value );
		if ( ! $id || 'attachment' !== get_post_type( $id ) || ! wp_attachment_is_image( $id ) ) { return 0; }
		return $id;
	}

	public function sanitize_attachment_id_list( $value ) {
		if ( ! is_array( $value ) ) { return array(); }
		$ids = array();
		foreach ( array_slice( $value, 0, 12 ) as $candidate ) {
			$id = $this->sanitize_attachment_id( $candidate );
			if ( $id ) { $ids[] = $id; }
		}
		return array_values( array_unique( $ids ) );
	}
}
