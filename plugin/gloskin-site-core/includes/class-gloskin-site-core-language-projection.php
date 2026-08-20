<?php
/**
 * Projection helpers for saved English presentation.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Gloskin_Site_Core_Language_Projection {
	/** Register public projection helpers. */
	public function register() {
		add_action( 'pre_get_posts', array( $this, 'enable_registered_post_filters' ), 999 );
		add_filter( 'get_post_metadata', array( $this, 'translate_page_meta' ), 15, 5 );
		add_action( 'template_redirect', array( $this, 'start_interface_buffer' ), 0 );
	}

	/**
	 * Extend the existing Translation screen only; do not create another admin
	 * destination or another translation state owner.
	 */
	public function register_admin() {
		add_action( 'admin_enqueue_scripts', array( $this, 'extend_page_meta_admin' ), 41 );
		add_action( 'wp_ajax_' . Gloskin_Site_Core_Language::AJAX_SAVE, array( $this, 'ajax_save_page_meta' ), 5 );
	}

	/**
	 * get_posts() suppresses filters by default. For EN requests only, opt the
	 * explicitly registered translation post types back into the normal result
	 * filters so existing Template Service projections receive translated clones.
	 * Query shape, IDs, relationships and ordering are unchanged.
	 *
	 * @param WP_Query $query Query.
	 * @return void
	 */
	public function enable_registered_post_filters( $query ) {
		if ( 'en' !== Gloskin_Site_Core_Language::language() || ! ( $query instanceof WP_Query ) ) { return; }
		$registry = Gloskin_Site_Core_Translation::registry();
		$registered = array_keys( $registry['post_types'] );
		$post_type = $query->get( 'post_type' );
		$types = is_array( $post_type ) ? array_map( 'strval', $post_type ) : array( (string) $post_type );
		if ( array_intersect( $registered, $types ) ) {
			$query->set( 'suppress_filters', false );
		}
	}

	/**
	 * Current public templates read a small set of editor-owned Page meta fields
	 * directly. Keep those fields in the same companion EN map so their existing
	 * render owners receive EN transparently while URLs/media IDs stay canonical.
	 *
	 * @param WP_Post|null $post Page object when known.
	 * @return array<string,array{label:string,rich:bool}>
	 */
	private static function page_fields( $post = null ) {
		$fields = array(
			'gloskin_hero_heading'   => array( 'label' => 'Hero heading', 'rich' => false ),
			'gloskin_hero_copy'      => array( 'label' => 'Hero copy', 'rich' => false ),
			'gloskin_hero_cta_label' => array( 'label' => 'Hero CTA label', 'rich' => false ),
		);
		if ( $post instanceof WP_Post && 'home' === (string) $post->post_name ) {
			$fields['gloskin_why_heading']       = array( 'label' => 'Why Gloskin heading', 'rich' => false );
			$fields['gloskin_why_lead']          = array( 'label' => 'Why Gloskin lead', 'rich' => false );
			$fields['gloskin_why_primary_title'] = array( 'label' => 'Why Gloskin primary title', 'rich' => false );
			$fields['gloskin_why_primary_copy']  = array( 'label' => 'Why Gloskin primary copy', 'rich' => false );
		}
		return $fields;
	}

	/**
	 * Overlay only registered visible Page meta. A missing/blank EN value returns
	 * null so WordPress continues to the canonical Indonesian value unchanged.
	 *
	 * @param mixed  $value Current short-circuit value.
	 * @param int    $object_id Post ID.
	 * @param string $meta_key Meta key.
	 * @param bool   $single Single flag.
	 * @param string $meta_type Meta type.
	 * @return mixed
	 */
	public function translate_page_meta( $value, $object_id, $meta_key, $single, $meta_type = 'post' ) {
		unset( $meta_type );
		if ( 'en' !== Gloskin_Site_Core_Language::language() || '' === (string) $meta_key || Gloskin_Site_Core_Translation::POST_META_KEY === $meta_key ) { return $value; }
		$post = get_post( absint( $object_id ) );
		if ( ! ( $post instanceof WP_Post ) || 'page' !== $post->post_type || ! isset( self::page_fields( $post )[ $meta_key ] ) ) { return $value; }
		$saved = Gloskin_Site_Core_Translation::post_translations( $post->ID );
		if ( ! isset( $saved[ $meta_key ] ) || '' === trim( (string) $saved[ $meta_key ] ) ) { return $value; }
		return $single ? (string) $saved[ $meta_key ] : array( (string) $saved[ $meta_key ] );
	}

	/** Append Page meta fields to the existing Translation record payload. */
	public function extend_page_meta_admin( $hook ) {
		if ( false === strpos( (string) $hook, Gloskin_Site_Core_Translation::ADMIN_SLUG ) ) { return; }
		$pages = get_posts( array(
			'post_type' => 'page',
			'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page' => -1,
			'orderby' => 'title',
			'order' => 'ASC',
			'suppress_filters' => true,
		) );
		$payload = array();
		foreach ( $pages as $page ) {
			$saved = Gloskin_Site_Core_Translation::post_translations( $page->ID );
			$fields = array();
			foreach ( self::page_fields( $page ) as $key => $definition ) {
				$source = (string) get_post_meta( $page->ID, $key, true );
				if ( '' === trim( wp_strip_all_tags( $source ) ) ) { continue; }
				$fields[] = array(
					'key' => $key,
					'label' => $definition['label'],
					'source' => $source,
					'en' => isset( $saved[ $key ] ) ? (string) $saved[ $key ] : '',
					'rich' => false,
				);
			}
			if ( $fields ) { $payload[ (string) $page->ID ] = $fields; }
		}
		$script = 'if(window.GloskinTranslationAdmin){(function(c,p){Object.keys(p).forEach(function(id){var r=(c.records||[]).find(function(x){return x.entity==="post"&&String(x.entityId)===String(id);});if(!r)return;p[id].forEach(function(f){if(!r.fields.some(function(x){return x.key===f.key;})){r.fields.push(f);}});r.total=r.fields.length;r.filled=r.fields.filter(function(x){return String(x.en||"").trim();}).length;});c.action=' . wp_json_encode( Gloskin_Site_Core_Language::AJAX_SAVE ) . ';})(window.GloskinTranslationAdmin,' . wp_json_encode( $payload ) . ');}';
		wp_add_inline_script( 'gloskin-translation-admin', $script, 'before' );
	}

	/**
	 * Intercept only registered Page-meta writes on the shared Phase-5 endpoint.
	 * Other entities fall through to Gloskin_Site_Core_Language::ajax_save().
	 */
	public function ajax_save_page_meta() {
		$entity = isset( $_POST['entity'] ) ? sanitize_key( wp_unslash( $_POST['entity'] ) ) : '';
		$field  = isset( $_POST['field'] ) ? sanitize_key( wp_unslash( $_POST['field'] ) ) : '';
		$id     = isset( $_POST['entity_id'] ) ? absint( wp_unslash( $_POST['entity_id'] ) ) : 0;
		if ( 'post' !== $entity || ! $id ) { return; }
		$post = get_post( $id );
		if ( ! ( $post instanceof WP_Post ) || 'page' !== $post->post_type || ! isset( self::page_fields( $post )[ $field ] ) ) { return; }
		if ( ! current_user_can( Gloskin_Site_Core_Translation::CAPABILITY ) ) { wp_send_json_error( array( 'message' => 'Forbidden.' ), 403 ); }
		check_ajax_referer( Gloskin_Site_Core_Language::NONCE, 'nonce' );
		$value = isset( $_POST['value'] ) ? wp_unslash( $_POST['value'] ) : '';
		if ( ! is_string( $value ) ) { wp_send_json_error( array( 'message' => 'Invalid translation value.' ), 400 ); }
		$value = sanitize_textarea_field( $value );
		$translations = Gloskin_Site_Core_Translation::post_translations( $id );
		$translations[ $field ] = $value;
		update_post_meta( $id, Gloskin_Site_Core_Translation::POST_META_KEY, $translations );
		wp_send_json_success( array( 'value' => $value ) );
	}

	/** Begin a lightweight visible-text interface resolver. */
	public function start_interface_buffer() {
		if ( 'en' !== Gloskin_Site_Core_Language::language() || is_admin() || is_feed() || wp_doing_ajax() ) { return; }
		ob_start( array( $this, 'translate_interface_html' ) );
	}

	/**
	 * Replace only complete visible text nodes outside tags/comments/script/
	 * style/pre/code. Substring replacement is deliberately forbidden: content
	 * with no saved EN must remain exact Indonesian fallback even if it happens
	 * to contain a UI word such as "Klinik" or "Promo".
	 *
	 * @param string $html Rendered HTML.
	 * @return string
	 */
	public function translate_interface_html( $html ) {
		if ( '' === $html || false === strpos( $html, '<' ) ) { return $this->translate_text_segment( $html ); }
		$parts = preg_split(
			'/(<!--[\s\S]*?-->|<script\b[^>]*>[\s\S]*?<\/script\s*>|<style\b[^>]*>[\s\S]*?<\/style\s*>|<pre\b[^>]*>[\s\S]*?<\/pre\s*>|<code\b[^>]*>[\s\S]*?<\/code\s*>|<[^>]+>)/i',
			$html,
			-1,
			PREG_SPLIT_DELIM_CAPTURE
		);
		if ( ! is_array( $parts ) ) { return $html; }
		foreach ( $parts as $index => $part ) {
			if ( '' === $part || '<' === $part[0] ) { continue; }
			$parts[ $index ] = $this->translate_text_segment( $part );
		}
		return implode( '', $parts );
	}

	/** @param string $text Visible text. @return string */
	private function translate_text_segment( $text ) {
		$needle = trim( (string) $text );
		if ( '' === $needle ) { return $text; }
		$saved = Gloskin_Site_Core_Translation::interface_translations();
		foreach ( Gloskin_Site_Core_Translation::interface_registry() as $key => $entry ) {
			if ( (string) $entry['source'] !== $needle ) { continue; }
			$replacement = isset( $saved[ $key ] ) && '' !== trim( (string) $saved[ $key ] ) ? (string) $saved[ $key ] : (string) $entry['en'];
			$leading = substr( $text, 0, strlen( $text ) - strlen( ltrim( $text ) ) );
			$trailing = substr( $text, strlen( rtrim( $text ) ) );
			return $leading . $replacement . $trailing;
		}
		return $text;
	}
}
