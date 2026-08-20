<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

trait Gloskin_Site_Core_Contact_Admin_Inbox_List_Trait {
	/** @return int */
	private function new_count() {
		$query = new WP_Query(
			array(
				'post_type'      => Gloskin_Site_Core_Contact_Service::MESSAGE_POST_TYPE,
				'post_status'    => 'private',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_gloskin_contact_status', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- private operational CPT, bounded count query.
				'meta_value'     => 'new', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- private operational CPT, bounded count query.
			)
		);
		return absint( $query->found_posts );
	}

	/** @return void */
	public function render_inbox() {
		if ( ! current_user_can( self::INBOX_CAPABILITY ) ) {
			wp_die( esc_html__( 'Anda tidak memiliki izin membuka Kotak Masuk.', 'gloskin-site-core' ) );
		}
		$message_id = isset( $_GET['message_id'] ) ? absint( $_GET['message_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only detail selector.
		if ( $message_id ) {
			$this->render_message_detail( $message_id );
			return;
		}
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
		$search = isset( $_GET['s'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_GET['s'] ) ), 0, 100 ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
		$paged = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination.
		$meta_query = array();
		if ( in_array( $status, array( 'new', 'read', 'resolved', 'spam' ), true ) ) {
			$meta_query[] = array( 'key' => '_gloskin_contact_status', 'value' => $status );
		}
		if ( '' !== $search ) {
			$meta_query[] = array(
				'relation' => 'OR',
				array( 'key' => '_gloskin_contact_name', 'value' => $search, 'compare' => 'LIKE' ),
				array( 'key' => '_gloskin_contact_email', 'value' => $search, 'compare' => 'LIKE' ),
				array( 'key' => '_gloskin_contact_topic', 'value' => $search, 'compare' => 'LIKE' ),
			);
		}
		if ( count( $meta_query ) > 1 ) {
			$meta_query = array_merge( array( 'relation' => 'AND' ), $meta_query );
		}
		$args = array(
			'post_type'      => Gloskin_Site_Core_Contact_Service::MESSAGE_POST_TYPE,
			'post_status'    => 'private',
			'posts_per_page' => 20,
			'paged'          => $paged,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		if ( $meta_query ) {
			$args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- bounded private inbox filters.
		}
		$query = new WP_Query( $args );
		$base = admin_url( 'admin.php?page=' . self::INBOX_SLUG );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Kotak Masuk', 'gloskin-site-core' ); ?> <span class="title-count"><?php echo esc_html( (string) $this->new_count() ); ?></span></h1>
			<p><?php echo esc_html__( 'Pesan Contact native Gloskin. Record tersimpan sebelum upaya email dilakukan.', 'gloskin-site-core' ); ?></p>
			<form method="get"><input type="hidden" name="page" value="<?php echo esc_attr( self::INBOX_SLUG ); ?>" /><p class="search-box"><label class="screen-reader-text" for="gloskin-inbox-search"><?php echo esc_html__( 'Cari pesan', 'gloskin-site-core' ); ?></label><input id="gloskin-inbox-search" type="search" name="s" value="<?php echo esc_attr( $search ); ?>" /><select name="status"><option value=""><?php echo esc_html__( 'Semua status', 'gloskin-site-core' ); ?></option><?php foreach ( array( 'new' => 'New', 'read' => 'Read', 'resolved' => 'Resolved', 'spam' => 'Spam' ) as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select> <button class="button"><?php echo esc_html__( 'Filter', 'gloskin-site-core' ); ?></button></p></form>
			<table class="widefat striped"><thead><tr><th><?php echo esc_html__( 'Pengirim', 'gloskin-site-core' ); ?></th><th><?php echo esc_html__( 'Topik', 'gloskin-site-core' ); ?></th><th><?php echo esc_html__( 'Klinik', 'gloskin-site-core' ); ?></th><th><?php echo esc_html__( 'Status', 'gloskin-site-core' ); ?></th><th><?php echo esc_html__( 'Diterima', 'gloskin-site-core' ); ?></th></tr></thead><tbody>
			<?php foreach ( $query->posts as $post ) :
				$name = (string) get_post_meta( $post->ID, '_gloskin_contact_name', true );
				$email = (string) get_post_meta( $post->ID, '_gloskin_contact_email', true );
				$topic_key = (string) get_post_meta( $post->ID, '_gloskin_contact_topic', true );
				$topic = isset( Gloskin_Site_Core_Contact_Service::topics()[ $topic_key ] ) ? Gloskin_Site_Core_Contact_Service::topics()[ $topic_key ] : $topic_key;
				$clinic_id = absint( get_post_meta( $post->ID, '_gloskin_contact_clinic_id', true ) );
				$message_status = (string) get_post_meta( $post->ID, '_gloskin_contact_status', true );
				?>
				<tr><td><a href="<?php echo esc_url( add_query_arg( 'message_id', $post->ID, $base ) ); ?>"><strong><?php echo esc_html( $name ); ?></strong></a><br><span class="description"><?php echo esc_html( $email ); ?></span></td><td><?php echo esc_html( $topic ); ?></td><td><?php echo esc_html( $clinic_id ? get_the_title( $clinic_id ) : '—' ); ?></td><td><?php echo esc_html( $message_status ); ?></td><td><?php echo esc_html( get_the_date( 'Y-m-d H:i', $post ) ); ?></td></tr>
			<?php endforeach; ?>
			<?php if ( ! $query->posts ) : ?><tr><td colspan="5"><?php echo esc_html__( 'Tidak ada pesan yang cocok.', 'gloskin-site-core' ); ?></td></tr><?php endif; ?>
			</tbody></table>
			<?php
			$links = paginate_links( array( 'base' => add_query_arg( array( 'paged' => '%#%', 'status' => $status, 's' => $search ), $base ), 'format' => '', 'current' => $paged, 'total' => max( 1, absint( $query->max_num_pages ) ), 'type' => 'list' ) );
			if ( $links ) { echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( $links ) . '</div></div>'; }
			?>
		</div>
		<?php
	}
}
