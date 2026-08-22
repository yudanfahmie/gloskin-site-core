<?php
/**
 * Canonical native-list management for Promo and Testimonial records.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Gloskin_Site_Core_Editorial_Manager {
	const NONCE_ACTION = 'gloskin_editorial_manager';
	const SETUP_ACTION = 'gloskin_editorial_setup';
	const SETUP_NONCE  = 'gloskin_editorial_setup_nonce';
	const SETUP_OPTION = 'gloskin_site_core_editorial_setup_v1_state';
	const SEED_META    = '_gloskin_editorial_seed_identity';

	/** @var string */
	private $plugin_file;

	/** @var string */
	private $version;

	/**
	 * @param string $plugin_file Main plugin file.
	 * @param string $version Plugin version.
	 */
	public function __construct( $plugin_file, $version ) {
		$this->plugin_file = (string) $plugin_file;
		$this->version     = (string) $version;
	}

	/** @return void */
	public function register() {
		add_filter( 'manage_edit-' . Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE . '_columns', array( $this, 'promo_columns' ), 50 );
		add_action( 'manage_' . Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE . '_posts_custom_column', array( $this, 'promo_column_cell' ), 50, 2 );
		add_filter( 'manage_edit-' . Gloskin_Site_Core_Content_Service::TESTIMONIAL_POST_TYPE . '_columns', array( $this, 'testimonial_columns' ), 50 );
		add_action( 'manage_' . Gloskin_Site_Core_Content_Service::TESTIMONIAL_POST_TYPE . '_posts_custom_column', array( $this, 'testimonial_column_cell' ), 50, 2 );
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 50, 2 );
		add_filter( 'get_edit_post_link', array( $this, 'edit_post_link' ), 50, 3 );
		add_filter( 'posts_clauses', array( $this, 'order_native_list' ), 30, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 40 );
		add_action( 'admin_footer', array( $this, 'render_modal' ) );
		add_action( 'admin_notices', array( $this, 'render_status_region' ), 30 );
		add_action( 'admin_notices', array( $this, 'render_setup_notice' ) );
		add_action( 'load-post.php', array( $this, 'redirect_legacy_editor' ) );
		add_action( 'load-post-new.php', array( $this, 'redirect_legacy_editor' ) );
		add_action( 'admin_init', array( $this, 'maybe_normalize_display_state' ), 30 );

		add_action( 'wp_ajax_gloskin_editorial_save', array( $this, 'ajax_save' ) );
		add_action( 'wp_ajax_gloskin_editorial_toggle', array( $this, 'ajax_toggle' ) );
		add_action( 'wp_ajax_gloskin_editorial_reorder', array( $this, 'ajax_reorder' ) );
		add_action( 'admin_post_' . self::SETUP_ACTION, array( $this, 'handle_setup' ) );
	}

	/** @return array<string,string> */
	public function promo_columns( $columns ) {
		return array(
			'cb'                        => isset( $columns['cb'] ) ? $columns['cb'] : '<input type="checkbox" />',
			'gloskin_editorial_order'  => __( 'Order', 'gloskin-site-core' ),
			'gloskin_editorial_image'  => __( 'Image', 'gloskin-site-core' ),
			'title'                     => __( 'Internal title', 'gloskin-site-core' ),
			'gloskin_promo_type'       => __( 'Type', 'gloskin-site-core' ),
			'gloskin_editorial_active' => __( 'Active / Popup', 'gloskin-site-core' ),
			'date'                      => isset( $columns['date'] ) ? $columns['date'] : __( 'Date', 'gloskin-site-core' ),
		);
	}

	/** @return array<string,string> */
	public function testimonial_columns( $columns ) {
		return array(
			'cb'                         => isset( $columns['cb'] ) ? $columns['cb'] : '<input type="checkbox" />',
			'gloskin_editorial_order'   => __( 'Order', 'gloskin-site-core' ),
			'gloskin_editorial_image'   => __( 'Photo', 'gloskin-site-core' ),
			'title'                      => __( 'Name', 'gloskin-site-core' ),
			'gloskin_testimonial_role'  => __( 'Role / subtitle', 'gloskin-site-core' ),
			'gloskin_testimonial_quote' => __( 'Quote', 'gloskin-site-core' ),
			'gloskin_editorial_active'  => __( 'Active', 'gloskin-site-core' ),
			'date'                       => isset( $columns['date'] ) ? $columns['date'] : __( 'Date', 'gloskin-site-core' ),
		);
	}

	/** @return void */
	public function promo_column_cell( $column, $post_id ) {
		if ( 'gloskin_promo_type' === $column ) {
			$type = (string) get_post_meta( $post_id, 'gloskin_promo_type', true );
			if ( 'limited' === $type ) {
				echo esc_html( __( 'Promo Terbatas', 'gloskin-site-core' ) );
			} elseif ( 'regular' === $type ) {
				echo esc_html( __( 'Promo Biasa', 'gloskin-site-core' ) );
			} else {
				echo '<span style="color:#c0392b" aria-label="' . esc_attr__( 'Type not set', 'gloskin-site-core' ) . '">—</span>';
			}
			return;
		}
		$this->shared_column_cell( $column, $post_id, Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE );
	}

	/** @return void */
	public function testimonial_column_cell( $column, $post_id ) {
		if ( 'gloskin_testimonial_role' === $column ) {
			$role = (string) get_post_meta( $post_id, 'gloskin_testimonial_subtitle', true );
			echo esc_html( '' !== $role ? $role : '—' );
			return;
		}
		if ( 'gloskin_testimonial_quote' === $column ) {
			$post  = get_post( $post_id );
			$quote = $post instanceof WP_Post ? trim( (string) $post->post_excerpt ) : '';
			echo esc_html( $quote ? wp_trim_words( $quote, 18, '…' ) : '—' );
			return;
		}
		$this->shared_column_cell( $column, $post_id, Gloskin_Site_Core_Content_Service::TESTIMONIAL_POST_TYPE );
	}

	/** @return void */
	private function shared_column_cell( $column, $post_id, $post_type ) {
		if ( 'gloskin_editorial_order' === $column ) {
			echo '<span class="gloskin-editorial-order-handle" aria-label="' . esc_attr__( 'Drag to reorder', 'gloskin-site-core' ) . '" title="' . esc_attr__( 'Drag to reorder', 'gloskin-site-core' ) . '"><span class="dashicons dashicons-menu" aria-hidden="true"></span></span>';
			return;
		}
		if ( 'gloskin_editorial_image' === $column ) {
			$image = get_the_post_thumbnail( $post_id, array( 64, 64 ), array( 'class' => 'gloskin-editorial-list-thumb', 'alt' => '' ) );
			echo $image ? wp_kses_post( $image ) : '<span aria-hidden="true">—</span>';
			return;
		}
		if ( 'gloskin_editorial_active' === $column ) {
			$active = '1' === (string) get_post_meta( $post_id, $this->active_meta_key( $post_type ), true );
			echo '<span class="gloskin-editorial-status-actions">';
			echo '<button type="button" class="button gloskin-editorial-active-toggle' . ( $active ? ' is-active' : '' ) . '" data-gloskin-editorial-toggle data-id="' . esc_attr( (string) $post_id ) . '" data-active="' . ( $active ? '1' : '0' ) . '" aria-pressed="' . ( $active ? 'true' : 'false' ) . '">' . esc_html( $active ? __( 'Active', 'gloskin-site-core' ) : __( 'Inactive', 'gloskin-site-core' ) ) . '</button>';
			if ( Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE === $post_type ) {
				$popup = '1' === (string) get_post_meta( $post_id, Gloskin_Site_Core_Promo_Modal::POPUP_META, true );
				echo '<button type="button" class="button gloskin-editorial-active-toggle gloskin-editorial-popup-toggle' . ( $popup ? ' is-active' : '' ) . '" data-gloskin-promo-popup-toggle data-id="' . esc_attr( (string) $post_id ) . '" data-popup="' . ( $popup ? '1' : '0' ) . '" aria-pressed="' . ( $popup ? 'true' : 'false' ) . '">' . esc_html( $popup ? __( 'Popup On', 'gloskin-site-core' ) : __( 'Popup Off', 'gloskin-site-core' ) ) . '</button>';
			}
			echo '</span>';
		}
	}

	/** @return array<string,string> */
	public function row_actions( $actions, $post ) {
		if ( ! $post instanceof WP_Post || ! $this->is_managed_type( $post->post_type ) ) {
			return $actions;
		}
		if ( isset( $actions['edit'] ) ) {
			$actions['edit'] = '<a href="' . esc_url( $this->list_url( $post->post_type, array( 'gloskin_edit' => $post->ID ) ) ) . '" data-gloskin-editorial-edit="' . esc_attr( (string) $post->ID ) . '">' . esc_html__( 'Edit', 'gloskin-site-core' ) . '</a>';
		}
		return $actions;
	}

	/** @return string */
	public function edit_post_link( $link, $post_id, $context ) {
		unset( $context );
		$post = get_post( $post_id );
		return $post instanceof WP_Post && $this->is_managed_type( $post->post_type ) ? $this->list_url( $post->post_type, array( 'gloskin_edit' => $post_id ) ) : $link;
	}

	/**
	 * Order the native list without an INNER JOIN, so historical rows that do
	 * not yet carry canonical order metadata are still visible and manageable.
	 *
	 * @param array<string,string> $clauses SQL clauses.
	 * @param WP_Query             $query Query.
	 * @return array<string,string>
	 */
	public function order_native_list( $clauses, $query ) {
		if ( ! is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query() ) {
			return $clauses;
		}
		$post_type = (string) $query->get( 'post_type' );
		if ( ! $this->is_managed_type( $post_type ) || isset( $_GET['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only native list preference.
			return $clauses;
		}
		global $wpdb;
		$alias = 'gloskin_editorial_order_meta';
		$key   = $this->order_meta_key( $post_type );
		$clauses['join'] .= $wpdb->prepare( " LEFT JOIN {$wpdb->postmeta} AS {$alias} ON ({$wpdb->posts}.ID = {$alias}.post_id AND {$alias}.meta_key = %s)", $key ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/alias names are fixed application identifiers.
		$clauses['orderby'] = "CASE WHEN {$alias}.meta_value IS NULL OR {$alias}.meta_value = '' OR CAST({$alias}.meta_value AS UNSIGNED) = 0 THEN 1 ELSE 0 END ASC, CAST({$alias}.meta_value AS UNSIGNED) ASC, {$wpdb->posts}.ID ASC";
		return $clauses;
	}

	/** @return void */
	public function enqueue_assets() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit' !== $screen->base || ! $this->is_managed_type( (string) $screen->post_type ) ) {
			return;
		}
		$post_type   = (string) $screen->post_type;
		$can_reorder = $this->can_reorder_list( $post_type );
		$profile     = Gloskin_Site_Core_Content_Service::editorial_profile( $post_type );
		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-sortable' );
		$base = plugin_dir_url( $this->plugin_file );
		wp_enqueue_style( 'gloskin-editorial-manager', $base . 'assets/css/gloskin-editorial-manager.css', array(), $this->version );
		wp_enqueue_script( 'gloskin-editorial-manager', $base . 'assets/js/gloskin-editorial-manager.js', array( 'jquery', 'jquery-ui-sortable', 'media-editor' ), $this->version, true );
		if ( Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE === $post_type ) {
			wp_enqueue_style( 'gloskin-editorial-promo-settings', $base . 'assets/css/gloskin-editorial-promo-settings.css', array( 'gloskin-editorial-manager' ), $this->version );
		}
		wp_localize_script( 'gloskin-editorial-manager', 'GloskinEditorialManager', array(
			'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
			'nonce'           => wp_create_nonce( self::NONCE_ACTION ),
			'postType'        => $post_type,
			'addId'           => isset( $_GET['gloskin_add'] ) ? 1 : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- modal-open state only.
			'editId'          => isset( $_GET['gloskin_edit'] ) ? absint( $_GET['gloskin_edit'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- modal-open state only.
			'canReorder'      => $can_reorder ? 1 : 0,
			'promoCropWidth'  => isset( $profile['crop_width'] ) ? absint( $profile['crop_width'] ) : 1648,
			'promoCropHeight' => isset( $profile['crop_height'] ) ? absint( $profile['crop_height'] ) : 928,
			'promoZoomMin'    => isset( $profile['zoom_min'] ) ? absint( $profile['zoom_min'] ) : 100,
			'promoZoomMax'    => isset( $profile['zoom_max'] ) ? absint( $profile['zoom_max'] ) : 300,
			'labels'          => array(
				'saving'                  => __( 'Saving…', 'gloskin-site-core' ),
				'error'                   => __( 'Could not save this record.', 'gloskin-site-core' ),
				'invalidEdit'             => __( 'That record is no longer available. The list was left unchanged.', 'gloskin-site-core' ),
				'saved'                   => __( 'Saved.', 'gloskin-site-core' ),
				'saveListFailed'          => __( 'Saved, but the native list could not be updated in place. Refresh the list manually if needed.', 'gloskin-site-core' ),
				'activeUpdated'           => __( 'Active state updated.', 'gloskin-site-core' ),
				'activeFailed'            => __( 'Active state could not be updated.', 'gloskin-site-core' ),
				'popupOn'                 => __( 'Popup On', 'gloskin-site-core' ),
				'popupOff'                => __( 'Popup Off', 'gloskin-site-core' ),
				'popupUpdated'            => __( 'Popup display state updated.', 'gloskin-site-core' ),
				'popupFailed'             => __( 'Popup state could not be updated.', 'gloskin-site-core' ),
				'reorderSaved'            => __( 'Order saved.', 'gloskin-site-core' ),
				'reorderFailed'           => __( 'Order could not be saved.', 'gloskin-site-core' ),
				'reorderHint'             => __( 'Clear filters to reorder items.', 'gloskin-site-core' ),
				'mediaUnavailable'        => __( 'Media Library could not be initialized. Refresh this page and try again.', 'gloskin-site-core' ),
				'cropApplied'             => __( 'Crop selection applied. Save the Promo to persist it.', 'gloskin-site-core' ),
				'cropApplyRequired'       => __( 'Use Crop & Apply before saving this Promo.', 'gloskin-site-core' ),
				'cropLowResolution'       => __( 'Image is below the required 1648 × 928 production size. Choose a larger source image.', 'gloskin-site-core' ),
				'cropLegacyLowResolution' => __( 'Legacy image is below 1648 × 928. You can reframe it, but replacing it requires a larger source image.', 'gloskin-site-core' ),
				'cropDimensionsUnknown'   => __( 'Image dimensions will be validated when you save.', 'gloskin-site-core' ),
				'cropSmart'               => __( 'Smart select', 'gloskin-site-core' ),
				'cropSmartWorking'        => __( 'Finding subject…', 'gloskin-site-core' ),
				'cropSmartReady'          => __( 'Smart crop selected. Fine-tune the selection, then choose Crop & Apply.', 'gloskin-site-core' ),
				'cropZoom'                => __( 'Crop size', 'gloskin-site-core' ),
				'cropZoomAria'            => __( 'Crop zoom', 'gloskin-site-core' ),
				'cropOutput'              => __( 'Production crop preview', 'gloskin-site-core' ),
				'cropSelectionLabel'      => __( 'Crop selection. Drag to move, drag a corner handle to resize, or use arrow keys.', 'gloskin-site-core' ),
			),
		) );
		if ( Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE === $post_type ) {
			wp_enqueue_script( 'gloskin-editorial-promo-settings', $base . 'assets/js/gloskin-editorial-promo-settings.js', array( 'gloskin-editorial-manager' ), $this->version, true );
		}
	}

	/** @return void */
	public function render_status_region() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit' !== $screen->base || ! $this->is_managed_type( (string) $screen->post_type ) ) {
			return;
		}
		?>
		<div class="notice notice-info gloskin-editorial-status" data-gloskin-editorial-status role="status" aria-live="polite" hidden><p data-gloskin-editorial-status-message></p></div>
		<?php
	}

	/** @return void */
	public function render_modal() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit' !== $screen->base || ! $this->is_managed_type( (string) $screen->post_type ) ) {
			return;
		}
		$post_type  = (string) $screen->post_type;
		$records    = $this->record_payloads( $post_type );
		$is_promo   = Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE === $post_type;
		$promo_pages = $is_promo ? get_pages( array( 'sort_column' => 'post_title', 'sort_order' => 'ASC', 'post_status' => 'publish' ) ) : array();
		?>
		<div class="gloskin-editorial-modal" data-gloskin-editorial-modal hidden>
			<div class="gloskin-editorial-modal__backdrop" data-gloskin-editorial-close></div>
			<div class="gloskin-editorial-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="gloskin-editorial-modal-title">
				<header class="gloskin-editorial-modal__header"><h2 id="gloskin-editorial-modal-title"><?php echo esc_html( $is_promo ? __( 'Promo', 'gloskin-site-core' ) : __( 'Testimonial', 'gloskin-site-core' ) ); ?></h2><button type="button" class="gloskin-editorial-modal__close" data-gloskin-editorial-close aria-label="<?php echo esc_attr__( 'Close', 'gloskin-site-core' ); ?>">×</button></header>
				<form data-gloskin-editorial-form>
					<input type="hidden" name="post_type" value="<?php echo esc_attr( $post_type ); ?>"><input type="hidden" name="post_id" value="0" data-gloskin-editorial-post-id>
					<div class="gloskin-editorial-modal__body">
						<label class="gloskin-editorial-field"><span><?php echo esc_html( $is_promo ? __( 'Internal title', 'gloskin-site-core' ) : __( 'Name', 'gloskin-site-core' ) ); ?></span><input type="text" name="title" required></label>
						<?php if ( $is_promo ) : ?>
						<label class="gloskin-editorial-field"><span><?php echo esc_html__( 'Type', 'gloskin-site-core' ); ?></span><select name="promo_type" required><option value="limited"><?php echo esc_html__( 'Promo Terbatas', 'gloskin-site-core' ); ?></option><option value="regular"><?php echo esc_html__( 'Promo Biasa', 'gloskin-site-core' ); ?></option></select></label>
						<?php else : ?>
						<label class="gloskin-editorial-field"><span><?php echo esc_html__( 'Role / subtitle', 'gloskin-site-core' ); ?></span><input type="text" name="subtitle"></label>
						<label class="gloskin-editorial-field"><span><?php echo esc_html__( 'Testimonial quote', 'gloskin-site-core' ); ?></span><textarea name="quote" rows="6" required></textarea></label>
						<?php endif; ?>
						<div class="gloskin-editorial-media-field">
							<span class="gloskin-editorial-field__label"><?php echo esc_html( $is_promo ? __( 'Image', 'gloskin-site-core' ) : __( 'Photo', 'gloskin-site-core' ) ); ?></span>
							<input type="hidden" name="image_id" value="0" data-gloskin-editorial-image-id>
							<?php if ( $is_promo ) : ?>
							<input type="hidden" name="focus_x" value="50"><input type="hidden" name="focus_y" value="50"><input type="hidden" name="crop_zoom" value="100">
							<div class="gloskin-editorial-crop" data-gloskin-promo-crop hidden>
								<div class="gloskin-editorial-crop__viewport" data-gloskin-editorial-preview data-gloskin-promo-crop-viewport tabindex="0" aria-label="<?php echo esc_attr__( 'Promo crop workspace. Move or resize the selection to choose the production crop.', 'gloskin-site-core' ); ?>"></div>
								<p class="gloskin-editorial-crop__quality" data-gloskin-promo-crop-quality aria-live="polite"></p>
								<p class="gloskin-editorial-crop__hint"><?php echo esc_html__( 'Use Smart select for an automatic subject-aware crop, then drag the selection, resize its corners, adjust Crop size, or use the arrow keys. Output remains locked to 1648:928.', 'gloskin-site-core' ); ?></p>
							</div>
							<?php else : ?>
							<div class="gloskin-editorial-media-field__preview" data-gloskin-editorial-preview></div>
							<?php endif; ?>
							<div class="gloskin-editorial-media-field__actions">
								<button type="button" class="button" data-gloskin-editorial-media><?php echo esc_html__( 'Choose / replace from Media Library', 'gloskin-site-core' ); ?></button>
								<?php if ( $is_promo ) : ?>
								<button type="button" class="button button-primary" data-gloskin-promo-crop-apply hidden><?php echo esc_html__( 'Crop & Apply', 'gloskin-site-core' ); ?></button>
								<button type="button" class="button" data-gloskin-promo-crop-reset hidden><?php echo esc_html__( 'Reset framing', 'gloskin-site-core' ); ?></button>
								<?php endif; ?>
								<button type="button" class="button button-link-delete" data-gloskin-editorial-media-remove><?php echo esc_html__( 'Remove', 'gloskin-site-core' ); ?></button>
							</div>
						</div>
						<label class="gloskin-editorial-active-field"><input type="checkbox" name="active" value="1"> <span><?php echo esc_html__( 'Active', 'gloskin-site-core' ); ?></span></label>
						<?php if ( $is_promo ) : ?>
						<fieldset class="gloskin-editorial-popup-settings">
							<legend><?php echo esc_html__( 'Popup display', 'gloskin-site-core' ); ?></legend>
							<div class="gloskin-editorial-popup-settings__grid">
								<label class="gloskin-editorial-active-field"><input type="checkbox" name="popup_enabled" value="1"> <span><?php echo esc_html__( 'Show as popup', 'gloskin-site-core' ); ?></span></label>
								<label class="gloskin-editorial-field"><span><?php echo esc_html__( 'Visibility', 'gloskin-site-core' ); ?></span><select name="visibility"><option value="homepage"><?php echo esc_html__( 'Homepage', 'gloskin-site-core' ); ?></option><option value="all_pages"><?php echo esc_html__( 'All Pages', 'gloskin-site-core' ); ?></option><option value="specific_pages"><?php echo esc_html__( 'Specific Pages', 'gloskin-site-core' ); ?></option></select></label>
								<label class="gloskin-editorial-field gloskin-editorial-popup-settings__destination"><span><?php echo esc_html__( 'Destination URL', 'gloskin-site-core' ); ?></span><input type="text" name="destination_url" inputmode="url" autocomplete="url" placeholder="https://example.com/promo/ or /promo/"><p class="gloskin-editorial-popup-settings__hint"><?php echo esc_html__( 'Same-site links may be root-relative. External destinations must use HTTPS.', 'gloskin-site-core' ); ?></p></label>
								<div class="gloskin-editorial-popup-settings__specific" data-gloskin-promo-specific-pages hidden>
									<label class="gloskin-editorial-field"><span><?php echo esc_html__( 'Specific Pages', 'gloskin-site-core' ); ?></span><input class="gloskin-editorial-popup-settings__search" type="search" data-gloskin-promo-page-search placeholder="<?php echo esc_attr__( 'Search pages…', 'gloskin-site-core' ); ?>"><select class="gloskin-editorial-popup-settings__pages" multiple size="7" data-gloskin-promo-page-select aria-label="<?php echo esc_attr__( 'Select WordPress pages', 'gloskin-site-core' ); ?>"><?php foreach ( $promo_pages as $promo_page ) : ?><option value="<?php echo esc_attr( (string) $promo_page->ID ); ?>"><?php echo esc_html( get_the_title( $promo_page ) ); ?></option><?php endforeach; ?></select></label>
									<input type="hidden" name="visibility_page_ids_csv" value="">
									<p class="gloskin-editorial-popup-settings__hint"><?php echo esc_html__( 'Choose one or more published WordPress pages. Selections are stored by post ID.', 'gloskin-site-core' ); ?></p>
								</div>
							</div>
						</fieldset>
						<?php endif; ?>
						<p class="gloskin-editorial-modal__error" data-gloskin-editorial-error role="alert" hidden></p>
					</div>
					<footer class="gloskin-editorial-modal__footer"><button type="button" class="button" data-gloskin-editorial-close><?php echo esc_html__( 'Cancel', 'gloskin-site-core' ); ?></button><button type="submit" class="button button-primary" data-gloskin-editorial-save><?php echo esc_html__( 'Save', 'gloskin-site-core' ); ?></button></footer>
				</form>
			</div>
		</div>
		<script type="application/json" id="gloskin-editorial-records"><?php echo wp_json_encode( $records ); ?></script>
		<?php
	}

	/** @return array<int,array<string,mixed>> */
	private function record_payloads( $post_type ) {
		$posts   = get_posts( array( 'post_type' => $post_type, 'post_status' => array( 'publish', 'draft', 'private', 'pending', 'future' ), 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) );
		$records = array();
		foreach ( $posts as $post ) {
			$records[ (int) $post->ID ] = $this->record_payload( $post );
		}
		return $records;
	}

	/** @return array<string,mixed> */
	private function record_payload( $post ) {
		if ( ! $post instanceof WP_Post || ! $this->is_managed_type( $post->post_type ) ) {
			return array();
		}
		$image_id = absint( get_post_thumbnail_id( $post->ID ) );
		$preview  = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
		$trash    = current_user_can( 'delete_post', $post->ID ) ? get_delete_post_link( $post->ID, '', false ) : '';
		$payload  = array(
			'id'         => (int) $post->ID,
			'title'      => (string) $post->post_title,
			'promo_type' => (string) get_post_meta( $post->ID, 'gloskin_promo_type', true ),
			'subtitle'   => (string) get_post_meta( $post->ID, 'gloskin_testimonial_subtitle', true ),
			'quote'      => (string) $post->post_excerpt,
			'image_id'   => $image_id,
			'image_url'  => $preview ? (string) $preview : '',
			'active'     => '1' === (string) get_post_meta( $post->ID, $this->active_meta_key( $post->post_type ), true ),
			'order'      => (int) get_post_meta( $post->ID, $this->order_meta_key( $post->post_type ), true ),
			'trash_url'  => $trash ? (string) $trash : '',
		);
		if ( Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE === $post->post_type ) {
			$profile = Gloskin_Site_Core_Content_Service::editorial_profile( $post->post_type );
			$dims    = $this->attachment_dimensions( $image_id );
			$full    = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : '';
			$payload['crop_image_url']      = $full ? (string) $full : ( $preview ? (string) $preview : '' );
			$payload['image_width']         = $dims['width'];
			$payload['image_height']        = $dims['height'];
			$payload['focus_x']             = $this->promo_focus_value( $post->ID, (string) ( $profile['focus_x_meta'] ?? '' ) );
			$payload['focus_y']             = $this->promo_focus_value( $post->ID, (string) ( $profile['focus_y_meta'] ?? '' ) );
			$payload['zoom']                = $this->promo_zoom_value( $post->ID, (string) ( $profile['zoom_meta'] ?? '' ) );
			$payload['popup_enabled']       = '1' === (string) get_post_meta( $post->ID, Gloskin_Site_Core_Promo_Modal::POPUP_META, true );
			$payload['visibility']          = Gloskin_Site_Core_Promo_Modal::sanitize_visibility( get_post_meta( $post->ID, Gloskin_Site_Core_Promo_Modal::VISIBILITY_META, true ) );
			$payload['visibility_page_ids'] = Gloskin_Site_Core_Promo_Modal::sanitize_page_ids( get_post_meta( $post->ID, Gloskin_Site_Core_Promo_Modal::PAGE_IDS_META, true ) );
			$payload['destination_url']     = Gloskin_Site_Core_Promo_Modal::sanitize_destination_url( get_post_meta( $post->ID, Gloskin_Site_Core_Promo_Modal::DESTINATION_META, true ) );
		}
		return $payload;
	}

	/** @return void */
	public function redirect_legacy_editor() {
		$post_type = '';
		$post_id   = 0;
		if ( isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- redirect only.
			$post_id = absint( $_GET['post'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$post = get_post( $post_id );
			$post_type = $post instanceof WP_Post ? (string) $post->post_type : '';
		} elseif ( isset( $_GET['post_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- redirect only.
			$post_type = sanitize_key( wp_unslash( $_GET['post_type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( ! $this->is_managed_type( $post_type ) ) {
			return;
		}
		wp_safe_redirect( $this->list_url( $post_type, $post_id ? array( 'gloskin_edit' => $post_id ) : array( 'gloskin_add' => 1 ) ) );
		exit;
	}

	/** @return void */
	public function ajax_save() {
		$this->verify_ajax();
		$post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
		$post_id   = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $this->is_managed_type( $post_type ) ) {
			wp_send_json_error( array( 'message' => __( 'Unsupported editorial record type.', 'gloskin-site-core' ) ), 400 );
		}
		if ( $post_id && $post_type !== get_post_type( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Record type does not match this editor.', 'gloskin-site-core' ) ), 400 );
		}
		$this->require_edit_capability( $post_type, $post_id );
		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		if ( '' === $title ) {
			wp_send_json_error( array( 'message' => __( 'Title / name is required.', 'gloskin-site-core' ) ), 400 );
		}

		$image_id = isset( $_POST['image_id'] ) ? absint( $_POST['image_id'] ) : 0;
		if ( $image_id && ( 'attachment' !== get_post_type( $image_id ) || ! wp_attachment_is_image( $image_id ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Choose a valid image from the WordPress Media Library.', 'gloskin-site-core' ) ), 400 );
		}
		$profile = Gloskin_Site_Core_Content_Service::editorial_profile( $post_type );
		$popup_enabled       = false;
		$visibility          = Gloskin_Site_Core_Promo_Modal::VISIBILITY_HOMEPAGE;
		$visibility_page_ids = array();
		$destination_url     = '';
		if ( Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE === $post_type ) {
			$popup_enabled = isset( $_POST['popup_enabled'] ) && '1' === (string) wp_unslash( $_POST['popup_enabled'] );
			$visibility    = Gloskin_Site_Core_Promo_Modal::sanitize_visibility( isset( $_POST['visibility'] ) ? wp_unslash( $_POST['visibility'] ) : Gloskin_Site_Core_Promo_Modal::VISIBILITY_HOMEPAGE );
			$page_csv      = isset( $_POST['visibility_page_ids_csv'] ) ? sanitize_text_field( wp_unslash( $_POST['visibility_page_ids_csv'] ) ) : '';
			$page_ids      = '' === $page_csv ? array() : preg_split( '/[\s,]+/', $page_csv, -1, PREG_SPLIT_NO_EMPTY );
			$visibility_page_ids = Gloskin_Site_Core_Promo_Modal::sanitize_page_ids( is_array( $page_ids ) ? $page_ids : array() );
			$destination_raw = isset( $_POST['destination_url'] ) ? trim( (string) wp_unslash( $_POST['destination_url'] ) ) : '';
			$destination_url = Gloskin_Site_Core_Promo_Modal::sanitize_destination_url( $destination_raw );
			if ( '' !== $destination_raw && '' === $destination_url ) {
				wp_send_json_error( array( 'message' => __( 'Destination URL must be a same-site HTTP/HTTPS URL or an external HTTPS URL.', 'gloskin-site-core' ) ), 400 );
			}
			if ( $popup_enabled ) {
				$popup_issue = $this->promo_popup_validation_issue( $image_id, $destination_url, $visibility, $visibility_page_ids );
				if ( $popup_issue ) {
					wp_send_json_error( $popup_issue, 400 );
				}
			}
		}
		if ( Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE === $post_type && $image_id ) {
			$current_image_id = $post_id ? absint( get_post_thumbnail_id( $post_id ) ) : 0;
			if ( $current_image_id !== $image_id ) {
				$dims       = $this->attachment_dimensions( $image_id );
				$min_width  = isset( $profile['crop_width'] ) ? absint( $profile['crop_width'] ) : 1648;
				$min_height = isset( $profile['crop_height'] ) ? absint( $profile['crop_height'] ) : 928;
				if ( $dims['width'] < $min_width || $dims['height'] < $min_height ) {
					wp_send_json_error( array( 'message' => sprintf( __( 'Promo image must be at least %1$d × %2$d pixels. The selected image is %3$d × %4$d pixels.', 'gloskin-site-core' ), $min_width, $min_height, $dims['width'], $dims['height'] ) ), 400 );
				}
			}
		}

		$postarr = array( 'post_type' => $post_type, 'post_status' => 'publish', 'post_title' => $title );
		if ( $post_id ) {
			$postarr['ID'] = $post_id;
		}
		if ( Gloskin_Site_Core_Content_Service::TESTIMONIAL_POST_TYPE === $post_type ) {
			$quote = isset( $_POST['quote'] ) ? sanitize_textarea_field( wp_unslash( $_POST['quote'] ) ) : '';
			if ( '' === trim( $quote ) ) {
				wp_send_json_error( array( 'message' => __( 'Testimonial quote is required.', 'gloskin-site-core' ) ), 400 );
			}
			$postarr['post_excerpt'] = $quote;
		}
		$saved_id = $post_id ? wp_update_post( wp_slash( $postarr ), true ) : wp_insert_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $saved_id ) ) {
			wp_send_json_error( array( 'message' => $saved_id->get_error_message() ), 500 );
		}
		$saved_id = absint( $saved_id );
		if ( Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE === $post_type ) {
			$allowed = isset( $profile['allowed_types'] ) && is_array( $profile['allowed_types'] ) ? $profile['allowed_types'] : array( 'limited', 'regular' );
			$type    = isset( $_POST['promo_type'] ) ? sanitize_key( wp_unslash( $_POST['promo_type'] ) ) : 'regular';
			update_post_meta( $saved_id, (string) $profile['type_meta'], in_array( $type, $allowed, true ) ? $type : 'regular' );
			$focus_x = $this->normalize_focus( isset( $_POST['focus_x'] ) ? wp_unslash( $_POST['focus_x'] ) : 50 );
			$focus_y = $this->normalize_focus( isset( $_POST['focus_y'] ) ? wp_unslash( $_POST['focus_y'] ) : 50 );
			$zoom    = $this->normalize_zoom( isset( $_POST['crop_zoom'] ) ? wp_unslash( $_POST['crop_zoom'] ) : 100 );
			update_post_meta( $saved_id, (string) ( $profile['focus_x_meta'] ?? 'gloskin_promo_focus_x' ), $focus_x );
			update_post_meta( $saved_id, (string) ( $profile['focus_y_meta'] ?? 'gloskin_promo_focus_y' ), $focus_y );
			update_post_meta( $saved_id, (string) ( $profile['zoom_meta'] ?? 'gloskin_promo_crop_zoom' ), $zoom );
			update_post_meta( $saved_id, Gloskin_Site_Core_Promo_Modal::POPUP_META, $popup_enabled ? '1' : '0' );
			update_post_meta( $saved_id, Gloskin_Site_Core_Promo_Modal::VISIBILITY_META, $visibility );
			update_post_meta( $saved_id, Gloskin_Site_Core_Promo_Modal::PAGE_IDS_META, $visibility_page_ids );
			if ( '' === $destination_url ) {
				delete_post_meta( $saved_id, Gloskin_Site_Core_Promo_Modal::DESTINATION_META );
			} else {
				update_post_meta( $saved_id, Gloskin_Site_Core_Promo_Modal::DESTINATION_META, $destination_url );
			}
		} else {
			$subtitle = isset( $_POST['subtitle'] ) ? sanitize_text_field( wp_unslash( $_POST['subtitle'] ) ) : '';
			update_post_meta( $saved_id, 'gloskin_testimonial_attribution', $title );
			update_post_meta( $saved_id, 'gloskin_testimonial_subtitle', $subtitle );
		}
		$active = isset( $_POST['active'] ) && '1' === (string) wp_unslash( $_POST['active'] );
		update_post_meta( $saved_id, $this->active_meta_key( $post_type ), $active ? '1' : '0' );
		if ( ! $post_id ) {
			update_post_meta( $saved_id, $this->order_meta_key( $post_type ), $this->next_order( $post_type ) );
		}
		if ( $image_id ) {
			set_post_thumbnail( $saved_id, $image_id );
		} else {
			delete_post_thumbnail( $saved_id );
		}
		$saved_post = get_post( $saved_id );
		if ( ! $saved_post instanceof WP_Post ) {
			wp_send_json_error( array( 'message' => __( 'Saved record could not be reloaded.', 'gloskin-site-core' ) ), 500 );
		}
		wp_send_json_success( array( 'record' => $this->record_payload( $saved_post ) ) );
	}

	/** @return void */
	public function ajax_toggle() {
		$this->verify_ajax();
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || ! $this->is_managed_type( $post->post_type ) ) {
			wp_send_json_error( array( 'message' => __( 'Record not found.', 'gloskin-site-core' ) ), 404 );
		}
		$this->require_edit_capability( $post->post_type, $post_id );
		$active = isset( $_POST['active'] ) && '1' === (string) wp_unslash( $_POST['active'] );
		$field  = isset( $_POST['field'] ) ? sanitize_key( wp_unslash( $_POST['field'] ) ) : 'active';
		if ( 'popup' === $field ) {
			if ( Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE !== $post->post_type ) {
				wp_send_json_error( array( 'message' => __( 'Popup display is only available for Promo records.', 'gloskin-site-core' ) ), 400 );
			}
			if ( ! $active ) {
				update_post_meta( $post_id, Gloskin_Site_Core_Promo_Modal::POPUP_META, '0' );
				wp_send_json_success( array( 'field' => 'popup', 'active' => false ) );
			}
			$image_id  = absint( get_post_thumbnail_id( $post_id ) );
			$url       = Gloskin_Site_Core_Promo_Modal::sanitize_destination_url( get_post_meta( $post_id, Gloskin_Site_Core_Promo_Modal::DESTINATION_META, true ) );
			$visibility = Gloskin_Site_Core_Promo_Modal::sanitize_visibility( get_post_meta( $post_id, Gloskin_Site_Core_Promo_Modal::VISIBILITY_META, true ) );
			$page_ids   = Gloskin_Site_Core_Promo_Modal::sanitize_page_ids( get_post_meta( $post_id, Gloskin_Site_Core_Promo_Modal::PAGE_IDS_META, true ) );
			$popup_issue = $this->promo_popup_validation_issue( $image_id, $url, $visibility, $page_ids );
			if ( $popup_issue ) {
				wp_send_json_error( $popup_issue, 400 );
			}
			update_post_meta( $post_id, Gloskin_Site_Core_Promo_Modal::POPUP_META, '1' );
			wp_send_json_success( array( 'field' => 'popup', 'active' => true ) );
		}
		if ( 'active' !== $field ) {
			wp_send_json_error( array( 'message' => __( 'Unsupported editorial toggle.', 'gloskin-site-core' ) ), 400 );
		}
		if ( $active && Gloskin_Site_Core_Content_Service::TESTIMONIAL_POST_TYPE === $post->post_type && '' === trim( (string) $post->post_excerpt ) ) {
			wp_send_json_error( array( 'message' => __( 'Add a testimonial quote before activating this record.', 'gloskin-site-core' ) ), 400 );
		}
		update_post_meta( $post_id, $this->active_meta_key( $post->post_type ), $active ? '1' : '0' );
		wp_send_json_success( array( 'field' => 'active', 'active' => $active ) );
	}

	/**
	 * Canonical popup readiness rule shared by Save and Popup On.
	 *
	 * @param int          $image_id Featured image attachment ID.
	 * @param string       $destination_url Sanitized destination URL.
	 * @param string       $visibility Canonical visibility value.
	 * @param array<int,int> $page_ids Canonical page IDs.
	 * @return array<string,string>
	 */
	private function promo_popup_validation_issue( $image_id, $destination_url, $visibility, $page_ids ) {
		if ( ! absint( $image_id ) ) {
			return array(
				'code'    => 'popup_incomplete',
				'field'   => 'image',
				'message' => __( 'Choose a featured Promo image before enabling popup display.', 'gloskin-site-core' ),
			);
		}
		if ( '' === trim( (string) $destination_url ) ) {
			return array(
				'code'    => 'popup_incomplete',
				'field'   => 'destination',
				'message' => __( 'Add a valid Destination URL before enabling popup display.', 'gloskin-site-core' ),
			);
		}
		if ( Gloskin_Site_Core_Promo_Modal::VISIBILITY_SPECIFIC === $visibility && ! $page_ids ) {
			return array(
				'code'    => 'popup_incomplete',
				'field'   => 'pages',
				'message' => __( 'Select at least one WordPress page for Specific Pages visibility.', 'gloskin-site-core' ),
			);
		}
		return array();
	}

	/** @return void */
	public function ajax_reorder() {
		$this->verify_ajax();
		$post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
		if ( ! $this->is_managed_type( $post_type ) ) {
			wp_send_json_error( array( 'message' => __( 'Unsupported editorial record type.', 'gloskin-site-core' ) ), 400 );
		}
		$this->require_edit_capability( $post_type, 0 );
		$ids       = isset( $_POST['ids'] ) ? array_values( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) ) ) : array();
		$canonical = $this->canonical_reorder_ids( $post_type );
		if ( count( $ids ) !== count( $canonical ) || count( $ids ) !== count( array_unique( $ids ) ) || array_diff( $ids, $canonical ) || array_diff( $canonical, $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Reorder requires the complete unfiltered collection.', 'gloskin-site-core' ) ), 400 );
		}
		$order = 1;
		foreach ( $ids as $post_id ) {
			update_post_meta( $post_id, $this->order_meta_key( $post_type ), $order++ );
		}
		wp_send_json_success( array( 'ordered' => $order - 1 ) );
	}

	/** @return void */
	private function verify_ajax() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
	}

	/** @return void */
	private function require_edit_capability( $post_type, $post_id ) {
		if ( $post_id ) {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				wp_send_json_error( array( 'message' => __( 'You are not allowed to edit this record.', 'gloskin-site-core' ) ), 403 );
			}
			return;
		}
		$object = get_post_type_object( $post_type );
		$cap    = $object && isset( $object->cap->edit_posts ) ? $object->cap->edit_posts : 'edit_posts';
		if ( ! current_user_can( $cap ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to manage these records.', 'gloskin-site-core' ) ), 403 );
		}
	}

	/** @return array<int,int> */
	private function canonical_reorder_ids( $post_type ) {
		return array_values( array_map( 'absint', get_posts( array(
			'post_type'      => $post_type,
			'post_status'    => array( 'publish', 'draft', 'private', 'pending', 'future' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
		) ) ) );
	}

	/** @return bool */
	private function can_reorder_list( $post_type ) {
		if ( ! $this->is_managed_type( $post_type ) ) {
			return false;
		}
		$allowed_query_args = array( 'post_type', 'paged', 'mode', 'gloskin_add', 'gloskin_edit' );
		foreach ( array_keys( $_GET ) as $query_arg ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list-state inspection.
			if ( ! in_array( sanitize_key( (string) $query_arg ), $allowed_query_args, true ) ) {
				return false;
			}
		}
		if ( isset( $_GET['paged'] ) && absint( $_GET['paged'] ) > 1 ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list-state inspection.
			return false;
		}
		$per_page = (int) get_user_option( 'edit_' . $post_type . '_per_page' );
		if ( $per_page < 1 ) {
			$per_page = 20;
		}
		return count( $this->canonical_reorder_ids( $post_type ) ) <= $per_page;
	}

	/** @return int */
	private function next_order( $post_type ) {
		$ids = get_posts( array( 'post_type' => $post_type, 'post_status' => array( 'publish', 'draft', 'private', 'pending', 'future' ), 'posts_per_page' => -1, 'fields' => 'ids' ) );
		$max = 0;
		foreach ( $ids as $id ) {
			$max = max( $max, (int) get_post_meta( $id, $this->order_meta_key( $post_type ), true ) );
		}
		return $max + 1;
	}

	/**
	 * One-time normalization translates historical hidden runtime state into
	 * explicit editor-visible state. The existing setup option owns the marker,
	 * so no second migration service/table/cache is introduced.
	 *
	 * @return void
	 */
	public function maybe_normalize_display_state() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$state   = get_option( self::SETUP_OPTION, array() );
		$state   = is_array( $state ) ? $state : array();
		$updated = false;

		if ( 'complete' !== (string) ( $state['display_contract_v1'] ?? '' ) ) {
			if ( ! class_exists( 'Gloskin_Site_Core_Content_Finalizer_Admin' ) ) {
				require_once __DIR__ . '/class-gloskin-site-core-content-finalizer-admin.php';
			}
			if ( Gloskin_Site_Core_Content_Finalizer_Admin::is_complete() ) {
				$mutations                              = $this->normalize_display_state();
				$state['display_contract_v1']           = 'complete';
				$state['display_contract_mutations']    = $mutations;
				$state['display_contract_completed_at'] = time();
				$updated                                = true;
			}
		}

		if ( 'complete' !== (string) ( $state['promo_type_v1'] ?? '' ) ) {
			$mutations                        = $this->normalize_promo_types();
			$state['promo_type_v1']           = 'complete';
			$state['promo_type_mutations']    = $mutations;
			$state['promo_type_completed_at'] = time();
			$updated                          = true;
		}

		if ( $updated ) {
			update_option( self::SETUP_OPTION, $state, false );
		}
	}

	/** @return int */
	private function normalize_promo_types() {
		$mutations = 0;
		$posts     = get_posts( array(
			'post_type'      => Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE,
			'post_status'    => array( 'publish', 'draft', 'private', 'pending', 'future', 'trash' ),
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		) );
		foreach ( $posts as $post ) {
			$type = (string) get_post_meta( $post->ID, 'gloskin_promo_type', true );
			if ( 'limited' !== $type && 'regular' !== $type ) {
				$mutations += $this->set_meta_if_changed( $post->ID, 'gloskin_promo_type', 'regular' );
			}
		}
		return $mutations;
	}

	/** @return int */
	private function normalize_display_state() {
		$mutations = 0;
		foreach ( array(
			Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE,
			Gloskin_Site_Core_Content_Service::TESTIMONIAL_POST_TYPE,
			Gloskin_Site_Core_Content_Service::ACHIEVEMENT_POST_TYPE,
		) as $post_type ) {
			$profile = Gloskin_Site_Core_Content_Service::editorial_profile( $post_type );
			$active_meta = isset( $profile['active_meta'] ) ? (string) $profile['active_meta'] : '';
			if ( '' === $active_meta ) {
				continue;
			}
			$posts = get_posts( array(
				'post_type'      => $post_type,
				'post_status'    => array( 'publish', 'draft', 'private', 'pending', 'future', 'trash' ),
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			) );
			foreach ( $posts as $post ) {
				if ( '' !== (string) get_post_meta( $post->ID, Gloskin_Site_Core_Content_Service::DEMO_IDENTITY_META, true ) ) {
					$mutations += $this->set_meta_if_changed( $post->ID, $active_meta, '0' );
					continue;
				}
				if ( Gloskin_Site_Core_Content_Service::TESTIMONIAL_POST_TYPE === $post_type
					&& '1' === (string) get_post_meta( $post->ID, $active_meta, true )
					&& '' === trim( (string) $post->post_excerpt ) ) {
					$mutations += $this->set_meta_if_changed( $post->ID, $active_meta, '0' );
				}
			}
		}

		$testimonial_profile = Gloskin_Site_Core_Content_Service::editorial_profile( Gloskin_Site_Core_Content_Service::TESTIMONIAL_POST_TYPE );
		$order_meta = (string) ( $testimonial_profile['order_meta'] ?? '' );
		if ( '' !== $order_meta ) {
			$testimonials = get_posts( array( 'post_type' => Gloskin_Site_Core_Content_Service::TESTIMONIAL_POST_TYPE, 'post_status' => array( 'publish', 'draft', 'private', 'pending', 'future', 'trash' ), 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) );
			$max_order = 0;
			$missing   = array();
			foreach ( $testimonials as $post ) {
				$order = (int) get_post_meta( $post->ID, $order_meta, true );
				if ( $order > 0 ) {
					$max_order = max( $max_order, $order );
				} else {
					$missing[] = (int) $post->ID;
				}
			}
			foreach ( $missing as $post_id ) {
				$max_order++;
				$mutations += $this->set_meta_if_changed( $post_id, $order_meta, (string) $max_order );
			}
		}
		return $mutations;
	}

	/** @return void */
	public function render_setup_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit' !== $screen->base || ! $this->is_managed_type( (string) $screen->post_type ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$state = get_option( self::SETUP_OPTION, array() );
		if ( is_array( $state ) && 'complete' === (string) ( $state['status'] ?? '' ) ) {
			return;
		}
		$ready = class_exists( 'Gloskin_Site_Core_Content_Finalizer_Admin' ) && Gloskin_Site_Core_Content_Finalizer_Admin::is_complete();
		?>
		<div class="notice <?php echo $ready ? 'notice-info' : 'notice-warning'; ?>"><p><strong><?php echo esc_html__( 'Editorial setup', 'gloskin-site-core' ); ?></strong> — <?php echo esc_html( $ready ? __( 'Ready to create the six canonical Promo records and migrate existing factual testimonial data.', 'gloskin-site-core' ) : __( 'Blocked until historical Content Finalizer is complete, so the old resolver cannot overwrite the canonical Promo collection.', 'gloskin-site-core' ) ); ?></p><?php if ( $ready ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0 0 12px"><input type="hidden" name="action" value="<?php echo esc_attr( self::SETUP_ACTION ); ?>"><?php wp_nonce_field( self::SETUP_ACTION, self::SETUP_NONCE ); ?><button type="submit" class="button button-primary"><?php echo esc_html__( 'Run one-shot Editorial Setup', 'gloskin-site-core' ); ?></button></form><?php endif; ?></div>
		<?php
	}

	/** @return void */
	public function handle_setup() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to run Editorial Setup.', 'gloskin-site-core' ), 403 );
		}
		check_admin_referer( self::SETUP_ACTION, self::SETUP_NONCE );
		$result = $this->run_setup();
		wp_safe_redirect( add_query_arg( array( 'post_type' => Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE, 'gloskin_editorial_setup' => rawurlencode( (string) $result['status'] ) ), admin_url( 'edit.php' ) ) );
		exit;
	}

	/** @return array{status:string,mutations:int} */
	public function run_setup() {
		$state = get_option( self::SETUP_OPTION, array() );
		if ( is_array( $state ) && 'complete' === (string) ( $state['status'] ?? '' ) ) {
			return array( 'status' => 'already_complete', 'mutations' => 0 );
		}
		if ( ! class_exists( 'Gloskin_Site_Core_Content_Finalizer_Admin' ) ) {
			require_once __DIR__ . '/class-gloskin-site-core-content-finalizer-admin.php';
		}
		if ( ! Gloskin_Site_Core_Content_Finalizer_Admin::is_complete() ) {
			return array( 'status' => 'blocked_content_finalizer', 'mutations' => 0 );
		}

		$mutations = 0;
		$seed_ids  = array();
		for ( $index = 1; $index <= 6; $index++ ) {
			$identity = 'promo-' . $index;
			$post_id  = $this->find_seed_post( $identity );
			if ( ! $post_id ) {
				$post_id = wp_insert_post( array( 'post_type' => Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE, 'post_status' => 'publish', 'post_title' => sprintf( 'Promo %d', $index ), 'post_name' => $identity ), true );
				if ( is_wp_error( $post_id ) ) {
					return $this->fail_setup( $mutations, $post_id->get_error_message() );
				}
				$mutations++;
			}
			$post_id = absint( $post_id );
			$seed_ids[] = $post_id;
			$mutations += $this->set_meta_if_changed( $post_id, self::SEED_META, $identity );
			$mutations += $this->set_meta_if_changed( $post_id, 'gloskin_promo_type', $index <= 3 ? 'limited' : 'regular' );
			$mutations += $this->set_meta_if_changed( $post_id, 'gloskin_promo_active', '1' );
			$mutations += $this->set_meta_if_changed( $post_id, 'gloskin_promo_order', (string) $index );
			$post = get_post( $post_id );
			if ( $post instanceof WP_Post && 'publish' !== $post->post_status ) {
				wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
				$mutations++;
			}
			$attachment_id = $this->seed_attachment( $identity, $index, $mutations );
			if ( ! $attachment_id ) {
				return $this->fail_setup( $mutations, sprintf( 'Could not import Media Library image for %s.', $identity ) );
			}
			if ( absint( get_post_thumbnail_id( $post_id ) ) !== $attachment_id ) {
				set_post_thumbnail( $post_id, $attachment_id );
				$mutations++;
			}
		}

		foreach ( $seed_ids as $post_id ) {
			foreach ( array( 'gloskin_promo_eyebrow', 'gloskin_promo_summary', 'gloskin_promo_cta_label', 'gloskin_promo_cta_url', 'gloskin_promo_start_date', 'gloskin_promo_end_date' ) as $obsolete ) {
				if ( metadata_exists( 'post', $post_id, $obsolete ) ) {
					delete_post_meta( $post_id, $obsolete );
					$mutations++;
				}
			}
		}

		$testimonials      = get_posts( array( 'post_type' => Gloskin_Site_Core_Content_Service::TESTIMONIAL_POST_TYPE, 'post_status' => array( 'publish', 'draft', 'private', 'pending', 'future', 'trash' ), 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) );
		$max_order         = 0;
		$missing_order_ids = array();
		foreach ( $testimonials as $post ) {
			$quote = trim( (string) $post->post_excerpt );
			if ( '' === $quote && '' !== trim( wp_strip_all_tags( (string) $post->post_content ) ) ) {
				$quote = trim( wp_strip_all_tags( (string) $post->post_content ) );
				wp_update_post( wp_slash( array( 'ID' => $post->ID, 'post_excerpt' => $quote ) ) );
				$mutations++;
			}
			if ( '' === trim( (string) get_post_meta( $post->ID, 'gloskin_testimonial_attribution', true ) ) && '' !== trim( (string) $post->post_title ) ) {
				update_post_meta( $post->ID, 'gloskin_testimonial_attribution', (string) $post->post_title );
				$mutations++;
			}
			if ( metadata_exists( 'post', $post->ID, 'gloskin_testimonial_source_note' ) ) {
				delete_post_meta( $post->ID, 'gloskin_testimonial_source_note' );
				$mutations++;
			}
			if ( ! metadata_exists( 'post', $post->ID, 'gloskin_testimonial_active' ) && 'publish' === $post->post_status && '' !== $quote ) {
				update_post_meta( $post->ID, 'gloskin_testimonial_active', '1' );
				$mutations++;
			}
			if ( '' === $quote && '1' === (string) get_post_meta( $post->ID, 'gloskin_testimonial_active', true ) ) {
				$mutations += $this->set_meta_if_changed( $post->ID, 'gloskin_testimonial_active', '0' );
			}

			$order = (int) get_post_meta( $post->ID, 'gloskin_testimonial_order', true );
			if ( metadata_exists( 'post', $post->ID, 'gloskin_testimonial_order' ) && $order > 0 ) {
				$max_order = max( $max_order, $order );
			} else {
				$missing_order_ids[] = (int) $post->ID;
			}
		}
		sort( $missing_order_ids, SORT_NUMERIC );
		foreach ( $missing_order_ids as $post_id ) {
			$max_order++;
			$mutations += $this->set_meta_if_changed( $post_id, 'gloskin_testimonial_order', (string) $max_order );
		}

		if ( ! $this->verify_seed_collection( $seed_ids ) ) {
			return $this->fail_setup( $mutations, 'Canonical Promo seed verification failed.' );
		}
		$state = is_array( $state ) ? $state : array();
		$state['status']       = 'complete';
		$state['mutations']    = $mutations;
		$state['completed_at'] = time();
		update_option( self::SETUP_OPTION, $state, false );
		return array( 'status' => 'complete', 'mutations' => $mutations );
	}

	/** @return array{status:string,mutations:int} */
	private function fail_setup( $mutations, $message ) {
		$state = get_option( self::SETUP_OPTION, array() );
		$state = is_array( $state ) ? $state : array();
		$state['status']     = 'failed';
		$state['last_error'] = sanitize_text_field( $message );
		$state['updated_at'] = time();
		update_option( self::SETUP_OPTION, $state, false );
		return array( 'status' => 'failed', 'mutations' => (int) $mutations );
	}

	/** @return bool */
	private function verify_seed_collection( $seed_ids ) {
		if ( 6 !== count( array_unique( array_map( 'absint', $seed_ids ) ) ) ) {
			return false;
		}
		$limited = 0;
		$regular = 0;
		foreach ( $seed_ids as $post_id ) {
			if ( 'publish' !== get_post_status( $post_id ) || '1' !== (string) get_post_meta( $post_id, 'gloskin_promo_active', true ) || ! absint( get_post_thumbnail_id( $post_id ) ) ) {
				return false;
			}
			$type = (string) get_post_meta( $post_id, 'gloskin_promo_type', true );
			if ( 'limited' === $type ) { $limited++; }
			if ( 'regular' === $type ) { $regular++; }
		}
		return 3 === $limited && 3 === $regular;
	}

	/** @return int */
	private function find_seed_post( $identity ) {
		$ids = get_posts( array( 'post_type' => Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE, 'post_status' => array( 'publish', 'draft', 'private', 'pending', 'future', 'trash' ), 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => self::SEED_META, 'meta_value' => $identity ) );
		return $ids ? absint( $ids[0] ) : 0;
	}

	/** @return int */
	private function seed_attachment( $identity, $index, &$mutations ) {
		$ids = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => self::SEED_META, 'meta_value' => $identity ) );
		if ( $ids ) {
			return absint( $ids[0] );
		}
		$source = plugin_dir_path( $this->plugin_file ) . 'assets/images/editorial/promo-' . $index . '.webp';
		if ( ! is_readable( $source ) ) {
			return 0;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$tmp = wp_tempnam( 'promo-' . $index . '.webp' );
		if ( ! $tmp || ! copy( $source, $tmp ) ) {
			return 0;
		}
		$file          = array( 'name' => 'gloskin-' . $identity . '.webp', 'tmp_name' => $tmp );
		$attachment_id = media_handle_sideload( $file, 0, sprintf( 'Gloskin %s', $identity ) );
		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- best-effort temporary-file cleanup.
			return 0;
		}
		update_post_meta( $attachment_id, self::SEED_META, $identity );
		$mutations++;
		return absint( $attachment_id );
	}

	/** @return int */
	private function set_meta_if_changed( $post_id, $key, $value ) {
		if ( (string) get_post_meta( $post_id, $key, true ) === (string) $value ) {
			return 0;
		}
		update_post_meta( $post_id, $key, $value );
		return 1;
	}

	/** @return array{width:int,height:int} */
	private function attachment_dimensions( $image_id ) {
		$metadata = $image_id ? wp_get_attachment_metadata( $image_id ) : array();
		return array(
			'width'  => is_array( $metadata ) && isset( $metadata['width'] ) ? absint( $metadata['width'] ) : 0,
			'height' => is_array( $metadata ) && isset( $metadata['height'] ) ? absint( $metadata['height'] ) : 0,
		);
	}

	/** @return float */
	private function normalize_focus( $value ) {
		$value = is_numeric( $value ) ? (float) $value : 50.0;
		return max( 0.0, min( 100.0, $value ) );
	}

	/** @return float */
	private function normalize_zoom( $value ) {
		$value = is_numeric( $value ) ? (float) $value : 100.0;
		return max( 100.0, min( 300.0, $value ) );
	}

	/** @return float */
	private function promo_focus_value( $post_id, $meta_key ) {
		if ( ! $post_id || '' === $meta_key || ! metadata_exists( 'post', $post_id, $meta_key ) ) {
			return 50.0;
		}
		return $this->normalize_focus( get_post_meta( $post_id, $meta_key, true ) );
	}

	/** @return float */
	private function promo_zoom_value( $post_id, $meta_key ) {
		if ( ! $post_id || '' === $meta_key || ! metadata_exists( 'post', $post_id, $meta_key ) ) {
			return 100.0;
		}
		return $this->normalize_zoom( get_post_meta( $post_id, $meta_key, true ) );
	}

	/** @return bool */
	private function is_managed_type( $post_type ) {
		return in_array( (string) $post_type, array( Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE, Gloskin_Site_Core_Content_Service::TESTIMONIAL_POST_TYPE ), true );
	}

	/** @return string */
	private function active_meta_key( $post_type ) {
		$profile = Gloskin_Site_Core_Content_Service::editorial_profile( $post_type );
		return isset( $profile['active_meta'] ) ? (string) $profile['active_meta'] : '';
	}

	/** @return string */
	private function order_meta_key( $post_type ) {
		$profile = Gloskin_Site_Core_Content_Service::editorial_profile( $post_type );
		return isset( $profile['order_meta'] ) ? (string) $profile['order_meta'] : '';
	}

	/** @return string */
	private function list_url( $post_type, $args = array() ) {
		return add_query_arg( array_merge( array( 'post_type' => $post_type ), $args ), admin_url( 'edit.php' ) );
	}
}
