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

	/*
	 * Treatment Consultation admin surface (docs/task-treatment-
	 * consultation-commerce-discovery.md section 5): exactly one custom
	 * destination under Woo Products, with internal tabs -- no separate
	 * sidebar entries for concerns/questions/mapping.
	 */
	const CONSULTATION_CAPABILITY   = 'manage_woocommerce';
	const CONSULTATION_SLUG         = 'gloskin-treatment-consultation';
	const CONCERN_ACTION            = 'gloskin_site_core_save_concern';
	const CONCERN_NONCE             = 'gloskin_site_core_save_concern';
	const CONCERN_DELETE_ACTION     = 'gloskin_site_core_delete_concern';
	const CONCERN_DELETE_NONCE      = 'gloskin_site_core_delete_concern';
	const MAPPING_ACTION            = 'gloskin_site_core_save_concern_mapping';
	const MAPPING_NONCE             = 'gloskin_site_core_save_concern_mapping';
	const DEMO_IMPORT_ACTION        = 'gloskin_site_core_import_consultation_demo';
	const DEMO_IMPORT_NONCE         = 'gloskin_site_core_import_consultation_demo';

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

		/* Treatment Consultation: product-family list filter/editor field
		 * (section 5.2/5.3) + the one Konsultasi Perawatan workspace
		 * (section 5.4). All under the existing Woo Products screens --
		 * no new sidebar entities, no second product editor. */
		add_action( 'restrict_manage_posts', array( $this, 'render_product_family_filter' ) );
		add_action( 'parse_query', array( $this, 'apply_product_family_filter' ) );
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'render_product_family_field' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_family_field' ) );
		add_action( 'admin_menu', array( $this, 'register_consultation_menu' ), 11 );
		add_action( 'admin_post_' . self::CONCERN_ACTION, array( $this, 'handle_save_concern' ) );
		add_action( 'admin_post_' . self::CONCERN_DELETE_ACTION, array( $this, 'handle_delete_concern' ) );
		add_action( 'admin_post_' . self::MAPPING_ACTION, array( $this, 'handle_save_mapping' ) );
		add_action( 'admin_post_' . self::DEMO_IMPORT_ACTION, array( $this, 'handle_demo_import' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_consultation_admin_assets' ), 30 );

		// List-table columns for managed CPTs (item 12).
		add_filter( 'manage_edit-' . Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE . '_columns', array( $this, 'promo_list_columns' ) );
		add_action( 'manage_' . Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE . '_posts_custom_column', array( $this, 'promo_list_column_cell' ), 10, 2 );
		add_filter( 'manage_edit-' . Gloskin_Site_Core_Content_Service::TESTIMONIAL_POST_TYPE . '_columns', array( $this, 'testimonial_list_columns' ) );
		add_action( 'manage_' . Gloskin_Site_Core_Content_Service::TESTIMONIAL_POST_TYPE . '_posts_custom_column', array( $this, 'testimonial_list_column_cell' ), 10, 2 );
		add_filter( 'manage_edit-' . Gloskin_Site_Core_Content_Service::ACHIEVEMENT_POST_TYPE . '_columns', array( $this, 'achievement_list_columns' ) );
		add_action( 'manage_' . Gloskin_Site_Core_Content_Service::ACHIEVEMENT_POST_TYPE . '_posts_custom_column', array( $this, 'achievement_list_column_cell' ), 10, 2 );
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
	 * @return array{form_shortcode:string,hero_video_media_id:int}
	 */
	public static function settings_defaults() {
		return array(
			'form_shortcode' => '',
			/* Home video-only mode's native background video:
			 * a WordPress Media Library attachment ID, resolved by
			 * Template_Service::hero_background_video(). Unset (0) is the
			 * normal/default state and renders a clean white Home hero. */
			'hero_video_media_id' => 0,
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
			Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE     => __( 'Treatments', 'gloskin-site-core' ),
			Gloskin_Site_Core_Content_Service::CLINIC_POST_TYPE         => __( 'Clinics', 'gloskin-site-core' ),
			Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE         => __( 'Doctors', 'gloskin-site-core' ),
			Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE          => __( 'Promos', 'gloskin-site-core' ),
			Gloskin_Site_Core_Content_Service::TESTIMONIAL_POST_TYPE    => __( 'Testimonials', 'gloskin-site-core' ),
			Gloskin_Site_Core_Content_Service::ACHIEVEMENT_POST_TYPE    => __( 'Achievements', 'gloskin-site-core' ),
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
		</div>
		<h2><?php echo esc_html__( 'Konten Terkelola', 'gloskin-site-core' ); ?></h2>
		<div class="gloskin-admin-overview">
		<?php
		$managed_types = array(
			Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE       => $labels[ Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE ],
			Gloskin_Site_Core_Content_Service::TESTIMONIAL_POST_TYPE  => $labels[ Gloskin_Site_Core_Content_Service::TESTIMONIAL_POST_TYPE ],
			Gloskin_Site_Core_Content_Service::ACHIEVEMENT_POST_TYPE  => $labels[ Gloskin_Site_Core_Content_Service::ACHIEVEMENT_POST_TYPE ],
		);
		foreach ( $managed_types as $mtype => $mlabel ) :
			$mcount = wp_count_posts( $mtype );
			$mall   = $mcount ? absint( $mcount->publish ) + absint( isset( $mcount->draft ) ? $mcount->draft : 0 ) : 0;
			$mlive  = $mcount && isset( $mcount->publish ) ? absint( $mcount->publish ) : 0;
			/* translators: %1$d: published; %2$d: total. */
			$mlabel_full = sprintf( __( '%1$d dipublikasikan, %2$d total.', 'gloskin-site-core' ), $mlive, $mall );
			?><div class="card"><h2><?php echo esc_html( $mlabel ); ?></h2><p><?php echo esc_html( $mlabel_full ); ?></p><p><a class="button button-secondary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . $mtype ) ); ?>"><?php echo esc_html__( 'Kelola Konten', 'gloskin-site-core' ); ?></a></p></div><?php
		endforeach; ?>
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
		return array(
			'form_shortcode' => isset( $value['form_shortcode'] ) ? sanitize_text_field( $value['form_shortcode'] ) : '',
			/* Plain attachment ID only; Template_Service::hero_background_video()
			 * re-resolves and mime-checks it at render time, so an ID that no
			 * longer points at an MP4/WebM safely yields a white Home hero. */
			'hero_video_media_id' => isset( $value['hero_video_media_id'] ) ? absint( $value['hero_video_media_id'] ) : 0,
		);
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

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		/* get_option()'s own $default is only ever used when the option row
		 * is entirely absent. Missing keys are merged against those defaults
		 * explicitly so the Settings screen reflects the real recommended
		 * defaults rather than an unintentional blank/unchecked state. */
		$defaults          = self::settings_defaults();
		$settings          = array_merge( $defaults, get_option( self::SETTINGS_OPTION, $defaults ) );
		$shortcode         = isset( $settings['form_shortcode'] ) ? $settings['form_shortcode'] : '';
		$hero_video_media_id = isset( $settings['hero_video_media_id'] ) ? absint( $settings['hero_video_media_id'] ) : 0;
		$hero_video_filename = '';
		$hero_video_warning  = '';
		if ( $hero_video_media_id ) {
			$attached_file = get_attached_file( $hero_video_media_id );
			$hero_video_filename = $attached_file ? basename( $attached_file ) : '';
			$hero_video_mime = get_post_mime_type( $hero_video_media_id );
			if ( ! in_array( (string) $hero_video_mime, array( 'video/mp4', 'video/webm' ), true ) ) {
				$hero_video_warning = __( 'File yang dipilih bukan MP4/WebM. Home akan tetap menggunakan latar putih.', 'gloskin-site-core' );
			} elseif ( '' === $hero_video_filename ) {
				$hero_video_warning = __( 'Attachment video tidak dapat ditemukan. Home akan tetap menggunakan latar putih.', 'gloskin-site-core' );
			}
		}
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
							<div class="notice notice-info inline"><p><?php echo esc_html__( 'Design direction is fixed to Medical Professional and managed by the developer. No editor setting is available.', 'gloskin-site-core' ); ?></p></div>
						</section>
						<section class="gloskin-admin-card" id="gloskin-admin-panel-header" role="tabpanel" aria-labelledby="gloskin-admin-tab-header" data-gloskin-admin-panel="header">
							<h2 class="gloskin-admin-card__title"><?php echo esc_html__( 'Header layout', 'gloskin-site-core' ); ?></h2>
							<p class="gloskin-admin-card__hint"><?php echo esc_html__( 'Komposisi header ditetapkan oleh developer. Tidak ada pengaturan yang tersedia di sini.', 'gloskin-site-core' ); ?></p>
							<div class="notice notice-info inline"><p><?php echo esc_html__( 'Header layout is fixed and managed by the developer. No editor setting is available.', 'gloskin-site-core' ); ?></p></div>
						</section>
						<section class="gloskin-admin-card" id="gloskin-admin-panel-hero" role="tabpanel" aria-labelledby="gloskin-admin-tab-hero" data-gloskin-admin-panel="hero">
							<h2 class="gloskin-admin-card__title"><?php echo esc_html__( 'Home hero video', 'gloskin-site-core' ); ?></h2>
							<p class="gloskin-admin-card__hint"><?php echo esc_html__( 'Video latar Home diputar otomatis tanpa suara dan memenuhi area hero.', 'gloskin-site-core' ); ?></p>
							<p class="gloskin-admin-field-label"><?php echo esc_html__( 'Background video', 'gloskin-site-core' ); ?></p>
							<div class="gloskin-admin-media-field" data-gloskin-hero-video-field data-empty-label="<?php echo esc_attr__( 'Belum ada video dipilih. Home akan menggunakan latar putih sampai video MP4/WebM dipilih.', 'gloskin-site-core' ); ?>" data-unsupported-label="<?php echo esc_attr__( 'File yang dipilih bukan MP4/WebM. Home akan tetap menggunakan latar putih.', 'gloskin-site-core' ); ?>">
								<input type="hidden" id="gloskin-hero-video-media-id" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[hero_video_media_id]" value="<?php echo esc_attr( $hero_video_media_id ? (string) $hero_video_media_id : '' ); ?>" />
								<p class="gloskin-admin-media-field__filename" id="gloskin-hero-video-media-filename-label" data-gloskin-hero-video-filename><?php echo esc_html( '' !== $hero_video_filename ? $hero_video_filename : __( 'Belum ada video dipilih. Home akan menggunakan latar putih sampai video MP4/WebM dipilih.', 'gloskin-site-core' ) ); ?></p>
								<p class="gloskin-admin-media-field__warning" data-gloskin-hero-video-warning<?php echo '' === $hero_video_warning ? ' hidden' : ''; ?>><?php echo esc_html( $hero_video_warning ); ?></p>
								<p>
									<button type="button" class="button" data-gloskin-video-picker data-target="#gloskin-hero-video-media-id" data-title="<?php echo esc_attr__( 'Pilih video latar Home', 'gloskin-site-core' ); ?>" data-button="<?php echo esc_attr__( 'Gunakan video ini', 'gloskin-site-core' ); ?>" data-label-choose="<?php echo esc_attr__( 'Choose Video', 'gloskin-site-core' ); ?>" data-label-replace="<?php echo esc_attr__( 'Replace Video', 'gloskin-site-core' ); ?>"><?php echo esc_html( $hero_video_media_id ? __( 'Replace Video', 'gloskin-site-core' ) : __( 'Choose Video', 'gloskin-site-core' ) ); ?></button>
									<button type="button" class="button button-link-delete" data-gloskin-video-remove data-target="#gloskin-hero-video-media-id" <?php echo $hero_video_media_id ? '' : 'hidden'; ?>><?php echo esc_html__( 'Remove Video', 'gloskin-site-core' ); ?></button>
								</p>
							</div>
							<p class="gloskin-admin-card__hint"><?php echo esc_html__( 'Format yang didukung: MP4 dan/atau WebM dari Media Library.', 'gloskin-site-core' ); ?></p>
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
		add_meta_box( 'gloskin-promo-details', __( 'Promo Details', 'gloskin-site-core' ), array( $this, 'render_promo_meta_box' ), Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE, 'normal', 'default' );
		add_meta_box( 'gloskin-testimonial-details', __( 'Testimonial Details', 'gloskin-site-core' ), array( $this, 'render_testimonial_meta_box' ), Gloskin_Site_Core_Content_Service::TESTIMONIAL_POST_TYPE, 'normal', 'default' );
		add_meta_box( 'gloskin-achievement-details', __( 'Achievement Details', 'gloskin-site-core' ), array( $this, 'render_achievement_meta_box' ), Gloskin_Site_Core_Content_Service::ACHIEVEMENT_POST_TYPE, 'normal', 'default' );
	}

	public function render_treatment_meta_box( $post ) {
		$this->nonce();
		$this->textarea_field( $post, 'gloskin_summary', __( 'Summary', 'gloskin-site-core' ) );
		$this->textarea_field( $post, 'gloskin_benefits', __( 'Benefits', 'gloskin-site-core' ), 8 );
		$this->textarea_field( $post, 'gloskin_contraindications', __( 'Contraindications', 'gloskin-site-core' ), 8 );
		$this->url_field( $post, 'gloskin_booking_target', __( 'Booking target', 'gloskin-site-core' ) );
		$this->relationship_field( $post, 'gloskin_clinic_ids', Gloskin_Site_Core_Content_Service::CLINIC_POST_TYPE, __( 'Related clinics', 'gloskin-site-core' ) );
		$this->relationship_field( $post, 'gloskin_doctor_ids', Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE, __( 'Related doctors', 'gloskin-site-core' ) );
		$this->checkbox_field( $post, 'gloskin_treatment_feature_on_home', __( 'Feature on Home (max 3 shown; use order in title if multiple)', 'gloskin-site-core' ) );
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

	public function render_promo_meta_box( $post ) {
		$this->nonce();
		$this->text_field( $post, 'gloskin_promo_eyebrow', __( 'Eyebrow label', 'gloskin-site-core' ) );
		$this->textarea_field( $post, 'gloskin_promo_summary', __( 'Summary copy', 'gloskin-site-core' ), 4 );
		$this->text_field( $post, 'gloskin_promo_cta_label', __( 'CTA button label', 'gloskin-site-core' ) );
		$this->url_field( $post, 'gloskin_promo_cta_url', __( 'CTA URL', 'gloskin-site-core' ) );
		$this->text_field( $post, 'gloskin_promo_start_date', __( 'Start date (YYYY-MM-DD, site timezone, leave blank for no limit)', 'gloskin-site-core' ) );
		$this->text_field( $post, 'gloskin_promo_end_date', __( 'End date (YYYY-MM-DD, inclusive, site timezone, leave blank for no limit)', 'gloskin-site-core' ) );
		$this->checkbox_field( $post, 'gloskin_promo_active', __( 'Active (eligible for display when within date range)', 'gloskin-site-core' ) );
		$this->text_field( $post, 'gloskin_promo_order', __( 'Display order (lower = first; leave blank = unspecified)', 'gloskin-site-core' ) );
		echo '<p class="description">' . esc_html__( 'Featured Image: use the standard Featured Image panel for the promo banner image.', 'gloskin-site-core' ) . '</p>';
	}

	public function render_testimonial_meta_box( $post ) {
		$this->nonce();
		$this->text_field( $post, 'gloskin_testimonial_attribution', __( 'Name / attribution', 'gloskin-site-core' ) );
		$this->text_field( $post, 'gloskin_testimonial_subtitle', __( 'Subtitle / type (e.g. Pasien Gloskin)', 'gloskin-site-core' ) );
		$this->textarea_field( $post, 'gloskin_testimonial_source_note', __( 'Source note (optional, for internal reference)', 'gloskin-site-core' ), 3 );
		$this->checkbox_field( $post, 'gloskin_testimonial_active', __( 'Active (eligible for display)', 'gloskin-site-core' ) );
		$this->text_field( $post, 'gloskin_testimonial_order', __( 'Display order (lower = first)', 'gloskin-site-core' ) );
		echo '<p class="description">' . esc_html__( 'The quote body is the post excerpt. Featured Image: use the standard Featured Image panel.', 'gloskin-site-core' ) . '</p>';
	}

	public function render_achievement_meta_box( $post ) {
		$this->nonce();
		$this->text_field( $post, 'gloskin_achievement_issuer', __( 'Issuing body / organization', 'gloskin-site-core' ) );
		$this->text_field( $post, 'gloskin_achievement_year', __( 'Year / date (e.g. 2024 or 2024-06)', 'gloskin-site-core' ) );
		$this->url_field( $post, 'gloskin_achievement_source_url', __( 'Source URL (optional verification link)', 'gloskin-site-core' ) );
		$this->checkbox_field( $post, 'gloskin_achievement_active', __( 'Active (eligible for display)', 'gloskin-site-core' ) );
		$this->checkbox_field( $post, 'gloskin_achievement_feature_on_home', __( 'Feature on Home page', 'gloskin-site-core' ) );
		$this->text_field( $post, 'gloskin_achievement_order', __( 'Display order (lower = first)', 'gloskin-site-core' ) );
		echo '<p class="description">' . esc_html__( 'Featured Image: use the standard Featured Image panel for the award badge/logo.', 'gloskin-site-core' ) . '</p>';
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
			echo '<hr><h3>' . esc_html__( 'About Founder', 'gloskin-site-core' ) . '</h3>';
			$this->text_field( $post, 'gloskin_about_founder_name', __( 'Founder name', 'gloskin-site-core' ) );
			$this->text_field( $post, 'gloskin_about_founder_role', __( 'Founder role / title', 'gloskin-site-core' ) );
			$this->textarea_field( $post, 'gloskin_about_founder_story', __( 'Founder story', 'gloskin-site-core' ), 8 );
			$founder_media_id = absint( get_post_meta( $post->ID, 'gloskin_about_founder_media_id', true ) );
			echo '<p><label for="gloskin-about-founder-media-id"><strong>' . esc_html__( 'Founder photo (media ID)', 'gloskin-site-core' ) . '</strong></label></p><div class="gloskin-admin-media-row"><input class="widefat" id="gloskin-about-founder-media-id" name="gloskin_about_founder_media_id" value="' . esc_attr( $founder_media_id ? (string) $founder_media_id : '' ) . '" /><button type="button" class="button" data-gloskin-media-picker data-target="#gloskin-about-founder-media-id" data-multiple="false">' . esc_html__( 'Choose image', 'gloskin-site-core' ) . '</button></div><p class="description">' . esc_html__( 'Founder section only renders when name and role are both present.', 'gloskin-site-core' ) . '</p>';
		}
		if ( 'home' === $post->post_name ) {
			echo '<hr><h3>' . esc_html__( 'Why Gloskin', 'gloskin-site-core' ) . '</h3>';
			echo '<p class="description">' . esc_html__( 'Leave fields blank to use built-in factual defaults.', 'gloskin-site-core' ) . '</p>';
			$this->text_field( $post, 'gloskin_why_heading', __( 'Section heading', 'gloskin-site-core' ) );
			$this->textarea_field( $post, 'gloskin_why_lead', __( 'Lead paragraph', 'gloskin-site-core' ), 3 );
			$this->text_field( $post, 'gloskin_why_primary_title', __( 'Primary card title', 'gloskin-site-core' ) );
			$this->textarea_field( $post, 'gloskin_why_primary_copy', __( 'Primary card copy', 'gloskin-site-core' ), 5 );
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
			Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE => array(
				'strings'  => array( 'gloskin_summary', 'gloskin_benefits', 'gloskin_contraindications', 'gloskin_booking_target', 'gloskin_treatment_feature_on_home' ),
				'arrays'   => array( 'gloskin_clinic_ids', 'gloskin_doctor_ids' ),
				'integers' => array(),
			),
			Gloskin_Site_Core_Content_Service::CLINIC_POST_TYPE => array(
				'strings'  => array( 'gloskin_address', 'gloskin_phone_display', 'gloskin_phone_uri', 'gloskin_whatsapp_number', 'gloskin_whatsapp_message', 'gloskin_operating_hours', 'gloskin_map_url', 'gloskin_map_embed', 'gloskin_short_location' ),
				'arrays'   => array( 'gloskin_gallery_image_ids' ),
				'integers' => array(),
			),
			Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE => array(
				'strings'  => array( 'gloskin_degree_title', 'gloskin_specialization', 'gloskin_sip_number', 'gloskin_credentials', 'gloskin_profile', 'gloskin_schedule', 'gloskin_booking_target' ),
				'arrays'   => array( 'gloskin_branch_ids' ),
				'integers' => array(),
			),
			'page' => array(
				'strings'  => array( 'gloskin_woo_category_slug', 'gloskin_about_vision', 'gloskin_about_mission', 'gloskin_about_values', 'gloskin_about_founder_name', 'gloskin_about_founder_role', 'gloskin_about_founder_story', 'gloskin_hero_heading', 'gloskin_hero_copy', 'gloskin_hero_cta_label', 'gloskin_hero_cta_url', 'gloskin_why_heading', 'gloskin_why_lead', 'gloskin_why_primary_title', 'gloskin_why_primary_copy' ),
				'arrays'   => array(),
				'integers' => array( 'gloskin_hero_media_id', 'gloskin_about_founder_media_id' ),
			),
			Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE => array(
				'strings'  => array( 'gloskin_promo_eyebrow', 'gloskin_promo_summary', 'gloskin_promo_cta_label', 'gloskin_promo_cta_url', 'gloskin_promo_start_date', 'gloskin_promo_end_date', 'gloskin_promo_active', 'gloskin_promo_order' ),
				'arrays'   => array(),
				'integers' => array(),
			),
			Gloskin_Site_Core_Content_Service::TESTIMONIAL_POST_TYPE => array(
				'strings'  => array( 'gloskin_testimonial_attribution', 'gloskin_testimonial_subtitle', 'gloskin_testimonial_source_note', 'gloskin_testimonial_active', 'gloskin_testimonial_order' ),
				'arrays'   => array(),
				'integers' => array(),
			),
			Gloskin_Site_Core_Content_Service::ACHIEVEMENT_POST_TYPE => array(
				'strings'  => array( 'gloskin_achievement_issuer', 'gloskin_achievement_year', 'gloskin_achievement_source_url', 'gloskin_achievement_active', 'gloskin_achievement_feature_on_home', 'gloskin_achievement_order' ),
				'arrays'   => array(),
				'integers' => array(),
			),
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

	/* -----------------------------------------------------------------
	 * Treatment Consultation: product-family list filter/editor field
	 * (docs/task-treatment-consultation-commerce-discovery.md section 5.2/
	 * 5.3). Woo product_cat is never touched; classification lives solely
	 * in gloskin_product_family term relationships.
	 * ----------------------------------------------------------------- */

	/**
	 * @return void
	 */
	public function render_product_family_filter() {
		global $typenow;
		if ( 'product' !== $typenow || ! taxonomy_exists( Gloskin_Site_Core_Content_Service::FAMILY_TAXONOMY ) ) {
			return;
		}
		$current = isset( $_GET['gloskin_product_family'] ) ? sanitize_key( wp_unslash( $_GET['gloskin_product_family'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list-table filter, same pattern as Woo's own native product filters.
		?>
		<select name="gloskin_product_family" id="gloskin-product-family-filter">
			<option value=""><?php echo esc_html__( 'Jenis Produk: Semua', 'gloskin-site-core' ); ?></option>
			<option value="skincare" <?php selected( $current, 'skincare' ); ?>><?php echo esc_html__( 'Skincare', 'gloskin-site-core' ); ?></option>
			<option value="treatment" <?php selected( $current, 'treatment' ); ?>><?php echo esc_html__( 'Perawatan', 'gloskin-site-core' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Skincare = explicit skincare term OR no family term assigned at all
	 * (legacy/unclassified backward-compat, per section 4.1). Perawatan =
	 * explicit treatment term only. Native list-table query lifecycle,
	 * same tax_query extension point Woo's own filters use -- no second
	 * product list table.
	 *
	 * @param WP_Query $query Current admin query.
	 * @return void
	 */
	public function apply_product_family_filter( $query ) {
		if ( ! is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query() ) {
			return;
		}
		if ( 'product' !== $query->get( 'post_type' ) ) {
			return;
		}
		$family = isset( $_GET['gloskin_product_family'] ) ? sanitize_key( wp_unslash( $_GET['gloskin_product_family'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list-table filter.
		if ( '' === $family || ! in_array( $family, array( 'skincare', 'treatment' ), true ) ) {
			return;
		}
		$existing = $query->get( 'tax_query' );
		$existing = is_array( $existing ) ? $existing : array();
		if ( 'treatment' === $family ) {
			$existing[] = array(
				'taxonomy' => Gloskin_Site_Core_Content_Service::FAMILY_TAXONOMY,
				'field'    => 'slug',
				'terms'    => array( 'treatment' ),
			);
		} else {
			$existing[] = array(
				'relation' => 'OR',
				array( 'taxonomy' => Gloskin_Site_Core_Content_Service::FAMILY_TAXONOMY, 'field' => 'slug', 'terms' => array( 'skincare' ) ),
				array( 'taxonomy' => Gloskin_Site_Core_Content_Service::FAMILY_TAXONOMY, 'operator' => 'NOT EXISTS' ),
			);
		}
		$query->set( 'tax_query', $existing ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- one native list-table filter, mirrors Woo's own product filter pattern.
	}

	/**
	 * One friendly "Jenis Produk" field on the classic Woo Product data
	 * panel (General tab) using Woo's own supported hook surface. Persists
	 * through wp_set_object_terms() only -- no duplicate family meta. Inert
	 * (and documented as such) on the newer Product Block Editor, which
	 * does not fire this classic hook; no DOM-hack is added to compensate.
	 *
	 * @return void
	 */
	public function render_product_family_field() {
		global $post;
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		$current = 'skincare';
		$terms   = get_the_terms( $post->ID, Gloskin_Site_Core_Content_Service::FAMILY_TAXONOMY );
		if ( is_array( $terms ) && ! empty( $terms ) ) {
			$current = (string) $terms[0]->slug;
		}
		echo '<div class="options_group gloskin-admin-product-family">';
		echo '<p class="form-field"><label>' . esc_html__( 'Jenis Produk', 'gloskin-site-core' ) . '</label>';
		echo '<label style="margin-right:16px;display:inline-block"><input type="radio" name="gloskin_product_family" value="skincare" ' . checked( $current, 'skincare', false ) . ' /> ' . esc_html__( 'Skincare', 'gloskin-site-core' ) . '</label>';
		echo '<label style="display:inline-block"><input type="radio" name="gloskin_product_family" value="treatment" ' . checked( $current, 'treatment', false ) . ' /> ' . esc_html__( 'Perawatan', 'gloskin-site-core' ) . '</label>';
		echo '</p></div>';
	}

	/**
	 * @param int $post_id Product ID (Woo already verified nonce/capability
	 *                     before firing woocommerce_process_product_meta).
	 * @return void
	 */
	public function save_product_family_field( $post_id ) {
		if ( ! taxonomy_exists( Gloskin_Site_Core_Content_Service::FAMILY_TAXONOMY ) || ! current_user_can( 'edit_product', $post_id ) ) {
			return;
		}
		$family = isset( $_POST['gloskin_product_family'] ) ? sanitize_key( wp_unslash( $_POST['gloskin_product_family'] ) ) : 'skincare';
		if ( ! in_array( $family, array( 'skincare', 'treatment' ), true ) ) {
			$family = 'skincare';
		}
		wp_set_object_terms( $post_id, $family, Gloskin_Site_Core_Content_Service::FAMILY_TAXONOMY, false );
	}

	/* -----------------------------------------------------------------
	 * Treatment Consultation workspace: one submenu, four internal tabs
	 * (section 5.4).
	 * ----------------------------------------------------------------- */

	/**
	 * @return void
	 */
	public function register_consultation_menu() {
		if ( ! post_type_exists( 'product' ) ) {
			return;
		}
		add_submenu_page(
			'edit.php?post_type=product',
			__( 'Konsultasi Perawatan', 'gloskin-site-core' ),
			__( 'Konsultasi Perawatan', 'gloskin-site-core' ),
			self::CONSULTATION_CAPABILITY,
			self::CONSULTATION_SLUG,
			array( $this, 'render_consultation_workspace' )
		);
	}

	/**
	 * @return void
	 */
	public function render_consultation_workspace() {
		if ( ! current_user_can( self::CONSULTATION_CAPABILITY ) ) {
			wp_die( esc_html__( 'Anda tidak memiliki izin untuk membuka Konsultasi Perawatan.', 'gloskin-site-core' ) );
		}
		$tabs = array(
			'ringkasan' => __( 'Ringkasan', 'gloskin-site-core' ),
			'keluhan'   => __( 'Keluhan', 'gloskin-site-core' ),
			'pertanyaan' => __( 'Pertanyaan', 'gloskin-site-core' ),
			'pemetaan'  => __( 'Pemetaan Produk', 'gloskin-site-core' ),
		);
		$active = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'ringkasan'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab selection.
		if ( ! isset( $tabs[ $active ] ) ) {
			$active = 'ringkasan';
		}
		$base_url = admin_url( 'edit.php?post_type=product&page=' . self::CONSULTATION_SLUG );
		?>
		<div class="wrap" data-gloskin-consultation-workspace>
			<h1><?php echo esc_html__( 'Konsultasi Perawatan', 'gloskin-site-core' ); ?></h1>
			<?php if ( isset( $_GET['gloskin_notice'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $this->consultation_notice_message( sanitize_key( wp_unslash( $_GET['gloskin_notice'] ) ) ) ); ?></p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['gloskin_error'] ) ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['gloskin_error'] ) ) ); ?></p></div>
			<?php endif; ?>
			<nav class="gloskin-consultation-tabs" aria-label="<?php echo esc_attr__( 'Bagian Konsultasi Perawatan', 'gloskin-site-core' ); ?>">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'tab', $key, $base_url ) ); ?>" class="gloskin-consultation-tabs__item<?php echo $active === $key ? ' is-active' : ''; ?>"<?php echo $active === $key ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<div class="gloskin-admin-consultation-panel">
				<?php
				switch ( $active ) {
					case 'keluhan':
						$this->render_consultation_keluhan();
						break;
					case 'pertanyaan':
						$this->render_consultation_pertanyaan();
						break;
					case 'pemetaan':
						$this->render_consultation_pemetaan();
						break;
					default:
						$this->render_consultation_ringkasan();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * @param string $key Notice key.
	 * @return string
	 */
	private function consultation_notice_message( $key ) {
		$messages = array(
			'concern-saved'   => __( 'Keluhan disimpan.', 'gloskin-site-core' ),
			'concern-deleted' => __( 'Keluhan dihapus.', 'gloskin-site-core' ),
			'mapping-saved'   => __( 'Pemetaan produk disimpan.', 'gloskin-site-core' ),
			'demo-imported'   => __( 'Data demo konsultasi berhasil diimpor.', 'gloskin-site-core' ),
		);
		return isset( $messages[ $key ] ) ? $messages[ $key ] : '';
	}

	/**
	 * Admin "is this consultation ready?" view (section 5.4 Ringkasan).
	 *
	 * @return void
	 */
	private function render_consultation_ringkasan() {
		$paths = get_terms( array( 'taxonomy' => Gloskin_Site_Core_Content_Service::CONSULTATION_TAXONOMY, 'hide_empty' => false ) );
		$paths = is_wp_error( $paths ) ? array() : $paths;
		$concern_count = wp_count_terms( array( 'taxonomy' => Gloskin_Site_Core_Content_Service::CONCERN_TAXONOMY, 'hide_empty' => false ) );
		$concern_count = is_wp_error( $concern_count ) ? 0 : (int) $concern_count;
		$question_counts = wp_count_posts( Gloskin_Site_Core_Content_Service::QUESTION_POST_TYPE );
		$published_questions = $question_counts && isset( $question_counts->publish ) ? (int) $question_counts->publish : 0;

		$treatment_count = 0;
		$unmapped_count  = 0;
		if ( ! class_exists( 'Gloskin_Site_Core_WooCommerce_Adapter' ) ) {
			require_once __DIR__ . '/class-gloskin-site-core-woocommerce-adapter.php';
		}
		if ( class_exists( 'Gloskin_Site_Core_WooCommerce_Adapter' ) && function_exists( 'wc_get_products' ) ) {
			$adapter = new Gloskin_Site_Core_WooCommerce_Adapter();
			$treatment_products = $adapter->treatment_products_with_concerns();
			$treatment_count = count( $treatment_products );
			foreach ( $treatment_products as $item ) {
				if ( empty( $item['concern_ids'] ) ) {
					$unmapped_count++;
				}
			}
		}

		$orphan_answers = $this->count_orphan_answer_mappings();

		echo '<div class="gloskin-consultation-metrics">';
		$this->render_readiness_card( __( 'Jalur Konsultasi', 'gloskin-site-core' ), count( $paths ) . ' / ' . Gloskin_Site_Core_Content_Service::PATH_MIN_VALID, count( $paths ) < Gloskin_Site_Core_Content_Service::PATH_MIN_VALID );
		$this->render_readiness_card( __( 'Produk Perawatan', 'gloskin-site-core' ), (string) $treatment_count, 0 === $treatment_count );
		$this->render_readiness_card( __( 'Keluhan', 'gloskin-site-core' ), (string) $concern_count, 0 === $concern_count );
		$this->render_readiness_card( __( 'Pertanyaan Terpublikasi (data admin)', 'gloskin-site-core' ), $published_questions . ' / ' . Gloskin_Site_Core_Content_Service::QUESTION_MIN_PUBLISHED, false, true );
		$this->render_readiness_card( __( 'Produk Belum Dipetakan', 'gloskin-site-core' ), (string) $unmapped_count, $unmapped_count > 0 );
		$this->render_readiness_card( __( 'Jawaban Tidak Valid/Orphan', 'gloskin-site-core' ), (string) $orphan_answers, $orphan_answers > 0 );
		echo '</div>';

		if ( count( $paths ) ) {
			echo '<h2 class="gloskin-consultation-section-title">' . esc_html__( 'Jalur Konsultasi', 'gloskin-site-core' ) . '</h2><div class="gloskin-consultation-path-cards">';
			foreach ( $paths as $path ) {
				echo '<div class="gloskin-consultation-path-card"><h3>' . esc_html( $path->name ) . '</h3><p>' . esc_html( $path->slug ) . '</p></div>';
			}
			echo '</div>';
		}

		echo '<h2 class="gloskin-consultation-section-title">' . esc_html__( 'Data & Import', 'gloskin-site-core' ) . '</h2>';
		echo '<div class="gloskin-consultation-import-cards">';
		$this->render_demo_import_card();
		$this->render_sample_import_card();
		echo '</div>';
	}

	/**
	 * @param string $label Card label.
	 * @param string $value Card value.
	 * @param bool   $warn Whether to visually flag this as needing attention.
	 * @return void
	 */
	private function render_readiness_card( $label, $value, $warn, $informational = false ) {
		$class = 'gloskin-consultation-metric-card' . ( $informational ? ' is-info' : ( $warn ? ' is-warning' : ' is-ready' ) );
		echo '<div class="' . esc_attr( $class ) . '"><p class="gloskin-consultation-metric-card__label">' . esc_html( $label ) . '</p><p class="gloskin-consultation-metric-card__value">' . esc_html( $value ) . '</p></div>';
	}

	/**
	 * Counts published gloskin_question answer options whose concern_id no
	 * longer resolves to a real gloskin_concern term -- raw meta is read
	 * directly (not through the write-time sanitizer) because a concern can
	 * be deleted after a question was saved, which the sanitizer never sees.
	 *
	 * @return int
	 */
	private function count_orphan_answer_mappings() {
		$questions = get_posts( array( 'post_type' => Gloskin_Site_Core_Content_Service::QUESTION_POST_TYPE, 'post_status' => array( 'publish', 'draft' ), 'posts_per_page' => -1, 'fields' => 'ids' ) );
		$orphans = 0;
		foreach ( $questions as $question_id ) {
			$answers = get_post_meta( $question_id, Gloskin_Site_Core_Content_Service::ANSWER_META_KEY, true );
			if ( ! is_array( $answers ) ) {
				continue;
			}
			foreach ( $answers as $answer ) {
				$concern_id = isset( $answer['concern_id'] ) ? absint( $answer['concern_id'] ) : 0;
				if ( ! $concern_id || ! term_exists( $concern_id, Gloskin_Site_Core_Content_Service::CONCERN_TAXONOMY ) ) {
					$orphans++;
				}
			}
		}
		return $orphans;
	}

	/**
	 * Demo import card (Data & Import): an explicit privileged admin
	 * workflow, not an environment gate -- capability + nonce + the
	 * explicit synthetic-data confirmation checkbox below are the entire
	 * access control. Once consumed, no re-import control is offered; the
	 * owner is instead handed straight to the two real destinations the
	 * imported data feeds.
	 *
	 * @return void
	 */
	private function render_demo_import_card() {
		require_once __DIR__ . '/class-gloskin-site-core-consultation-demo-importer.php';
		$state = Gloskin_Site_Core_Consultation_Demo_Importer::state();
		echo '<div class="gloskin-consultation-import-card">';
		echo '<h3>' . esc_html__( 'Data Demo Konsultasi', 'gloskin-site-core' ) . '</h3>';
		if ( 'consumed' === $state['status'] ) {
			echo '<p class="gloskin-consultation-import-card__status is-done">' . esc_html__( '✓ Import selesai', 'gloskin-site-core' ) . '</p>';
			printf(
				'<p>%s</p>',
				esc_html(
					sprintf(
						/* translators: %1$d: paths; %2$d: concerns; %3$d: questions; %4$d: products. */
						__( '%1$d jalur, %2$d+ keluhan, %3$d+ pertanyaan, %4$d produk perawatan', 'gloskin-site-core' ),
						(int) $state['paths'],
						(int) $state['concerns'],
						(int) $state['questions'],
						(int) $state['products']
					)
				)
			);
			$mapping_url = add_query_arg( array( 'tab' => 'pemetaan' ), admin_url( 'edit.php?post_type=product&page=' . self::CONSULTATION_SLUG ) );
			echo '<p class="gloskin-consultation-import-card__links">';
			echo '<a class="gloskin-consultation-action gloskin-consultation-action--secondary" href="' . esc_url( $mapping_url ) . '">' . esc_html__( 'Pemetaan Produk', 'gloskin-site-core' ) . '</a> ';
			echo '<a class="gloskin-consultation-action gloskin-consultation-action--secondary" href="' . esc_url( admin_url( 'edit.php?post_type=product&gloskin_product_family=treatment' ) ) . '">' . esc_html__( 'Semua Produk Perawatan', 'gloskin-site-core' ) . '</a>';
			echo '</p>';
			echo '</div>';
			return;
		}
		if ( ! empty( $state['last_error'] ) ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( $state['last_error'] ) . '</p></div>';
		}
		?>
		<p><?php echo esc_html__( 'Impor 4 jalur konsultasi, 10+ keluhan, 13+ pertanyaan, dan 8 produk perawatan sintetis.', 'gloskin-site-core' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( self::DEMO_IMPORT_NONCE ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( self::DEMO_IMPORT_ACTION ); ?>" />
			<p class="gloskin-consultation-confirm-field">
				<label>
					<input type="checkbox" name="confirm_demo_import" value="1" required />
					<?php echo esc_html__( 'Saya memahami bahwa data demo sintetis akan dibuat pada situs ini.', 'gloskin-site-core' ); ?>
				</label>
			</p>
			<button type="submit" class="gloskin-consultation-action gloskin-consultation-action--primary"><?php echo esc_html( 'pending' === $state['status'] && $state['processed'] > 0 ? __( 'Lanjutkan Import Demo', 'gloskin-site-core' ) : __( 'Import Data Demo', 'gloskin-site-core' ) ); ?></button>
		</form>
		<?php
		echo '</div>';
	}

	/**
	 * Sample Product Catalog card (Data & Import): a thin discoverability
	 * shortcut only -- reads the existing
	 * Gloskin_Site_Core_Sample_Product_Importer::get_summary() and links to
	 * its existing dedicated screen/workflow. No second importer, no
	 * duplicated import logic.
	 *
	 * @return void
	 */
	private function render_sample_import_card() {
		echo '<div class="gloskin-consultation-import-card">';
		echo '<h3>' . esc_html__( 'Sample Product Catalog', 'gloskin-site-core' ) . '</h3>';
		if ( ! current_user_can( self::MIGRATION_CAPABILITY ) || '' === $this->plugin_file ) {
			echo '<p>' . esc_html__( 'Import sample product tidak tersedia untuk akun ini.', 'gloskin-site-core' ) . '</p></div>';
			return;
		}
		$summary   = $this->sample_importer()->get_summary();
		$detection = isset( $summary['detection'] ) ? (string) $summary['detection'] : 'none';
		if ( 'consumed' === $detection ) {
			echo '<p class="gloskin-consultation-import-card__status is-done">' . esc_html__( 'Import selesai', 'gloskin-site-core' ) . '</p>';
		} elseif ( in_array( $detection, array( 'pending', 'failed', 'running', 'verifying' ), true ) ) {
			$processed = isset( $summary['processed_products'] ) ? (int) $summary['processed_products'] : 0;
			$expected  = isset( $summary['expected_products'] ) ? (int) $summary['expected_products'] : 13;
			echo '<p class="gloskin-consultation-import-card__status">' . esc_html(
				sprintf(
					/* translators: %1$s: status key (pending/failed/running/verifying); %2$d: processed products; %3$d: expected products. */
					__( 'Status: %1$s (%2$d/%3$d produk)', 'gloskin-site-core' ),
					$detection,
					$processed,
					$expected
				)
			) . '</p>';
			echo '<p><a class="gloskin-consultation-action gloskin-consultation-action--primary" href="' . esc_url( admin_url( 'admin.php?page=' . self::MIGRATION_SLUG ) ) . '">' . esc_html__( 'Buka Import Sample Products', 'gloskin-site-core' ) . '</a></p>';
		} else {
			echo '<p>' . esc_html__( 'Bundle sample product tidak tersedia.', 'gloskin-site-core' ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Friendly gloskin_concern term CRUD (section 5.4 Keluhan). Deletion is
	 * blocked while the concern is still referenced by a product mapping or
	 * a question answer -- never silently removed.
	 *
	 * @return void
	 */
	private function render_consultation_keluhan() {
		$concerns = get_terms( array( 'taxonomy' => Gloskin_Site_Core_Content_Service::CONCERN_TAXONOMY, 'hide_empty' => false ) );
		$concerns = is_wp_error( $concerns ) ? array() : $concerns;
		?>
		<h2><?php echo esc_html__( 'Tambah Keluhan', 'gloskin-site-core' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( self::CONCERN_NONCE ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( self::CONCERN_ACTION ); ?>" />
			<input type="text" name="concern_name" placeholder="<?php echo esc_attr__( 'Nama keluhan, mis. Jerawat Aktif', 'gloskin-site-core' ); ?>" required />
			<button type="submit" class="gloskin-consultation-action gloskin-consultation-action--secondary"><?php echo esc_html__( 'Tambah', 'gloskin-site-core' ); ?></button>
		</form>
		<table class="widefat striped gloskin-consultation-concerns-table">
			<thead><tr>
				<th><?php echo esc_html__( 'Nama', 'gloskin-site-core' ); ?></th>
				<th><?php echo esc_html__( 'Slug', 'gloskin-site-core' ); ?></th>
				<th><?php echo esc_html__( 'Produk Terpetakan', 'gloskin-site-core' ); ?></th>
				<th><?php echo esc_html__( 'Referensi Jawaban', 'gloskin-site-core' ); ?></th>
				<th></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $concerns as $concern ) :
				$mapped = (int) $concern->count;
				$answer_refs = $this->count_answer_references( $concern->term_id );
				?>
				<tr>
					<td>
						<form class="gloskin-consultation-inline-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( self::CONCERN_NONCE ); ?>
							<input type="hidden" name="action" value="<?php echo esc_attr( self::CONCERN_ACTION ); ?>" />
							<input type="hidden" name="concern_id" value="<?php echo esc_attr( (string) $concern->term_id ); ?>" />
							<input type="text" name="concern_name" value="<?php echo esc_attr( $concern->name ); ?>" />
							<button type="submit" class="gloskin-consultation-action gloskin-consultation-action--primary gloskin-consultation-action--small"><?php echo esc_html__( 'Simpan', 'gloskin-site-core' ); ?></button>
						</form>
					</td>
					<td><?php echo esc_html( $concern->slug ); ?></td>
					<td><?php echo esc_html( (string) $mapped ); ?></td>
					<td><?php echo esc_html( (string) $answer_refs ); ?></td>
					<td>
						<?php if ( 0 === $mapped && 0 === $answer_refs ) : ?>
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . self::CONCERN_DELETE_ACTION . '&concern_id=' . $concern->term_id ), self::CONCERN_DELETE_NONCE ) ); ?>" class="gloskin-consultation-action gloskin-consultation-action--danger gloskin-consultation-action--small" onclick="return confirm('<?php echo esc_js( __( 'Hapus keluhan ini?', 'gloskin-site-core' ) ); ?>')"><?php echo esc_html__( 'Hapus', 'gloskin-site-core' ); ?></a>
						<?php else : ?>
							<span class="description"><?php echo esc_html__( 'Masih direferensikan', 'gloskin-site-core' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			<?php if ( ! $concerns ) : ?><tr><td colspan="5"><?php echo esc_html__( 'Belum ada keluhan.', 'gloskin-site-core' ); ?></td></tr><?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * @param int $concern_id Concern term ID.
	 * @return int
	 */
	private function count_answer_references( $concern_id ) {
		$questions = get_posts( array( 'post_type' => Gloskin_Site_Core_Content_Service::QUESTION_POST_TYPE, 'post_status' => array( 'publish', 'draft' ), 'posts_per_page' => -1, 'fields' => 'ids' ) );
		$count = 0;
		foreach ( $questions as $question_id ) {
			$answers = get_post_meta( $question_id, Gloskin_Site_Core_Content_Service::ANSWER_META_KEY, true );
			if ( ! is_array( $answers ) ) {
				continue;
			}
			foreach ( $answers as $answer ) {
				if ( isset( $answer['concern_id'] ) && absint( $answer['concern_id'] ) === absint( $concern_id ) ) {
					$count++;
				}
			}
		}
		return $count;
	}

	/**
	 * @return void
	 */
	public function handle_save_concern() {
		if ( ! current_user_can( self::CONSULTATION_CAPABILITY ) ) {
			wp_die( esc_html__( 'Capability manage_woocommerce diperlukan.', 'gloskin-site-core' ) );
		}
		check_admin_referer( self::CONCERN_NONCE );
		$name = isset( $_POST['concern_name'] ) ? sanitize_text_field( wp_unslash( $_POST['concern_name'] ) ) : '';
		$concern_id = isset( $_POST['concern_id'] ) ? absint( $_POST['concern_id'] ) : 0;
		if ( '' !== $name ) {
			if ( $concern_id && term_exists( $concern_id, Gloskin_Site_Core_Content_Service::CONCERN_TAXONOMY ) ) {
				wp_update_term( $concern_id, Gloskin_Site_Core_Content_Service::CONCERN_TAXONOMY, array( 'name' => $name ) );
			} else {
				wp_insert_term( $name, Gloskin_Site_Core_Content_Service::CONCERN_TAXONOMY );
			}
		}
		$this->redirect_to_consultation_tab( 'keluhan', 'concern-saved' );
	}

	/**
	 * @return void
	 */
	public function handle_delete_concern() {
		if ( ! current_user_can( self::CONSULTATION_CAPABILITY ) ) {
			wp_die( esc_html__( 'Capability manage_woocommerce diperlukan.', 'gloskin-site-core' ) );
		}
		check_admin_referer( self::CONCERN_DELETE_NONCE );
		$concern_id = isset( $_GET['concern_id'] ) ? absint( $_GET['concern_id'] ) : 0;
		$term = $concern_id ? get_term( $concern_id, Gloskin_Site_Core_Content_Service::CONCERN_TAXONOMY ) : null;
		if ( $term instanceof WP_Term && 0 === (int) $term->count && 0 === $this->count_answer_references( $concern_id ) ) {
			wp_delete_term( $concern_id, Gloskin_Site_Core_Content_Service::CONCERN_TAXONOMY );
		}
		$this->redirect_to_consultation_tab( 'keluhan', 'concern-deleted' );
	}

	/**
	 * Compact gloskin_question readiness list (section 5.4 Pertanyaan).
	 * Links to the native hidden CPT edit screen -- no second rich editor.
	 *
	 * @return void
	 */
	private function render_consultation_pertanyaan() {
		$questions = get_posts( array( 'post_type' => Gloskin_Site_Core_Content_Service::QUESTION_POST_TYPE, 'post_status' => array( 'publish', 'draft' ), 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		?>
		<p><a class="gloskin-consultation-action gloskin-consultation-action--primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . Gloskin_Site_Core_Content_Service::QUESTION_POST_TYPE ) ); ?>"><?php echo esc_html__( 'Tambah Pertanyaan', 'gloskin-site-core' ); ?></a></p>
		<table class="widefat striped gloskin-consultation-questions-table">
			<thead><tr>
				<th><?php echo esc_html__( 'Pertanyaan', 'gloskin-site-core' ); ?></th>
				<th><?php echo esc_html__( 'Status', 'gloskin-site-core' ); ?></th>
				<th><?php echo esc_html__( 'Jalur', 'gloskin-site-core' ); ?></th>
				<th><?php echo esc_html__( 'Jumlah Jawaban', 'gloskin-site-core' ); ?></th>
				<th><?php echo esc_html__( 'Kesiapan', 'gloskin-site-core' ); ?></th>
				<th></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $questions as $question ) :
				$answers = get_post_meta( $question->ID, Gloskin_Site_Core_Content_Service::ANSWER_META_KEY, true );
				$answers = is_array( $answers ) ? $answers : array();
				$paths = get_the_terms( $question->ID, Gloskin_Site_Core_Content_Service::CONSULTATION_TAXONOMY );
				$path_names = is_array( $paths ) ? implode( ', ', wp_list_pluck( $paths, 'name' ) ) : '';
				$ready = 'publish' === $question->post_status && count( $answers ) > 0;
				?>
				<tr>
					<td><a href="<?php echo esc_url( get_edit_post_link( $question->ID ) ); ?>"><?php echo esc_html( get_the_title( $question ) ); ?></a></td>
					<td><?php echo esc_html( 'publish' === $question->post_status ? __( 'Aktif', 'gloskin-site-core' ) : __( 'Draft', 'gloskin-site-core' ) ); ?></td>
					<td><?php echo esc_html( $path_names ); ?></td>
					<td><?php echo esc_html( (string) count( $answers ) ); ?></td>
					<td><?php echo $ready ? '✅' : '⚠️'; ?></td>
					<td><a class="gloskin-consultation-action gloskin-consultation-action--quiet gloskin-consultation-action--small" href="<?php echo esc_url( get_edit_post_link( $question->ID ) ); ?>"><?php echo esc_html__( 'Edit', 'gloskin-site-core' ); ?></a></td>
				</tr>
			<?php endforeach; ?>
			<?php if ( ! $questions ) : ?><tr><td colspan="6"><?php echo esc_html__( 'Belum ada pertanyaan.', 'gloskin-site-core' ); ?></td></tr><?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Concern <-> Treatment Product mapping UI (section 8). The visible
	 * checkbox matrix IS the real, canonical, keyboard/no-JS-accessible
	 * form -- gloskin-ui1-admin.js only progressively re-skins it into a
	 * drag-and-drop bucket view; nothing about what gets submitted
	 * changes. Canonical persistence is native taxonomy relationships
	 * only, via wp_set_object_terms() in handle_save_mapping() below --
	 * no second mapping store.
	 *
	 * @return void
	 */
	private function render_consultation_pemetaan() {
		$concerns = get_terms( array( 'taxonomy' => Gloskin_Site_Core_Content_Service::CONCERN_TAXONOMY, 'hide_empty' => false ) );
		$concerns = is_wp_error( $concerns ) ? array() : $concerns;
		require_once __DIR__ . '/class-gloskin-site-core-woocommerce-adapter.php';
		$adapter = new Gloskin_Site_Core_WooCommerce_Adapter();
		$products = $adapter->treatment_products_with_concerns();
		if ( ! $concerns || ! $products ) {
			echo '<p>' . esc_html__( 'Tambahkan minimal satu keluhan dan satu produk perawatan (Jenis Produk: Perawatan) sebelum memetakan.', 'gloskin-site-core' ) . '</p>';
			return;
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-gloskin-mapping-form>
			<?php wp_nonce_field( self::MAPPING_NONCE ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( self::MAPPING_ACTION ); ?>" />
			<p>
				<input type="search" data-gloskin-mapping-search placeholder="<?php echo esc_attr__( 'Cari produk perawatan…', 'gloskin-site-core' ); ?>" />
			</p>
			<div class="gloskin-admin-mapping-grid">
				<?php foreach ( $concerns as $concern ) : ?>
					<fieldset class="gloskin-admin-mapping-bucket" data-gloskin-mapping-bucket>
						<legend><?php echo esc_html( $concern->name ); ?></legend>
						<?php foreach ( $products as $product ) :
							$checked = in_array( (int) $concern->term_id, (array) $product['concern_ids'], true );
							?>
							<label class="gloskin-admin-mapping-item" data-gloskin-mapping-item data-product-name="<?php echo esc_attr( strtolower( $product['name'] ) ); ?>">
								<input type="checkbox" name="mapping[<?php echo esc_attr( (string) $concern->term_id ); ?>][]" value="<?php echo esc_attr( (string) $product['id'] ); ?>" <?php checked( $checked ); ?> />
								<?php echo esc_html( $product['name'] ); ?>
							</label>
						<?php endforeach; ?>
					</fieldset>
				<?php endforeach; ?>
			</div>
			<p><button type="submit" class="gloskin-consultation-action gloskin-consultation-action--primary"><?php echo esc_html__( 'Simpan Pemetaan', 'gloskin-site-core' ); ?></button></p>
		</form>
		<?php
	}

	/**
	 * Canonical persistence remains product taxonomy relationships only
	 * (section 8.1) -- inverts the submitted concern-bucket matrix into
	 * one wp_set_object_terms() call per product, replacing that
	 * product's full gloskin_concern set. Every treatment product is
	 * considered (including ones absent from the matrix entirely, which
	 * correctly become fully unmapped) so a product unchecked from every
	 * bucket is genuinely cleared, not left stale.
	 *
	 * @return void
	 */
	public function handle_save_mapping() {
		if ( ! current_user_can( self::CONSULTATION_CAPABILITY ) ) {
			wp_die( esc_html__( 'Capability manage_woocommerce diperlukan.', 'gloskin-site-core' ) );
		}
		check_admin_referer( self::MAPPING_NONCE );

		require_once __DIR__ . '/class-gloskin-site-core-woocommerce-adapter.php';
		$adapter = new Gloskin_Site_Core_WooCommerce_Adapter();
		$treatment_products = wp_list_pluck( $adapter->treatment_products_with_concerns(), 'id' );

		$raw_mapping = isset( $_POST['mapping'] ) ? (array) wp_unslash( $_POST['mapping'] ) : array();
		$by_product = array();
		foreach ( $raw_mapping as $concern_id => $product_ids ) {
			$concern_id = absint( $concern_id );
			if ( ! $concern_id || ! term_exists( $concern_id, Gloskin_Site_Core_Content_Service::CONCERN_TAXONOMY ) ) {
				continue;
			}
			foreach ( (array) $product_ids as $product_id ) {
				$product_id = absint( $product_id );
				/* Accept only real Woo product IDs explicitly classified as
				 * Treatment Products (section 8.2's save-time boundary). */
				if ( ! in_array( $product_id, $treatment_products, true ) ) {
					continue;
				}
				if ( ! isset( $by_product[ $product_id ] ) ) {
					$by_product[ $product_id ] = array();
				}
				$by_product[ $product_id ][] = $concern_id;
			}
		}

		foreach ( $treatment_products as $product_id ) {
			$concern_ids = isset( $by_product[ $product_id ] ) ? array_values( array_unique( $by_product[ $product_id ] ) ) : array();
			wp_set_object_terms( $product_id, $concern_ids, Gloskin_Site_Core_Content_Service::CONCERN_TAXONOMY, false );
		}

		$this->redirect_to_consultation_tab( 'pemetaan', 'mapping-saved' );
	}

	/**
	 * Capability/nonce-protected, one-shot demo import trigger. Explicit
	 * privileged confirmation replaces the former environment gate: the
	 * server independently re-verifies confirm_demo_import=1 was actually
	 * posted (never trusting the HTML checkbox's `required` attribute
	 * alone) before the importer is allowed to create any synthetic data.
	 * All idempotency/collision/verification logic still lives in the
	 * importer itself.
	 *
	 * @return void
	 */
	public function handle_demo_import() {
		if ( ! current_user_can( self::CONSULTATION_CAPABILITY ) ) {
			wp_die( esc_html__( 'Capability manage_woocommerce diperlukan.', 'gloskin-site-core' ) );
		}
		check_admin_referer( self::DEMO_IMPORT_NONCE );
		require_once __DIR__ . '/class-gloskin-site-core-consultation-demo-importer.php';
		$confirmed = isset( $_POST['confirm_demo_import'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['confirm_demo_import'] ) );
		try {
			Gloskin_Site_Core_Consultation_Demo_Importer::run( $confirmed );
			$this->redirect_to_consultation_tab( 'ringkasan', 'demo-imported' );
		} catch ( Throwable $error ) {
			wp_safe_redirect( add_query_arg( array( 'tab' => 'ringkasan', 'gloskin_error' => rawurlencode( $error->getMessage() ) ), admin_url( 'edit.php?post_type=product&page=' . self::CONSULTATION_SLUG ) ) );
			exit;
		}
	}

	/**
	 * @param string $tab Tab key.
	 * @param string $notice Notice key.
	 * @return void
	 */
	private function redirect_to_consultation_tab( $tab, $notice ) {
		wp_safe_redirect( add_query_arg( array( 'tab' => $tab, 'gloskin_notice' => $notice ), admin_url( 'edit.php?post_type=product&page=' . self::CONSULTATION_SLUG ) ) );
		exit;
	}

	/**
	 * Small DnD progressive-enhancement script for the mapping matrix,
	 * enqueued only on the Konsultasi Perawatan screen itself.
	 *
	 * @param string $hook_suffix Admin screen hook.
	 * @return void
	 */
	public function enqueue_consultation_admin_assets( $hook_suffix ) {
		if ( false === strpos( (string) $hook_suffix, self::CONSULTATION_SLUG ) ) {
			return;
		}
		if ( is_object( $this->assets ) && method_exists( $this->assets, 'enqueue_consultation_admin' ) ) {
			$this->assets->enqueue_consultation_admin();
		}
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
	private function checkbox_field( $post, $key, $label ) { $checked = (bool) get_post_meta( $post->ID, $key, true ); echo '<p><label><input type="checkbox" name="' . esc_attr( $key ) . '" value="1" ' . checked( $checked, true, false ) . ' /> <strong>' . esc_html( $label ) . '</strong></label></p>'; }
	private function relationship_field( $post, $key, $target_type, $label ) {
		$selected = get_post_meta( $post->ID, $key, true ); $selected = is_array( $selected ) ? array_map( 'absint', $selected ) : array();
		$choices = get_posts( array( 'post_type' => $target_type, 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		echo '<p><label for="' . esc_attr( $key ) . '"><strong>' . esc_html( $label ) . '</strong></label></p><select class="widefat" multiple size="8" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '[]">'; foreach ( $choices as $choice ) { echo '<option value="' . esc_attr( (string) $choice->ID ) . '" ' . selected( in_array( (int) $choice->ID, $selected, true ), true, false ) . '>' . esc_html( get_the_title( $choice ) ) . '</option>'; } echo '</select>';
	}

	// -------------------------------------------------------------------------
	// List-table columns — Promo (item 12)
	// -------------------------------------------------------------------------

	/** @param array<string,string> $columns @return array<string,string> */
	public function promo_list_columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['gloskin_promo_active']     = __( 'Active', 'gloskin-site-core' );
				$new['gloskin_promo_dates']      = __( 'Date range', 'gloskin-site-core' );
				$new['gloskin_promo_order']      = __( 'Order', 'gloskin-site-core' );
				$new['gloskin_promo_is_demo']    = __( 'Demo', 'gloskin-site-core' );
			}
		}
		return $new;
	}

	/** @return void */
	public function promo_list_column_cell( $column, $post_id ) {
		switch ( $column ) {
			case 'gloskin_promo_active':
				echo get_post_meta( $post_id, 'gloskin_promo_active', true ) ? '✔' : '—';
				break;
			case 'gloskin_promo_dates':
				$start = (string) get_post_meta( $post_id, 'gloskin_promo_start_date', true );
				$end   = (string) get_post_meta( $post_id, 'gloskin_promo_end_date', true );
				echo esc_html( ( $start ?: '∞' ) . ' → ' . ( $end ?: '∞' ) );
				break;
			case 'gloskin_promo_order':
				$order = (string) get_post_meta( $post_id, 'gloskin_promo_order', true );
				echo esc_html( '' !== $order ? $order : '—' );
				break;
			case 'gloskin_promo_is_demo':
				$identity = get_post_meta( $post_id, '_gloskin_demo_identity', true );
				echo $identity ? '✔' : '—';
				break;
		}
	}

	// -------------------------------------------------------------------------
	// List-table columns — Testimonial (item 12)
	// -------------------------------------------------------------------------

	/** @param array<string,string> $columns @return array<string,string> */
	public function testimonial_list_columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['gloskin_testimonial_attribution'] = __( 'Attribution', 'gloskin-site-core' );
				$new['gloskin_testimonial_active']      = __( 'Active', 'gloskin-site-core' );
				$new['gloskin_testimonial_order']       = __( 'Order', 'gloskin-site-core' );
				$new['gloskin_testimonial_is_demo']     = __( 'Demo', 'gloskin-site-core' );
			}
		}
		return $new;
	}

	/** @return void */
	public function testimonial_list_column_cell( $column, $post_id ) {
		switch ( $column ) {
			case 'gloskin_testimonial_attribution':
				$attr = (string) get_post_meta( $post_id, 'gloskin_testimonial_attribution', true );
				echo esc_html( $attr ?: '—' );
				break;
			case 'gloskin_testimonial_active':
				echo get_post_meta( $post_id, 'gloskin_testimonial_active', true ) ? '✔' : '—';
				break;
			case 'gloskin_testimonial_order':
				$order = (string) get_post_meta( $post_id, 'gloskin_testimonial_order', true );
				echo esc_html( '' !== $order ? $order : '—' );
				break;
			case 'gloskin_testimonial_is_demo':
				$identity = get_post_meta( $post_id, '_gloskin_demo_identity', true );
				echo $identity ? '✔' : '—';
				break;
		}
	}

	// -------------------------------------------------------------------------
	// List-table columns — Achievement (item 12)
	// -------------------------------------------------------------------------

	/** @param array<string,string> $columns @return array<string,string> */
	public function achievement_list_columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['gloskin_achievement_issuer_year'] = __( 'Issuer / Year', 'gloskin-site-core' );
				$new['gloskin_achievement_feature']     = __( 'Feature Home', 'gloskin-site-core' );
				$new['gloskin_achievement_active']      = __( 'Active', 'gloskin-site-core' );
				$new['gloskin_achievement_order']       = __( 'Order', 'gloskin-site-core' );
				$new['gloskin_achievement_is_demo']     = __( 'Demo', 'gloskin-site-core' );
			}
		}
		return $new;
	}

	/** @return void */
	public function achievement_list_column_cell( $column, $post_id ) {
		switch ( $column ) {
			case 'gloskin_achievement_issuer_year':
				$issuer = (string) get_post_meta( $post_id, 'gloskin_achievement_issuer', true );
				$year   = (string) get_post_meta( $post_id, 'gloskin_achievement_year', true );
				echo esc_html( trim( $issuer . ( $year ? ' (' . $year . ')' : '' ) ) ?: '—' );
				break;
			case 'gloskin_achievement_feature':
				echo get_post_meta( $post_id, 'gloskin_achievement_feature_on_home', true ) ? '✔' : '—';
				break;
			case 'gloskin_achievement_active':
				echo get_post_meta( $post_id, 'gloskin_achievement_active', true ) ? '✔' : '—';
				break;
			case 'gloskin_achievement_order':
				$order = (string) get_post_meta( $post_id, 'gloskin_achievement_order', true );
				echo esc_html( '' !== $order ? $order : '—' );
				break;
			case 'gloskin_achievement_is_demo':
				$identity = get_post_meta( $post_id, '_gloskin_demo_identity', true );
				echo $identity ? '✔' : '—';
				break;
		}
	}
}
