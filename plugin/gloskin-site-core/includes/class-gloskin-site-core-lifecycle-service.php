<?php
/**
 * Gloskin plugin lifecycle owner.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Gloskin_Site_Core_Lifecycle_Service {
	const BASE_SCHEMA_VERSION = '0.2.2';
	const SCHEMA_VERSION      = '0.3.0';
	const VERSION_OPTION      = 'gloskin_site_core_schema_version';
	const ABOUT_RECONCILIATION_OPTION  = 'gloskin_site_core_about_reconciliation_v1_state';
	const ABOUT_RECONCILIATION_VERSION = '2026-08-21.2';

	/**
	 * Register narrowly scoped upgrades for already-active installs.
	 *
	 * Schema 0.3.0 is intentionally completed by the bounded prototype IA
	 * migration runner. admin_init never continuously repairs the primary menu.
	 *
	 * @return void
	 */
	public function register_upgrade() {
		add_action( 'admin_init', array( $this, 'maybe_upgrade' ), 5 );
	}

	/**
	 * Register the one bounded About content reconciliation on normal init.
	 * Existing editor-owned values are never overwritten and a completed state
	 * is terminal. This is deliberately separate from historical migrations so
	 * resolving the final About fields cannot reopen Phase-3/Phase-4 runners.
	 *
	 * @return void
	 */
	public function register_about_reconciliation() {
		add_action( 'init', array( $this, 'maybe_reconcile_about_content' ), 30 );
	}

	/**
	 * Fill only genuinely empty About Story/Founder fields from current factual
	 * first-party sources. Vision/Mission/Values are never auto-written here:
	 * existing production values are preserved, while any of those fields that
	 * this reconciler wrote in an earlier version are surfaced in persisted
	 * state for operator factual approval. Founder media reuses the existing
	 * canonical dr. Nanang doctor thumbnail; no attachment is created or copied.
	 *
	 * A same-version complete state is terminal. A same-version needs_attention
	 * state also becomes read-only on public requests so an unresolved field does
	 * not cause repeated frontend option writes; an admin request may re-evaluate
	 * after an operator edit, and a reconciliation version change may retry once.
	 *
	 * @return void
	 */
	public function maybe_reconcile_about_content() {
		$state        = get_option( self::ABOUT_RECONCILIATION_OPTION, array() );
		$same_version = is_array( $state )
			&& self::ABOUT_RECONCILIATION_VERSION === (string) ( $state['version'] ?? '' );
		$status       = is_array( $state ) ? (string) ( $state['status'] ?? '' ) : '';

		if ( $same_version && 'complete' === $status ) {
			return;
		}
		if ( $same_version && 'needs_attention' === $status && ! is_admin() ) {
			return;
		}

		$previous_mutations = is_array( $state ) && isset( $state['mutations'] ) && is_array( $state['mutations'] )
			? array_values( array_unique( array_filter( $state['mutations'], 'is_string' ) ) )
			: array();
		$reviewable_fields = array(
			'gloskin_about_vision',
			'gloskin_about_mission',
			'gloskin_about_values',
		);
		$requires_operator_approval = array_values( array_intersect( $previous_mutations, $reviewable_fields ) );

		$page = get_page_by_path( 'about', OBJECT, 'page' );
		if ( ! ( $page instanceof WP_Post ) || 'trash' === $page->post_status ) {
			$next_state = array(
				'version'                    => self::ABOUT_RECONCILIATION_VERSION,
				'status'                     => 'needs_attention',
				'missing'                    => array( 'about_page' ),
				'mutations'                  => $previous_mutations,
				'requires_operator_approval' => $requires_operator_approval,
			);
			if ( $next_state !== $state ) {
				update_option( self::ABOUT_RECONCILIATION_OPTION, $next_state, false );
			}
			return;
		}

		$mutations = $previous_mutations;
		$defaults  = $this->about_reconciliation_defaults();

		if ( '' === trim( (string) $page->post_content ) ) {
			$result = wp_update_post(
				array(
					'ID'           => (int) $page->ID,
					'post_content' => $defaults['post_content'],
				),
				true
			);
			if ( ! is_wp_error( $result ) ) {
				$mutations[] = 'post_content';
			}
		}

		$meta_defaults = array(
			'gloskin_about_founder_name'  => $defaults['founder_name'],
			'gloskin_about_founder_role'  => $defaults['founder_role'],
			'gloskin_about_founder_story' => $defaults['founder_story'],
		);
		foreach ( $meta_defaults as $key => $value ) {
			if ( '' !== trim( (string) get_post_meta( $page->ID, $key, true ) ) ) {
				continue;
			}
			update_post_meta( $page->ID, $key, $value );
			$mutations[] = $key;
		}

		$founder_media_id = absint( get_post_meta( $page->ID, 'gloskin_about_founder_media_id', true ) );
		if ( ! $founder_media_id ) {
			$founder = get_page_by_path(
				'dr-nanang-masrani-m-biomed-aam',
				OBJECT,
				Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE
			);
			if ( $founder instanceof WP_Post ) {
				$canonical_media_id = absint( get_post_thumbnail_id( $founder->ID ) );
				if ( $canonical_media_id ) {
					update_post_meta( $page->ID, 'gloskin_about_founder_media_id', $canonical_media_id );
					$mutations[] = 'gloskin_about_founder_media_id';
				}
			}
		}

		$mutations                  = array_values( array_unique( $mutations ) );
		$requires_operator_approval = array_values( array_intersect( $mutations, $reviewable_fields ) );
		$verified_page              = get_post( $page->ID );
		$missing                    = array();
		if ( ! ( $verified_page instanceof WP_Post ) || '' === trim( (string) $verified_page->post_content ) ) {
			$missing[] = 'post_content';
		}
		foreach ( array_keys( $meta_defaults ) as $key ) {
			if ( '' === trim( (string) get_post_meta( $page->ID, $key, true ) ) ) {
				$missing[] = $key;
			}
		}
		foreach ( $reviewable_fields as $key ) {
			if ( '' === trim( (string) get_post_meta( $page->ID, $key, true ) ) ) {
				$missing[] = $key;
			}
		}
		if ( ! absint( get_post_meta( $page->ID, 'gloskin_about_founder_media_id', true ) ) ) {
			$missing[] = 'gloskin_about_founder_media_id';
		}

		$next_state = array(
			'version'                    => self::ABOUT_RECONCILIATION_VERSION,
			'status'                     => empty( $missing ) && empty( $requires_operator_approval ) ? 'complete' : 'needs_attention',
			'missing'                    => array_values( array_unique( $missing ) ),
			'mutations'                  => $mutations,
			'requires_operator_approval' => $requires_operator_approval,
			'sources'                    => array(
				'current_story'           => 'https://gloskin.id/mengapa-harus-gloskin-',
				'current_founder_profile' => 'https://gloskin.id/founder-and-dokter-gloskin',
				'vision_mission_values'   => 'operator approval required; historical references are not auto-write authority',
				'founder_media'           => 'canonical gloskin_doctor thumbnail: dr-nanang-masrani-m-biomed-aam',
			),
		);
		if ( $next_state !== $state ) {
			update_option( self::ABOUT_RECONCILIATION_OPTION, $next_state, false );
		}
	}

	/**
	 * Factual About defaults used only when the managed WordPress fields are
	 * empty. Corporate Vision/Mission/Values intentionally have no defaults:
	 * those fields require editor/operator-approved authority.
	 *
	 * @return array<string,string>
	 */
	private function about_reconciliation_defaults() {
		return array(
			'post_content'  => 'Gloskin Aesthetic, Anti-Aging & Hair Clinic didirikan oleh dr. Nanang Masrani, M.Biomed (AAM) sebagai klinik aesthetic berbasis medis. Dengan pendekatan evidence-based dan konsep Skin Barrier & Quality Xpert, Gloskin berfokus pada peningkatan kualitas kulit, kesehatan rambut, serta hasil perawatan yang aman, natural, dan berkelanjutan.',
			'founder_name'  => 'dr. Nanang Masrani, M.Biomed (AAM)',
			'founder_role'  => 'Pendiri & Medical Director',
			'founder_story' => 'dr. Nanang mulai menekuni dunia estetika sejak 2007 dan mendirikan GLOSKIN Aesthetic Clinic pada 2012. Dengan latar belakang Magister Biomedik (Anti-Aging Medicine) serta pelatihan internasional di Eropa, Amerika, dan Asia, beliau mengembangkan Gloskin dengan pendekatan medical aesthetic berbasis evidence-based dan konsep Skin Barrier & Quality Xpert.',
		);
	}

	/**
	 * Register historical admin runners only while their supported upgrade
	 * checkpoint is genuinely pending. Consumed migrations never participate in
	 * normal Kernel boot; their persisted state remains authoritative.
	 *
	 * This method runs during plugin bootstrap, before WordPress has loaded the
	 * pluggable current-user API. Do not perform capability checks here; each
	 * registered admin callback enforces its own capability at the proper hook.
	 *
	 * @param Gloskin_Site_Core_Asset_Service $assets      Asset owner.
	 * @param string                          $plugin_file Main plugin file.
	 * @return void
	 */
	public function register_historical_upgrade_admins( $assets, $plugin_file ) {
		if ( ! is_admin() ) {
			return;
		}

		$current        = (string) get_option( self::VERSION_OPTION, '' );
		$schema_pending = '' === $current || version_compare( $current, self::SCHEMA_VERSION, '<' );

		if ( $schema_pending ) {
			$insight_state = get_option( 'gloskin_site_core_insights_v1_state', array() );
			if ( ! is_array( $insight_state ) || 'consumed' !== (string) ( $insight_state['status'] ?? '' ) ) {
				require_once __DIR__ . '/class-gloskin-site-core-insight-migration-admin.php';
				$insight_migration = new Gloskin_Site_Core_Insight_Migration_Admin( $plugin_file );
				$insight_migration->register();
			}

			$final_state = get_option( 'gloskin_site_core_revision_20260819f_state', array() );
			if ( ! is_array( $final_state ) || 'consumed' !== (string) ( $final_state['status'] ?? '' ) ) {
				require_once __DIR__ . '/class-gloskin-site-core-revision-20260819-final-migration-admin.php';
				$final_migration = new Gloskin_Site_Core_Revision_20260819_Final_Migration_Admin( $assets, $plugin_file );
				$final_migration->register();
			}
			return;
		}

		$promo_state = get_option( 'gloskin_site_core_revision_20260820_promo_recovery_state', array() );
		if ( ! is_array( $promo_state ) || 'consumed' !== (string) ( $promo_state['status'] ?? '' ) ) {
			require_once __DIR__ . '/class-gloskin-site-core-revision-20260820-promo-recovery-admin.php';
			$promo_recovery = new Gloskin_Site_Core_Revision_20260820_Promo_Recovery_Admin( $assets );
			$promo_recovery->register();
		}
	}

	/** @return void */
	public function maybe_upgrade() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$this->resolve_primary_navigation_labels();

		$current = (string) get_option( self::VERSION_OPTION, '' );
		if ( '' === $current || version_compare( $current, self::BASE_SCHEMA_VERSION, '<' ) ) {
			$this->provision_approved_structure();
			update_option( self::VERSION_OPTION, self::BASE_SCHEMA_VERSION, false );
			flush_rewrite_rules( false );
			$current = self::BASE_SCHEMA_VERSION;
		}

		if ( version_compare( $current, self::SCHEMA_VERSION, '>=' ) ) {
			return;
		}

		/*
		 * Do not auto-run schema 0.3.0 here. The client-approved IA revision
		 * changes a real editor-visible primary menu and provisions the new
		 * native Promo page, so one bounded admin workflow owns that mutation.
		 * Its consumed checkpoint writes SCHEMA_VERSION exactly once after
		 * page/menu/Woo safety verification.
		 */
	}

	/**
	 * Persist the Phase-1 approved primary-nav labels exactly once without
	 * mutating the editor-owned WordPress menu. One option carries version,
	 * progress state and the canonical map, so there is no parallel settings
	 * store. A completed record is a terminal gate: admin requests never enter a
	 * repair loop. Incomplete writes are verified before the same option is
	 * marked complete and may safely resume on the next privileged admin load.
	 *
	 * @return void
	 */
	public function resolve_primary_navigation_labels() {
		require_once __DIR__ . '/class-gloskin-site-core-navigation-service.php';

		$option  = Gloskin_Site_Core_Navigation_Service::APPROVED_LABELS_OPTION;
		$version = Gloskin_Site_Core_Navigation_Service::APPROVED_LABELS_VERSION;
		$labels  = Gloskin_Site_Core_Navigation_Service::approved_label_defaults();
		$state   = get_option( $option, array() );

		if ( is_array( $state )
			&& isset( $state['version'], $state['status'] )
			&& $version === (string) $state['version']
			&& 'complete' === (string) $state['status'] ) {
			return;
		}

		$pending = array(
			'version' => $version,
			'status'  => 'resolving',
			'labels'  => $labels,
		);
		update_option( $option, $pending, false );

		$verified = get_option( $option, array() );
		if ( ! is_array( $verified )
			|| ! isset( $verified['version'], $verified['status'], $verified['labels'] )
			|| $version !== (string) $verified['version']
			|| 'resolving' !== (string) $verified['status']
			|| $labels !== $verified['labels'] ) {
			return;
		}

		$pending['status'] = 'complete';
		update_option( $option, $pending, false );
	}

	/**
	 * Register rewrites, populate the pre-revision safe baseline structure and flush once.
	 *
	 * Activation is schema-monotonic. A new install records BASE_SCHEMA_VERSION,
	 * while an install that already completed the 0.3.0 IA migration (or any
	 * later schema) keeps that newer version across deactivate/reactivate. The
	 * migration's own consumed state therefore remains authoritative and is not
	 * made pending again merely because the plugin was reactivated.
	 *
	 * @return void
	 */
	public function activate() {
		Gloskin_Site_Core_Content_Service::register_content_types();
		/* register_content_types() only registers post types. The family
		 * taxonomy ensure_family_terms() depends on below must also be
		 * registered here explicitly -- the normal init hook has not
		 * fired yet on this static register_activation_hook path. */
		Gloskin_Site_Core_Content_Service::register_taxonomies();

		$this->resolve_primary_navigation_labels();

		$current = (string) get_option( self::VERSION_OPTION, '' );
		$this->provision_approved_structure();
		if ( '' === $current || version_compare( $current, self::BASE_SCHEMA_VERSION, '<' ) ) {
			update_option( self::VERSION_OPTION, self::BASE_SCHEMA_VERSION, false );
		}
		flush_rewrite_rules( false );
	}

	/** @return void */
	public function deactivate() {
		flush_rewrite_rules( false );
	}

	/**
	 * Create only factual/structural records normalized in the pre-revision baseline.
	 * Existing editor content is never overwritten. The new `/promo/` Page is
	 * deliberately NOT owned here; Prototype_IA_Migration owns that revision.
	 *
	 * @return void
	 */
	private function provision_approved_structure() {
		$pages = array(
			'home'       => 'Beranda',
			'about'      => 'Tentang Gloskin',
			'treatments' => 'Perawatan',
			'skincare'   => 'Skincare',
			'clinics'    => 'Klinik',
			'contact'    => 'Kontak',
			'insights'   => 'Insight',
			'shop'       => 'Belanja',
			'doctors'    => 'Dokter',
		);

		$page_ids = array();
		foreach ( $pages as $slug => $title ) {
			$page_ids[ $slug ] = $this->ensure_page( $slug, $title, 0 );
		}

		$this->align_woo_shop_page( isset( $page_ids['shop'] ) ? absint( $page_ids['shop'] ) : 0 );

		$skincare_parent = isset( $page_ids['skincare'] ) ? absint( $page_ids['skincare'] ) : 0;
		foreach ( Gloskin_Site_Core_Content_Service::skincare_definitions() as $slug => $title ) {
			$page_id = $this->ensure_page( $slug, $title, $skincare_parent );
			if ( $page_id && '' === (string) get_post_meta( $page_id, 'gloskin_woo_category_slug', true ) ) {
				update_post_meta( $page_id, 'gloskin_woo_category_slug', $slug );
			}
		}

		foreach ( Gloskin_Site_Core_Content_Service::clinic_definitions() as $slug => $title ) {
			$this->ensure_post( Gloskin_Site_Core_Content_Service::CLINIC_POST_TYPE, $slug, $title );
		}

		Gloskin_Site_Core_Content_Service::ensure_family_terms();
	}

	/**
	 * WooCommerce, not Gloskin, decides which page is "the Shop page" via
	 * the woocommerce_shop_page_id option. If that setting is genuinely
	 * unconfigured (empty, the -1 "no page" sentinel, or points at a page
	 * that no longer exists/is trashed), safely point it at Gloskin's own
	 * provisioned /shop/ page so the two stay aligned. If a merchant has
	 * already configured a valid Shop page -- Gloskin's own or a different
	 * one -- that choice is preserved untouched.
	 *
	 * @param int $shop_page_id Gloskin's own provisioned /shop/ page ID.
	 * @return void
	 */
	private function align_woo_shop_page( $shop_page_id ) {
		if ( ! $shop_page_id || ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$configured_id = (int) get_option( 'woocommerce_shop_page_id', 0 );
		if ( $configured_id > 0 ) {
			$configured_page = get_post( $configured_id );
			if ( $configured_page instanceof WP_Post && 'trash' !== $configured_page->post_status ) {
				return;
			}
		}

		update_option( 'woocommerce_shop_page_id', $shop_page_id );
	}

	/**
	 * @param string $slug Page slug.
	 * @param string $title Page title.
	 * @param int    $parent_id Parent page ID.
	 * @return int
	 */
	private function ensure_page( $slug, $title, $parent_id ) {
		$path = $parent_id ? get_page_uri( $parent_id ) . '/' . $slug : $slug;
		$page = get_page_by_path( $path, OBJECT, 'page' );
		if ( $page instanceof WP_Post && 'trash' !== $page->post_status ) {
			return (int) $page->ID;
		}

		$result = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_name'   => $slug,
				'post_parent' => $parent_id,
			),
			true
		);

		return is_wp_error( $result ) ? 0 : absint( $result );
	}

	/**
	 * @param string $post_type Post type.
	 * @param string $slug Post slug.
	 * @param string $title Post title.
	 * @return int
	 */
	private function ensure_post( $post_type, $slug, $title ) {
		$post = get_page_by_path( $slug, OBJECT, $post_type );
		if ( $post instanceof WP_Post ) {
			return (int) $post->ID;
		}

		$result = wp_insert_post(
			array(
				'post_type'   => $post_type,
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_name'   => $slug,
			),
			true
		);

		return is_wp_error( $result ) ? 0 : absint( $result );
	}
}
