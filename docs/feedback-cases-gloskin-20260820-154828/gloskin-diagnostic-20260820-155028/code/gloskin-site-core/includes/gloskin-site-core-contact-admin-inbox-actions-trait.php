<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

trait Gloskin_Site_Core_Contact_Admin_Inbox_Actions_Trait {
	/** @param int $message_id Message ID. @return void */
	private function render_message_detail( $message_id ) {
		$post = get_post( $message_id );
		if ( ! $post instanceof WP_Post || Gloskin_Site_Core_Contact_Service::MESSAGE_POST_TYPE !== $post->post_type ) {
			wp_die( esc_html__( 'Pesan tidak ditemukan.', 'gloskin-site-core' ) );
		}
		$status = (string) get_post_meta( $message_id, '_gloskin_contact_status', true );
		$name = (string) get_post_meta( $message_id, '_gloskin_contact_name', true );
		$email = (string) get_post_meta( $message_id, '_gloskin_contact_email', true );
		$phone = (string) get_post_meta( $message_id, '_gloskin_contact_phone', true );
		$topic_key = (string) get_post_meta( $message_id, '_gloskin_contact_topic', true );
		$topic = isset( Gloskin_Site_Core_Contact_Service::topics()[ $topic_key ] ) ? Gloskin_Site_Core_Contact_Service::topics()[ $topic_key ] : $topic_key;
		$clinic_id = absint( get_post_meta( $message_id, '_gloskin_contact_clinic_id', true ) );
		$message = (string) get_post_meta( $message_id, '_gloskin_contact_message', true );
		$staff_status = (string) get_post_meta( $message_id, '_gloskin_contact_staff_mail_status', true );
		$auto_status = (string) get_post_meta( $message_id, '_gloskin_contact_autoreply_status', true );
		$base = admin_url( 'admin.php?page=' . self::INBOX_SLUG );
		?>
		<div class="wrap"><h1><?php echo esc_html__( 'Detail Pesan', 'gloskin-site-core' ); ?> #<?php echo esc_html( (string) $message_id ); ?></h1><p><a href="<?php echo esc_url( $base ); ?>">&larr; <?php echo esc_html__( 'Kotak Masuk', 'gloskin-site-core' ); ?></a></p>
		<table class="widefat striped" style="max-width:900px"><tbody><tr><th><?php echo esc_html__( 'Pengirim', 'gloskin-site-core' ); ?></th><td><?php echo esc_html( $name ); ?> &lt;<?php echo esc_html( $email ); ?>&gt;</td></tr><tr><th><?php echo esc_html__( 'WhatsApp/Telepon', 'gloskin-site-core' ); ?></th><td><?php echo esc_html( $phone ); ?></td></tr><tr><th><?php echo esc_html__( 'Topik', 'gloskin-site-core' ); ?></th><td><?php echo esc_html( $topic ); ?></td></tr><tr><th><?php echo esc_html__( 'Klinik', 'gloskin-site-core' ); ?></th><td><?php echo esc_html( $clinic_id ? get_the_title( $clinic_id ) : '—' ); ?></td></tr><tr><th><?php echo esc_html__( 'Status', 'gloskin-site-core' ); ?></th><td><?php echo esc_html( $status ); ?></td></tr><tr><th><?php echo esc_html__( 'Staff mail', 'gloskin-site-core' ); ?></th><td><?php echo esc_html( $staff_status ); ?></td></tr><tr><th><?php echo esc_html__( 'Auto reply', 'gloskin-site-core' ); ?></th><td><?php echo esc_html( $auto_status ); ?></td></tr></tbody></table>
		<h2><?php echo esc_html__( 'Pesan', 'gloskin-site-core' ); ?></h2><div class="card" style="max-width:900px;white-space:pre-wrap"><?php echo esc_html( $message ); ?></div>
		<h2><?php echo esc_html__( 'Ubah status', 'gloskin-site-core' ); ?></h2><p><?php foreach ( array( 'new' => 'New', 'read' => 'Read', 'resolved' => 'Resolved', 'spam' => 'Spam' ) as $key => $label ) : ?><a class="button<?php echo $status === $key ? ' button-primary' : ''; ?>" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . self::STATUS_ACTION . '&message_id=' . $message_id . '&status=' . $key ), self::STATUS_NONCE ) ); ?>"><?php echo esc_html( $label ); ?></a> <?php endforeach; ?></p>
		<p><a class="button button-link-delete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . self::DELETE_ACTION . '&message_id=' . $message_id ), self::DELETE_NONCE ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Hapus pesan ini secara permanen?', 'gloskin-site-core' ) ); ?>')"><?php echo esc_html__( 'Hapus pesan', 'gloskin-site-core' ); ?></a></p></div>
		<?php
	}

	/** @return void */
	public function handle_status_action() {
		if ( ! current_user_can( self::INBOX_CAPABILITY ) ) { wp_die( esc_html__( 'Izin tidak cukup.', 'gloskin-site-core' ) ); }
		check_admin_referer( self::STATUS_NONCE );
		$id = isset( $_GET['message_id'] ) ? absint( $_GET['message_id'] ) : 0;
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$post = $id ? get_post( $id ) : null;
		if ( $post instanceof WP_Post && Gloskin_Site_Core_Contact_Service::MESSAGE_POST_TYPE === $post->post_type && in_array( $status, array( 'new', 'read', 'resolved', 'spam' ), true ) ) {
			update_post_meta( $id, '_gloskin_contact_status', $status );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::INBOX_SLUG . '&message_id=' . $id ) ); exit;
	}

	/** @return void */
	public function handle_delete_action() {
		if ( ! current_user_can( self::INBOX_CAPABILITY ) ) { wp_die( esc_html__( 'Izin tidak cukup.', 'gloskin-site-core' ) ); }
		check_admin_referer( self::DELETE_NONCE );
		$id = isset( $_GET['message_id'] ) ? absint( $_GET['message_id'] ) : 0;
		$post = $id ? get_post( $id ) : null;
		if ( $post instanceof WP_Post && Gloskin_Site_Core_Contact_Service::MESSAGE_POST_TYPE === $post->post_type ) {
			wp_delete_post( $id, true );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::INBOX_SLUG ) ); exit;
	}
}
