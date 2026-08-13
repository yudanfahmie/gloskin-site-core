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
	const SETTINGS_OPTION      = 'gloskin_site_core_settings';
	const SETTINGS_SLUG        = 'gloskin-site-core';
	const MIGRATION_CAPABILITY = 'manage_woocommerce';
	const MIGRATION_SLUG       = 'gloskin-sample-product-import';
	const MIGRATION_AJAX       = 'gloskin_site_core_sample_product_import';
	const MIGRATION_NONCE      = 'gloskin_site_core_sample_product_import';
	const CONSOLIDATION_OPTION       = 'gloskin_site_core_description_consolidation';
	const CONSOLIDATION_ERROR_OPTION = 'gloskin_site_core_description_consolidation_error';
	const CONSOLIDATION_ACTION       = 'gloskin_site_core_consolidate_descriptions';
	const CONSOLIDATION_NONCE        = 'gloskin_site_core_consolidate_descriptions';

	/** @var Gloskin_Site_Core_Content_Service */
	private $content;

	/** @var Gloskin_Site_Core_Asset_Service|null */
	private $assets;

	/** @var string */
	private $plugin_file;

	/** @var Gloskin_Site_Core_Sample_Product_Importer|null */
	private $sample_importer = null;

	/** @var string */
	private $migration_hook = '';

	/** @var string */
	private $settings_hook = '';

	/** @param Gloskin_Site_Core_Content_Service $content Content owner. */
	public function __construct( $content, $assets = null, $plugin_file = '' ) {
		$this->content = $content; $this->assets = $assets; $this->plugin_file = (string) $plugin_file;
	}

	/** @return void */
	public function register() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ), 9 );
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_meta' ), 20, 2 );
		add_action( 'admin_notices', array( $this, 'content_readiness_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_migration_assets' ), 30 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_settings_assets' ), 30 );
		add_action( 'wp_ajax_' . self::MIGRATION_AJAX, array( $this, 'ajax_sample_product_import' ) );
		add_action( 'admin_post_' . self::CONSOLIDATION_ACTION, array( $this, 'handle_consolidate_descriptions' ) );
		// Goal 5: classic Woo Product edit screen only. remove_post_type_support()
		// and add_meta_boxes()/remove_meta_box() are both inert on the newer
		// React/block-based Product Block Editor route (a different admin page
		// entirely, not driven by these meta-box/post-type-support APIs) -- no
		// DOM-hack or editor-detection branch is needed either way.
		add_action( 'init', array( $this, 'maybe_simplify_product_editor' ) );
		add_action( 'add_meta_boxes', array( $this, 'maybe_reprioritize_short_description_box' ), 20 );
	}

	public function register_settings() {
		register_setting( 'gloskin_site_core', self::SETTINGS_OPTION, array(
			'type' => 'array', 'default' => self::settings_defaults(),
			'sanitize_callback' => array( $this, 'sanitize_settings' ),
		) );
	}

	/**
	 * Single source of truth for this one settings option's shape/defaults,
	 * reused by both the registration default and every read fallback below.
	 *
	 * @return array{design_variant:string,form_shortcode:string,header_variant:string,hero_video_enabled:bool,hero_video_url:string}
	 */
	public static function settings_defaults() {
		return array(
			'design_variant' => 'medical',
			'form_shortcode' => '',
			'header_variant' => 'header-1',
			'hero_video_enabled' => true,
			'hero_video_url' => 'https://www.youtube.com/watch?v=otej7WLdPh0&pp=ygUPc2tpbmNhcmUgdGVhc2Vy',
		);
	}

	public function register_admin_menu() {
		$parent = Gloskin_Site_Core_Content_Service::ADMIN_MENU_SLUG;
		add_menu_page( __( 'Gloskin Content Overview', 'gloskin-site-core' ), __( 'Gloskin Content', 'gloskin-site-core' ), 'edit_posts', $parent, array( $this, 'render_content_overview' ), 'dashicons-admin-site-alt3', 21 );
		add_submenu_page( $parent, __( 'Gloskin Content Overview', 'gloskin-site-core' ), __( 'Overview', 'gloskin-site-core' ), 'edit_posts', $parent, array( $this, 'render_content_overview' ) );
		$settings_hook = add_submenu_page( $parent, __( 'Gloskin Settings', 'gloskin-site-core' ), __( 'Settings', 'gloskin-site-core' ), 'manage_options', self::SETTINGS_SLUG, array( $this, 'render_settings_page' ) );
		if ( is_string( $settings_hook ) ) { $this->settings_hook = $settings_hook; }
		if ( current_user_can( self::MIGRATION_CAPABILITY ) && '' !== $this->plugin_file ) {
			$migration = $this->sample_importer();
			if ( $migration->should_show_menu() ) {
				$hook = add_submenu_page( $parent, __( 'Sample Product Import', 'gloskin-site-core' ), __( 'Sample Product Import', 'gloskin-site-core' ), self::MIGRATION_CAPABILITY, self::MIGRATION_SLUG, array( $this, 'render_sample_product_import' ) );
				if ( is_string( $hook ) ) { $this->migration_hook = $hook; }
			}
		}
	}

	public function render_content_overview() {
		if ( ! current_user_can( 'edit_posts' ) ) { return; }
		$labels = array(
			Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE => __( 'Treatments', 'gloskin-site-core' ),
			Gloskin_Site_Core_Content_Service::CLINIC_POST_TYPE => __( 'Clinics', 'gloskin-site-core' ),
			Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE => __( 'Doctors', 'gloskin-site-core' ),
		);
		?>
		<div class="wrap"><h1><?php echo esc_html__( 'Gloskin Content', 'gloskin-site-core' ); ?></h1><p><?php echo esc_html__( 'Ringkasan konten Gloskin dan pintasan ke layar WordPress yang tetap menjadi pemilik datanya.', 'gloskin-site-core' ); ?></p><div class="gloskin-admin-overview">
		<?php
		foreach ( Gloskin_Site_Core_Content_Service::record_targets() as $post_type => $target ) :
			$count = wp_count_posts( $post_type );
			$live  = $count && isset( $count->publish ) ? absint( $count->publish ) : 0;
			/* translators: %1$d: number of published records; %2$d: target record count for this content type. */
			$progress_label = sprintf( __( '%1$d dari target %2$d record telah dipublikasikan.', 'gloskin-site-core' ), $live, $target );
			?><div class="card"><h2><?php echo esc_html( $labels[ $post_type ] ); ?></h2><p><?php echo esc_html( $progress_label ); ?></p><p><a class="button button-secondary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . $post_type ) ); ?>"><?php echo esc_html__( 'Kelola Konten', 'gloskin-site-core' ); ?></a></p></div><?php endforeach; ?>
		</div><hr><h2><?php echo esc_html__( 'Konten WordPress yang Tetap Native', 'gloskin-site-core' ); ?></h2><p><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=page' ) ); ?>"><?php echo esc_html__( 'Pages Gloskin', 'gloskin-site-core' ); ?></a> · <a href="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>"><?php echo esc_html__( 'Posts / Insights', 'gloskin-site-core' ); ?></a> · <a href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>"><?php echo esc_html__( 'Media Library', 'gloskin-site-core' ); ?></a></p><?php if ( current_user_can( 'manage_options' ) ) : ?><p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SETTINGS_SLUG ) ); ?>"><?php echo esc_html__( 'Buka Gloskin Settings', 'gloskin-site-core' ); ?></a></p><?php endif; ?>
		<?php
		if ( current_user_can( self::MIGRATION_CAPABILITY ) && '' !== $this->plugin_file ) :
			$sample_summary = $this->sample_importer()->get_summary();
			if ( 'consumed' === $sample_summary['detection'] && 'failed' === $sample_summary['cleanup'] ) :
				?><div class="notice notice-warning inline"><p><?php echo esc_html__( 'Sample product sudah terverifikasi dan dikonsumsi, tetapi cleanup runtime gagal. Import tetap terkunci permanen.', 'gloskin-site-core' ); ?> <?php echo esc_html( $sample_summary['cleanup_error'] ); ?></p></div><?php
			endif;
		endif;
		?>
		</div>
		<?php
	}

	public function sanitize_settings( $value ) {
		$value = is_array( $value ) ? $value : array();
		$variant = isset( $value['design_variant'] ) ? sanitize_key( $value['design_variant'] ) : 'medical';
		$header_variant = isset( $value['header_variant'] ) ? sanitize_key( $value['header_variant'] ) : 'header-1';
		return array(
			'design_variant' => in_array( $variant, array( 'medical', 'modern', 'luxury' ), true ) ? $variant : 'medical',
			'form_shortcode' => isset( $value['form_shortcode'] ) ? sanitize_text_field( $value['form_shortcode'] ) : '',
			/* Strict allowlist, same shape as design_variant above: anything
			 * outside the two known header layouts (including a missing/
			 * tampered value) always falls back to the default production
			 * header, never a partial/unknown composition. */
			'header_variant' => in_array( $header_variant, array( 'header-1', 'header-2' ), true ) ? $header_variant : 'header-1',
			/* Stored as plain sanitized URL text, never trusted HTML -- the
			 * strict YouTube-ID pattern check happens at render time via
			 * resolve_youtube_video_id() below, so an invalid/garbled URL
			 * here can never do worse than fall back to the existing
			 * non-video hero media, never break the save itself. */
			'hero_video_enabled' => ! empty( $value['hero_video_enabled'] ),
			'hero_video_url' => isset( $value['hero_video_url'] ) ? esc_url_raw( trim( (string) $value['hero_video_url'] ) ) : '',
		);
	}

	/**
	 * The one pure helper that resolves a valid YouTube video ID from a
	 * user-supplied URL, or '' when the URL is empty/malformed/non-YouTube.
	 * Never trusts/echoes arbitrary HTML -- the return value is always
	 * either an 11-character YouTube ID matching YouTube's own safe ID
	 * pattern, or an empty string. Supports exactly the four documented
	 * shapes: watch?v=, youtu.be/, /embed/, /shorts/.
	 *
	 * @param string $url Raw (already-sanitized-as-a-URL) hero video URL.
	 * @return string 11-char YouTube video ID, or '' if unresolvable.
	 */
	public static function resolve_youtube_video_id( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}
		$pattern = '~^(?:https?:)?//(?:www\.)?(?:'
			. 'youtube\.com/watch\?(?:[^\s#]*&)?v=(?<id1>[A-Za-z0-9_-]{11})'
			. '|youtu\.be/(?<id2>[A-Za-z0-9_-]{11})'
			. '|youtube\.com/embed/(?<id3>[A-Za-z0-9_-]{11})'
			. '|youtube\.com/shorts/(?<id4>[A-Za-z0-9_-]{11})'
			. ')(?:[/?&#].*)?$~i';
		if ( ! preg_match( $pattern, $url, $matches ) ) {
			return '';
		}
		foreach ( array( 'id1', 'id2', 'id3', 'id4' ) as $key ) {
			if ( ! empty( $matches[ $key ] ) ) {
				return $matches[ $key ];
			}
		}
		return '';
	}

	/**
	 * Only enqueue the Gloskin admin presentation shell's own small CSS/JS on
	 * this exact Settings screen -- never globally in wp-admin. AssetService
	 * remains the sole asset registry/enqueue owner; this only asks it to.
	 *
	 * @param string $hook_suffix Admin screen hook.
	 * @return void
	 */
	public function enqueue_settings_assets( $hook_suffix ) {
		if ( '' === $this->settings_hook || $hook_suffix !== $this->settings_hook ) { return; }
		if ( is_object( $this->assets ) && method_exists( $this->assets, 'enqueue_admin_settings' ) ) {
			$this->assets->enqueue_admin_settings();
		}
	}

	/**
	 * @return array{header-1:string,header-2:string} Preview image URLs, keyed by variant.
	 */
	private function header_variant_previews() {
		if ( '' === $this->plugin_file ) { return array( 'header-1' => '', 'header-2' => '' ); }
		return array(
			'header-1' => plugins_url( 'assets/admin/header-type-1.png', $this->plugin_file ),
			'header-2' => plugins_url( 'assets/admin/header-type-2.png', $this->plugin_file ),
		);
	}

	/**
	 * One radio-selectable preview card for a Header Type. A real <input
	 * type="radio"> stays the sole canonical control; the surrounding
	 * <label> lets the whole card (including the decorative preview image)
	 * activate it without any JS. The image itself carries alt="" -- the
	 * adjacent title/description text is the accessible description, so the
	 * image stays presentation-only rather than a duplicate announcement.
	 *
	 * @param string $value Option value (header-1|header-2).
	 * @param string $current Currently-saved header_variant value.
	 * @param string $title Card title.
	 * @param string $description Card description.
	 * @param string $preview_url Preview PNG URL.
	 * @param int    $preview_width Preview PNG intrinsic width (avoids layout shift).
	 * @param int    $preview_height Preview PNG intrinsic height (avoids layout shift).
	 * @return void
	 */
	private function render_header_variant_card( $value, $current, $title, $description, $preview_url, $preview_width, $preview_height ) {
		$field_id = 'gloskin-header-variant-' . $value;
		?>
		<label class="gloskin-admin-header-card<?php echo $current === $value ? ' is-selected' : ''; ?>" for="<?php echo esc_attr( $field_id ); ?>">
			<input class="gloskin-admin-header-card__radio" type="radio" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[header_variant]" value="<?php echo esc_attr( $value ); ?>" <?php checked( $current, $value ); ?> />
			<?php if ( '' !== $preview_url ) : ?><span class="gloskin-admin-header-card__preview"><img src="<?php echo esc_url( $preview_url ); ?>" alt="" loading="lazy" width="<?php echo esc_attr( (string) $preview_width ); ?>" height="<?php echo esc_attr( (string) $preview_height ); ?>" /></span><?php endif; ?>
			<span class="gloskin-admin-header-card__body">
				<strong class="gloskin-admin-header-card__title"><?php echo esc_html( $title ); ?></strong>
				<span class="gloskin-admin-header-card__desc"><?php echo esc_html( $description ); ?></span>
			</span>
		</label>
		<?php
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$settings          = get_option( self::SETTINGS_OPTION, self::settings_defaults() );
		$variant           = isset( $settings['design_variant'] ) ? $settings['design_variant'] : 'medical';
		$shortcode         = isset( $settings['form_shortcode'] ) ? $settings['form_shortcode'] : '';
		$header_variant    = isset( $settings['header_variant'] ) ? $settings['header_variant'] : 'header-1';
		$hero_video_on     = ! empty( $settings['hero_video_enabled'] );
		$hero_video_url    = isset( $settings['hero_video_url'] ) ? (string) $settings['hero_video_url'] : '';
		$previews          = $this->header_variant_previews();
		$tabs              = array(
			'brand'   => __( 'Brand', 'gloskin-site-core' ),
			'header'  => __( 'Header', 'gloskin-site-core' ),
			'hero'    => __( 'Hero', 'gloskin-site-core' ),
			'booking' => __( 'Booking & Social', 'gloskin-site-core' ),
			'mapping' => __( 'Page Mapping', 'gloskin-site-core' ),
		);
		?>
		<div id="gloskin-admin-root" class="gloskin-admin-shell">
			<aside class="gloskin-admin-shell__sidebar">
				<p class="gloskin-admin-shell__eyebrow"><?php echo esc_html__( 'Gloskin Site Core', 'gloskin-site-core' ); ?></p>
				<h1 class="gloskin-admin-shell__title"><?php echo esc_html__( 'Settings', 'gloskin-site-core' ); ?></h1>
				<p class="gloskin-admin-shell__lede"><?php echo esc_html__( 'Konfigurasi global yang memang dimiliki Gloskin Site Core.', 'gloskin-site-core' ); ?></p>
				<a class="gloskin-admin-shell__back" href="<?php echo esc_url( admin_url( 'admin.php?page=' . Gloskin_Site_Core_Content_Service::ADMIN_MENU_SLUG ) ); ?>">&larr; <?php echo esc_html__( 'Gloskin Content', 'gloskin-site-core' ); ?></a>
			</aside>
			<div class="gloskin-admin-shell__workspace">
				<form method="post" action="options.php">
					<?php settings_fields( 'gloskin_site_core' ); ?>
					<div class="gloskin-admin-tabs" role="tablist" aria-label="<?php echo esc_attr__( 'Bagian pengaturan Gloskin', 'gloskin-site-core' ); ?>" data-gloskin-admin-tabs>
						<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
							<button type="button" class="gloskin-admin-tabs__tab<?php echo 'brand' === $tab_key ? ' is-active' : ''; ?>" id="gloskin-admin-tab-<?php echo esc_attr( $tab_key ); ?>" role="tab" aria-selected="<?php echo 'brand' === $tab_key ? 'true' : 'false'; ?>" aria-controls="gloskin-admin-panel-<?php echo esc_attr( $tab_key ); ?>" tabindex="<?php echo 'brand' === $tab_key ? '0' : '-1'; ?>" data-gloskin-admin-tab="<?php echo esc_attr( $tab_key ); ?>"><?php echo esc_html( $tab_label ); ?></button>
						<?php endforeach; ?>
					</div>
					<div class="gloskin-admin-canvas">
						<section class="gloskin-admin-card" id="gloskin-admin-panel-brand" role="tabpanel" aria-labelledby="gloskin-admin-tab-brand" data-gloskin-admin-panel="brand">
							<h2 class="gloskin-admin-card__title"><?php echo esc_html__( 'Design direction', 'gloskin-site-core' ); ?></h2>
							<p class="gloskin-admin-card__hint"><?php echo esc_html__( 'Arah palet dan tone visual global Gloskin.', 'gloskin-site-core' ); ?></p>
							<p><label class="gloskin-admin-field-label" for="gloskin-design-variant"><?php echo esc_html__( 'Design direction', 'gloskin-site-core' ); ?></label><br />
							<select id="gloskin-design-variant" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[design_variant]"><option value="medical" <?php selected( $variant, 'medical' ); ?>><?php echo esc_html__( 'Medical Professional', 'gloskin-site-core' ); ?></option><option value="modern" <?php selected( $variant, 'modern' ); ?>><?php echo esc_html__( 'Modern Aesthetic', 'gloskin-site-core' ); ?></option><option value="luxury" <?php selected( $variant, 'luxury' ); ?>><?php echo esc_html__( 'Premium Luxury', 'gloskin-site-core' ); ?></option></select></p>
						</section>
						<section class="gloskin-admin-card" id="gloskin-admin-panel-header" role="tabpanel" aria-labelledby="gloskin-admin-tab-header" data-gloskin-admin-panel="header">
							<h2 class="gloskin-admin-card__title"><?php echo esc_html__( 'Header layout', 'gloskin-site-core' ); ?></h2>
							<p class="gloskin-admin-card__hint"><?php echo esc_html__( 'Pilih komposisi header. Header Type 1 tetap default dan tidak berubah untuk situs yang sudah ada.', 'gloskin-site-core' ); ?></p>
							<div class="gloskin-admin-header-picker">
								<?php
								$this->render_header_variant_card( 'header-1', $header_variant, __( 'Header Type 1', 'gloskin-site-core' ), __( 'Centered-logo layout (default)', 'gloskin-site-core' ), $previews['header-1'], 1440, 133 );
								$this->render_header_variant_card( 'header-2', $header_variant, __( 'Header Type 2', 'gloskin-site-core' ), __( 'Logo / Navigation / Actions', 'gloskin-site-core' ), $previews['header-2'], 1440, 73 );
								?>
							</div>
						</section>
						<section class="gloskin-admin-card" id="gloskin-admin-panel-hero" role="tabpanel" aria-labelledby="gloskin-admin-tab-hero" data-gloskin-admin-panel="hero">
							<h2 class="gloskin-admin-card__title"><?php echo esc_html__( 'Home hero video', 'gloskin-site-core' ); ?></h2>
							<p class="gloskin-admin-card__hint"><?php echo esc_html__( 'Video ditampilkan di dalam slot media hero yang sudah ada di halaman Home.', 'gloskin-site-core' ); ?></p>
							<p><label class="gloskin-admin-field-label" for="gloskin-hero-video-enabled"><input type="checkbox" id="gloskin-hero-video-enabled" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[hero_video_enabled]" value="1" <?php checked( $hero_video_on ); ?> /> <?php echo esc_html__( 'Enable hero video', 'gloskin-site-core' ); ?></label></p>
							<p><label class="gloskin-admin-field-label" for="gloskin-hero-video-url"><?php echo esc_html__( 'YouTube video URL', 'gloskin-site-core' ); ?></label><br />
							<input class="regular-text" type="url" id="gloskin-hero-video-url" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[hero_video_url]" value="<?php echo esc_attr( $hero_video_url ); ?>" placeholder="https://www.youtube.com/watch?v=..." /></p>
							<p class="gloskin-admin-card__hint"><?php echo esc_html__( 'Supports standard YouTube and youtu.be URLs. Video is rendered using a performance-first poster/facade.', 'gloskin-site-core' ); ?></p>
						</section>
						<section class="gloskin-admin-card" id="gloskin-admin-panel-booking" role="tabpanel" aria-labelledby="gloskin-admin-tab-booking" data-gloskin-admin-panel="booking">
							<h2 class="gloskin-admin-card__title"><?php echo esc_html__( 'Booking & Social', 'gloskin-site-core' ); ?></h2>
							<p class="gloskin-admin-card__hint"><?php echo esc_html__( 'Kanal kontak/booking global. Nomor WhatsApp dan jam operasional per klinik tetap dimiliki masing-masing halaman Klinik.', 'gloskin-site-core' ); ?></p>
							<p><label class="gloskin-admin-field-label" for="gloskin-form-shortcode"><?php echo esc_html__( 'Contact form shortcode', 'gloskin-site-core' ); ?></label><br />
							<input class="regular-text" id="gloskin-form-shortcode" type="text" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[form_shortcode]" value="<?php echo esc_attr( $shortcode ); ?>" placeholder="[contact-form-7 id=&quot;...&quot;]" /></p>
							<p class="gloskin-admin-card__hint"><?php echo esc_html__( 'The form provider continues to own submission, anti-spam, storage, confirmation and mail delivery.', 'gloskin-site-core' ); ?></p>
							<p><a class="button button-secondary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Gloskin_Site_Core_Content_Service::CLINIC_POST_TYPE ) ); ?>"><?php echo esc_html__( 'Kelola Klinik (WhatsApp, telepon, jam operasional)', 'gloskin-site-core' ); ?></a></p>
						</section>
						<section class="gloskin-admin-card" id="gloskin-admin-panel-mapping" role="tabpanel" aria-labelledby="gloskin-admin-tab-mapping" data-gloskin-admin-panel="mapping">
							<h2 class="gloskin-admin-card__title"><?php echo esc_html__( 'Page Mapping', 'gloskin-site-core' ); ?></h2>
							<p class="gloskin-admin-card__hint"><?php echo esc_html__( 'Pemetaan kategori skincare ke Page tetap dimiliki oleh field "WooCommerce category slug" pada meta box masing-masing Page turunan Skincare.', 'gloskin-site-core' ); ?></p>
							<p><a class="button button-secondary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=page' ) ); ?>"><?php echo esc_html__( 'Kelola Pages', 'gloskin-site-core' ); ?></a></p>
						</section>
					</div>
					<?php submit_button(); ?>
				</form>
				<?php $this->render_description_consolidation_card(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Goal 4/5: a separate card/form (deliberately outside the settings
	 * <form action="options.php"> above -- HTML forms cannot nest) that
	 * triggers the one-canonical-description consolidation and reports its
	 * real result. Retiring the main content editor (see
	 * maybe_simplify_product_editor()) only ever activates after this has
	 * actually run at least once.
	 *
	 * @return void
	 */
	private function render_description_consolidation_card() {
		if ( ! current_user_can( self::MIGRATION_CAPABILITY ) ) {
			return;
		}
		$summary = get_option( self::CONSOLIDATION_OPTION, array() );
		$done    = $this->descriptions_consolidated();
		$failure = get_option( self::CONSOLIDATION_ERROR_OPTION, array() );
		?>
		<div class="gloskin-admin-card" style="margin-top:var(--gloskin-admin-space-4,24px)">
			<h2 class="gloskin-admin-card__title"><?php echo esc_html__( 'Product Descriptions', 'gloskin-site-core' ); ?></h2>
			<p class="gloskin-admin-card__hint"><?php echo esc_html__( 'Consolidates every product\'s existing long description into its Short Description, deterministically, without deleting or duplicating content -- the Short Description becomes the one primary PDP body field.', 'gloskin-site-core' ); ?></p>
			<?php if ( is_array( $failure ) && ! empty( $failure['failed_at'] ) ) : ?>
				<div class="notice notice-error inline"><p>
					<?php
					printf(
						/* translators: %s: real failure reason. */
						esc_html__( 'Konsolidasi terakhir GAGAL dan TIDAK ditandai selesai -- editor konten utama tetap aktif: %s', 'gloskin-site-core' ),
						esc_html( isset( $failure['message'] ) ? $failure['message'] : '' )
					);
					?>
				</p></div>
			<?php endif; ?>
			<?php if ( $done ) : ?>
				<p>
					<?php
					printf(
						/* translators: %1$d: products audited; %2$d: products actually migrated. */
						esc_html__( 'Terakhir dijalankan: %1$d produk diaudit, %2$d produk dimigrasikan.', 'gloskin-site-core' ),
						isset( $summary['audited'] ) ? (int) $summary['audited'] : 0,
						isset( $summary['migrated'] ) ? (int) $summary['migrated'] : 0
					);
					?>
				</p>
			<?php else : ?>
				<p><?php echo esc_html__( 'Belum pernah dijalankan. Editor konten utama tetap aktif sampai konsolidasi ini terbukti berjalan.', 'gloskin-site-core' ); ?></p>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( self::CONSOLIDATION_NONCE ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::CONSOLIDATION_ACTION ); ?>" />
				<button type="submit" class="button button-secondary"><?php echo esc_html( $done ? __( 'Run Again', 'gloskin-site-core' ) : __( 'Consolidate Product Descriptions', 'gloskin-site-core' ) ); ?></button>
			</form>
		</div>
		<?php
	}

	public function register_meta_boxes() {
		add_meta_box( 'gloskin-treatment-details', __( 'Treatment Details', 'gloskin-site-core' ), array( $this, 'render_treatment_meta_box' ), Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE, 'normal', 'default' );
		add_meta_box( 'gloskin-clinic-details', __( 'Clinic Details', 'gloskin-site-core' ), array( $this, 'render_clinic_meta_box' ), Gloskin_Site_Core_Content_Service::CLINIC_POST_TYPE, 'normal', 'default' );
		add_meta_box( 'gloskin-doctor-details', __( 'Doctor Details', 'gloskin-site-core' ), array( $this, 'render_doctor_meta_box' ), Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE, 'normal', 'default' );
		add_meta_box( 'gloskin-page-details', __( 'Gloskin Page Settings', 'gloskin-site-core' ), array( $this, 'render_page_meta_box' ), 'page', 'normal', 'default' );
	}

	public function render_treatment_meta_box( $post ) {
		$this->nonce();
		$this->textarea_field( $post, 'gloskin_summary', __( 'Summary', 'gloskin-site-core' ) );
		$this->textarea_field( $post, 'gloskin_benefits', __( 'Benefits', 'gloskin-site-core' ), 8 );
		$this->textarea_field( $post, 'gloskin_contraindications', __( 'Contraindications', 'gloskin-site-core' ), 8 );
		$this->url_field( $post, 'gloskin_booking_target', __( 'Booking target', 'gloskin-site-core' ) );
		$this->relationship_field( $post, 'gloskin_clinic_ids', Gloskin_Site_Core_Content_Service::CLINIC_POST_TYPE, __( 'Related clinics', 'gloskin-site-core' ) );
		$this->relationship_field( $post, 'gloskin_doctor_ids', Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE, __( 'Related doctors', 'gloskin-site-core' ) );
	}

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
		echo '<p><label for="gloskin-gallery-ids"><strong>' . esc_html__( 'Gallery image IDs', 'gloskin-site-core' ) . '</strong></label></p><div class="gloskin-admin-media-row"><input class="widefat" id="gloskin-gallery-ids" name="gloskin_gallery_image_ids" value="' . esc_attr( $value ) . '" /><button type="button" class="button" data-gloskin-media-picker data-target="#gloskin-gallery-ids" data-multiple="true">' . esc_html__( 'Choose images', 'gloskin-site-core' ) . '</button></div><p class="description">' . esc_html__( 'Uses WordPress Media Library attachment IDs. Missing gallery media is allowed.', 'gloskin-site-core' ) . '</p>';
	}

	public function render_doctor_meta_box( $post ) {
		$this->nonce();
		$this->text_field( $post, 'gloskin_degree_title', __( 'Degree / title', 'gloskin-site-core' ) );
		$this->text_field( $post, 'gloskin_specialization', __( 'Specialization', 'gloskin-site-core' ) );
		$this->text_field( $post, 'gloskin_sip_number', __( 'SIP number', 'gloskin-site-core' ) );
		$this->textarea_field( $post, 'gloskin_credentials', __( 'Credentials', 'gloskin-site-core' ), 6 );
		$this->textarea_field( $post, 'gloskin_profile', __( 'Profile', 'gloskin-site-core' ), 8 );
		$this->textarea_field( $post, 'gloskin_schedule', __( 'Schedule', 'gloskin-site-core' ), 5 );
		$this->url_field( $post, 'gloskin_booking_target', __( 'Booking target', 'gloskin-site-core' ) );
		$this->relationship_field( $post, 'gloskin_branch_ids', Gloskin_Site_Core_Content_Service::CLINIC_POST_TYPE, __( 'Practice branches', 'gloskin-site-core' ) );
		echo '<p class="description">' . esc_html__( 'Use the standard Featured Image panel for the approved doctor portrait.', 'gloskin-site-core' ) . '</p>';
	}

	public function render_page_meta_box( $post ) {
		$this->nonce();
		$this->text_field( $post, 'gloskin_hero_heading', __( 'Hero heading', 'gloskin-site-core' ) );
		$this->textarea_field( $post, 'gloskin_hero_copy', __( 'Hero copy', 'gloskin-site-core' ), 4 );
		$this->text_field( $post, 'gloskin_hero_cta_label', __( 'Hero CTA label', 'gloskin-site-core' ) );
		$this->url_field( $post, 'gloskin_hero_cta_url', __( 'Hero CTA URL', 'gloskin-site-core' ) );
		$media_id = absint( get_post_meta( $post->ID, 'gloskin_hero_media_id', true ) );
		echo '<p><label for="gloskin-hero-media-id"><strong>' . esc_html__( 'Hero media image ID', 'gloskin-site-core' ) . '</strong></label></p><div class="gloskin-admin-media-row"><input class="widefat" id="gloskin-hero-media-id" name="gloskin_hero_media_id" value="' . esc_attr( $media_id ? (string) $media_id : '' ) . '" /><button type="button" class="button" data-gloskin-media-picker data-target="#gloskin-hero-media-id" data-multiple="false">' . esc_html__( 'Choose image', 'gloskin-site-core' ) . '</button></div>';
		if ( 'about' === $post->post_name ) {
			$this->textarea_field( $post, 'gloskin_about_vision', __( 'Vision', 'gloskin-site-core' ), 5 );
			$this->textarea_field( $post, 'gloskin_about_mission', __( 'Mission', 'gloskin-site-core' ), 5 );
			$this->textarea_field( $post, 'gloskin_about_values', __( 'Values', 'gloskin-site-core' ), 5 );
		}
		$parent = $post->post_parent ? get_post( $post->post_parent ) : null;
		if ( $parent instanceof WP_Post && 'skincare' === $parent->post_name ) { $this->text_field( $post, 'gloskin_woo_category_slug', __( 'WooCommerce category slug', 'gloskin-site-core' ) ); }
	}

	public function save_meta( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		if ( wp_is_post_revision( $post_id ) || ! isset( $_POST[ self::META_NONCE_NAME ] ) ) { return; }
		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::META_NONCE_NAME ] ) );
		if ( ! wp_verify_nonce( $nonce, self::META_NONCE_ACTION ) || ! current_user_can( 'edit_post', $post_id ) ) { return; }
		$schemas = $this->save_schema();
		if ( ! isset( $schemas[ $post->post_type ] ) ) { return; }
		/*
		 * Every key below is registered in Gloskin_Site_Core_Content_Service::register_meta()
		 * with an explicit sanitize_callback. Posted values must still be run through
		 * sanitize_meta() here -- update_post_meta() never auto-applies a meta type's
		 * registered sanitizer on its own, that only happens for REST/meta-API writers.
		 * This makes the sanitize-before-persist boundary explicit and visible instead
		 * of implicit/invisible to review, even though the registered callbacks were
		 * already a mitigating control.
		 */
		foreach ( $schemas[ $post->post_type ]['strings'] as $key ) {
			$value = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
			$value = sanitize_meta( $key, $value, 'post', $post->post_type );
			update_post_meta( $post_id, $key, $value );
		}
		foreach ( $schemas[ $post->post_type ]['arrays'] as $key ) {
			if ( 'gloskin_gallery_image_ids' === $key ) {
				$value = isset( $_POST[ $key ] ) ? explode( ',', (string) wp_unslash( $_POST[ $key ] ) ) : array();
			} else {
				$value = isset( $_POST[ $key ] ) ? (array) wp_unslash( $_POST[ $key ] ) : array();
			}
			$value = sanitize_meta( $key, $value, 'post', $post->post_type );
			update_post_meta( $post_id, $key, $value );
		}
		foreach ( $schemas[ $post->post_type ]['integers'] as $key ) {
			$value = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : 0;
			$value = sanitize_meta( $key, $value, 'post', $post->post_type );
			update_post_meta( $post_id, $key, $value );
		}
	}

	private function save_schema() {
		return array(
			Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE => array( 'strings' => array( 'gloskin_summary', 'gloskin_benefits', 'gloskin_contraindications', 'gloskin_booking_target' ), 'arrays' => array( 'gloskin_clinic_ids', 'gloskin_doctor_ids' ), 'integers' => array() ),
			Gloskin_Site_Core_Content_Service::CLINIC_POST_TYPE => array( 'strings' => array( 'gloskin_address', 'gloskin_phone_display', 'gloskin_phone_uri', 'gloskin_whatsapp_number', 'gloskin_whatsapp_message', 'gloskin_operating_hours', 'gloskin_map_url', 'gloskin_map_embed', 'gloskin_short_location' ), 'arrays' => array( 'gloskin_gallery_image_ids' ), 'integers' => array() ),
			Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE => array( 'strings' => array( 'gloskin_degree_title', 'gloskin_specialization', 'gloskin_sip_number', 'gloskin_credentials', 'gloskin_profile', 'gloskin_schedule', 'gloskin_booking_target' ), 'arrays' => array( 'gloskin_branch_ids' ), 'integers' => array() ),
			'page' => array( 'strings' => array( 'gloskin_woo_category_slug', 'gloskin_about_vision', 'gloskin_about_mission', 'gloskin_about_values', 'gloskin_hero_heading', 'gloskin_hero_copy', 'gloskin_hero_cta_label', 'gloskin_hero_cta_url' ), 'arrays' => array(), 'integers' => array( 'gloskin_hero_media_id' ) ),
		);
	}

	public function content_readiness_notice() {
		if ( ! current_user_can( 'edit_posts' ) ) { return; }
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$allowed = array( 'dashboard', 'edit', 'post', 'toplevel_page_gloskin-content', 'gloskin-content_page_gloskin-site-core' );
		if ( ! $screen || ! in_array( $screen->base, $allowed, true ) ) { return; }
		$targets = Gloskin_Site_Core_Content_Service::record_targets(); $parts = array();
		foreach ( $targets as $post_type => $target ) {
			$count = wp_count_posts( $post_type );
			$live  = $count && isset( $count->publish ) ? absint( $count->publish ) : 0;
			if ( $live < $target ) {
				/* translators: %1$s: post type key; %2$d: number of published records; %3$d: target record count. */
				$parts[] = sprintf( __( '%1$s: %2$d/%3$d approved records published', 'gloskin-site-core' ), $post_type, $live, $target );
			}
		}
		if ( ! $parts ) { return; }
		echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'Gloskin content readiness:', 'gloskin-site-core' ) . '</strong> ' . esc_html( implode( '; ', $parts ) ) . '. ' . esc_html__( 'Publish only client-approved facts; do not create placeholder medical or doctor data.', 'gloskin-site-core' ) . '</p></div>';
	}


	public function enqueue_migration_assets( $hook_suffix ) {
		if ( '' === $this->migration_hook || $hook_suffix !== $this->migration_hook || ! current_user_can( self::MIGRATION_CAPABILITY ) ) { return; }
		if ( is_object( $this->assets ) && method_exists( $this->assets, 'enqueue_admin_migration' ) ) {
			$this->assets->enqueue_admin_migration( self::MIGRATION_AJAX, wp_create_nonce( self::MIGRATION_NONCE ) );
		}
	}

	public function render_sample_product_import() {
		if ( ! current_user_can( self::MIGRATION_CAPABILITY ) ) { wp_die( esc_html__( 'Anda tidak memiliki izin untuk menjalankan import sample product.', 'gloskin-site-core' ) ); }
		$summary = $this->sample_importer()->get_summary();
		if ( ! in_array( $summary['detection'], array( 'pending', 'failed', 'running', 'verifying' ), true ) ) { wp_die( esc_html__( 'Bundle sample product tidak tersedia atau sudah dikonsumsi.', 'gloskin-site-core' ) ); }
		$processed = isset( $summary['processed_products'] ) ? (int) $summary['processed_products'] : 0;
		$expected = isset( $summary['expected_products'] ) ? (int) $summary['expected_products'] : 13;
		?>
		<div class="wrap" data-gloskin-sample-import><h1><?php echo esc_html__( 'Sample Product Import', 'gloskin-site-core' ); ?></h1>
		<p><?php echo esc_html__( 'Synthetic staging/demo catalog — not verified commercial product truth.', 'gloskin-site-core' ); ?></p>
		<p><?php echo esc_html__( 'Produk parent dibuat sebagai draft. Variasi produk variable disiapkan aktif agar langsung berfungsi ketika parent dipublikasikan.', 'gloskin-site-core' ); ?></p>
		<table class="widefat striped" style="max-width:760px"><tbody>
		<tr><th><?php echo esc_html__( 'Bundle', 'gloskin-site-core' ); ?></th><td><?php echo esc_html( isset( $summary['bundle_id'] ) ? $summary['bundle_id'] : 'gloskin-sample-products-v1' ); ?></td></tr>
		<tr><th><?php echo esc_html__( 'Products', 'gloskin-site-core' ); ?></th><td>13</td></tr>
		<tr><th><?php echo esc_html__( 'Simple', 'gloskin-site-core' ); ?></th><td>8</td></tr>
		<tr><th><?php echo esc_html__( 'Variable', 'gloskin-site-core' ); ?></th><td>5</td></tr>
		<tr><th><?php echo esc_html__( 'Variations', 'gloskin-site-core' ); ?></th><td>10</td></tr>
		<tr><th><?php echo esc_html__( 'Images', 'gloskin-site-core' ); ?></th><td>58</td></tr>
		<tr><th><?php echo esc_html__( 'Status', 'gloskin-site-core' ); ?></th><td data-gloskin-sample-status><?php echo esc_html( $summary['detection'] ); ?></td></tr>
		<tr><th><?php echo esc_html__( 'Progress', 'gloskin-site-core' ); ?></th><td><span data-gloskin-sample-progress><?php echo esc_html( sprintf( '%d/%d', $processed, $expected ) ); ?></span></td></tr>
		</tbody></table>
		<div class="notice notice-error inline" data-gloskin-sample-error <?php echo empty( $summary['last_error'] ) ? 'hidden' : ''; ?>><p><?php echo esc_html( isset( $summary['last_error'] ) ? $summary['last_error'] : '' ); ?></p></div>
		<p><button type="button" class="button button-primary" data-gloskin-sample-run><?php echo esc_html( $processed > 0 ? __( 'Resume Import', 'gloskin-site-core' ) : __( 'Import Sample Products', 'gloskin-site-core' ) ); ?></button></p>
		<p class="description"><?php echo esc_html__( 'Satu klik memulai rangkaian checkpoint berurutan. Setiap request memproses maksimum satu parent beserta media dan variasinya.', 'gloskin-site-core' ); ?></p></div>
		<?php
	}

	public function ajax_sample_product_import() {
		if ( ! current_user_can( self::MIGRATION_CAPABILITY ) ) { wp_send_json_error( array( 'message' => __( 'Capability manage_woocommerce diperlukan.', 'gloskin-site-core' ) ), 403 ); }
		check_ajax_referer( self::MIGRATION_NONCE, 'nonce' );
		$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';
		try {
			wp_send_json_success( $this->sample_importer()->advance( $mode ) );
		} catch ( Throwable $error ) {
			wp_send_json_error( array( 'message' => $error->getMessage(), 'state' => $this->sample_importer()->get_summary() ), 409 );
		}
	}

	/**
	 * Goal 4: audit every real Woo product and deterministically migrate any
	 * long-description content missing from its Short Description into it --
	 * never deleting/overwriting post_content, never duplicating a paragraph
	 * already present. Records a real, honest summary (not a guess) for the
	 * admin card above and for the editor-simplification gate below.
	 *
	 * Bug fixed: this admin-post request runs on the Kernel's is_admin()
	 * bootstrap path, which never loads class-gloskin-site-core-woocommerce-
	 * adapter.php (that require_once only lives on the frontend/template
	 * bootstrap path -- see Kernel::boot()). The pure static
	 * consolidate_description_content() helper this method depends on is
	 * therefore explicitly required here, on this one admin-migration
	 * execution path only -- never registering/instantiating a second Woo
	 * adapter service, never touching Kernel's frontend composition.
	 *
	 * CONSOLIDATION_OPTION's completed_at is written ONLY when the audit/
	 * migration loop genuinely executed (Woo functions present, the adapter
	 * class/helper resolved, the product query itself succeeded). Any
	 * failure along that path is recorded as a truthful, visible error and
	 * leaves the prior consolidation state (and therefore the editor-
	 * retirement gate) completely untouched -- never a silent false 0/0
	 * "success" that could retire the main content editor on data that was
	 * never actually migrated.
	 *
	 * @return void
	 */
	public function handle_consolidate_descriptions() {
		if ( ! current_user_can( self::MIGRATION_CAPABILITY ) ) {
			wp_die( esc_html__( 'Capability manage_woocommerce diperlukan.', 'gloskin-site-core' ) );
		}
		check_admin_referer( self::CONSOLIDATION_NONCE );

		$audited  = 0;
		$migrated = 0;
		$executed = false;
		$error    = '';

		try {
			if ( ! function_exists( 'wc_get_products' ) || ! function_exists( 'wc_get_product' ) ) {
				throw new RuntimeException( __( 'WooCommerce tidak aktif atau belum siap saat konsolidasi dijalankan.', 'gloskin-site-core' ) );
			}
			if ( ! class_exists( 'Gloskin_Site_Core_WooCommerce_Adapter' ) ) {
				require_once __DIR__ . '/class-gloskin-site-core-woocommerce-adapter.php';
			}
			if ( ! class_exists( 'Gloskin_Site_Core_WooCommerce_Adapter' )
				|| ! method_exists( 'Gloskin_Site_Core_WooCommerce_Adapter', 'consolidate_description_content' ) ) {
				throw new RuntimeException( __( 'Helper konsolidasi deskripsi tidak tersedia.', 'gloskin-site-core' ) );
			}

			$ids = wc_get_products(
				array(
					'limit'  => -1,
					'return' => 'ids',
					'status' => array( 'publish', 'draft', 'private' ),
					'type'   => array( 'simple', 'variable' ),
				)
			);
			if ( ! is_array( $ids ) ) {
				throw new RuntimeException( __( 'Query produk WooCommerce gagal.', 'gloskin-site-core' ) );
			}

			foreach ( $ids as $id ) {
				$product = wc_get_product( $id );
				if ( ! $product ) {
					continue;
				}
				$audited++;
				$merge = Gloskin_Site_Core_WooCommerce_Adapter::consolidate_description_content( $product->get_short_description(), $product->get_description() );
				if ( ! $merge['changed'] ) {
					continue;
				}
				$product->set_short_description( $merge['result'] );
				$product->save();
				$migrated++;
			}
			$executed = true;
		} catch ( Throwable $throwable ) {
			$error = $throwable->getMessage();
		}

		if ( ! $executed ) {
			update_option(
				self::CONSOLIDATION_ERROR_OPTION,
				array(
					'message'  => $error,
					'failed_at' => time(),
				)
			);
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::SETTINGS_SLUG . '&gloskin_consolidation=error' ) );
			exit;
		}

		delete_option( self::CONSOLIDATION_ERROR_OPTION );
		update_option(
			self::CONSOLIDATION_OPTION,
			array(
				'audited'      => $audited,
				'migrated'     => $migrated,
				'completed_at' => time(),
			)
		);
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SETTINGS_SLUG . '&gloskin_consolidation=success' ) );
		exit;
	}

	/**
	 * True once handle_consolidate_descriptions() has actually, successfully
	 * run. Self-heals a false-complete state left over from the pre-fix
	 * bootstrap bug: a stored completed_at with audited=0 while WooCommerce
	 * genuinely has products cannot be a real result (that bug always
	 * produced exactly this shape), so it is treated as invalid and cleared
	 * rather than trusted -- the next admin page load then reports "belum
	 * pernah dijalankan" again, prompting a real re-run instead of silently
	 * keeping the main editor retired on unproven data.
	 *
	 * @return bool
	 */
	private function descriptions_consolidated() {
		$summary = get_option( self::CONSOLIDATION_OPTION, array() );
		if ( ! is_array( $summary ) || empty( $summary['completed_at'] ) ) {
			return false;
		}
		$audited = isset( $summary['audited'] ) ? (int) $summary['audited'] : 0;
		if ( 0 === $audited && function_exists( 'wc_get_products' ) ) {
			$has_products = (bool) wc_get_products( array( 'limit' => 1, 'return' => 'ids' ) );
			if ( $has_products ) {
				delete_option( self::CONSOLIDATION_OPTION );
				return false;
			}
		}
		return true;
	}

	/**
	 * Goal 5: retire the main WordPress content editor on the classic Woo
	 * Product edit screen, but only after consolidation is proven -- never
	 * unconditionally. Woo product data (price/stock/attributes/etc.) is
	 * untouched; this only removes the 'editor' post-type support, which
	 * WordPress core itself uses to decide whether to render the main
	 * content editor at all.
	 *
	 * @return void
	 */
	public function maybe_simplify_product_editor() {
		if ( ! $this->descriptions_consolidated() ) {
			return;
		}
		remove_post_type_support( 'product', 'editor' );
	}

	/**
	 * Goal 5: move the native "Product short description" (postexcerpt) box
	 * to the top of the classic Product edit screen, immediately below the
	 * title, and give it a clearer heading -- still WordPress's own
	 * post_excerpt_meta_box callback, so it still only ever saves to Woo's
	 * canonical post_excerpt. No duplicate description meta is created.
	 *
	 * @param string $post_type Current screen's post type.
	 * @return void
	 */
	public function maybe_reprioritize_short_description_box( $post_type ) {
		if ( 'product' !== $post_type || ! function_exists( 'remove_meta_box' ) ) {
			return;
		}
		remove_meta_box( 'postexcerpt', 'product', 'normal' );
		add_meta_box( 'postexcerpt', __( 'Product Description (Deskripsi Produk)', 'gloskin-site-core' ), 'post_excerpt_meta_box', 'product', 'normal', 'high' );
	}

	private function sample_importer() {
		if ( null === $this->sample_importer ) {
			require_once __DIR__ . '/class-gloskin-site-core-sample-product-importer.php';
			$this->sample_importer = new Gloskin_Site_Core_Sample_Product_Importer( $this->plugin_file );
		}
		return $this->sample_importer;
	}

	private function nonce() { wp_nonce_field( self::META_NONCE_ACTION, self::META_NONCE_NAME ); }
	private function text_field( $post, $key, $label ) { $value = (string) get_post_meta( $post->ID, $key, true ); echo '<p><label for="' . esc_attr( $key ) . '"><strong>' . esc_html( $label ) . '</strong></label></p><input class="widefat" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />'; }
	private function textarea_field( $post, $key, $label, $rows = 4 ) { $value = (string) get_post_meta( $post->ID, $key, true ); echo '<p><label for="' . esc_attr( $key ) . '"><strong>' . esc_html( $label ) . '</strong></label></p><textarea class="widefat" rows="' . esc_attr( (string) $rows ) . '" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '">' . esc_textarea( $value ) . '</textarea>'; }
	private function url_field( $post, $key, $label ) { $value = (string) get_post_meta( $post->ID, $key, true ); echo '<p><label for="' . esc_attr( $key ) . '"><strong>' . esc_html( $label ) . '</strong></label></p><input class="widefat" type="url" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />'; }
	private function relationship_field( $post, $key, $target_type, $label ) {
		$selected = get_post_meta( $post->ID, $key, true ); $selected = is_array( $selected ) ? array_map( 'absint', $selected ) : array();
		$choices = get_posts( array( 'post_type' => $target_type, 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		echo '<p><label for="' . esc_attr( $key ) . '"><strong>' . esc_html( $label ) . '</strong></label></p><select class="widefat" multiple size="8" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '[]">'; foreach ( $choices as $choice ) { echo '<option value="' . esc_attr( (string) $choice->ID ) . '" ' . selected( in_array( (int) $choice->ID, $selected, true ), true, false ) . '>' . esc_html( get_the_title( $choice ) ) . '</option>'; } echo '</select>';
	}
}
