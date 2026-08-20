<?php
/**
 * Phase 4 Home presentation selection.
 *
 * Extends the existing TemplateService-curated three featured Treatment cards
 * with three deterministic, published Treatment cards without changing the
 * Phase-3 feature meta owner or introducing another Treatment content model.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'gloskin_phase4_home_treatment_cards' ) ) {
	/**
	 * @param array<int,array<string,mixed>> $featured Existing curated cards.
	 * @return array<int,array<string,mixed>>
	 */
	function gloskin_phase4_home_treatment_cards( $featured ) {
		$cards = array_values( array_slice( is_array( $featured ) ? $featured : array(), 0, 3 ) );
		$exclude = array();
		foreach ( $cards as $card ) {
			if ( ! empty( $card['id'] ) ) {
				$exclude[] = absint( $card['id'] );
			}
		}

		$remaining = max( 0, 6 - count( $cards ) );
		if ( $remaining > 0 ) {
			$posts = get_posts( array(
				'post_type'      => Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => $remaining,
				'post__not_in'   => $exclude,
				'orderby'        => 'title',
				'order'          => 'ASC',
			) );
			foreach ( $posts as $post ) {
				$cards[] = array(
					'id'       => (int) $post->ID,
					'title'    => (string) get_the_title( $post ),
					'url'      => (string) get_permalink( $post ),
					'image_id' => absint( get_post_thumbnail_id( $post->ID ) ),
					'summary'  => (string) get_post_meta( $post->ID, 'gloskin_summary', true ),
					'excerpt'  => has_excerpt( $post ) ? (string) get_the_excerpt( $post ) : '',
				);
			}
		}

		return array_values( array_slice( $cards, 0, 6 ) );
	}
}
