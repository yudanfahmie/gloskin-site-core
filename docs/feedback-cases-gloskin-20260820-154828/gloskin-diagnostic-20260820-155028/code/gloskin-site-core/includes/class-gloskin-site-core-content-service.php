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
	const TREATMENT_POST_TYPE    = 'gloskin_treatment';
	const CLINIC_POST_TYPE       = 'gloskin_clinic';
	const DOCTOR_POST_TYPE       = 'gloskin_doctor';
	const PROMO_POST_TYPE        = 'gloskin_promo';
	const TESTIMONIAL_POST_TYPE  = 'gloskin_testimonial';
	const ACHIEVEMENT_POST_TYPE  = 'gloskin_achievement';
	const ADMIN_MENU_SLUG        = 'gloskin-content';

	const TREATMENT_TARGET_COUNT = 8;
	const CLINIC_TARGET_COUNT    = 9;
	const DOCTOR_TARGET_COUNT    = 13;

	/* Demo identity meta key — used to prevent seeding duplicates. */
	const DEMO_IDENTITY_META     = '_gloskin_demo_identity';
	const DEMO_REVISION_META     = '_gloskin_demo_revision';

	/*
	 * Treatment Consultation data model (docs/task-treatment-consultation-
	 * commerce-discovery.md). Three private taxonomies + one private CPT,
	 * all registered here alongside the existing content structures --
	 * no new bootable service, no custom table. WooCommerce products
	 * remain the sole purchasable entity; these are classification/
	 * questionnaire structures only, never a second commerce model.
	 */
	const FAMILY_TAXONOMY       = 'gloskin_product_family';
	const CONCERN_TAXONOMY      = 'gloskin_concern';
	const CONSULTATION_TAXONOMY = 'gloskin_consultation_path';
	const QUESTION_POST_TYPE    = 'gloskin_question';

	const FAMILY_SKINCARE  = 'skincare';
	const FAMILY_TREATMENT = 'treatment';

	const ANSWER_META_KEY      = 'gloskin_question_answers';
	const ANSWER_MAX_OPTIONS   = 12;
	const ANSWER_MAX_WEIGHT    = 3;
	const ANSWER_LABEL_MAXLEN  = 80;

	const PATH_META_ORDER    = 'gloskin_path_order';
	const PATH_META_IMAGE_ID = 'gloskin_path_image_id';
	const PATH_META_BASELINE = 'gloskin_path_baseline_concerns';

	const QUESTION_MIN_PUBLISHED = 13;
	const PATH_MIN_VALID         = 4;

	/** @return void */
	public function register() {
		add_action( 'init', array( $this, 'register_content' ), 5 );
	}

	/** @return void */
	public function register_content() {
		self::register_content_types();
		self::register_taxonomies();
		$this->register_meta();
		$this->register_question_answer_meta();
		$this->register_path_term_meta();
	}

	/** @return void */
	public static function register_content_types() {
		register_post_type( self::TREATMENT_POST_TYPE, self::post_type_args( __( 'Treatments', 'gloskin-site-core' ), __( 'Treatment', 'gloskin-site-core' ), 'treatments' ) );
		register_post_type( self::CLINIC_POST_TYPE, self::post_type_args( __( 'Clinics', 'gloskin-site-core' ), __( 'Clinic', 'gloskin-site-core' ), 'clinics' ) );
		register_post_type( self::DOCTOR_POST_TYPE, self::post_type_args( __( 'Doctors', 'gloskin-site-core' ), __( 'Doctor', 'gloskin-site-core' ), 'doctors' ) );
		register_post_type( self::QUESTION_POST_TYPE, self::question_post_type_args() );
		register_post_type( self::PROMO_POST_TYPE, self::private_managed_post_type_args( __( 'Promos', 'gloskin-site-core' ), __( 'Promo', 'gloskin-site-core' ) ) );
		register_post_type( self::TESTIMONIAL_POST_TYPE, self::private_managed_post_type_args( __( 'Testimonials', 'gloskin-site-core' ), __( 'Testimonial', 'gloskin-site-core' ) ) );
		register_post_type( self::ACHIEVEMENT_POST_TYPE, self::private_managed_post_type_args( __( 'Achievements', 'gloskin-site-core' ), __( 'Achievement', 'gloskin-site-core' ) ) );
	}

	/**
	 * Private managed CPT — native WordPress CRUD under Gloskin Content,
	 * not publicly queryable, no public URL, no archive.
	 *
	 * @param string $plural   Plural label.
	 * @param string $singular Singular label.
	 * @return array<string,mixed>
	 */
	private static function private_managed_post_type_args( $plural, $singular ) {
		return array(
			'labels' => array(
				'name'          => $plural,
				'singular_name' => $singular,
				/* translators: %s: singular post type label. */
				'add_new_item'  => sprintf( __( 'Add New %s', 'gloskin-site-core' ), $singular ),
				/* translators: %s: singular post type label. */
				'edit_item'     => sprintf( __( 'Edit %s', 'gloskin-site-core' ), $singular ),
			),
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => true,
			'show_in_menu'        => self::ADMIN_MENU_SLUG,
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => false,
			'show_in_rest'        => true,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'exclude_from_search' => true,
			'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'page-attributes' ),
			'map_meta_cap'        => true,
			'capability_type'     => 'post',
			'delete_with_user'    => false,
		);
	}

	/**
	 * Private questionnaire question CPT. public=false / no rewrite / no
	 * public query var (never a second public route); show_ui=true so the
	 * record can still use native WordPress post editing (title = question
	 * text, publish/draft = active/inactive) while show_in_menu=false keeps
	 * it out of the wp-admin sidebar -- Konsultasi Perawatan -> Pertanyaan
	 * is the only surface that links to it (native edit.php list + native
	 * post.php edit screen, not a rebuilt editor).
	 *
	 * @return array<string,mixed>
	 */
	private static function question_post_type_args() {
		return array(
			'labels' => array(
				'name'          => __( 'Consultation Questions', 'gloskin-site-core' ),
				'singular_name' => __( 'Consultation Question', 'gloskin-site-core' ),
				'add_new_item'  => __( 'Add New Question', 'gloskin-site-core' ),
				'edit_item'     => __( 'Edit Question', 'gloskin-site-core' ),
				'view_item'     => __( 'View Question', 'gloskin-site-core' ),
				'search_items'  => __( 'Search Questions', 'gloskin-site-core' ),
			),
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => false,
			'show_in_nav_menus'  => false,
			'show_in_admin_bar'  => false,
			'show_in_rest'       => false,
			'has_archive'        => false,
			'rewrite'            => false,
			'query_var'          => false,
			'exclude_from_search' => true,
			'supports'           => array( 'title' ),
			'map_meta_cap'       => true,
			'capability_type'    => 'post',
		);
	}

	/**
	 * gloskin_product_family / gloskin_concern (Woo `product`) and
	 * gloskin_consultation_path (this plugin's private question CPT):
	 * all non-public, no rewrite, no public query var, no standalone
	 * taxonomy submenu -- classification only, never Woo product_cat
	 * (which already owns merchandising IA and must stay independent).
	 *
	 * Object-type slugs are canonical registration keys in WordPress; they
	 * do not require the post type to have been registered first. Registering
	 * these relationships unconditionally keeps the schema deterministic when
	 * Gloskin's init callback runs before WooCommerce registers `product`.
	 * Never snapshot Woo availability here: the adapter resolves that only at
	 * point of use, while this schema must exist for the entire request.
	 *
	 * @return void
	 */
	public static function register_taxonomies() {
		register_taxonomy(
			self::FAMILY_TAXONOMY,
			'product',
			array(
				'labels' => array(
					'name'          => __( 'Jenis Produk', 'gloskin-site-core' ),
					'singular_name' => __( 'Jenis Produk', 'gloskin-site-core' ),
				),
				'public'            => false,
				'publicly_queryable' => false,
				'show_ui'           => false,
				'show_in_menu'      => false,
				'show_in_nav_menus' => false,
				'show_in_rest'      => false,
				'show_admin_column' => false,
				'query_var'         => false,
				'rewrite'           => false,
				'hierarchical'      => false,
			)
		);
		register_taxonomy(
			self::CONCERN_TAXONOMY,
			'product',
			array(
				'labels' => array(
					'name'          => __( 'Keluhan', 'gloskin-site-core' ),
					'singular_name' => __( 'Keluhan', 'gloskin-site-core' ),
				),
				'public'            => false,
				'publicly_queryable' => false,
				'show_ui'           => false,
				'show_in_menu'      => false,
				'show_in_nav_menus' => false,
				'show_in_rest'      => false,
				'show_admin_column' => false,
				'query_var'         => false,
				'rewrite'           => false,
				'hierarchical'      => false,
			)
		);
		register_taxonomy(
			self::CONSULTATION_TAXONOMY,
			self::QUESTION_POST_TYPE,
			array(
				'labels' => array(
					'name'          => __( 'Jalur Konsultasi', 'gloskin-site-core' ),
					'singular_name' => __( 'Jalur Konsultasi', 'gloskin-site-core' ),
				),
				'public'            => false,
				'publicly_queryable' => false,
				'show_ui'           => false,
				'show_in_menu'      => false,
				'show_in_nav_menus' => false,
				'show_in_rest'      => false,
				'show_admin_column' => false,
				'query_var'         => false,
				'rewrite'           => false,
				'hierarchical'      => false,
			)
		);
	}

	/**
	 * One registered post-meta array per question -- bounded structured
	 * answer options, never a separate "answer" CPT. Shape:
	 * array( array( 'label' => string, 'concern_id' => int, 'weight' => 1..3 ), ... ).
	 * Sanitization drops (never fatals on) any option referencing a
	 * missing/invalid concern term -- admin readiness surfaces that
	 * instead of the save silently failing.
	 *
	 * @return void
	 */
	private function register_question_answer_meta() {
		register_post_meta(
			self::QUESTION_POST_TYPE,
			self::ANSWER_META_KEY,
			array(
				'type'              => 'array',
				'single'            => true,
				'default'           => array(),
				'show_in_rest'      => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'label'      => array( 'type' => 'string' ),
								'concern_id' => array( 'type' => 'integer' ),
								'weight'     => array( 'type' => 'integer' ),
							),
						),
						'default' => array(),
					),
				),
				'sanitize_callback' => array( $this, 'sanitize_answer_options' ),
				'auth_callback'     => array( $this, 'authorize_meta' ),
			)
		);
	}

	/**
	 * @param mixed $value Raw meta value.
	 * @return array<int,array{label:string,concern_id:int,weight:int}>
	 */
	public function sanitize_answer_options( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$clean = array();
		foreach ( array_slice( $value, 0, self::ANSWER_MAX_OPTIONS ) as $option ) {
			if ( ! is_array( $option ) ) {
				continue;
			}
			$label = isset( $option['label'] ) ? sanitize_text_field( (string) $option['label'] ) : '';
			$label = mb_substr( $label, 0, self::ANSWER_LABEL_MAXLEN );
			if ( '' === $label ) {
				continue;
			}
			$concern_id = isset( $option['concern_id'] ) ? absint( $option['concern_id'] ) : 0;
			/* A missing/deleted concern term is dropped here silently (never
			 * fatal, per the task's boundary rules) -- admin readiness
			 * (Ringkasan) is the surface that reports orphan answers, not
			 * this sanitizer. term_exists() also confirms the term belongs
			 * to this exact taxonomy, not an unrelated ID collision. */
			if ( ! $concern_id || ! term_exists( $concern_id, self::CONCERN_TAXONOMY ) ) {
				continue;
			}
			$weight = isset( $option['weight'] ) ? absint( $option['weight'] ) : 1;
			$weight = max( 1, min( self::ANSWER_MAX_WEIGHT, $weight ) );
			$clean[] = array(
				'label'      => $label,
				'concern_id' => $concern_id,
				'weight'     => $weight,
			);
		}
		return $clean;
	}

	/**
	 * Small presentation/recommendation term meta for gloskin_consultation_path
	 * terms: optional image attachment ID, display order, default baseline
	 * concern term IDs. Registered via register_term_meta() (native taxonomy
	 * term-meta API), not a second option/table.
	 *
	 * @return void
	 */
	private function register_path_term_meta() {
		register_term_meta(
			self::CONSULTATION_TAXONOMY,
			self::PATH_META_ORDER,
			array(
				'type'              => 'integer',
				'single'            => true,
				'default'           => 0,
				'show_in_rest'      => true,
				'sanitize_callback' => 'absint',
				'auth_callback'     => array( $this, 'authorize_term_meta' ),
			)
		);
		register_term_meta(
			self::CONSULTATION_TAXONOMY,
			self::PATH_META_IMAGE_ID,
			array(
				'type'              => 'integer',
				'single'            => true,
				'default'           => 0,
				'show_in_rest'      => true,
				'sanitize_callback' => array( $this, 'sanitize_attachment_id' ),
				'auth_callback'     => array( $this, 'authorize_term_meta' ),
			)
		);
		register_term_meta(
			self::CONSULTATION_TAXONOMY,
			self::PATH_META_BASELINE,
			array(
				'type'              => 'array',
				'single'            => true,
				'default'           => array(),
				'show_in_rest'      => array( 'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ), 'default' => array() ) ),
				'sanitize_callback' => array( $this, 'sanitize_baseline_concerns' ),
				'auth_callback'     => array( $this, 'authorize_term_meta' ),
			)
		);
	}

	/**
	 * @param mixed $value Raw term meta value.
	 * @return array<int,int>
	 */
	public function sanitize_baseline_concerns( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$ids = array();
		foreach ( array_slice( $value, 0, self::ANSWER_MAX_OPTIONS ) as $candidate ) {
			$id = absint( $candidate );
			if ( $id && term_exists( $id, self::CONCERN_TAXONOMY ) ) {
				$ids[] = $id;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * @param bool   $allowed Whether the current user may edit this term meta.
	 * @param string $meta_key Meta key.
	 * @param int    $object_id Term ID.
	 * @return bool
	 */
	public function authorize_term_meta( $allowed, $meta_key, $object_id ) {
		unset( $allowed, $meta_key, $object_id );
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Ensures exactly the two stable gloskin_product_family terms exist
	 * (skincare, treatment) -- structural only, called once from
	 * LifecycleService's upgrade path, never seeding synthetic
	 * consultation/demo data. Idempotent: wp_insert_term() itself already
	 * no-ops when a term of that slug exists.
	 *
	 * @return void
	 */
	public static function ensure_family_terms() {
		if ( ! taxonomy_exists( self::FAMILY_TAXONOMY ) ) {
			return;
		}
		foreach ( array( self::FAMILY_SKINCARE => __( 'Skincare', 'gloskin-site-core' ), self::FAMILY_TREATMENT => __( 'Perawatan', 'gloskin-site-core' ) ) as $slug => $label ) {
			if ( ! term_exists( $slug, self::FAMILY_TAXONOMY ) ) {
				wp_insert_term( $label, self::FAMILY_TAXONOMY, array( 'slug' => $slug ) );
			}
		}
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
				/* translators: %s: the post type's singular label, used in the admin "Add New" menu item. */
				'add_new_item' => sprintf( __( 'Add New %s', 'gloskin-site-core' ), $singular ),
				/* translators: %s: the post type's singular label, used in the admin "Edit" menu item. */
				'edit_item' => sprintf( __( 'Edit %s', 'gloskin-site-core' ), $singular ),
				/* translators: %s: the post type's singular label, used in the admin "View" menu item. */
				'view_item' => sprintf( __( 'View %s', 'gloskin-site-core' ), $singular ),
				/* translators: %s: the post type's plural label, used in the admin "Search" menu item. */
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
		/* gloskin_promo managed fields */
		$this->register_string_meta( self::PROMO_POST_TYPE, 'gloskin_promo_eyebrow', 'text' );
		$this->register_string_meta( self::PROMO_POST_TYPE, 'gloskin_promo_summary', 'textarea' );
		$this->register_string_meta( self::PROMO_POST_TYPE, 'gloskin_promo_cta_label', 'text' );
		$this->register_string_meta( self::PROMO_POST_TYPE, 'gloskin_promo_cta_url', 'action_url' );
		$this->register_string_meta( self::PROMO_POST_TYPE, 'gloskin_promo_start_date', 'text' );
		$this->register_string_meta( self::PROMO_POST_TYPE, 'gloskin_promo_end_date', 'text' );
		$this->register_string_meta( self::PROMO_POST_TYPE, 'gloskin_promo_active', 'text' );
		$this->register_string_meta( self::PROMO_POST_TYPE, 'gloskin_promo_order', 'text' );

		/* gloskin_testimonial managed fields */
		$this->register_string_meta( self::TESTIMONIAL_POST_TYPE, 'gloskin_testimonial_attribution', 'text' );
		$this->register_string_meta( self::TESTIMONIAL_POST_TYPE, 'gloskin_testimonial_subtitle', 'text' );
		$this->register_string_meta( self::TESTIMONIAL_POST_TYPE, 'gloskin_testimonial_active', 'text' );
		$this->register_string_meta( self::TESTIMONIAL_POST_TYPE, 'gloskin_testimonial_source_note', 'textarea' );
		$this->register_string_meta( self::TESTIMONIAL_POST_TYPE, 'gloskin_testimonial_order', 'text' );

		/* gloskin_achievement managed fields */
		$this->register_string_meta( self::ACHIEVEMENT_POST_TYPE, 'gloskin_achievement_issuer', 'text' );
		$this->register_string_meta( self::ACHIEVEMENT_POST_TYPE, 'gloskin_achievement_year', 'text' );
		$this->register_string_meta( self::ACHIEVEMENT_POST_TYPE, 'gloskin_achievement_source_url', 'http_url' );
		$this->register_string_meta( self::ACHIEVEMENT_POST_TYPE, 'gloskin_achievement_feature_on_home', 'text' );
		$this->register_string_meta( self::ACHIEVEMENT_POST_TYPE, 'gloskin_achievement_active', 'text' );
		$this->register_string_meta( self::ACHIEVEMENT_POST_TYPE, 'gloskin_achievement_order', 'text' );

		$this->register_string_meta( self::TREATMENT_POST_TYPE, 'gloskin_summary', 'textarea' );
		$this->register_string_meta( self::TREATMENT_POST_TYPE, 'gloskin_treatment_feature_on_home', 'text' );
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
		$this->register_string_meta( 'page', 'gloskin_about_founder_name', 'text' );
		$this->register_string_meta( 'page', 'gloskin_about_founder_role', 'text' );
		$this->register_string_meta( 'page', 'gloskin_about_founder_story', 'rich' );
		$this->register_attachment_id_meta( 'page', 'gloskin_about_founder_media_id' );
		$this->register_string_meta( 'page', 'gloskin_hero_heading', 'text' );
		$this->register_string_meta( 'page', 'gloskin_hero_copy', 'textarea' );
		$this->register_string_meta( 'page', 'gloskin_hero_cta_label', 'text' );
		$this->register_string_meta( 'page', 'gloskin_hero_cta_url', 'action_url' );
		$this->register_attachment_id_meta( 'page', 'gloskin_hero_media_id' );
		/* Why Gloskin editor-manageable meta (home page only; template falls back to defaults) */
		$this->register_string_meta( 'page', 'gloskin_why_heading', 'text' );
		$this->register_string_meta( 'page', 'gloskin_why_lead', 'textarea' );
		$this->register_string_meta( 'page', 'gloskin_why_primary_title', 'text' );
		$this->register_string_meta( 'page', 'gloskin_why_primary_copy', 'textarea' );
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
