<?php
/**
 * Read-only, bounded Gloskin diagnostic ZIP exporter.
 *
 * Loaded lazily by the authenticated admin download handler only. It never
 * boots on public requests and never writes outside a WordPress temp file.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Gloskin_Site_Core_Diagnostic_Exporter {
	const SCHEMA_VERSION          = '1.0';
	const MAX_SOURCE_FILE_BYTES   = 1048576;
	const MAX_SOURCE_TOTAL_BYTES  = 20971520;
	const MAX_ARCHIVE_BYTES       = 52428800;
	const MAX_ROUTE_CHECKS        = 20;

	/** @var string */
	private $plugin_file;

	/** @var string */
	private $plugin_root;

	/** @var string */
	private $plugin_version;

	/** @var array<int,string> */
	private $warnings = array();

	/** @var array<int,int> */
	private $media_ids = array();

	/** @var array<int,array<string,mixed>> */
	private $media_relations = array();

	/** @var int */
	private $source_bytes = 0;

	/**
	 * @param string $plugin_file Main plugin file.
	 * @param string $plugin_version Current plugin version.
	 */
	public function __construct( $plugin_file, $plugin_version ) {
		$this->plugin_file    = (string) $plugin_file;
		$this->plugin_version = (string) $plugin_version;
		$root                 = realpath( dirname( $this->plugin_file ) );
		$this->plugin_root    = false !== $root ? $root : dirname( $this->plugin_file );
	}

	/**
	 * Build a temporary archive. The caller owns streaming and final cleanup.
	 *
	 * @return array{path:string,filename:string}|WP_Error
	 */
	public function create() {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'gloskin_diagnostic_zip_unavailable', __( 'ZIP support is unavailable on this server. Enable the PHP Zip extension and try again.', 'gloskin-site-core' ) );
		}

		$temp_path = wp_tempnam( 'gloskin-diagnostic' );
		if ( ! is_string( $temp_path ) || '' === $temp_path ) {
			return new WP_Error( 'gloskin_diagnostic_temp_failed', __( 'WordPress could not create a temporary diagnostic file.', 'gloskin-site-core' ) );
		}

		register_shutdown_function( static function () use ( $temp_path ) {
			if ( is_file( $temp_path ) ) {
				@unlink( $temp_path );
			}
		} );

		try {
			$files = $this->collect_payload_files();
			ksort( $files, SORT_STRING );

			$manifest_files = array();
			foreach ( $files as $path => $contents ) {
				$manifest_files[] = array(
					'path'       => $path,
					'byte_size'  => strlen( $contents ),
					'sha256'     => hash( 'sha256', $contents ),
				);
			}
			$manifest_files[] = array(
				'path'          => 'manifest.json',
				'byte_size'     => null,
				'sha256'        => null,
				'self_reference' => true,
			);

			$files['manifest.json'] = $this->json( array(
				'schema_version'       => self::SCHEMA_VERSION,
				'plugin_version'       => $this->plugin_version,
				'wordpress_version'    => get_bloginfo( 'version' ),
				'woocommerce_version'  => defined( 'WC_VERSION' ) ? WC_VERSION : null,
				'generated_utc'        => gmdate( 'c' ),
				'generated_site_time'  => wp_date( 'c' ),
				'home_url'             => home_url( '/' ),
				'site_url'             => site_url( '/' ),
				'files'                => $manifest_files,
				'warnings'             => array_values( array_unique( $this->warnings ) ),
				'skipped_items'         => array_values( array_filter( array_unique( $this->warnings ), static function ( $warning ) { return 0 === strpos( $warning, 'Skipped ' ); } ) ),
				'redaction_policy'     => $this->redaction_policy(),
				'limits'               => $this->limits(),
			) );
			ksort( $files, SORT_STRING );

			$zip = new ZipArchive();
			$opened = $zip->open( $temp_path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
			if ( true !== $opened ) {
				throw new RuntimeException( 'ZipArchive open failed with code ' . (string) $opened );
			}
			foreach ( $files as $path => $contents ) {
				if ( ! $this->is_safe_archive_path( $path ) || ! $zip->addFromString( $path, $contents ) ) {
					$zip->close();
					throw new RuntimeException( 'Unable to add safe archive entry: ' . $path );
				}
			}
			if ( ! $zip->close() ) {
				throw new RuntimeException( 'ZipArchive close failed.' );
			}
			clearstatcache( true, $temp_path );
			$size = filesize( $temp_path );
			if ( false === $size || $size > self::MAX_ARCHIVE_BYTES ) {
				throw new RuntimeException( 'Final archive exceeded the 50 MB safety limit.' );
			}

			return array(
				'path'     => $temp_path,
				'filename' => 'gloskin-diagnostic-' . wp_date( 'Ymd-His' ) . '.zip',
			);
		} catch ( Throwable $error ) {
			if ( is_file( $temp_path ) ) {
				@unlink( $temp_path );
			}
			return new WP_Error( 'gloskin_diagnostic_build_failed', __( 'The diagnostic archive could not be generated safely.', 'gloskin-site-core' ), array( 'detail' => $this->redact_text( $error->getMessage() ) ) );
		}
	}

	/** @return array<string,string> */
	private function collect_payload_files() {
		$files = array(
			'README.txt'                => $this->readme(),
			'environment.json'          => $this->json( $this->environment() ),
			'site-structure.json'       => $this->json( $this->site_structure() ),
			'promo-diagnostic.json'     => $this->json( $this->promo_diagnostic() ),
			'migration-state.json'      => $this->json( $this->migration_state() ),
			'woocommerce-boundary.json' => $this->json( $this->woocommerce_boundary() ),
		);

		foreach ( $this->content_files() as $path => $contents ) {
			$files[ $path ] = $contents;
		}

		$code = $this->code_snapshot();
		$files['code-manifest.json'] = $this->json( $code['manifest'] );
		foreach ( $code['sources'] as $path => $contents ) {
			$files[ $path ] = $contents;
		}

		$files['media-manifest.json'] = $this->json( $this->media_manifest() );
		$files['runtime-health.json'] = $this->json( $this->runtime_health() );
		$files['route-checks.json']   = $this->json( $this->route_checks() );
		return $files;
	}

	/** @return string */
	private function readme() {
		return implode( "\n", array(
			'GLOSKIN CONTENT DIAGNOSTIC',
			'===========================',
			'',
			'Purpose: a read-only snapshot for diagnosing Gloskin content, routes, migrations, Promo, WooCommerce boundaries, media references, and first-party integration code.',
			'Generated: ' . wp_date( 'c' ) . ' (' . wp_timezone_string() . ')',
			'Schema version: ' . self::SCHEMA_VERSION,
			'',
			'Included: safe environment metadata, site structure, allowlisted editorial content/meta, Promo eligibility, migration state, catalog-only WooCommerce data, referenced media metadata, bounded code manifests/source, runtime health, and same-origin route checks.',
			'Excluded: credentials, salts, environment variables, users/usermeta, authentication data, logs, database dumps, complete options/postmeta, orders, customers, addresses, payment/refund/session data, form submissions, consultation inboxes, private messages, and media binaries.',
			'',
			'Redaction: suspicious structured keys and high-confidence credential patterns are replaced. Absolute WordPress paths are removed. Source files are copied into this ZIP only; deployed files are never changed.',
			'Limits: 1 MB per source file, 20 MB total source snapshot, 50 MB final ZIP, and 20 same-origin route checks.',
			'Known limitation: manifest.json lists itself as self-referential without its own size/hash, because embedding its final hash would recursively change that hash.',
			'',
			'Safe sharing: review this bundle before sharing and provide it only to trusted maintainers. The exporter does not change website data and leaves no persistent archive.',
			'',
		) );
	}

	/** @return array<string,mixed> */
	private function environment() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		global $wpdb;
		$active       = (array) get_option( 'active_plugins', array() );
		$network      = is_multisite() ? array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) : array();
		$plugin_rows  = array();
		foreach ( (array) get_plugins() as $file => $data ) {
			if ( false === stripos( $file, 'gloskin' ) && false === stripos( (string) $data['Name'], 'gloskin' ) ) { continue; }
			$plugin_rows[] = array( 'file' => $file, 'name' => $data['Name'], 'version' => $data['Version'], 'active' => in_array( $file, $active, true ) || in_array( $file, $network, true ) );
		}
		usort( $plugin_rows, static function ( $a, $b ) { return strcmp( $a['file'], $b['file'] ); } );
		$mu_rows = array();
		foreach ( (array) get_mu_plugins() as $file => $data ) {
			$mu_rows[] = array( 'file' => $file, 'name' => $data['Name'], 'version' => $data['Version'] );
		}
		usort( $mu_rows, static function ( $a, $b ) { return strcmp( $a['file'], $b['file'] ); } );
		$theme = wp_get_theme();
		$parent = $theme->parent();
		return array(
			'php_version'       => PHP_VERSION,
			'wordpress_version' => get_bloginfo( 'version' ),
			'database_server'   => is_object( $wpdb ) && method_exists( $wpdb, 'db_version' ) ? $wpdb->db_version() : null,
			'woocommerce_version' => defined( 'WC_VERSION' ) ? WC_VERSION : null,
			'environment_type'  => function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : null,
			'theme'             => array( 'name' => $theme->get( 'Name' ), 'stylesheet' => $theme->get_stylesheet(), 'version' => $theme->get( 'Version' ), 'parent' => $parent ? array( 'name' => $parent->get( 'Name' ), 'stylesheet' => $parent->get_stylesheet(), 'version' => $parent->get( 'Version' ) ) : null ),
			'gloskin_plugins'   => $plugin_rows,
			'must_use_plugins'  => $mu_rows,
			'locale'            => get_locale(),
			'timezone'          => wp_timezone_string(),
			'permalink_structure' => get_option( 'permalink_structure', '' ),
			'multisite'         => is_multisite(),
			'debug_flags'       => array( 'wp_debug' => defined( 'WP_DEBUG' ) && WP_DEBUG, 'wp_debug_display' => defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY, 'script_debug' => defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ),
			'limits'            => array( 'memory_limit' => ini_get( 'memory_limit' ), 'upload_max_filesize' => ini_get( 'upload_max_filesize' ), 'post_max_size' => ini_get( 'post_max_size' ), 'max_execution_time' => ini_get( 'max_execution_time' ) ),
			'extensions'        => array( 'zip' => class_exists( 'ZipArchive' ), 'curl' => extension_loaded( 'curl' ), 'dom' => extension_loaded( 'dom' ), 'mbstring' => extension_loaded( 'mbstring' ) ),
			'server_software'   => isset( $_SERVER['SERVER_SOFTWARE'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ), 0, 160 ) : null,
		);
	}

	/** @return array<string,mixed> */
	private function site_structure() {
		$post_types = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $object ) {
			$post_types[] = array( 'name' => $object->name, 'label' => $object->label, 'rewrite' => $object->rewrite, 'has_archive' => $object->has_archive );
		}
		$taxonomies = array();
		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $object ) {
			$taxonomies[] = array( 'name' => $object->name, 'label' => $object->label, 'object_types' => array_values( $object->object_type ), 'rewrite' => $object->rewrite );
		}
		$pages = array();
		$slug_groups = array();
		$unresolved_pages = array();
		foreach ( get_posts( array( 'post_type' => 'page', 'post_status' => array( 'publish', 'draft', 'pending', 'future' ), 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) as $page ) {
			if ( '' !== (string) $page->post_password ) { continue; }
			$path = get_page_uri( $page );
			$pages[] = array( 'id' => (int) $page->ID, 'title' => $page->post_title, 'slug' => $page->post_name, 'path' => $path, 'status' => $page->post_status, 'parent' => (int) $page->post_parent, 'template' => get_page_template_slug( $page->ID ), 'url' => 'publish' === $page->post_status ? get_permalink( $page ) : null );
			$slug_groups[ $path ][] = (int) $page->ID;
			if ( $page->post_parent && ! get_post( $page->post_parent ) ) { $unresolved_pages[] = array( 'id' => (int) $page->ID, 'missing_parent' => (int) $page->post_parent ); }
		}
		$menus = array();
		foreach ( wp_get_nav_menus() as $menu ) {
			$items = array();
			foreach ( (array) wp_get_nav_menu_items( $menu->term_id ) as $item ) {
				$items[] = array( 'id' => (int) $item->ID, 'title' => $item->title, 'parent' => (int) $item->menu_item_parent, 'type' => $item->type, 'object' => $item->object, 'object_id' => (int) $item->object_id, 'url' => $item->url );
			}
			$menus[] = array( 'id' => (int) $menu->term_id, 'name' => $menu->name, 'slug' => $menu->slug, 'items' => $items );
		}
		$duplicates = array();
		foreach ( $slug_groups as $path => $ids ) { if ( count( $ids ) > 1 ) { $duplicates[] = array( 'path' => $path, 'ids' => $ids ); } }
		return array(
			'public_post_types' => $post_types,
			'public_taxonomies' => $taxonomies,
			'front_page' => array( 'show_on_front' => get_option( 'show_on_front' ), 'page_on_front' => (int) get_option( 'page_on_front' ), 'page_for_posts' => (int) get_option( 'page_for_posts' ) ),
			'pages' => $pages,
			'menu_locations' => get_nav_menu_locations(),
			'navigation_menus' => $menus,
			'gloskin_routes' => $this->canonical_routes(),
			'duplicate_canonical_slugs' => $duplicates,
			'unresolved_page_relationships' => $unresolved_pages,
		);
	}

	/** @return array<string,string> */
	private function content_files() {
		$types = array(
			Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE,
			Gloskin_Site_Core_Content_Service::CLINIC_POST_TYPE,
			Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE,
			Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE,
			Gloskin_Site_Core_Content_Service::TESTIMONIAL_POST_TYPE,
			Gloskin_Site_Core_Content_Service::ACHIEVEMENT_POST_TYPE,
			Gloskin_Site_Core_Content_Service::QUESTION_POST_TYPE,
			'page',
			'post',
		);
		$files = array();
		foreach ( $types as $type ) {
			if ( ! post_type_exists( $type ) ) { continue; }
			$records = array();
			$posts = get_posts( array( 'post_type' => $type, 'post_status' => array( 'publish', 'draft', 'pending', 'future' ), 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) );
			foreach ( $posts as $post ) {
				if ( '' !== (string) $post->post_password ) {
					$this->warnings[] = 'Skipped password-protected post ID ' . (int) $post->ID . '.';
					continue;
				}
				$records[] = $this->post_record( $post );
			}
			$files[ 'gloskin-content/' . sanitize_file_name( $type ) . '.json' ] = $this->json( array( 'post_type' => $type, 'records' => $records ) );
		}
		$taxonomy_records = array();
		foreach ( array( Gloskin_Site_Core_Content_Service::FAMILY_TAXONOMY, Gloskin_Site_Core_Content_Service::CONCERN_TAXONOMY, Gloskin_Site_Core_Content_Service::CONSULTATION_TAXONOMY ) as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) { continue; }
			$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'orderby' => 'term_id', 'order' => 'ASC' ) );
			if ( is_wp_error( $terms ) ) { $this->warnings[] = 'Unable to collect taxonomy: ' . $taxonomy; continue; }
			foreach ( $terms as $term ) {
				$image_id = (int) get_term_meta( $term->term_id, Gloskin_Site_Core_Content_Service::PATH_META_IMAGE_ID, true );
				if ( $image_id ) { $this->remember_media( $image_id, 'consultation_path', (int) $term->term_id ); }
				$taxonomy_records[] = array( 'id' => (int) $term->term_id, 'taxonomy' => $taxonomy, 'name' => $term->name, 'slug' => $term->slug, 'description' => $term->description, 'parent' => (int) $term->parent, 'count' => (int) $term->count, 'path_order' => get_term_meta( $term->term_id, Gloskin_Site_Core_Content_Service::PATH_META_ORDER, true ), 'path_image_id' => $image_id, 'baseline_concerns' => get_term_meta( $term->term_id, Gloskin_Site_Core_Content_Service::PATH_META_BASELINE, true ) );
			}
		}
		$files['gloskin-content/taxonomies.json'] = $this->json( array( 'terms' => $taxonomy_records ) );
		return $files;
	}

	/** @param WP_Post $post Post. @return array<string,mixed> */
	private function post_record( $post ) {
		$taxonomies = get_object_taxonomies( $post->post_type );
		$terms = array();
		if ( $taxonomies ) {
			$found = wp_get_object_terms( $post->ID, $taxonomies );
			if ( ! is_wp_error( $found ) ) {
				foreach ( $found as $term ) { $terms[] = array( 'id' => (int) $term->term_id, 'taxonomy' => $term->taxonomy, 'name' => $term->name, 'slug' => $term->slug, 'parent' => (int) $term->parent ); }
			}
		}
		$meta = array();
		foreach ( $this->safe_meta_keys() as $key ) {
			$value = get_post_meta( $post->ID, $key, true );
			if ( '' !== $value && array() !== $value ) {
				$meta[ $key ] = $value;
				if ( in_array( $key, array( 'gloskin_gallery_image_ids', 'gloskin_about_founder_media_id', 'gloskin_hero_media_id' ), true ) ) {
					foreach ( (array) $value as $media_id ) { $this->remember_media( (int) $media_id, 'editorial_meta', (int) $post->ID ); }
				}
			}
		}
		$image_id = (int) get_post_thumbnail_id( $post->ID );
		if ( $image_id ) { $this->remember_media( $image_id, 'featured_image', $post->ID ); }
		return array(
			'id' => (int) $post->ID, 'type' => $post->post_type, 'status' => $post->post_status,
			'title' => $post->post_title, 'slug' => $post->post_name,
			'content' => $this->redact_text( $post->post_content ), 'excerpt' => $this->redact_text( $post->post_excerpt ),
			'created' => $post->post_date, 'modified' => $post->post_modified,
			'parent' => (int) $post->post_parent, 'menu_order' => (int) $post->menu_order,
			'url' => 'publish' === $post->post_status ? get_permalink( $post ) : null,
			'terms' => $terms, 'featured_media' => $this->media_summary( $image_id ), 'meta' => $meta,
		);
	}

	/** @return array<int,string> */
	private function safe_meta_keys() {
		return array(
			'_gloskin_demo_identity','_gloskin_demo_revision','gloskin_summary','gloskin_treatment_feature_on_home','gloskin_benefits','gloskin_contraindications','gloskin_clinic_ids','gloskin_doctor_ids','gloskin_booking_target',
			'gloskin_address','gloskin_phone_display','gloskin_phone_uri','gloskin_whatsapp_number','gloskin_whatsapp_message','gloskin_operating_hours','gloskin_map_url','gloskin_map_embed','gloskin_gallery_image_ids','gloskin_short_location',
			'gloskin_degree_title','gloskin_specialization','gloskin_branch_ids','gloskin_sip_number','gloskin_credentials','gloskin_profile','gloskin_schedule',
			'gloskin_promo_eyebrow','gloskin_promo_summary','gloskin_promo_cta_label','gloskin_promo_cta_url','gloskin_promo_start_date','gloskin_promo_end_date','gloskin_promo_active','gloskin_promo_order',
			'gloskin_testimonial_attribution','gloskin_testimonial_subtitle','gloskin_testimonial_active','gloskin_testimonial_source_note','gloskin_testimonial_order',
			'gloskin_achievement_issuer','gloskin_achievement_year','gloskin_achievement_source_url','gloskin_achievement_feature_on_home','gloskin_achievement_active','gloskin_achievement_order',
			'gloskin_woo_category_slug','gloskin_about_vision','gloskin_about_mission','gloskin_about_values','gloskin_about_founder_name','gloskin_about_founder_role','gloskin_about_founder_story','gloskin_about_founder_media_id',
			'gloskin_hero_heading','gloskin_hero_copy','gloskin_hero_cta_label','gloskin_hero_cta_url','gloskin_hero_media_id','gloskin_why_heading','gloskin_why_lead','gloskin_why_primary_title','gloskin_why_primary_copy',
			Gloskin_Site_Core_Content_Service::ANSWER_META_KEY,
		);
	}

	/** @return array<string,mixed> */
	private function promo_diagnostic() {
		$page = get_page_by_path( 'promo', OBJECT, 'page' );
		$now = function_exists( 'current_datetime' ) ? current_datetime() : new DateTimeImmutable( 'now', wp_timezone() );
		$rows = array();
		$eligible = array();
		$posts = post_type_exists( Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE ) ? get_posts( array( 'post_type' => Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE, 'post_status' => array( 'publish', 'draft', 'pending', 'future', 'private' ), 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) : array();
		foreach ( $posts as $post ) {
			$reasons = array();
			$active = '1' === (string) get_post_meta( $post->ID, 'gloskin_promo_active', true );
			$demo = '' !== (string) get_post_meta( $post->ID, '_gloskin_demo_identity', true );
			if ( 'publish' !== $post->post_status ) { $reasons[] = 'status_not_publish'; }
			if ( ! $active ) { $reasons[] = 'inactive'; }
			if ( $demo ) { $reasons[] = 'demo_identity'; }
			$reasons = array_merge( $reasons, $this->promo_date_reasons( $post->ID, $now ) );
			if ( array() === $reasons ) { $eligible[] = $post; }
			$summary = trim( (string) get_post_meta( $post->ID, 'gloskin_promo_summary', true ) );
			$cta_label = trim( (string) get_post_meta( $post->ID, 'gloskin_promo_cta_label', true ) );
			$cta_url = trim( (string) get_post_meta( $post->ID, 'gloskin_promo_cta_url', true ) );
			$image_id = (int) get_post_thumbnail_id( $post->ID );
			if ( $image_id ) { $this->remember_media( $image_id, 'promo', $post->ID ); }
			$rows[] = array( 'id' => (int) $post->ID, 'status' => $post->post_status, 'title' => $post->post_title, 'start_date' => get_post_meta( $post->ID, 'gloskin_promo_start_date', true ), 'end_date' => get_post_meta( $post->ID, 'gloskin_promo_end_date', true ), 'order' => get_post_meta( $post->ID, 'gloskin_promo_order', true ), 'active' => $active, 'demo_identity' => $demo, 'summary_ready' => '' !== $summary, 'content_ready' => '' !== trim( $post->post_content ), 'cta_ready' => '' !== $cta_label && '' !== $cta_url, 'media_ready' => $image_id > 0, 'eligible' => array() === $reasons, 'exclusion_reasons' => $reasons );
		}
		usort( $eligible, array( $this, 'compare_promos' ) );
		$eligible_ids = array_map( static function ( $post ) { return (int) $post->ID; }, $eligible );
		if ( $posts && ! $eligible ) { $this->warnings[] = 'Published or imported Promo records exist, but none are eligible for rendering.'; }
		if ( $page && '' === trim( $page->post_content ) ) { $this->warnings[] = 'The Promo Page body is empty because the managed Promo renderer owns its public content.'; }
		return array(
			'current_site_time' => $now->format( DATE_ATOM ), 'timezone' => wp_timezone_string(),
			'promo_page' => $page ? array( 'exists' => true, 'id' => (int) $page->ID, 'status' => $page->post_status, 'slug' => $page->post_name, 'template' => get_page_template_slug( $page->ID ), 'url' => get_permalink( $page ), 'content_length' => strlen( $page->post_content ) ) : array( 'exists' => false ),
			'records' => $rows,
			'homepage_selected_ids' => array_slice( $eligible_ids, 0, 5 ),
			'promo_page_selected_ids' => array_slice( $eligible_ids, 0, 10 ),
			'query' => array( 'post_type' => Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC', 'meta_query' => array( array( 'key' => 'gloskin_promo_active', 'value' => '1', 'compare' => '=' ) ) ),
			'eligibility_rules' => array( 'published', 'active_equals_1', 'no_demo_identity', 'start_date_inclusive', 'end_date_inclusive_local_23_59_59', 'invalid_dates_warn_and_remain_eligible', 'order_meta_then_title_then_id' ),
		);
	}

	/** @param int $post_id Post ID. @param DateTimeInterface $now Current time. @return array<int,string> */
	private function promo_date_reasons( $post_id, $now ) {
		$reasons = array(); $tz = wp_timezone();
		foreach ( array( 'start' => 'gloskin_promo_start_date', 'end' => 'gloskin_promo_end_date' ) as $side => $key ) {
			$value = trim( (string) get_post_meta( $post_id, $key, true ) );
			if ( '' === $value ) { continue; }
			try {
				$point = new DateTimeImmutable( $value . ( 'start' === $side ? ' 00:00:00' : ' 23:59:59' ), $tz );
				if ( 'start' === $side && $now < $point ) { $reasons[] = 'before_start_date'; }
				if ( 'end' === $side && $now > $point ) { $reasons[] = 'after_end_date'; }
			} catch ( Exception $error ) {
				$this->warnings[] = 'Promo ID ' . (int) $post_id . ' has an invalid ' . $side . ' date; frontend rules keep it eligible.';
			}
		}
		return $reasons;
	}

	/** @param WP_Post $a First. @param WP_Post $b Second. @return int */
	public function compare_promos( $a, $b ) {
		$ao = (int) get_post_meta( $a->ID, 'gloskin_promo_order', true ); $bo = (int) get_post_meta( $b->ID, 'gloskin_promo_order', true );
		if ( $ao > 0 && $bo <= 0 ) { return -1; } if ( $ao <= 0 && $bo > 0 ) { return 1; } if ( $ao !== $bo ) { return $ao <=> $bo; }
		$title = strcmp( (string) $a->post_title, (string) $b->post_title ); return 0 !== $title ? $title : ( (int) $a->ID <=> (int) $b->ID );
	}

	/** @return array<string,mixed> */
	private function migration_state() {
		$options = array(
			'gloskin_site_core_schema_version','gloskin_site_core_prototype_ia_20260818_state','gloskin_site_core_prototype_ia_20260818_lock','gloskin_site_core_revision_20260819_state','gloskin_site_core_revision_20260819_lock','gloskin_site_core_revision_20260819f_state','gloskin_site_core_revision_20260819f_lock','gloskin_site_core_sample_products_v1_state','gloskin_site_core_sample_products_v1_lock','gloskin_site_core_insights_v1_state','gloskin_site_core_insights_v1_lock','gloskin_site_core_doctor_migration_v1','gloskin_site_core_doctor_migration_v1_lock','gloskin_site_core_consultation_demo','gloskin_site_core_editorial_media_v1','gloskin_site_core_description_consolidation','gloskin_site_core_description_consolidation_error',
		);
		$state = array(); foreach ( $options as $option ) { $value = get_option( $option, null ); if ( null !== $value ) { $state[ $option ] = $value; } }
		return array( 'allowlisted_options' => $state, 'note' => 'No complete options-table export is performed.' );
	}

	/** @return array<string,mixed> */
	private function woocommerce_boundary() {
		if ( ! function_exists( 'wc_get_product' ) ) { return array( 'available' => false, 'excluded' => $this->woocommerce_exclusions() ); }
		$pages = array();
		foreach ( array( 'shop', 'cart', 'checkout', 'myaccount' ) as $name ) {
			$id = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( $name ) : 0; $post = $id > 0 ? get_post( $id ) : null;
			$pages[ $name ] = array( 'id' => $id, 'status' => $post ? $post->post_status : null, 'url' => $id > 0 ? get_permalink( $id ) : null );
		}
		$ids = get_posts( array( 'post_type' => 'product', 'post_status' => array( 'publish', 'draft', 'pending', 'future' ), 'posts_per_page' => 500, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ids' ) );
		if ( 500 === count( $ids ) ) { $this->warnings[] = 'Product snapshot reached its defensive 500-record limit.'; }
		$products = array();
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id ); if ( ! $product ) { continue; }
			$attributes = array(); foreach ( $product->get_attributes() as $attribute ) { $attributes[] = array( 'name' => $attribute->get_name(), 'visible' => $attribute->get_visible(), 'variation' => $attribute->get_variation(), 'options' => $attribute->get_options() ); }
			$images = array_merge( array( (int) $product->get_image_id() ), array_map( 'intval', $product->get_gallery_image_ids() ) );
			foreach ( array_filter( $images ) as $image_id ) { $this->remember_media( $image_id, 'product', (int) $id ); }
			$products[] = array( 'id' => (int) $id, 'status' => $product->get_status(), 'name' => $product->get_name(), 'slug' => $product->get_slug(), 'description' => $this->redact_text( $product->get_description() ), 'short_description' => $this->redact_text( $product->get_short_description() ), 'sku' => $product->get_sku(), 'price' => $product->get_price(), 'regular_price' => $product->get_regular_price(), 'sale_price' => $product->get_sale_price(), 'type' => $product->get_type(), 'category_ids' => array_map( 'intval', $product->get_category_ids() ), 'attributes' => $attributes, 'image_ids' => array_values( array_filter( $images ) ), 'stock_status' => $product->get_stock_status() );
		}
		$counts = wp_count_posts( 'product' );
		return array( 'available' => true, 'version' => defined( 'WC_VERSION' ) ? WC_VERSION : null, 'pages' => $pages, 'product_counts' => $counts, 'products' => $products, 'excluded' => $this->woocommerce_exclusions() );
	}

	/** @return array<int,string> */
	private function woocommerce_exclusions() { return array( 'orders', 'customers', 'addresses', 'payment data', 'refunds', 'sessions', 'gateway configuration', 'private notes' ); }

	/** @return array<string,mixed> */
	private function code_snapshot() {
		$plugin = $this->scan_code_root( $this->plugin_root, 'code/gloskin-site-core', true );
		$theme_root = function_exists( 'get_stylesheet_directory' ) ? realpath( get_stylesheet_directory() ) : false;
		$theme = $theme_root ? $this->scan_code_root( $theme_root, 'code/theme', $this->is_first_party_theme() ) : array( 'files' => array(), 'sources' => array() );
		return array( 'manifest' => array( 'plugin_root' => 'gloskin-site-core', 'plugin_files' => $plugin['files'], 'active_theme' => wp_get_theme()->get( 'Name' ), 'theme_files' => $theme['files'], 'theme_source_included' => $this->is_first_party_theme() ), 'sources' => array_merge( $plugin['sources'], $theme['sources'] ) );
	}

	/** @param string $root Root. @param string $archive_prefix Prefix. @param bool $include_source Include text. @return array<string,mixed> */
	private function scan_code_root( $root, $archive_prefix, $include_source ) {
		$files = array(); $sources = array(); $root_real = realpath( $root ); if ( false === $root_real ) { return array( 'files' => $files, 'sources' => $sources ); }
		try { $iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root_real, FilesystemIterator::SKIP_DOTS ) ); } catch ( UnexpectedValueException $error ) { $this->warnings[] = 'Unable to inspect code root.'; return array( 'files' => $files, 'sources' => $sources ); }
		foreach ( $iterator as $file ) {
			if ( $file->isLink() || ! $file->isFile() ) { continue; }
			$real = realpath( $file->getPathname() ); if ( false === $real || 0 !== strpos( $real, $root_real . DIRECTORY_SEPARATOR ) ) { $this->warnings[] = 'Skipped source outside its validated root.'; continue; }
			$relative = str_replace( DIRECTORY_SEPARATOR, '/', substr( $real, strlen( $root_real ) + 1 ) );
			if ( $this->excluded_code_path( $relative ) ) { continue; }
			$size = (int) $file->getSize(); $files[] = array( 'path' => $relative, 'byte_size' => $size, 'modified_utc' => gmdate( 'c', $file->getMTime() ), 'sha256' => hash_file( 'sha256', $real ) );
			if ( ! $include_source || ! preg_match( '/\.(?:php|css|js|json|md|markdown|ya?ml|txt)$/i', $relative ) ) { continue; }
			if ( $size > self::MAX_SOURCE_FILE_BYTES || $this->source_bytes + $size > self::MAX_SOURCE_TOTAL_BYTES ) { $this->warnings[] = 'Skipped bounded source file: ' . $relative; continue; }
			$contents = file_get_contents( $real ); if ( false === $contents ) { $this->warnings[] = 'Unable to read source file: ' . $relative; continue; }
			$this->source_bytes += strlen( $contents ); $sources[ trim( $archive_prefix, '/' ) . '/' . $relative ] = $this->redact_source( $contents );
		}
		usort( $files, static function ( $a, $b ) { return strcmp( $a['path'], $b['path'] ); } ); ksort( $sources, SORT_STRING );
		return array( 'files' => $files, 'sources' => $sources );
	}

	/** @param string $path Relative path. @return bool */
	private function excluded_code_path( $path ) {
		$normalized = '/' . strtolower( str_replace( '\\', '/', $path ) ) . '/';
		if ( preg_match( '#/(?:vendor|node_modules|uploads|cache|tmp|temp|migration-media|\.git)/#', $normalized ) ) { return true; }
		return (bool) preg_match( '/(?:^|\/)(?:\.env(?:\..*)?|wp-config\.php|debug\.log|[^\/]*(?:credential|private[-_]?key|secret)[^\/]*)$/i', $path );
	}

	/** @return bool */
	private function is_first_party_theme() { $theme = wp_get_theme(); return false !== stripos( $theme->get( 'Name' ), 'gloskin' ) || false !== stripos( $theme->get_stylesheet(), 'gloskin' ) || false !== stripos( $theme->get( 'Author' ), 'gloskin' ); }

	/** @return array<string,mixed> */
	private function media_manifest() {
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $this->media_ids ) ) ) ); sort( $ids, SORT_NUMERIC );
		$uploads = wp_get_upload_dir(); $upload_root = ! empty( $uploads['basedir'] ) ? realpath( $uploads['basedir'] ) : false; $records = array();
		foreach ( $ids as $id ) {
			$post = get_post( $id ); if ( ! $post || 'attachment' !== $post->post_type ) { $records[] = array( 'id' => $id, 'exists' => false ); continue; }
			$meta = wp_get_attachment_metadata( $id ); $absolute = get_attached_file( $id ); $real = $absolute ? realpath( $absolute ) : false; $contained = $real && $upload_root && ( $real === $upload_root || 0 === strpos( $real, $upload_root . DIRECTORY_SEPARATOR ) );
			$records[] = array( 'id' => $id, 'exists' => true, 'title' => $post->post_title, 'mime_type' => $post->post_mime_type, 'dimensions' => is_array( $meta ) ? array( 'width' => isset( $meta['width'] ) ? (int) $meta['width'] : null, 'height' => isset( $meta['height'] ) ? (int) $meta['height'] : null ) : null, 'relative_upload_path' => get_post_meta( $id, '_wp_attached_file', true ), 'url' => wp_get_attachment_url( $id ), 'alt' => get_post_meta( $id, '_wp_attachment_image_alt', true ), 'caption' => $post->post_excerpt, 'file_exists' => (bool) $real, 'sha256' => $contained && is_file( $real ) ? hash_file( 'sha256', $real ) : null, 'relationships' => array_values( array_filter( $this->media_relations, static function ( $relation ) use ( $id ) { return $id === $relation['media_id']; } ) ) );
		}
		return array( 'media_binaries_included' => false, 'attachments' => $records );
	}

	/** @param int $id Media ID. @param string $relation Relation. @param int $object_id Object. @return void */
	private function remember_media( $id, $relation, $object_id ) { $id = (int) $id; if ( $id <= 0 ) { return; } $this->media_ids[] = $id; $this->media_relations[] = array( 'media_id' => $id, 'relation' => $relation, 'object_id' => (int) $object_id ); }

	/** @param int $id Media ID. @return array<string,mixed>|null */
	private function media_summary( $id ) { if ( $id <= 0 ) { return null; } $post = get_post( $id ); return $post ? array( 'id' => $id, 'url' => wp_get_attachment_url( $id ), 'alt' => get_post_meta( $id, '_wp_attachment_image_alt', true ), 'caption' => $post->post_excerpt ) : array( 'id' => $id, 'missing' => true ); }

	/** @return array<string,mixed> */
	private function runtime_health() {
		$counts = array(); foreach ( array_merge( array( 'page', 'post', 'product' ), Gloskin_Site_Core_Content_Service::record_targets() ? array_keys( Gloskin_Site_Core_Content_Service::record_targets() ) : array() ) as $type ) { if ( post_type_exists( $type ) ) { $counts[ $type ] = wp_count_posts( $type ); } }
		$cron_hooks = array(); if ( function_exists( '_get_cron_array' ) ) { foreach ( (array) _get_cron_array() as $timestamp => $hooks ) { foreach ( array_keys( $hooks ) as $hook ) { if ( false !== stripos( $hook, 'gloskin' ) ) { $cron_hooks[] = array( 'hook' => $hook, 'next_utc' => gmdate( 'c', (int) $timestamp ) ); } } } }
		$rest_routes = array(); if ( function_exists( 'rest_get_server' ) ) { foreach ( array_keys( rest_get_server()->get_routes() ) as $route ) { if ( false !== stripos( $route, 'gloskin' ) ) { $rest_routes[] = $route; } } }
		$rewrite = array(); foreach ( (array) get_option( 'rewrite_rules', array() ) as $pattern => $target ) { if ( false !== stripos( $pattern . $target, 'gloskin' ) ) { $rewrite[] = array( 'pattern' => $pattern, 'target' => $target ); } }
		$missing_media = array(); foreach ( array_unique( $this->media_ids ) as $media_id ) { if ( 'attachment' !== get_post_type( $media_id ) ) { $missing_media[] = (int) $media_id; } }
		$invalid_cta = array();
		foreach ( array( 'gloskin_promo_cta_url', 'gloskin_hero_cta_url', 'gloskin_booking_target', 'gloskin_achievement_source_url' ) as $meta_key ) {
			foreach ( get_posts( array( 'post_type' => 'any', 'post_status' => array( 'publish', 'draft', 'pending', 'future' ), 'posts_per_page' => 500, 'orderby' => 'ID', 'order' => 'ASC', 'meta_key' => $meta_key, 'meta_compare' => 'EXISTS' ) ) as $post ) {
				$url = trim( (string) get_post_meta( $post->ID, $meta_key, true ) );
				if ( '' !== $url && ! wp_http_validate_url( $url ) ) { $invalid_cta[] = array( 'post_id' => (int) $post->ID, 'meta_key' => $meta_key ); }
			}
		}
		return array( 'gloskin_cron_hooks' => $cron_hooks, 'gloskin_rest_routes' => $rest_routes, 'relevant_rewrite_rules' => $rewrite, 'content_counts' => $counts, 'missing_referenced_media_ids' => $missing_media, 'invalid_cta_references' => $invalid_cta, 'warnings' => array_values( array_unique( $this->warnings ) ) );
	}

	/** @return array<string,mixed> */
	private function route_checks() {
		$results = array(); $routes = array_slice( $this->canonical_routes(), 0, self::MAX_ROUTE_CHECKS ); $home_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		foreach ( $routes as $route ) {
			$url = $route['url']; if ( wp_parse_url( $url, PHP_URL_HOST ) !== $home_host ) { continue; }
			$response = wp_remote_get( $url, array( 'timeout' => 3, 'redirection' => 0, 'cookies' => array(), 'user-agent' => 'Gloskin-Diagnostic/' . $this->plugin_version ) );
			if ( is_wp_error( $response ) ) { $results[] = array( 'requested_url' => $url, 'error' => $response->get_error_code() ); $this->warnings[] = 'Route check failed: ' . $url; continue; }
			$status = (int) wp_remote_retrieve_response_code( $response ); $body = substr( (string) wp_remote_retrieve_body( $response ), 0, 524288 ); $location = wp_remote_retrieve_header( $response, 'location' );
			if ( $location && wp_parse_url( $location, PHP_URL_HOST ) && wp_parse_url( $location, PHP_URL_HOST ) !== $home_host ) { $this->warnings[] = 'External redirect was not followed: ' . $url; }
			preg_match( '/<title[^>]*>(.*?)<\/title>/is', $body, $title ); preg_match_all( '/<h1\b[^>]*>/i', $body, $h1 );
			$results[] = array( 'requested_url' => $url, 'final_url' => $url, 'status' => $status, 'content_type' => wp_remote_retrieve_header( $response, 'content-type' ), 'redirect_location' => $location ?: null, 'title' => isset( $title[1] ) ? html_entity_decode( wp_strip_all_tags( $title[1] ), ENT_QUOTES, get_bloginfo( 'charset' ) ) : null, 'h1_count' => count( $h1[0] ) );
		}
		return array( 'authentication_cookies_sent' => false, 'external_redirects_followed' => false, 'checks' => $results );
	}

	/** @return array<int,array<string,string>> */
	private function canonical_routes() {
		$paths = array( '/', '/treatments/', '/skincare/', '/shop/', '/about/', '/promo/', '/insights/', '/clinics/', '/doctors/', '/contact/' ); $routes = array();
		foreach ( $paths as $path ) { $routes[] = array( 'path' => $path, 'url' => home_url( $path ) ); }
		$product = get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 1, 'orderby' => 'ID', 'order' => 'ASC' ) ); if ( $product ) { $routes[] = array( 'path' => 'product:' . $product[0]->post_name, 'url' => get_permalink( $product[0] ) ); }
		return $routes;
	}

	/** @param mixed $value Value. @return string */
	private function json( $value ) { $encoded = wp_json_encode( $this->sort_structured( $this->redact_structured( $value ) ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); return false === $encoded ? "{}\n" : $encoded . "\n"; }

	/** @param mixed $value Value. @return mixed */
	private function sort_structured( $value ) {
		if ( ! is_array( $value ) ) { return $value; }
		foreach ( $value as $key => $child ) { $value[ $key ] = $this->sort_structured( $child ); }
		if ( array() !== $value && array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) { ksort( $value, SORT_STRING ); }
		return $value;
	}

	/** @param mixed $value Value. @param string $key Key. @return mixed */
	private function redact_structured( $value, $key = '' ) {
		if ( '' !== $key && preg_match( '/(?:password|passwd|secret|token|authorization|cookie|nonce|license|client[_-]?secret|api[_-]?key|private[_-]?key|(?:^|[_-])key(?:$|[_-]))/i', $key ) ) {
			if ( is_string( $value ) && 0 === strpos( $value, 'gloskin_' ) ) { return $value; }
			return '[REDACTED]';
		}
		if ( is_array( $value ) ) { $out = array(); foreach ( $value as $child_key => $child ) { $out[ $child_key ] = $this->redact_structured( $child, (string) $child_key ); } return $out; }
		if ( is_object( $value ) ) { return $this->redact_structured( get_object_vars( $value ), $key ); }
		return is_string( $value ) ? $this->redact_text( $value ) : $value;
	}

	/** @param string $text Text. @return string */
	private function redact_text( $text ) {
		$text = (string) $text;
		foreach ( array( defined( 'ABSPATH' ) ? ABSPATH : '', defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : '' ) as $root ) { if ( '' !== $root ) { $text = str_replace( array( $root, str_replace( '\\', '/', $root ) ), '[REDACTED_PATH]/', $text ); } }
		$text = preg_replace( '/-----BEGIN [^-]*PRIVATE KEY-----.*?-----END [^-]*PRIVATE KEY-----/s', '[REDACTED_PRIVATE_KEY]', $text );
		return is_string( $text ) ? $text : '';
	}

	/** @param string $source Source. @return string */
	private function redact_source( $source ) {
		$source = $this->redact_text( $source );
		$source = preg_replace( '/((?:password|passwd|secret|token|api[_-]?key|client[_-]?secret|authorization|license)\s*(?:=>|=|:)\s*[\'\"])[^\'\"]+([\'\"])/i', '$1[REDACTED]$2', $source );
		return is_string( $source ) ? $source : '';
	}

	/** @param string $path Archive path. @return bool */
	private function is_safe_archive_path( $path ) { return '' !== $path && '/' !== $path[0] && false === strpos( $path, '\\' ) && ! preg_match( '#(?:^|/)\.\.(?:/|$)#', $path ); }

	/** @return array<string,mixed> */
	private function limits() { return array( 'source_file_bytes' => self::MAX_SOURCE_FILE_BYTES, 'source_total_bytes' => self::MAX_SOURCE_TOTAL_BYTES, 'archive_bytes' => self::MAX_ARCHIVE_BYTES, 'route_checks' => self::MAX_ROUTE_CHECKS ); }

	/** @return array<string,mixed> */
	private function redaction_policy() { return array( 'strict_allowlists' => true, 'structured_secret_keys_redacted' => true, 'embedded_credential_patterns_redacted' => true, 'absolute_wordpress_paths_removed' => true, 'users_and_usermeta_excluded' => true, 'commerce_private_data_excluded' => true, 'media_binaries_excluded' => true ); }
}
