<?php
/**
 * Saved Indonesian / English presentation context.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Gloskin_Site_Core_Language {
	const COOKIE = 'gloskin_lang';
	const AJAX_SAVE = 'gloskin_translation_save_phase5';
	const NONCE = 'gloskin_translation_save';

	/** @var string */
	private $plugin_file;

	/** @param string $plugin_file Main plugin file. */
	public function __construct( $plugin_file ) { $this->plugin_file = (string) $plugin_file; }

	/** Admin bridge: keep one Translation screen while covering current About custom text. */
	public function register_admin() {
		add_action( 'admin_enqueue_scripts', array( $this, 'extend_translation_admin' ), 40 );
		add_action( 'wp_ajax_' . self::AJAX_SAVE, array( $this, 'ajax_save' ) );
	}

	/** Public-only language context and saved translation resolvers. */
	public function register_frontend() {
		add_action( 'init', array( $this, 'capture_language' ), 1 );
		add_filter( 'language_attributes', array( $this, 'language_attributes' ), 20, 2 );
		add_filter( 'the_posts', array( $this, 'translate_posts' ), 20, 2 );
		add_filter( 'the_title', array( $this, 'translate_title' ), 20, 2 );
		add_filter( 'post_title', array( $this, 'translate_title' ), 20, 2 );
		add_filter( 'the_content', array( $this, 'translate_content' ), 20 );
		add_filter( 'post_content', array( $this, 'translate_content_field' ), 20, 3 );
		add_filter( 'get_the_excerpt', array( $this, 'translate_excerpt' ), 20, 2 );
		add_filter( 'post_excerpt', array( $this, 'translate_excerpt_field' ), 20, 3 );
		add_filter( 'get_post_metadata', array( $this, 'translate_post_meta' ), 20, 5 );
		add_filter( 'get_term', array( $this, 'translate_term' ), 20, 2 );
		add_filter( 'gettext', array( $this, 'translate_interface' ), 20, 3 );
		add_filter( 'nav_menu_item_title', array( $this, 'translate_nav_title' ), 20, 4 );
		add_filter( 'document_title_parts', array( $this, 'translate_document_title' ), 99 );
		add_filter( 'woocommerce_product_get_name', array( $this, 'translate_product_name' ), 20, 2 );
		add_filter( 'woocommerce_product_get_short_description', array( $this, 'translate_product_short_description' ), 20, 2 );
		add_filter( 'woocommerce_product_get_description', array( $this, 'translate_product_description' ), 20, 2 );
		add_action( 'wp_footer', array( $this, 'activate_existing_switcher' ), 100 );
	}

	/** @return string id|en */
	public static function language() {
		if ( isset( $_GET['gloskin_lang'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- presentation preference only.
			$request = sanitize_key( wp_unslash( $_GET['gloskin_lang'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( in_array( $request, array( 'id', 'en' ), true ) ) { return $request; }
		}
		if ( isset( $_COOKIE[ self::COOKIE ] ) ) {
			$cookie = sanitize_key( wp_unslash( $_COOKIE[ self::COOKIE ] ) );
			if ( in_array( $cookie, array( 'id', 'en' ), true ) ) { return $cookie; }
		}
		return 'id';
	}

	/** @return void */
	public function capture_language() {
		if ( ! isset( $_GET['gloskin_lang'] ) ) { return; } // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$lang = sanitize_key( wp_unslash( $_GET['gloskin_lang'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $lang, array( 'id', 'en' ), true ) ) { return; }
		setcookie( self::COOKIE, $lang, array(
			'expires' => time() + YEAR_IN_SECONDS,
			'path' => COOKIEPATH ? COOKIEPATH : '/',
			'domain' => COOKIE_DOMAIN,
			'secure' => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		) );
		$_COOKIE[ self::COOKIE ] = $lang;
	}

	/** @param string $output Attributes. @param string $doctype Doctype. @return string */
	public function language_attributes( $output, $doctype = 'html' ) {
		unset( $doctype );
		$lang = self::language();
		if ( preg_match( '/\blang=("|\')[^"\']*(\1)/i', $output ) ) {
			return preg_replace( '/\blang=("|\')[^"\']*(\1)/i', 'lang="' . $lang . '"', $output, 1 );
		}
		return trim( $output . ' lang="' . $lang . '"' );
	}

	/** @return array<string,array{label:string,rich:bool}> */
	private static function about_fields() {
		return array(
			'gloskin_about_vision' => array( 'label' => 'Vision', 'rich' => true ),
			'gloskin_about_mission' => array( 'label' => 'Mission', 'rich' => true ),
			'gloskin_about_values' => array( 'label' => 'Values', 'rich' => true ),
			'gloskin_about_founder_role' => array( 'label' => 'Founder role', 'rich' => false ),
			'gloskin_about_founder_story' => array( 'label' => 'Founder story', 'rich' => true ),
		);
	}

	/** Add current About custom text into the existing record/editor, with no second menu/screen. */
	public function extend_translation_admin( $hook ) {
		if ( false === strpos( (string) $hook, Gloskin_Site_Core_Translation::ADMIN_SLUG ) ) { return; }
		$page = Gloskin_Site_Core_Page_Lookup::find( 'about' );
		if ( ! ( $page instanceof WP_Post ) ) { return; }
		$saved = Gloskin_Site_Core_Translation::post_translations( $page->ID );
		$fields = array();
		foreach ( self::about_fields() as $key => $definition ) {
			$source = (string) get_post_meta( $page->ID, $key, true );
			if ( '' === trim( wp_strip_all_tags( $source ) ) ) { continue; }
			$fields[] = array(
				'key' => $key,
				'label' => $definition['label'],
				'source' => $source,
				'en' => isset( $saved[ $key ] ) ? (string) $saved[ $key ] : '',
				'rich' => (bool) $definition['rich'],
			);
		}
		if ( ! $fields ) { return; }
		$script = 'if(window.GloskinTranslationAdmin){(function(c){var r=(c.records||[]).find(function(x){return x.entity==="post"&&String(x.entityId)===' . wp_json_encode( (string) $page->ID ) . ';});if(r){var f=' . wp_json_encode( $fields ) . ';f.forEach(function(x){if(!r.fields.some(function(y){return y.key===x.key;})){r.fields.push(x);}});r.total=r.fields.length;r.filled=r.fields.filter(function(x){return String(x.en||"").trim();}).length;}c.action=' . wp_json_encode( self::AJAX_SAVE ) . ';})(window.GloskinTranslationAdmin);}';
		wp_add_inline_script( 'gloskin-translation-admin', $script, 'before' );
	}

	/** Save original registry fields plus the bounded About bridge. */
	public function ajax_save() {
		if ( ! current_user_can( Gloskin_Site_Core_Translation::CAPABILITY ) ) { wp_send_json_error( array( 'message' => 'Forbidden.' ), 403 ); }
		check_ajax_referer( self::NONCE, 'nonce' );
		$entity = isset( $_POST['entity'] ) ? sanitize_key( wp_unslash( $_POST['entity'] ) ) : '';
		$field = isset( $_POST['field'] ) ? sanitize_key( wp_unslash( $_POST['field'] ) ) : '';
		$id_raw = isset( $_POST['entity_id'] ) ? wp_unslash( $_POST['entity_id'] ) : '';
		$value = isset( $_POST['value'] ) ? wp_unslash( $_POST['value'] ) : '';
		if ( ! is_string( $value ) || ! $this->allowed_target( $entity, $id_raw, $field ) ) { wp_send_json_error( array( 'message' => 'Invalid translation target.' ), 400 ); }
		$value = $this->sanitize_value( $entity, $id_raw, $field, $value );
		if ( 'post' === $entity ) {
			$id = absint( $id_raw ); $translations = Gloskin_Site_Core_Translation::post_translations( $id ); $translations[ $field ] = $value;
			update_post_meta( $id, Gloskin_Site_Core_Translation::POST_META_KEY, $translations );
		} elseif ( 'term' === $entity ) {
			$id = absint( $id_raw ); $translations = Gloskin_Site_Core_Translation::term_translations( $id ); $translations[ $field ] = $value;
			update_term_meta( $id, Gloskin_Site_Core_Translation::TERM_META_KEY, $translations );
		} else {
			$key = sanitize_key( (string) $id_raw ); $translations = Gloskin_Site_Core_Translation::interface_translations(); $translations[ $key ] = $value;
			update_option( Gloskin_Site_Core_Translation::INTERFACE_OPTION, $translations, false );
		}
		wp_send_json_success( array( 'value' => $value ) );
	}

	/** @return bool */
	private function allowed_target( $entity, $id_raw, $field ) {
		$registry = Gloskin_Site_Core_Translation::registry();
		if ( 'post' === $entity ) {
			$post = get_post( absint( $id_raw ) );
			if ( ! $post || ! isset( $registry['post_types'][ $post->post_type ] ) ) { return false; }
			$definition = $registry['post_types'][ $post->post_type ];
			if ( isset( $definition['fields'][ $field ] ) || isset( $definition['meta'][ $field ] ) ) { return true; }
			if ( 'page' === $post->post_type && 'about' === $post->post_name && isset( self::about_fields()[ $field ] ) ) { return true; }
			if ( Gloskin_Site_Core_Content_Service::QUESTION_POST_TYPE === $post->post_type && 0 === strpos( $field, 'answer_label_' ) ) {
				$answers = get_post_meta( $post->ID, Gloskin_Site_Core_Content_Service::ANSWER_META_KEY, true );
				return is_array( $answers ) && isset( $answers[ absint( substr( $field, strlen( 'answer_label_' ) ) ) ]['label'] );
			}
			return false;
		}
		if ( 'term' === $entity ) {
			$term = get_term( absint( $id_raw ) );
			return $term && ! is_wp_error( $term ) && isset( $registry['taxonomies'][ $term->taxonomy ]['fields'][ $field ] );
		}
		return 'interface' === $entity && 'text' === $field && isset( $registry['interface'][ sanitize_key( (string) $id_raw ) ] );
	}

	/** @return string */
	private function sanitize_value( $entity, $id_raw, $field, $value ) {
		if ( 'post_content' === $field || 'description' === $field || in_array( $field, array( 'gloskin_benefits', 'gloskin_contraindications' ), true ) ) { return wp_kses_post( $value ); }
		if ( 'post' === $entity ) {
			$post = get_post( absint( $id_raw ) );
			if ( $post && 'page' === $post->post_type && isset( self::about_fields()[ $field ] ) && self::about_fields()[ $field ]['rich'] ) { return wp_kses_post( $value ); }
		}
		if ( 'post_excerpt' === $field || false !== strpos( $field, 'summary' ) || false !== strpos( $field, 'source_note' ) || false !== strpos( $field, 'address' ) || false !== strpos( $field, 'hours' ) ) { return sanitize_textarea_field( $value ); }
		return sanitize_text_field( $value );
	}

	/** @return string */
	private static function saved_post_field( $post_id, $field, $fallback ) {
		if ( 'en' !== self::language() ) { return $fallback; }
		$post = get_post( absint( $post_id ) ); $registry = Gloskin_Site_Core_Translation::registry();
		if ( ! $post || ! isset( $registry['post_types'][ $post->post_type ]['fields'][ $field ] ) ) { return $fallback; }
		$saved = Gloskin_Site_Core_Translation::post_translations( $post->ID );
		return isset( $saved[ $field ] ) && '' !== trim( (string) $saved[ $field ] ) ? (string) $saved[ $field ] : $fallback;
	}

	/** @param WP_Post $post Post. @return WP_Post */
	public static function translate_post_object( $post ) {
		if ( 'en' !== self::language() || ! ( $post instanceof WP_Post ) ) { return $post; }
		$registry = Gloskin_Site_Core_Translation::registry();
		if ( ! isset( $registry['post_types'][ $post->post_type ] ) ) { return $post; }
		$saved = Gloskin_Site_Core_Translation::post_translations( $post->ID ); $copy = clone $post;
		foreach ( $registry['post_types'][ $post->post_type ]['fields'] as $field => $label ) { unset( $label ); if ( isset( $saved[ $field ] ) && '' !== trim( (string) $saved[ $field ] ) ) { $copy->$field = (string) $saved[ $field ]; } }
		return $copy;
	}

	/** @param array<int,WP_Post> $posts Posts. @param WP_Query $query Query. @return array<int,WP_Post> */
	public function translate_posts( $posts, $query = null ) { unset( $query ); if ( 'en' !== self::language() ) { return $posts; } return array_map( array( __CLASS__, 'translate_post_object' ), $posts ); }
	/** @return string */ public function translate_title( $value, $post_id = 0 ) { return $post_id ? self::saved_post_field( $post_id, 'post_title', $value ) : $value; }
	/** @return string */ public function translate_content( $value ) { $id = get_the_ID(); return $id ? self::saved_post_field( $id, 'post_content', $value ) : $value; }
	/** @return string */ public function translate_content_field( $value, $post_id = 0, $context = 'display' ) { unset( $context ); return $post_id ? self::saved_post_field( $post_id, 'post_content', $value ) : $value; }
	/** @return string */ public function translate_excerpt( $value, $post = null ) { $id = $post instanceof WP_Post ? $post->ID : get_the_ID(); return $id ? self::saved_post_field( $id, 'post_excerpt', $value ) : $value; }
	/** @return string */ public function translate_excerpt_field( $value, $post_id = 0, $context = 'display' ) { unset( $context ); return $post_id ? self::saved_post_field( $post_id, 'post_excerpt', $value ) : $value; }

	/** Visible meta only; question option IDs/weights/order remain canonical. */
	public function translate_post_meta( $value, $object_id, $meta_key, $single, $meta_type = 'post' ) {
		unset( $meta_type );
		if ( 'en' !== self::language() || '' === (string) $meta_key || Gloskin_Site_Core_Translation::POST_META_KEY === $meta_key ) { return $value; }
		$post = get_post( absint( $object_id ) ); if ( ! $post ) { return $value; }
		$saved = Gloskin_Site_Core_Translation::post_translations( $post->ID );
		if ( Gloskin_Site_Core_Content_Service::QUESTION_POST_TYPE === $post->post_type && Gloskin_Site_Core_Content_Service::ANSWER_META_KEY === $meta_key && function_exists( 'get_metadata_raw' ) ) {
			$raw = get_metadata_raw( 'post', $post->ID, $meta_key, true ); if ( ! is_array( $raw ) ) { return $value; }
			foreach ( $raw as $index => &$answer ) { $key = 'answer_label_' . absint( $index ); if ( is_array( $answer ) && isset( $saved[ $key ] ) && '' !== trim( (string) $saved[ $key ] ) ) { $answer['label'] = (string) $saved[ $key ]; } } unset( $answer );
			return $single ? $raw : array( $raw );
		}
		$registry = Gloskin_Site_Core_Translation::registry(); $allowed = isset( $registry['post_types'][ $post->post_type ]['meta'][ $meta_key ] );
		if ( 'page' === $post->post_type && 'about' === $post->post_name && isset( self::about_fields()[ $meta_key ] ) ) { $allowed = true; }
		if ( ! $allowed || ! isset( $saved[ $meta_key ] ) || '' === trim( (string) $saved[ $meta_key ] ) ) { return $value; }
		return $single ? (string) $saved[ $meta_key ] : array( (string) $saved[ $meta_key ] );
	}

	/** @param mixed $term Term. @param string $taxonomy Taxonomy. @return mixed */
	public function translate_term( $term, $taxonomy = '' ) {
		if ( 'en' !== self::language() || ! ( $term instanceof WP_Term ) ) { return $term; }
		$taxonomy = $taxonomy ? $taxonomy : $term->taxonomy; $registry = Gloskin_Site_Core_Translation::registry();
		if ( ! isset( $registry['taxonomies'][ $taxonomy ] ) ) { return $term; }
		$saved = Gloskin_Site_Core_Translation::term_translations( $term->term_id ); $copy = clone $term;
		foreach ( $registry['taxonomies'][ $taxonomy ]['fields'] as $field => $label ) { unset( $label ); if ( isset( $saved[ $field ] ) && '' !== trim( (string) $saved[ $field ] ) ) { $copy->$field = (string) $saved[ $field ]; } }
		return $copy;
	}

	/** @return string */
	private static function interface_text( $source ) {
		if ( 'en' !== self::language() ) { return $source; }
		$saved = Gloskin_Site_Core_Translation::interface_translations();
		foreach ( Gloskin_Site_Core_Translation::interface_registry() as $key => $entry ) {
			if ( (string) $entry['source'] !== (string) $source ) { continue; }
			return isset( $saved[ $key ] ) && '' !== trim( (string) $saved[ $key ] ) ? (string) $saved[ $key ] : (string) $entry['en'];
		}
		return $source;
	}

	/** @return string */ public function translate_interface( $translation, $text, $domain ) { return 'gloskin-site-core' === $domain ? self::interface_text( $text ) : $translation; }
	/** @return string */
	public function translate_nav_title( $title, $item = null, $args = null, $depth = 0 ) {
		unset( $args, $depth ); if ( 'en' !== self::language() ) { return $title; }
		if ( is_object( $item ) && ! empty( $item->object_id ) ) { $resolved = self::saved_post_field( absint( $item->object_id ), 'post_title', $title ); if ( $resolved !== $title ) { return $resolved; } }
		return self::interface_text( $title );
	}
	/** @param array<string,string> $parts Parts. @return array<string,string> */ public function translate_document_title( $parts ) { if ( 'en' === self::language() && isset( $parts['title'] ) ) { $parts['title'] = self::interface_text( $parts['title'] ); } return $parts; }
	/** @return string */ public function translate_product_name( $value, $product ) { return is_object( $product ) && method_exists( $product, 'get_id' ) ? self::saved_post_field( $product->get_id(), 'post_title', $value ) : $value; }
	/** @return string */ public function translate_product_short_description( $value, $product ) { return is_object( $product ) && method_exists( $product, 'get_id' ) ? self::saved_post_field( $product->get_id(), 'post_excerpt', $value ) : $value; }
	/** @return string */ public function translate_product_description( $value, $product ) { return is_object( $product ) && method_exists( $product, 'get_id' ) ? self::saved_post_field( $product->get_id(), 'post_content', $value ) : $value; }

	/** Reuse Phase 4.1's existing static switcher; no second switcher is rendered. */
	public function activate_existing_switcher() {
		$lang = self::language();
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$current = home_url( '/' . ltrim( strtok( (string) $uri, '?' ), '/' ) );
		$query = array(); $parts = wp_parse_url( (string) $uri ); if ( ! empty( $parts['query'] ) ) { parse_str( $parts['query'], $query ); }
		unset( $query['gloskin_lang'] );
		$urls = array( 'id' => add_query_arg( array_merge( $query, array( 'gloskin_lang' => 'id' ) ), $current ), 'en' => add_query_arg( array_merge( $query, array( 'gloskin_lang' => 'en' ) ), $current ) );
		?>
		<script>(function(){var root=document.querySelector('.gloskin-ui1-lang-switcher');if(!root)return;var current=<?php echo wp_json_encode( $lang ); ?>,urls=<?php echo wp_json_encode( $urls ); ?>;root.querySelectorAll('[lang="id"],[lang="en"]').forEach(function(el){var l=el.getAttribute('lang'),on=l===current;el.classList.toggle('gloskin-ui1-lang-switcher__option--current',on);el.classList.toggle('gloskin-ui1-lang-switcher__option--inactive',!on);if(on){el.setAttribute('aria-current','true');el.removeAttribute('aria-disabled');el.removeAttribute('role');el.removeAttribute('tabindex');el.style.cursor='default';}else{el.removeAttribute('aria-current');el.removeAttribute('aria-disabled');el.setAttribute('role','link');el.setAttribute('tabindex','0');el.style.cursor='pointer';var go=function(){window.location.assign(urls[l]);};el.addEventListener('click',go);el.addEventListener('keydown',function(e){if(e.key==='Enter'||e.key===' '){e.preventDefault();go();}});}});})();</script>
		<?php
	}
}
