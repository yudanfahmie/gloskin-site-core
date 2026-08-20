<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

trait Gloskin_Site_Core_Contact_Service_Mail_Trait {
	/** @param int $post_id Message ID. @param array<string,mixed> $payload Payload. @param array<string,mixed> $settings Settings. @return array<string,mixed> */
	private function send_staff_mail( $post_id, $payload, $settings ) {
		$recipients = isset( $settings['recipient_emails'] ) && is_array( $settings['recipient_emails'] ) ? array_values( array_filter( array_map( 'sanitize_email', $settings['recipient_emails'] ), 'is_email' ) ) : array();
		if ( ! $recipients ) {
			return array( 'accepted' => false, 'error_code' => 'recipient_missing', 'error_message' => __( 'No valid Contact recipient is configured.', 'gloskin-site-core' ) );
		}
		$topic_label = isset( self::topics()[ $payload['topic'] ] ) ? self::topics()[ $payload['topic'] ] : $payload['topic'];
		$clinic = $payload['clinic_id'] ? get_the_title( $payload['clinic_id'] ) : __( 'Tidak dipilih', 'gloskin-site-core' );
		$subject = sprintf( '[Gloskin] %s — %s', $topic_label, $payload['name'] );
		$body = "Pesan kontak Gloskin #{$post_id}\n\nNama: {$payload['name']}\nEmail: {$payload['email']}\nWhatsApp/Telepon: {$payload['phone']}\nTopik: {$topic_label}\nKlinik: {$clinic}\n\nPesan:\n{$payload['message']}\n";
		return $this->mailer->send( array_slice( $recipients, 0, 5 ), $subject, $body, $payload['email'] );
	}

	/** @param int $post_id Message ID. @param array<string,mixed> $payload Payload. @param array<string,mixed> $settings Settings. @return array<string,mixed> */
	private function send_autoreply( $post_id, $payload, $settings ) {
		$topic_label = isset( self::topics()[ $payload['topic'] ] ) ? self::topics()[ $payload['topic'] ] : $payload['topic'];
		$replacements = array(
			'{name}'       => $payload['name'],
			'{topic}'      => $topic_label,
			'{site_name}'  => get_bloginfo( 'name' ) ? get_bloginfo( 'name' ) : 'Gloskin',
			'{message_id}' => (string) $post_id,
		);
		$subject = strtr( (string) $settings['autoreply_subject'], $replacements );
		$body = strtr( (string) $settings['autoreply_body'], $replacements );
		return $this->mailer->send( $payload['email'], mb_substr( $subject, 0, 180 ), mb_substr( $body, 0, 5000 ) );
	}

	/** @param int $post_id Message ID. @param string $channel staff_mail|autoreply. @param array<string,mixed> $result Mail result. @return void */
	private function save_transport_result( $post_id, $channel, $result ) {
		$prefix = 'staff_mail' === $channel ? '_gloskin_contact_staff_mail' : '_gloskin_contact_autoreply';
		update_post_meta( $post_id, $prefix . '_status', ! empty( $result['accepted'] ) ? 'accepted' : 'failed' );
		if ( empty( $result['accepted'] ) ) {
			update_post_meta( $post_id, $prefix . '_error_code', sanitize_key( isset( $result['error_code'] ) ? $result['error_code'] : 'failed' ) );
			update_post_meta( $post_id, $prefix . '_error_summary', mb_substr( sanitize_text_field( isset( $result['error_message'] ) ? $result['error_message'] : '' ), 0, 300 ) );
		} else {
			delete_post_meta( $post_id, $prefix . '_error_code' );
			delete_post_meta( $post_id, $prefix . '_error_summary' );
		}
	}

	/** @param string $status success|error. @return void */
	private function redirect( $status ) {
		$url = add_query_arg( 'gloskin_contact', in_array( $status, array( 'success', 'error' ), true ) ? $status : 'error', home_url( '/contact/' ) );
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Bounded privacy retention. At most 50 old private inbox records are
	 * removed per admin request, and only this CPT is eligible.
	 *
	 * @return void
	 */
	public function maybe_prune_retention() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( get_transient( 'gloskin_contact_retention_sweep' ) ) {
			return;
		}
		set_transient( 'gloskin_contact_retention_sweep', 1, DAY_IN_SECONDS );
		$days = max( 30, min( 730, absint( self::settings()['retention_days'] ) ) );
		$ids = get_posts(
			array(
				'post_type'      => self::MESSAGE_POST_TYPE,
				'post_status'    => 'private',
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'date_query'     => array( array( 'before' => gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS * $days ), 'inclusive' => true ) ),
			)
		);
		foreach ( (array) $ids as $id ) {
			wp_delete_post( absint( $id ), true );
		}
	}
}
