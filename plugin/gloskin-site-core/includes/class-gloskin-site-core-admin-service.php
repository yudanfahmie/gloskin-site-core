<?php
/**
 * Minimal native WordPress admin experience for Gloskin-owned data.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Gloskin_Site_Core_Admin_Service {
	const META_NONCE_ACTION = 'gloskin_site_core_save_meta';
	const META_NONCE_NAME   = 'gloskin_site_core_meta_nonce';
	const SETTINGS_OPTION   = 'gloskin_site_core_settings';

	/** @var Gloskin_Site_Core_Content_Service */
	private $content;

	/**
	 * @param Gloskin_Site_Core_Content_Service $content Content owner.
	 */
	public function __construct( $content ) {
		$this->content = $content;
	}

	/**
	 * @return void
	 */
	public function register() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_meta' ), 20, 2 );
		add_action( 'admin_notices', array( $this, 'content_readiness_notice' ) );
	}

	/**
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'gloskin_site_core',
			self::SETTINGS_OPTION,
			array(
				'type'              => 'array',
				'default'           => array(
					'design_variant' => 'medical',
					'form_shortcode' => '',
				),
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * @return void
	 */
	public function register_settings_page() {
		add_options_page(
			__( 'Gloskin Site Core', 'gloskin-site-core' ),
			__( 'Gloskin Site Core', 'gloskin-site-core' ),
			'manage_options',
			'gloskin-site-core',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * @param mixed $value Raw settings.
	 * @return array<string,string>
	 */
	public function sanitize_settings( $value ) {
		$value   = is_array( $value ) ? $value : array();
		$variant = isset( $value['design_variant'] ) ? sanitize_key( $value['design_variant'] ) : 'medical';

		return array(
			'design_variant' => in_array( $variant, array( 'medical', 'modern', 'luxury' ), true ) ? $variant : 'medical',
			'form_shortcode' => isset( $value['form_shortcode'] ) ? sanitize_text_field( $value['form_shortcode'] ) : '',
		);
	}

	/**
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = get_option(
			self::SETTINGS_OPTION,
			array( 'design_variant' => 'medical', 'form_shortcode' => '' )
		);
		$variant   = isset( $settings['design_variant'] ) ? $settings['design_variant'] : 'medical';
		$shortcode = isset( $settings['form_shortcode'] ) ? $settings['form_shortcode'] : '';
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Gloskin Site Core', 'gloskin-site-core' ); ?></h1>
			<p><?php echo esc_html__( 'Configure only the small set of global presentation integrations owned by Gloskin.', 'gloskin-site-core' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'gloskin_site_core' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="gloskin-design-variant"><?php echo esc_html__( 'Design direction', 'gloskin-site-core' ); ?></label></th>
						<td>
							<select id="gloskin-design-variant" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[design_variant]">
								<option value="medical" <?php selected( $variant, 'medical' ); ?>><?php echo esc_html__( 'Medical Professional', 'gloskin-site-core' ); ?></option>
								<option value="modern" <?php selected( $variant, 'modern' ); ?>><?php echo esc_html__( 'Modern Aesthetic', 'gloskin-site-core' ); ?></option>
								<option value="luxury" <?php selected( $variant, 'luxury' ); ?>><?php echo esc_html__( 'Premium Luxury', 'gloskin-site-core' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="gloskin-form-shortcode"><?php echo esc_html__( 'Contact form shortcode', 'gloskin-site-core' ); ?></label></th>
						<td>
							<input class="regular-text" id="gloskin-form-shortcode" type="text" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[form_shortcode]" value="<?php echo esc_attr( $shortcode ); ?>" placeholder="[contact-form-7 id=&quot;...&quot;]" />
							<p class="description"><?php echo esc_html__( 'The form provider continues to own submission, anti-spam, storage, confirmation and mail delivery.', 'gloskin-site-core' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * @return void
	 */
	public function register_meta_boxes() {
		add_meta_box(
			'gloskin-treatment-details',
			__( 'Treatment Details', 'gloskin-site-core' ),
			array( $this, 'render_treatment_meta_box' ),
			Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE,
			'normal',
			'default'
		);

		add_meta_box(
			'gloskin-clinic-details',
			__( 'Clinic Details', 'gloskin-site-core' ),
			array( $this, 'render_clinic_meta_box' ),
			Gloskin_Site_Core_Content_Service::CLINIC_POST_TYPE,
			'normal',
			'default'
		);

		add_meta_box(
			'gloskin-doctor-details',
			__( 'Doctor Details', 'gloskin-site-core' ),
			array( $this, 'render_doctor_meta_box' ),
			Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE,
			'normal',
			'default'
		);

		add_meta_box(
			'gloskin-page-details',
			__( 'Gloskin Page Settings', 'gloskin-site-core' ),
			array( $this, 'render_page_meta_box' ),
			'page',
			'normal',
			'default'
		);
	}

	/**
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public function render_treatment_meta_box( $post ) {
		$this->nonce();
		$this->textarea_field( $post, 'gloskin_summary', __( 'Summary', 'gloskin-site-core' ) );
		$this->textarea_field( $post, 'gloskin_benefits', __( 'Benefits', 'gloskin-site-core' ), 8 );
		$this->textarea_field( $post, 'gloskin_contraindications', __( 'Contraindications', 'gloskin-site-core' ), 8 );
		$this->url_field( $post, 'gloskin_booking_target', __( 'Booking target', 'gloskin-site-core' ) );
		$this->relationship_field(
			$post,
			'gloskin_clinic_ids',
			Gloskin_Site_Core_Content_Service::CLINIC_POST_TYPE,
			__( 'Related clinics', 'gloskin-site-core' )
		);
		$this->relationship_field(
			$post,
			'gloskin_doctor_ids',
			Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE,
			__( 'Related doctors', 'gloskin-site-core' )
		);
	}

	/**
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public function render_clinic_meta_box( $post ) {
		$this->nonce();
		$this->textarea_field( $post, 'gloskin_address', __( 'Address', 'gloskin-site-core' ) );
		$this->text_field( $post, 'gloskin_short_location', __( 'Short location', 'gloskin-site-core' ) );
		$this->text_field( $post, 'gloskin_phone_display', __( 'Phone display', 'gloskin-site-core' ) );
		$this->text_field( $post, 'gloskin_phone_uri', __( 'Phone link value', 'gloskin-site-core' ) );
		$this->text_field( $post, 'gloskin_whatsapp_number', __( 'WhatsApp number', 'gloskin-site-core' ) );
		$this->text_field( $post, 'gloskin_whatsapp_message', __( 'WhatsApp prefilled message', 'gloskin-site-core' ) );
		$this->textarea_field( $post, 'gloskin_operating_hours', __( 'Operating hours', 'gloskin-site-core' ), 5 );
		$this->url_field( $post, 'gloskin_map_url', __( 'Google Maps URL', 'gloskin-site-core' ) );
		$this->url_field( $post, 'gloskin_map_embed', __( 'Google Maps embed URL', 'gloskin-site-core' ) );

		$value = get_post_meta( $post->ID, 'gloskin_gallery_image_ids', true );
		$value = is_array( $value ) ? implode( ',', array_map( 'absint', $value ) ) : '';
		echo '<p><label for="gloskin-gallery-ids"><strong>' . esc_html__( 'Gallery image IDs', 'gloskin-site-core' ) . '</strong></label></p>';
		echo '<div class="gloskin-admin-media-row">';
		echo '<input class="widefat" id="gloskin-gallery-ids" name="gloskin_gallery_image_ids" value="' . esc_attr( $value ) . '" />';
		echo '<button type="button" class="button" data-gloskin-media-picker data-target="#gloskin-gallery-ids" data-multiple="true">'
			. esc_html__( 'Choose images', 'gloskin-site-core' )
			. '</button>';
		echo '</div>';
		echo '<p class="description">' . esc_html__( 'Uses WordPress Media Library attachment IDs. Missing gallery media is allowed.', 'gloskin-site-core' ) . '</p>';
	}

	/**
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public function render_doctor_meta_box( $post ) {
		$this->nonce();
		$this->text_field( $post, 'gloskin_degree_title', __( 'Degree / title', 'gloskin-site-core' ) );
		$this->text_field( $post, 'gloskin_specialization', __( 'Specialization', 'gloskin-site-core' ) );
		$this->text_field( $post, 'gloskin_sip_number', __( 'SIP number', 'gloskin-site-core' ) );
		$this->textarea_field( $post, 'gloskin_credentials', __( 'Credentials', 'gloskin-site-core' ), 6 );
		$this->textarea_field( $post, 'gloskin_profile', __( 'Profile', 'gloskin-site-core' ), 8 );
		$this->textarea_field( $post, 'gloskin_schedule', __( 'Schedule', 'gloskin-site-core' ), 5 );
		$this->url_field( $post, 'gloskin_booking_target', __( 'Booking target', 'gloskin-site-core' ) );
		$this->relationship_field(
			$post,
			'gloskin_branch_ids',
			Gloskin_Site_Core_Content_Service::CLINIC_POST_TYPE,
			__( 'Practice branches', 'gloskin-site-core' )
		);
		echo '<p class="description">' . esc_html__( 'Use the standard Featured Image panel for the approved doctor portrait.', 'gloskin-site-core' ) . '</p>';
	}

	/**
	 * @param WP_Post $post Current page.
	 * @return void
	 */
	public function render_page_meta_box( $post ) {
		$this->nonce();
		$this->text_field( $post, 'gloskin_hero_heading', __( 'Hero heading', 'gloskin-site-core' ) );
		$this->textarea_field( $post, 'gloskin_hero_copy', __( 'Hero copy', 'gloskin-site-core' ), 4 );
		$this->text_field( $post, 'gloskin_hero_cta_label', __( 'Hero CTA label', 'gloskin-site-core' ) );
		$this->url_field( $post, 'gloskin_hero_cta_url', __( 'Hero CTA URL', 'gloskin-site-core' ) );

		$media_id = absint( get_post_meta( $post->ID, 'gloskin_hero_media_id', true ) );
		echo '<p><label for="gloskin-hero-media-id"><strong>' . esc_html__( 'Hero media image ID', 'gloskin-site-core' ) . '</strong></label></p>';
		echo '<div class="gloskin-admin-media-row">';
		echo '<input class="widefat" id="gloskin-hero-media-id" name="gloskin_hero_media_id" value="' . esc_attr( $media_id ? (string) $media_id : '' ) . '" />';
		echo '<button type="button" class="button" data-gloskin-media-picker data-target="#gloskin-hero-media-id" data-multiple="false">'
			. esc_html__( 'Choose image', 'gloskin-site-core' )
			. '</button>';
		echo '</div>';

		if ( 'about' === $post->post_name ) {
			$this->textarea_field( $post, 'gloskin_about_vision', __( 'Vision', 'gloskin-site-core' ), 5 );
			$this->textarea_field( $post, 'gloskin_about_mission', __( 'Mission', 'gloskin-site-core' ), 5 );
			$this->textarea_field( $post, 'gloskin_about_values', __( 'Values', 'gloskin-site-core' ), 5 );
		}

		$parent = $post->post_parent ? get_post( $post->post_parent ) : null;
		if ( $parent instanceof WP_Post && 'skincare' === $parent->post_name ) {
			$this->text_field( $post, 'gloskin_woo_category_slug', __( 'WooCommerce category slug', 'gloskin-site-core' ) );
		}
	}

	/**
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function save_meta( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST[ self::META_NONCE_NAME ] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::META_NONCE_NAME ] ) );
		if ( ! wp_verify_nonce( $nonce, self::META_NONCE_ACTION ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$schemas = $this->save_schema();
		if ( ! isset( $schemas[ $post->post_type ] ) ) {
			return;
		}

		foreach ( $schemas[ $post->post_type ]['strings'] as $key ) {
			$value = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
			update_post_meta( $post_id, $key, $value );
		}

		foreach ( $schemas[ $post->post_type ]['arrays'] as $key ) {
			$value = isset( $_POST[ $key ] ) ? (array) wp_unslash( $_POST[ $key ] ) : array();
			if ( 'gloskin_gallery_image_ids' === $key ) {
				$value = isset( $_POST[ $key ] ) ? explode( ',', (string) wp_unslash( $_POST[ $key ] ) ) : array();
			}
			update_post_meta( $post_id, $key, $value );
		}

		foreach ( $schemas[ $post->post_type ]['integers'] as $key ) {
			$value = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : 0;
			update_post_meta( $post_id, $key, $value );
		}
	}

	/**
	 * @return array<string,array<string,array<int,string>>>
	 */
	private function save_schema() {
		return array(
			Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE => array(
				'strings'  => array( 'gloskin_summary', 'gloskin_benefits', 'gloskin_contraindications', 'gloskin_booking_target' ),
				'arrays'   => array( 'gloskin_clinic_ids', 'gloskin_doctor_ids' ),
				'integers' => array(),
			),
			Gloskin_Site_Core_Content_Service::CLINIC_POST_TYPE => array(
				'strings'  => array(
					'gloskin_address',
					'gloskin_phone_display',
					'gloskin_phone_uri',
					'gloskin_whatsapp_number',
					'gloskin_whatsapp_message',
					'gloskin_operating_hours',
					'gloskin_map_url',
					'gloskin_map_embed',
					'gloskin_short_location',
				),
				'arrays'   => array( 'gloskin_gallery_image_ids' ),
				'integers' => array(),
			),
			Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE => array(
				'strings'  => array(
					'gloskin_degree_title',
					'gloskin_specialization',
					'gloskin_sip_number',
					'gloskin_credentials',
					'gloskin_profile',
					'gloskin_schedule',
					'gloskin_booking_target',
				),
				'arrays'   => array( 'gloskin_branch_ids' ),
				'integers' => array(),
			),
			'page' => array(
				'strings'  => array(
					'gloskin_woo_category_slug',
					'gloskin_about_vision',
					'gloskin_about_mission',
					'gloskin_about_values',
					'gloskin_hero_heading',
					'gloskin_hero_copy',
					'gloskin_hero_cta_label',
					'gloskin_hero_cta_url',
				),
				'arrays'   => array(),
				'integers' => array( 'gloskin_hero_media_id' ),
			),
		);
	}

	/**
	 * @return void
	 */
	public function content_readiness_notice() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->base, array( 'dashboard', 'edit', 'post', 'settings_page_gloskin-site-core' ), true ) ) {
			return;
		}

		$targets = Gloskin_Site_Core_Content_Service::record_targets();
		$parts   = array();
		foreach ( $targets as $post_type => $target ) {
			$count = wp_count_posts( $post_type );
			$live  = $count && isset( $count->publish ) ? absint( $count->publish ) : 0;
			if ( $live < $target ) {
				$parts[] = sprintf(
					/* translators: 1: post type label, 2: live count, 3: target count */
					__( '%1$s: %2$d/%3$d approved records published', 'gloskin-site-core' ),
					$post_type,
					$live,
					$target
				);
			}
		}

		if ( ! $parts ) {
			return;
		}

		echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'Gloskin content readiness:', 'gloskin-site-core' ) . '</strong> '
			. esc_html( implode( '; ', $parts ) )
			. '. '
			. esc_html__( 'Publish only client-approved facts; do not create placeholder medical or doctor data.', 'gloskin-site-core' )
			. '</p></div>';
	}

	/**
	 * @return void
	 */
	private function nonce() {
		wp_nonce_field( self::META_NONCE_ACTION, self::META_NONCE_NAME );
	}

	/**
	 * @param WP_Post $post Post.
	 * @param string  $key Meta key.
	 * @param string  $label Label.
	 * @return void
	 */
	private function text_field( $post, $key, $label ) {
		$value = (string) get_post_meta( $post->ID, $key, true );
		echo '<p><label for="' . esc_attr( $key ) . '"><strong>' . esc_html( $label ) . '</strong></label></p>';
		echo '<input class="widefat" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />';
	}

	/**
	 * @param WP_Post $post Post.
	 * @param string  $key Meta key.
	 * @param string  $label Label.
	 * @param int     $rows Rows.
	 * @return void
	 */
	private function textarea_field( $post, $key, $label, $rows = 4 ) {
		$value = (string) get_post_meta( $post->ID, $key, true );
		echo '<p><label for="' . esc_attr( $key ) . '"><strong>' . esc_html( $label ) . '</strong></label></p>';
		echo '<textarea class="widefat" rows="' . esc_attr( (string) $rows ) . '" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '">'
			. esc_textarea( $value )
			. '</textarea>';
	}

	/**
	 * @param WP_Post $post Post.
	 * @param string  $key Meta key.
	 * @param string  $label Label.
	 * @return void
	 */
	private function url_field( $post, $key, $label ) {
		$value = (string) get_post_meta( $post->ID, $key, true );
		echo '<p><label for="' . esc_attr( $key ) . '"><strong>' . esc_html( $label ) . '</strong></label></p>';
		echo '<input class="widefat" type="url" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />';
	}

	/**
	 * @param WP_Post $post Current post.
	 * @param string  $key Canonical relationship key.
	 * @param string  $target_type Target post type.
	 * @param string  $label Label.
	 * @return void
	 */
	private function relationship_field( $post, $key, $target_type, $label ) {
		$selected = get_post_meta( $post->ID, $key, true );
		$selected = is_array( $selected ) ? array_map( 'absint', $selected ) : array();
		$choices  = get_posts(
			array(
				'post_type'      => $target_type,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		echo '<p><label for="' . esc_attr( $key ) . '"><strong>' . esc_html( $label ) . '</strong></label></p>';
		echo '<select class="widefat" multiple size="8" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '[]">';
		foreach ( $choices as $choice ) {
			echo '<option value="' . esc_attr( (string) $choice->ID ) . '" ' . selected( in_array( (int) $choice->ID, $selected, true ), true, false ) . '>'
				. esc_html( get_the_title( $choice ) )
				. '</option>';
		}
		echo '</select>';
	}
}
