<?php
/**
 * Canonical frontend projection for managed editorial records.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Gloskin_Site_Core_Editorial_Projection {
	/** @return void */
	public function register() {
		/* TemplateService resolves the shell/context at 99. This projection runs
		 * immediately afterwards and owns the canonical Promo collection shape. */
		add_filter( 'template_include', array( $this, 'project_context' ), 100 );
	}

	/** @param string $template Resolved template. @return string */
	public function project_context( $template ) {
		$context = get_query_var( 'gloskin_context', array() );
		if ( ! is_array( $context ) || 'promo' !== (string) ( $context['view'] ?? '' ) ) {
			return $template;
		}

		$collections = $this->promo_collections();
		unset( $context['promos'] );
		$context['limited_promos'] = $collections['limited'];
		$context['regular_promos'] = $collections['regular'];
		set_query_var( 'gloskin_context', $context );
		return $template;
	}

	/**
	 * Canonical Promo projection: no legacy date/copy fields and no arbitrary
	 * record limit. Operators may add records and order them via metadata.
	 *
	 * @return array{limited:array<int,array<string,mixed>>,regular:array<int,array<string,mixed>>}
	 */
	private function promo_collections() {
		$collections = array( 'limited' => array(), 'regular' => array() );
		if ( ! post_type_exists( Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE ) ) {
			return $collections;
		}

		$posts = get_posts( array(
			'post_type'      => Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		) );

		$records = array();
		foreach ( $posts as $post ) {
			if ( ! $post instanceof WP_Post || '1' !== (string) get_post_meta( $post->ID, 'gloskin_promo_active', true ) ) {
				continue;
			}
			$type = (string) get_post_meta( $post->ID, 'gloskin_promo_type', true );
			if ( ! in_array( $type, array( 'limited', 'regular' ), true ) ) {
				continue;
			}
			$records[] = array(
				'id'       => (int) $post->ID,
				'title'    => (string) get_the_title( $post ),
				'type'     => $type,
				'image_id' => absint( get_post_thumbnail_id( $post->ID ) ),
				'order'    => (int) get_post_meta( $post->ID, 'gloskin_promo_order', true ),
			);
		}

		usort( $records, static function ( $left, $right ) {
			$left_order  = (int) ( $left['order'] ?? 0 );
			$right_order = (int) ( $right['order'] ?? 0 );
			if ( $left_order === $right_order ) {
				return (int) $left['id'] <=> (int) $right['id'];
			}
			return $left_order <=> $right_order;
		} );

		foreach ( $records as $record ) {
			$type = $record['type'];
			unset( $record['order'] );
			$collections[ $type ][] = $record;
		}
		return $collections;
	}
}
