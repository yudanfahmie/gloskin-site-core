<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
trait Gloskin_Site_Core_Doctor_Importer_Upsert_Trait {
	/** @param array<string,string> $record Doctor record. @return int */
	private function upsert_doctor( $record ) {
		$owned = get_posts(
			array(
				'post_type'      => Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
				'posts_per_page' => 2,
				'fields'         => 'ids',
				'meta_key'       => self::SOURCE_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- stable migration identity lookup.
				'meta_value'     => $record['source_id'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- stable migration identity lookup.
			)
		);
		if ( count( $owned ) > 1 ) {
			throw new RuntimeException( __( 'Collision: lebih dari satu doctor memakai source ID yang sama.', 'gloskin-site-core' ) );
		}
		if ( $owned ) {
			$post_id = absint( $owned[0] );
			$result = wp_update_post( array( 'ID' => $post_id, 'post_title' => $record['post_title'], 'post_name' => $record['slug'] ), true );
			if ( is_wp_error( $result ) ) { throw new RuntimeException( $result->get_error_message() ); }
			$this->write_provenance( $post_id, $record );
			return $post_id;
		}

		$slug_collision = get_page_by_path( $record['slug'], OBJECT, Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE );
		$title_collision = get_posts(
			array(
				'post_type'      => Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
				'posts_per_page' => 1,
				'title'          => $record['post_title'],
			)
		);
		if ( $slug_collision instanceof WP_Post || ! empty( $title_collision ) ) {
			throw new RuntimeException( sprintf( /* translators: %s: doctor name. */ __( 'Unowned doctor collision untuk %s; rekonsiliasi manual diperlukan.', 'gloskin-site-core' ), $record['post_title'] ) );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $record['post_title'],
				'post_name'   => $record['slug'],
				'post_content'=> '',
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			throw new RuntimeException( $post_id->get_error_message() );
		}
		$this->write_provenance( (int) $post_id, $record );
		return (int) $post_id;
	}

	/** @param int $post_id Doctor ID. @param array<string,string> $record Record. @return void */
	private function write_provenance( $post_id, $record ) {
		update_post_meta( $post_id, self::SOURCE_META, $record['source_id'] );
		update_post_meta( $post_id, self::BUNDLE_META, Gloskin_Site_Core_Doctor_Bundle::BUNDLE_ID );
		update_post_meta( $post_id, self::SOURCE_URL_META, $record['source_url'] );
		update_post_meta( $post_id, self::SOURCE_CHECKED_META, $record['source_checked_at'] );
		/* Unsupported facts deliberately stay untouched/blank on new records:
		 * SIP, schedule, branch, specialization/profile, booking and featured
		 * image are never synthesized by this bundle. */
	}
}
