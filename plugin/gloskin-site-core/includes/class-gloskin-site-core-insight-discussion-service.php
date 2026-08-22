<?php
/**
 * Native WordPress discussion availability for imported Insight posts.
 *
 * The historical Insight bundle was imported with comment_status=closed.
 * This service performs one bounded reconciliation for those bundle-owned
 * posts only, then gets out of the way so editors retain normal WordPress
 * per-post discussion control afterward.
 *
 * @package GloskinSiteCore
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Gloskin_Site_Core_Insight_Discussion_Service {
	const RECONCILE_OPTION = 'gloskin_site_core_insight_comments_v1_reconciled';
	const IMPORT_STATE_OPTION = 'gloskin_site_core_insights_v1_state';
	const BUNDLE_META = '_gloskin_insight_bundle_id';
	const BUNDLE_ID = 'gloskin-insights-v1';
	const EXPECTED_POSTS = 13;

	/** @return void */
	public function register() {
		add_action( 'admin_init', array( $this, 'maybe_reconcile' ), 25 );
		add_filter( 'comments_open', array( $this, 'bridge_comments_open_until_reconciled' ), 10, 2 );
	}

	/**
	 * Keep the already-imported Insight posts commentable immediately while the
	 * one-time database reconciliation is still pending. Once reconciliation is
	 * complete this filter becomes a no-op so editors can close an individual
	 * post normally through WordPress.
	 *
	 * @param bool $open    Current WordPress comments-open state.
	 * @param int  $post_id Post ID.
	 * @return bool
	 */
	public function bridge_comments_open_until_reconciled( $open, $post_id ) {
		if ( $open || $this->is_reconciled() ) {
			return (bool) $open;
		}

		$post = get_post( absint( $post_id ) );
		if ( ! $post instanceof WP_Post || 'post' !== $post->post_type || 'publish' !== $post->post_status ) {
			return (bool) $open;
		}

		return self::BUNDLE_ID === (string) get_post_meta( $post->ID, self::BUNDLE_META, true ) ? true : (bool) $open;
	}

	/**
	 * Open discussion once for the exact historical Insight bundle only.
	 *
	 * The migration must already be consumed and all 13 published bundle posts
	 * must resolve before mutation. Verification is fail-closed: the completion
	 * option is written only when all 13 records are confirmed commentable.
	 *
	 * @return void
	 */
	public function maybe_reconcile() {
		if ( $this->is_reconciled() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$import_state = get_option( self::IMPORT_STATE_OPTION, array() );
		if ( ! is_array( $import_state ) || 'consumed' !== (string) ( $import_state['status'] ?? '' ) ) {
			return;
		}

		$post_ids = array_map( 'absint', (array) get_posts( array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'meta_key'       => self::BUNDLE_META,
			'meta_value'     => self::BUNDLE_ID,
			'fields'         => 'ids',
			'numberposts'    => -1,
			'no_found_rows'  => true,
			'suppress_filters' => true,
		) ) );

		if ( self::EXPECTED_POSTS !== count( $post_ids ) ) {
			return;
		}

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post instanceof WP_Post || 'post' !== $post->post_type || 'publish' !== $post->post_status ) {
				return;
			}
			if ( 'open' === (string) $post->comment_status ) {
				continue;
			}

			$result = wp_update_post( array(
				'ID'             => $post_id,
				'comment_status' => 'open',
			), true );
			if ( is_wp_error( $result ) || ! $result ) {
				return;
			}
		}

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post instanceof WP_Post || 'open' !== (string) $post->comment_status ) {
				return;
			}
		}

		update_option( self::RECONCILE_OPTION, array(
			'status'     => 'complete',
			'post_count' => self::EXPECTED_POSTS,
			'updated_at' => time(),
		), false );
	}

	/** @return bool */
	private function is_reconciled() {
		$state = get_option( self::RECONCILE_OPTION, array() );
		return is_array( $state )
			&& 'complete' === (string) ( $state['status'] ?? '' )
			&& self::EXPECTED_POSTS === absint( $state['post_count'] ?? 0 );
	}
}
