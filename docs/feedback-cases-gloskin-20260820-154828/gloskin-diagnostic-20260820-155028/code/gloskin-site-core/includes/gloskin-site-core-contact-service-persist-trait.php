<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

trait Gloskin_Site_Core_Contact_Service_Persist_Trait {
	/** @param array<string,string> $raw Raw fields. @return array<string,mixed> */
	private function sanitize_payload( $raw ) {
		return array(
			'name'      => mb_substr( sanitize_text_field( $raw['full_name'] ), 0, 120 ),
			'email'     => mb_substr( sanitize_email( $raw['email'] ), 0, 190 ),
			'phone'     => mb_substr( sanitize_text_field( $raw['phone'] ), 0, 32 ),
			'topic'     => sanitize_key( $raw['topic'] ),
			'clinic_id' => absint( $raw['clinic_id'] ),
			'message'   => mb_substr( sanitize_textarea_field( $raw['message'] ), 0, 3000 ),
			'source_path' => mb_substr( sanitize_text_field( isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/contact/' ), 0, 250 ),
		);
	}

	/** @param array<string,mixed> $payload Sanitized payload. @return int|WP_Error */
	private function persist_message( $payload ) {
		$topic_label = isset( self::topics()[ $payload['topic'] ] ) ? self::topics()[ $payload['topic'] ] : $payload['topic'];
		$post_id = wp_insert_post(
			array(
				'post_type'   => self::MESSAGE_POST_TYPE,
				'post_status' => 'private',
				'post_title'  => mb_substr( $payload['name'] . ' — ' . $topic_label, 0, 200 ),
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		$meta = array(
			'_gloskin_contact_name'             => $payload['name'],
			'_gloskin_contact_email'            => $payload['email'],
			'_gloskin_contact_phone'            => $payload['phone'],
			'_gloskin_contact_topic'            => $payload['topic'],
			'_gloskin_contact_clinic_id'        => absint( $payload['clinic_id'] ),
			'_gloskin_contact_message'          => $payload['message'],
			'_gloskin_contact_status'           => 'new',
			'_gloskin_contact_staff_mail_status'=> 'pending',
			'_gloskin_contact_autoreply_status' => ! empty( self::settings()['autoreply_enabled'] ) ? 'pending' : 'disabled',
			'_gloskin_contact_source_path'      => $payload['source_path'],
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}
		return (int) $post_id;
	}
}
