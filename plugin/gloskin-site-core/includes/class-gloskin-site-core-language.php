<?php
/**
 * Saved Indonesian / English presentation context.
 *
 * Admin/storage ownership lives in Gloskin_Site_Core_Translation. This class
 * owns only the public language context and freshness-aware projections.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Gloskin_Site_Core_Language {
	const COOKIE = 'gloskin_lang';

	/** @var string */
	private $plugin_file;

	/** @param string $plugin_file Main plugin file. */
	public function __construct( $plugin_file ) { $this->plugin_file = (string) $plugin_file; }

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

	/** Build the current-request URL for a first-party language. */
	public static function switch_url( $language ) {
		$language = sanitize_key( (string) $language );
		if ( ! in_array( $language, array( 'id', 'en' ), true ) ) { $language = 'id'; }
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : '/';
		$uri = remove_query_arg( 'gloskin_lang', $uri );
		return add_query_arg( 'gloskin_lang', $language, $uri );
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

	/** Return saved EN only when its source hash matches the current source. */
	private static function saved_post_field( $post_id, $field, $fallback ) {
		if ( 'en' !== self::language() ) { return $fallback; }
		$post = get_post( absint( $post_id ) );
		$registry = Gloskin_Site_Core_Translation::registry();
		if ( ! $post || ! isset( $registry['post_types'][ $post->post_type ]['fields'][ $field ] ) ) { return $fallback; }
		return Gloskin_Site_Core_Translation::fresh_post_value( $post->ID, $field, (string) $fallback );
	}

	/** @param WP_Post $post Post. @return WP_Post */
	public static function translate_post_object( $post ) {
		if ( 'en' !== self::language() || ! ( $post instanceof WP_Post ) ) { return $post; }
		$registry = Gloskin_Site_Core_Translation::registry();
		if ( ! isset( $registry['post_types'][ $post->post_type ] ) ) { return $post; }
		$copy = clone $post;
		foreach ( $registry['post_types'][ $post->post_type ]['fields'] as $field => $label ) {
			unset( $label );
			$source = isset( $post->$field ) ? (string) $post->$field : '';
			$copy->$field = Gloskin_Site_Core_Translation::fresh_post_value( $post->ID, $field, $source );
		}
		return $copy;
	}

	/** @param array<int,WP_Post> $posts Posts. @param WP_Query $query Query. @return array<int,WP_Post> */
	public function translate_posts( $posts, $query = null ) { unset( $query ); if ( 'en' !== self::language() ) { return $posts; } return array_map( array( __CLASS__, 'translate_post_object' ), $posts ); }
	/** @return string */ public function translate_title( $value, $post_id = 0 ) { return $post_id ? self::saved_post_field( $post_id, 'post_title', $value ) : $value; }
	/** @return string */ public function translate_content( $value ) { $id = get_the_ID(); return $id ? self::saved_post_field( $id, 'post_content', $value ) : $value; }
	/** @return string */ public function translate_content_field( $value, $post_id = 0, $context = 'display' ) { unset( $context ); return $post_id ? self::saved_post_field( $post_id, 'post_content', $value ) : $value; }
	/** @return string */ public function translate_excerpt( $value, $post = null ) { $id = $post instanceof WP_Post ? $post->ID : get_the_ID(); return $id ? self::saved_post_field( $id, 'post_excerpt', $value ) : $value; }
	/** @return string */ public function translate_excerpt_field( $value, $post_id = 0, $context = 'display' ) { unset( $context ); return $post_id ? self::saved_post_field( $post_id, 'post_excerpt', $value ) : $value; }

	/** Visible registered meta only; structural IDs/weights/order stay canonical. */
	public function translate_post_meta( $value, $object_id, $meta_key, $single, $meta_type = 'post' ) {
		unset( $meta_type );
		if ( 'en' !== self::language() || '' === (string) $meta_key || in_array( $meta_key, array( Gloskin_Site_Core_Translation::POST_META_KEY, Gloskin_Site_Core_Translation::POST_STATE_META_KEY ), true ) ) { return $value; }
		$post = get_post( absint( $object_id ) );
		if ( ! $post ) { return $value; }

		if ( Gloskin_Site_Core_Content_Service::QUESTION_POST_TYPE === $post->post_type && Gloskin_Site_Core_Content_Service::ANSWER_META_KEY === $meta_key && function_exists( 'get_metadata_raw' ) ) {
			$raw = get_metadata_raw( 'post', $post->ID, $meta_key, true );
			if ( ! is_array( $raw ) ) { return $value; }
			foreach ( $raw as $index => &$answer ) {
				if ( ! is_array( $answer ) || ! isset( $answer['label'] ) ) { continue; }
				$key = 'answer_label_' . absint( $index );
				$answer['label'] = Gloskin_Site_Core_Translation::fresh_post_value( $post->ID, $key, (string) $answer['label'] );
			}
			unset( $answer );
			return $single ? $raw : array( $raw );
		}

		$registry = Gloskin_Site_Core_Translation::registry();
		if ( ! isset( $registry['post_types'][ $post->post_type ]['meta'][ $meta_key ] ) ) { return $value; }
		if ( ! function_exists( 'get_metadata_raw' ) ) { return $value; }
		$source = get_metadata_raw( 'post', $post->ID, $meta_key, true );
		$source = is_scalar( $source ) ? (string) $source : '';
		$translated = Gloskin_Site_Core_Translation::fresh_post_value( $post->ID, $meta_key, $source );
		if ( $translated === $source ) { return $value; }
		return $single ? $translated : array( $translated );
	}

	/** @param mixed $term Term. @param string $taxonomy Taxonomy. @return mixed */
	public function translate_term( $term, $taxonomy = '' ) {
		if ( 'en' !== self::language() || ! ( $term instanceof WP_Term ) ) { return $term; }
		$taxonomy = $taxonomy ? $taxonomy : $term->taxonomy;
		$registry = Gloskin_Site_Core_Translation::registry();
		if ( ! isset( $registry['taxonomies'][ $taxonomy ] ) ) { return $term; }
		$copy = clone $term;
		foreach ( $registry['taxonomies'][ $taxonomy ]['fields'] as $field => $label ) {
			unset( $label );
			$source = isset( $term->$field ) ? (string) $term->$field : '';
			$copy->$field = Gloskin_Site_Core_Translation::fresh_term_value( $term->term_id, $field, $source );
		}
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
}
