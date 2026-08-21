<?php
/**
 * Fail-closed Home readiness contract for the existing Content Finalizer state.
 *
 * This service does not own a second runner or state. It only guards the
 * Content Finalizer's transition to `complete` and makes pre-contract complete
 * state appear stale until an operator explicitly reruns the existing finalizer.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Gloskin_Site_Core_Home_Readiness_Contract {
	const VERSION      = '2026-08-21.1';
	const STATE_OPTION = 'gloskin_site_core_phase4_finalizer_v1_state';

	/** @return void */
	public function register() {
		add_filter( 'option_' . self::STATE_OPTION, array( $this, 'mark_legacy_completion_stale' ), 20 );
		add_filter( 'pre_update_option_' . self::STATE_OPTION, array( $this, 'guard_completion' ), 20, 3 );
	}

	/**
	 * A completion written before this contract must be explicitly reverified by
	 * the operator through the existing Content Finalizer. This changes only the
	 * in-memory read projection; it does not mutate persisted state on page load.
	 *
	 * @param mixed $state Stored option value.
	 * @return mixed
	 */
	public function mark_legacy_completion_stale( $state ) {
		if (
			is_array( $state ) &&
			'complete' === (string) ( $state['status'] ?? '' ) &&
			self::VERSION !== (string) ( $state['home_readiness_contract'] ?? '' )
		) {
			$state['status']     = 'stale';
			$state['last_error'] = 'Home readiness contract changed; run Finalisasi Konten once to reverify 6 Treatment, 3 Testimoni, and 4 Piagam.';
		}
		return $state;
	}

	/**
	 * Guard only the existing Finalizer's complete transition. Throwing here is
	 * intentionally caught by its run() Throwable boundary, which persists
	 * status=failed and an operator-readable error instead of a false complete.
	 *
	 * @param mixed  $new_value New state.
	 * @param mixed  $old_value Previous state.
	 * @param string $option    Option name.
	 * @return mixed
	 */
	public function guard_completion( $new_value, $old_value, $option ) {
		unset( $old_value, $option );
		if ( ! is_array( $new_value ) || 'complete' !== (string) ( $new_value['status'] ?? '' ) ) {
			return $new_value;
		}

		$counts = $this->verify_home_readiness();
		$new_value['home_readiness_contract'] = self::VERSION;
		$new_value['home_treatments']          = $counts['treatments'];
		$new_value['home_testimonials']        = $counts['testimonials'];
		$new_value['home_piagam']              = $counts['piagam'];
		return $new_value;
	}

	/** @return array{treatments:int,testimonials:int,piagam:int} */
	private function verify_home_readiness() {
		$treatments   = $this->verify_home_treatments();
		$testimonials = $this->verify_home_testimonials();
		$piagam       = $this->verify_home_piagam();
		return array(
			'treatments'   => $treatments,
			'testimonials' => $testimonials,
			'piagam'       => $piagam,
		);
	}

	/** @return int */
	private function verify_home_treatments() {
		$post_type = Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE;
		if ( ! post_type_exists( $post_type ) ) {
			throw new RuntimeException( 'Home readiness failed: Treatment post type is unavailable.' );
		}

		$featured = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'meta_query'     => array(
					array(
						'key'     => 'gloskin_treatment_feature_on_home',
						'value'   => '1',
						'compare' => '=',
					),
				),
			)
		);
		if ( 3 !== count( $featured ) ) {
			throw new RuntimeException( 'Home readiness failed: expected exactly 3 published Treatment records with gloskin_treatment_feature_on_home=1.' );
		}

		$featured_ids = array_map( 'absint', wp_list_pluck( $featured, 'ID' ) );
		$additional   = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => 3,
				'post__not_in'   => $featured_ids,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$additional_ids = array_map( 'absint', wp_list_pluck( $additional, 'ID' ) );
		$selected_ids   = array_merge( $featured_ids, $additional_ids );
		if ( 6 !== count( $selected_ids ) || 6 !== count( array_unique( $selected_ids ) ) ) {
			throw new RuntimeException( 'Home readiness failed: Treatment Unggulan must resolve exactly 6 unique published records (3 featured + 3 deterministic additional).' );
		}
		return 6;
	}

	/** @return int */
	private function verify_home_testimonials() {
		$post_type = Gloskin_Site_Core_Content_Service::TESTIMONIAL_POST_TYPE;
		if ( ! post_type_exists( $post_type ) ) {
			throw new RuntimeException( 'Home readiness failed: Testimonial post type is unavailable.' );
		}
		$posts = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'meta_query'     => array(
					array(
						'key'     => 'gloskin_testimonial_active',
						'value'   => '1',
						'compare' => '=',
					),
				),
			)
		);
		$posts = array_values(
			array_filter(
				$posts,
				static function ( $post ) {
					return '' === (string) get_post_meta( $post->ID, '_gloskin_demo_identity', true );
				}
			)
		);
		$this->sort_managed_posts( $posts, 'gloskin_testimonial_order' );
		if ( 3 !== count( $posts ) ) {
			throw new RuntimeException( 'Home readiness failed: expected exactly 3 factual published active Testimoni records.' );
		}

		$ids = array();
		foreach ( $posts as $post ) {
			$ids[]       = absint( $post->ID );
			$quote       = trim( wp_strip_all_tags( (string) get_the_excerpt( $post ) ) );
			$attribution = trim( (string) get_post_meta( $post->ID, 'gloskin_testimonial_attribution', true ) );
			if ( '' === $quote || '' === $attribution ) {
				throw new RuntimeException( 'Home readiness failed: every Testimoni needs factual quote content and attribution.' );
			}
		}
		if ( 3 !== count( array_unique( $ids ) ) ) {
			throw new RuntimeException( 'Home readiness failed: duplicate Testimoni IDs detected.' );
		}
		return 3;
	}

	/** @return int */
	private function verify_home_piagam() {
		$post_type = Gloskin_Site_Core_Content_Service::ACHIEVEMENT_POST_TYPE;
		if ( ! post_type_exists( $post_type ) ) {
			throw new RuntimeException( 'Home readiness failed: Piagam post type is unavailable.' );
		}
		$posts = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'     => 'gloskin_achievement_active',
						'value'   => '1',
						'compare' => '=',
					),
					array(
						'key'     => 'gloskin_achievement_feature_on_home',
						'value'   => '1',
						'compare' => '=',
					),
				),
			)
		);
		$posts = array_values(
			array_filter(
				$posts,
				static function ( $post ) {
					return '' === (string) get_post_meta( $post->ID, '_gloskin_demo_identity', true );
				}
			)
		);
		$this->sort_managed_posts( $posts, 'gloskin_achievement_order' );
		if ( 4 !== count( $posts ) ) {
			throw new RuntimeException( 'Home readiness failed: expected exactly 4 published active featured Piagam records.' );
		}

		$ids = array();
		foreach ( $posts as $post ) {
			$ids[] = absint( $post->ID );
			if ( ! $this->attachment_is_usable_image( get_post_thumbnail_id( $post->ID ) ) ) {
				throw new RuntimeException( 'Home readiness failed: all 4 Piagam records require usable images.' );
			}
		}
		if ( 4 !== count( array_unique( $ids ) ) ) {
			throw new RuntimeException( 'Home readiness failed: duplicate Piagam IDs detected.' );
		}
		return 4;
	}

	/**
	 * Mirror the frontend managed-CPT ordering contract.
	 *
	 * @param array<int,WP_Post> $posts Posts, mutated in-place.
	 * @param string             $order_meta_key Order key.
	 * @return void
	 */
	private function sort_managed_posts( &$posts, $order_meta_key ) {
		usort(
			$posts,
			static function ( $a, $b ) use ( $order_meta_key ) {
				$ao = (int) get_post_meta( $a->ID, $order_meta_key, true );
				$bo = (int) get_post_meta( $b->ID, $order_meta_key, true );
				$ah = $ao > 0;
				$bh = $bo > 0;
				if ( $ah && ! $bh ) {
					return -1;
				}
				if ( ! $ah && $bh ) {
					return 1;
				}
				if ( $ao !== $bo ) {
					return $ao <=> $bo;
				}
				$title_cmp = strcmp( (string) $a->post_title, (string) $b->post_title );
				return 0 !== $title_cmp ? $title_cmp : ( (int) $a->ID <=> (int) $b->ID );
			}
		);
	}

	/** @param int $attachment_id Attachment ID. @return bool */
	private function attachment_is_usable_image( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id || ! wp_attachment_is_image( $attachment_id ) ) {
			return false;
		}
		$file = get_attached_file( $attachment_id );
		return is_string( $file ) && '' !== $file && file_exists( $file ) && filesize( $file ) > 0;
	}
}
