<?php
/**
 * Lightweight companion English translation registry, storage and admin console.
 *
 * Canonical Indonesian content is never mutated. English is stored only in
 * companion post/term meta or the interface option map.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Gloskin_Site_Core_Translation {
	const POST_META_KEY   = '_gloskin_translation_en';
	const TERM_META_KEY   = '_gloskin_translation_en';
	const INTERFACE_OPTION = 'gloskin_translation_en_interface';
	const ADMIN_SLUG      = 'gloskin-translation';
	const CAPABILITY      = 'manage_options';
	const AJAX_SAVE       = 'gloskin_translation_save';
	const NONCE_ACTION    = 'gloskin_translation_save';

	/** @var string */
	private $plugin_file;

	/** @var string */
	private $version;

	/** @var string */
	private $admin_hook = '';

	/**
	 * @param string $plugin_file Main plugin file.
	 * @param string $version Runtime version.
	 */
	public function __construct( $plugin_file, $version ) {
		$this->plugin_file = (string) $plugin_file;
		$this->version     = (string) $version;
	}

	/** Register the admin-only surface. */
	public function register_admin() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 10 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ), 30 );
		add_action( 'wp_ajax_' . self::AJAX_SAVE, array( $this, 'ajax_save' ) );
	}

	/** @return array<string,mixed> */
	public static function registry() {
		$base = array( 'post_title' => 'Title', 'post_excerpt' => 'Excerpt', 'post_content' => 'Content' );
		return array(
			'post_types' => array(
				'page' => array( 'label' => 'Page', 'fields' => $base, 'meta' => array() ),
				Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE => array(
					'label' => 'Treatment', 'fields' => $base,
					'meta' => array(
						'gloskin_summary' => array( 'label' => 'Summary', 'rich' => false ),
						'gloskin_benefits' => array( 'label' => 'Benefits', 'rich' => true ),
						'gloskin_contraindications' => array( 'label' => 'Contraindications', 'rich' => true ),
					),
				),
				Gloskin_Site_Core_Content_Service::CLINIC_POST_TYPE => array(
					'label' => 'Clinic', 'fields' => $base,
					'meta' => array(
						'gloskin_address' => array( 'label' => 'Address', 'rich' => false ),
						'gloskin_whatsapp_message' => array( 'label' => 'WhatsApp message', 'rich' => false ),
						'gloskin_operating_hours' => array( 'label' => 'Operating hours', 'rich' => false ),
						'gloskin_short_location' => array( 'label' => 'Short location', 'rich' => false ),
					),
				),
				Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE => array(
					'label' => 'Doctor', 'fields' => $base,
					'meta' => array(
						'gloskin_degree_title' => array( 'label' => 'Degree', 'rich' => false ),
						'gloskin_specialization' => array( 'label' => 'Specialization', 'rich' => false ),
					),
				),
				Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE => array(
					'label' => 'Promo', 'fields' => $base,
					'meta' => array(
						'gloskin_promo_eyebrow' => array( 'label' => 'Eyebrow', 'rich' => false ),
						'gloskin_promo_summary' => array( 'label' => 'Summary', 'rich' => false ),
						'gloskin_promo_cta_label' => array( 'label' => 'CTA label', 'rich' => false ),
					),
				),
				Gloskin_Site_Core_Content_Service::TESTIMONIAL_POST_TYPE => array(
					'label' => 'Testimonial', 'fields' => $base,
					'meta' => array(
						'gloskin_testimonial_attribution' => array( 'label' => 'Attribution', 'rich' => false ),
						'gloskin_testimonial_subtitle' => array( 'label' => 'Subtitle', 'rich' => false ),
						'gloskin_testimonial_source_note' => array( 'label' => 'Source note', 'rich' => false ),
					),
				),
				Gloskin_Site_Core_Content_Service::ACHIEVEMENT_POST_TYPE => array(
					'label' => 'Achievement', 'fields' => $base,
					'meta' => array( 'gloskin_achievement_issuer' => array( 'label' => 'Issuer', 'rich' => false ) ),
				),
				'product' => array( 'label' => 'Product', 'fields' => $base, 'meta' => array() ),
				Gloskin_Site_Core_Content_Service::QUESTION_POST_TYPE => array(
					'label' => 'Consultation',
					'fields' => array( 'post_title' => 'Question' ),
					'meta' => array(),
				),
			),
			'taxonomies' => array(
				'product_cat' => array( 'label' => 'Product category', 'fields' => array( 'name' => 'Name', 'description' => 'Description' ) ),
				Gloskin_Site_Core_Content_Service::CONCERN_TAXONOMY => array( 'label' => 'Concern', 'fields' => array( 'name' => 'Name', 'description' => 'Description' ) ),
				Gloskin_Site_Core_Content_Service::CONSULTATION_TAXONOMY => array( 'label' => 'Consultation path', 'fields' => array( 'name' => 'Name', 'description' => 'Description' ) ),
			),
			'interface' => self::interface_registry(),
		);
	}

	/**
	 * Small Gloskin-owned frontend interface registry. Default EN values are
	 * presentation defaults and can be overridden by the saved option map.
	 *
	 * @return array<string,array{source:string,en:string}>
	 */
	public static function interface_registry() {
		$pairs = array(
			'home' => array( 'Beranda', 'Home' ),
			'about' => array( 'Tentang Kami', 'About Us' ),
			'treatments' => array( 'Perawatan', 'Treatments' ),
			'promos' => array( 'Promo', 'Promotions' ),
			'clinics' => array( 'Klinik', 'Clinics' ),
			'doctors' => array( 'Dokter', 'Doctors' ),
			'contact' => array( 'Hubungi Kami', 'Contact Us' ),
			'featured_treatment' => array( 'Treatment Unggulan', 'Featured Treatments' ),
			'limited_promo' => array( 'Promo Terbatas', 'Limited Promotion' ),
			'promo_poster' => array( 'Promo Poster', 'Promotion Posters' ),
			'why_gloskin' => array( 'Kenapa Memilih GLOSKIN', 'Why Choose GLOSKIN' ),
			'why_discovery' => array( 'Temukan pilihan perawatan berdasarkan keluhan dan kondisi kulit — bukan label generik.', 'Explore treatment options based on concerns and skin condition rather than generic labels.' ),
			'why_ecosystem' => array( 'Perawatan klinik dan produk skincare Gloskin dirancang dalam satu ekosistem yang saling melengkapi.', 'Gloskin clinic treatments and skincare products are designed as one complementary ecosystem.' ),
			'why_doctors' => array( 'Tim dokter Gloskin tersedia di jaringan klinik untuk konsultasi dan perencanaan perawatan.', 'Gloskin doctors are available across the clinic network for consultation and treatment planning.' ),
			'testimonials' => array( 'Testimoni', 'Testimonials' ),
			'certificates' => array( 'Piagam', 'Certificates' ),
			'about_gloskin' => array( 'Tentang GLOSKIN', 'About GLOSKIN' ),
			'principles' => array( 'Visi · Misi · Nilai', 'Vision · Mission · Values' ),
			'choose_focus' => array( 'Pilih fokus utama Anda', 'Choose your main focus' ),
			'view_all_products' => array( 'Lihat Semua Produk', 'View All Products' ),
			'all_products' => array( 'Semua Produk', 'All Products' ),
			'search' => array( 'Pencarian', 'Search' ),
			'price' => array( 'Harga', 'Price' ),
			'category' => array( 'Kategori', 'Category' ),
			'story' => array( 'Cerita Gloskin', 'Gloskin Story' ),
			'founder' => array( 'Pendiri', 'Founder' ),
			'vision' => array( 'Visi', 'Vision' ),
			'mission' => array( 'Misi', 'Mission' ),
			'values' => array( 'Nilai', 'Values' ),
			'doctor_team' => array( 'Tim Dokter', 'Doctor Team' ),
			'clinic_network' => array( 'Jaringan Klinik', 'Clinic Network' ),
			'next_step' => array( 'Langkah Berikutnya', 'Next Step' ),
			'choose_clinic' => array( 'Pilih Klinik', 'Choose Clinic' ),
		);
		$out = array();
		foreach ( $pairs as $key => $pair ) {
			$out[ $key ] = array( 'source' => $pair[0], 'en' => $pair[1] );
		}
		return $out;
	}

	/** @return void */
	public function register_menu() {
		$hook = add_submenu_page(
			Gloskin_Site_Core_Content_Service::ADMIN_MENU_SLUG,
			__( 'Translation', 'gloskin-site-core' ),
			__( 'Translation', 'gloskin-site-core' ),
			self::CAPABILITY,
			self::ADMIN_SLUG,
			array( $this, 'render_admin_page' )
		);
		if ( is_string( $hook ) ) {
			$this->admin_hook = $hook;
		}
	}

	/** @param string $hook Current admin hook. */
	public function enqueue_admin_assets( $hook ) {
		if ( '' === $this->admin_hook || $hook !== $this->admin_hook ) {
			return;
		}
		$base = plugin_dir_url( $this->plugin_file );
		wp_enqueue_style( 'gloskin-translation-admin', $base . 'assets/css/gloskin-translation-admin.css', array(), $this->version );
		wp_enqueue_script( 'gloskin-translation-admin', $base . 'assets/js/gloskin-translation-admin.js', array(), $this->version, true );
		wp_localize_script(
			'gloskin-translation-admin',
			'GloskinTranslationAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( self::NONCE_ACTION ),
				'action' => self::AJAX_SAVE,
				'workerUrl' => $base . 'assets/js/gloskin-translation-worker.js?ver=' . rawurlencode( $this->version ),
				'records' => $this->records(),
				'protectedTerms' => $this->protected_terms(),
			)
		);
	}

	/** @return void */
	public function render_admin_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to manage translations.', 'gloskin-site-core' ), '', array( 'response' => 403 ) );
		}
		?>
		<div class="wrap gloskin-translation" data-gloskin-translation-root>
			<h1><?php echo esc_html__( 'Translation', 'gloskin-site-core' ); ?></h1>
			<p class="description"><?php echo esc_html__( 'English is stored beside canonical Indonesian content. Generate Missing never overwrites populated English fields.', 'gloskin-site-core' ); ?></p>
			<div class="gloskin-translation__controls">
				<input type="search" data-translation-search placeholder="<?php echo esc_attr__( 'Search…', 'gloskin-site-core' ); ?>">
				<select data-translation-type><option value=""><?php echo esc_html__( 'All types', 'gloskin-site-core' ); ?></option></select>
				<label><input type="checkbox" data-translation-missing> <?php echo esc_html__( 'Only missing', 'gloskin-site-core' ); ?></label>
				<button type="button" class="button button-primary" data-translation-generate><?php echo esc_html__( 'Generate Missing', 'gloskin-site-core' ); ?></button>
			</div>
			<p class="gloskin-translation__status" data-translation-status role="status" aria-live="polite"></p>
			<table class="widefat striped gloskin-translation__table">
				<thead><tr><th><?php echo esc_html__( 'Type', 'gloskin-site-core' ); ?></th><th><?php echo esc_html__( 'Record', 'gloskin-site-core' ); ?></th><th><?php echo esc_html__( 'EN', 'gloskin-site-core' ); ?></th></tr></thead>
				<tbody data-translation-rows></tbody>
			</table>
			<section class="gloskin-translation__editor" data-translation-editor hidden></section>
		</div>
		<?php
	}

	/**
	 * Discover dynamic records while field definitions remain explicit.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function records() {
		$registry = self::registry();
		$records  = array();
		foreach ( $registry['post_types'] as $post_type => $definition ) {
			if ( ! post_type_exists( $post_type ) ) {
				continue;
			}
			$posts = get_posts( array(
				'post_type' => $post_type,
				'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => -1,
				'orderby' => 'title',
				'order' => 'ASC',
				'suppress_filters' => true,
			) );
			foreach ( $posts as $post ) {
				$fields = array();
				$saved  = self::post_translations( $post->ID );
				foreach ( $definition['fields'] as $field => $label ) {
					$source = isset( $post->$field ) ? (string) $post->$field : '';
					if ( '' === trim( wp_strip_all_tags( $source ) ) ) { continue; }
					$fields[] = $this->field_payload( $field, $label, $source, isset( $saved[ $field ] ) ? $saved[ $field ] : '', 'post_content' === $field );
				}
				foreach ( $definition['meta'] as $field => $meta_definition ) {
					$source = (string) get_post_meta( $post->ID, $field, true );
					if ( '' === trim( wp_strip_all_tags( $source ) ) ) { continue; }
					$fields[] = $this->field_payload( $field, $meta_definition['label'], $source, isset( $saved[ $field ] ) ? $saved[ $field ] : '', ! empty( $meta_definition['rich'] ) );
				}
				if ( Gloskin_Site_Core_Content_Service::QUESTION_POST_TYPE === $post_type ) {
					$answers = get_post_meta( $post->ID, Gloskin_Site_Core_Content_Service::ANSWER_META_KEY, true );
					if ( is_array( $answers ) ) {
						foreach ( $answers as $index => $answer ) {
							if ( ! is_array( $answer ) || empty( $answer['label'] ) ) { continue; }
							$key = 'answer_label_' . absint( $index );
							$fields[] = $this->field_payload( $key, sprintf( 'Answer %d', absint( $index ) + 1 ), (string) $answer['label'], isset( $saved[ $key ] ) ? $saved[ $key ] : '', false );
						}
					}
				}
				if ( $fields ) {
					$records[] = $this->record_payload( 'post', $post->ID, $definition['label'], (string) $post->post_title, $fields );
				}
			}
		}
		foreach ( $registry['taxonomies'] as $taxonomy => $definition ) {
			if ( ! taxonomy_exists( $taxonomy ) ) { continue; }
			$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
			if ( is_wp_error( $terms ) ) { continue; }
			foreach ( $terms as $term ) {
				$saved = self::term_translations( $term->term_id );
				$fields = array();
				foreach ( $definition['fields'] as $field => $label ) {
					$source = isset( $term->$field ) ? (string) $term->$field : '';
					if ( '' === trim( wp_strip_all_tags( $source ) ) ) { continue; }
					$fields[] = $this->field_payload( $field, $label, $source, isset( $saved[ $field ] ) ? $saved[ $field ] : '', 'description' === $field );
				}
				if ( $fields ) { $records[] = $this->record_payload( 'term', $term->term_id, $definition['label'], $term->name, $fields, $taxonomy ); }
			}
		}
		$saved_interface = self::interface_translations();
		foreach ( $registry['interface'] as $key => $entry ) {
			$en = isset( $saved_interface[ $key ] ) && '' !== trim( (string) $saved_interface[ $key ] ) ? (string) $saved_interface[ $key ] : (string) $entry['en'];
			$records[] = $this->record_payload( 'interface', $key, 'Interface', $entry['source'], array( $this->field_payload( 'text', 'Text', $entry['source'], $en, false ) ) );
		}
		return $records;
	}

	/** @return array<string,string> */
	private function protected_terms() {
		$terms = array( 'Gloskin', 'Skinvive', 'Botox' );
		foreach ( array( Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE, 'product' ) as $type ) {
			if ( ! post_type_exists( $type ) ) { continue; }
			$names = get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'suppress_filters' => true ) );
			foreach ( $names as $id ) {
				$name = get_post_field( 'post_title', $id, 'raw' );
				if ( is_string( $name ) && '' !== trim( $name ) ) { $terms[] = trim( $name ); }
			}
		}
		return array_values( array_unique( $terms ) );
	}

	/** @return array<string,string> */
	public static function post_translations( $post_id ) {
		$value = get_post_meta( absint( $post_id ), self::POST_META_KEY, true );
		return is_array( $value ) ? array_map( 'strval', $value ) : array();
	}

	/** @return array<string,string> */
	public static function term_translations( $term_id ) {
		$value = get_term_meta( absint( $term_id ), self::TERM_META_KEY, true );
		return is_array( $value ) ? array_map( 'strval', $value ) : array();
	}

	/** @return array<string,string> */
	public static function interface_translations() {
		$value = get_option( self::INTERFACE_OPTION, array() );
		return is_array( $value ) ? array_map( 'strval', $value ) : array();
	}

	/** @return void */
	public function ajax_save() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => 'Forbidden.' ), 403 );
		}
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		$entity = isset( $_POST['entity'] ) ? sanitize_key( wp_unslash( $_POST['entity'] ) ) : '';
		$field  = isset( $_POST['field'] ) ? sanitize_key( wp_unslash( $_POST['field'] ) ) : '';
		$id_raw = isset( $_POST['entity_id'] ) ? wp_unslash( $_POST['entity_id'] ) : '';
		$value  = isset( $_POST['value'] ) ? wp_unslash( $_POST['value'] ) : '';
		if ( ! is_string( $value ) || ! $this->field_is_allowed( $entity, $id_raw, $field ) ) {
			wp_send_json_error( array( 'message' => 'Invalid translation target.' ), 400 );
		}
		$value = $this->sanitize_translation( $entity, $field, $value );
		if ( 'post' === $entity ) {
			$id = absint( $id_raw );
			$translations = self::post_translations( $id );
			$translations[ $field ] = $value;
			update_post_meta( $id, self::POST_META_KEY, $translations );
		} elseif ( 'term' === $entity ) {
			$id = absint( $id_raw );
			$translations = self::term_translations( $id );
			$translations[ $field ] = $value;
			update_term_meta( $id, self::TERM_META_KEY, $translations );
		} else {
			$key = sanitize_key( (string) $id_raw );
			$translations = self::interface_translations();
			$translations[ $key ] = $value;
			update_option( self::INTERFACE_OPTION, $translations, false );
		}
		wp_send_json_success( array( 'value' => $value ) );
	}

	/** @return bool */
	private function field_is_allowed( $entity, $id_raw, $field ) {
		$registry = self::registry();
		if ( 'post' === $entity ) {
			$id = absint( $id_raw );
			$post = $id ? get_post( $id ) : null;
			if ( ! $post || ! isset( $registry['post_types'][ $post->post_type ] ) ) { return false; }
			$definition = $registry['post_types'][ $post->post_type ];
			if ( isset( $definition['fields'][ $field ] ) || isset( $definition['meta'][ $field ] ) ) { return true; }
			if ( Gloskin_Site_Core_Content_Service::QUESTION_POST_TYPE === $post->post_type && 0 === strpos( $field, 'answer_label_' ) ) {
				$index = absint( substr( $field, strlen( 'answer_label_' ) ) );
				$answers = get_post_meta( $id, Gloskin_Site_Core_Content_Service::ANSWER_META_KEY, true );
				return is_array( $answers ) && isset( $answers[ $index ]['label'] );
			}
			return false;
		}
		if ( 'term' === $entity ) {
			$id = absint( $id_raw );
			$term = $id ? get_term( $id ) : null;
			return $term && ! is_wp_error( $term ) && isset( $registry['taxonomies'][ $term->taxonomy ]['fields'][ $field ] );
		}
		if ( 'interface' === $entity && 'text' === $field ) {
			return isset( $registry['interface'][ sanitize_key( (string) $id_raw ) ] );
		}
		return false;
	}

	/** @return string */
	private function sanitize_translation( $entity, $field, $value ) {
		if ( 'post_content' === $field || 'description' === $field || in_array( $field, array( 'gloskin_benefits', 'gloskin_contraindications' ), true ) ) {
			return wp_kses_post( $value );
		}
		if ( 'post_excerpt' === $field || false !== strpos( $field, 'summary' ) || false !== strpos( $field, 'source_note' ) || false !== strpos( $field, 'address' ) || false !== strpos( $field, 'hours' ) ) {
			return sanitize_textarea_field( $value );
		}
		return sanitize_text_field( $value );
	}

	/** @return array<string,mixed> */
	private function field_payload( $key, $label, $source, $en, $rich ) {
		return array( 'key' => (string) $key, 'label' => (string) $label, 'source' => (string) $source, 'en' => (string) $en, 'rich' => (bool) $rich );
	}

	/** @return array<string,mixed> */
	private function record_payload( $entity, $entity_id, $type, $label, $fields, $taxonomy = '' ) {
		$filled = 0;
		foreach ( $fields as $field ) { if ( '' !== trim( (string) $field['en'] ) ) { ++$filled; } }
		return array(
			'key' => $entity . ':' . (string) $entity_id . ( $taxonomy ? ':' . $taxonomy : '' ),
			'entity' => $entity,
			'entityId' => (string) $entity_id,
			'taxonomy' => (string) $taxonomy,
			'type' => (string) $type,
			'label' => (string) $label,
			'filled' => $filled,
			'total' => count( $fields ),
			'fields' => array_values( $fields ),
		);
	}
}
