#!/usr/bin/env python3
"""One-shot patch driver for the 2026-08-19 final closure.

This file is intentionally temporary. The companion one-shot workflow removes
itself and this driver in the final closure commit after the exact committed
runtime suite passes.
"""
from __future__ import annotations

import hashlib
import json
import re
import subprocess
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
START_HEAD = "35776f3f42f5bf40b42ec3db845387b912173edc"


def read(rel: str) -> str:
    return (ROOT / rel).read_text(encoding="utf-8")


def write(rel: str, text: str) -> None:
    path = ROOT / rel
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(text, encoding="utf-8")


def replace_once(rel: str, old: str, new: str) -> None:
    text = read(rel)
    count = text.count(old)
    if count != 1:
        raise RuntimeError(f"{rel}: expected one anchor, found {count}: {old[:100]!r}")
    write(rel, text.replace(old, new, 1))


def regex_replace_once(rel: str, pattern: str, replacement: str) -> None:
    text = read(rel)
    updated, count = re.subn(pattern, replacement, text, count=1, flags=re.S)
    if count != 1:
        raise RuntimeError(f"{rel}: expected one regex match, found {count}: {pattern[:100]!r}")
    write(rel, updated)


def download(url: str) -> bytes:
    req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0 GloskinEditorialMigration/1.0"})
    with urllib.request.urlopen(req, timeout=45) as response:
        data = response.read()
    if len(data) < 20_000:
        raise RuntimeError(f"Downloaded editorial asset is unexpectedly small ({len(data)} bytes): {url}")
    return data


def sniff_extension(data: bytes) -> str:
    if data.startswith(b"\xff\xd8\xff"):
        return ".jpg"
    if data.startswith(b"\x89PNG\r\n\x1a\n"):
        return ".png"
    if len(data) > 12 and data[:4] == b"RIFF" and data[8:12] == b"WEBP":
        return ".webp"
    raise RuntimeError("Unsupported downloaded editorial image format")


def build_editorial_bundle() -> None:
    bundle = ROOT / "plugin/gloskin-site-core/migration-runtime/gloskin-editorial-media-v1"
    bundle.mkdir(parents=True, exist_ok=True)
    sources = [
        {
            "key": "home_why",
            "kind": "editorial",
            "role": "Home — Why Gloskin",
            "source_page": "https://gloskin.id/mengapa-harus-gloskin-",
            "source_asset_url": "https://assets.zyrosite.com/cdn-cgi/image/format%3Dauto%2Cw%3D1280%2Ch%3D1280%2Cfit%3Dcrop/NNlEVZXDhMFiG6pt/face-theraphy-ts5VWUYwzmGrVtqc.jpg",
        },
        {
            "key": "home_brand_story",
            "kind": "editorial",
            "role": "Home — brand story transition",
            "source_page": "https://gloskin.id/",
            "source_asset_url": "https://assets.zyrosite.com/cdn-cgi/image/format%3Dauto%2Cw%3D1280%2Ch%3D1280%2Cfit%3Dcrop/NNlEVZXDhMFiG6pt/gloskin-0202987a-0zsJ4tsdQ98FaGfK.jpg",
        },
        {
            "key": "treatment_discovery",
            "kind": "treatment",
            "role": "Treatment discovery — general",
            "source_page": "https://gloskin.id/",
            "source_asset_url": "https://assets.zyrosite.com/cdn-cgi/image/format%3Dauto%2Cw%3D1280%2Ch%3D815%2Cfit%3Dcrop/NNlEVZXDhMFiG6pt/vip-light-3ibydhNkQ0yFkbRH.png",
        },
        {
            "key": "treatment_clinical",
            "kind": "treatment",
            "role": "Treatment discovery — clinical procedure",
            "source_page": "https://gloskin.id/mengapa-harus-gloskin-",
            "source_asset_url": "https://assets.zyrosite.com/cdn-cgi/image/format%3Dauto%2Cw%3D1280%2Ch%3D815%2Cfit%3Dcrop/NNlEVZXDhMFiG6pt/injeksi-acne-ftB9QvWDD9GlDfgx.png",
        },
        {
            "key": "skincare_editorial",
            "kind": "skincare",
            "role": "Skincare editorial discovery",
            "source_page": "https://gloskin.id/serum",
            "source_asset_url": "https://assets.zyrosite.com/cdn-cgi/image/format%3Dauto%2Cw%3D1280%2Ch%3D855%2Cfit%3Dcrop/NNlEVZXDhMFiG6pt/serum-f5ZNuylGu6zQrMrr.png",
        },
        {
            "key": "about_story",
            "kind": "editorial",
            "role": "About / brand story",
            "source_page": "https://gloskin.id/mengapa-harus-gloskin-",
            "source_asset_url": "https://assets.zyrosite.com/cdn-cgi/image/format%3Dauto%2Cw%3D1280%2Ch%3D932%2Cfit%3Dcrop/NNlEVZXDhMFiG6pt/foto-member-YHM4XDB0LWqzig8A.jpg",
        },
    ]
    items = []
    for source in sources:
        data = download(source["source_asset_url"])
        ext = sniff_extension(data)
        filename = f"{source['key']}{ext}"
        (bundle / filename).write_bytes(data)
        item = dict(source)
        item["file"] = filename
        item["sha256"] = hashlib.sha256(data).hexdigest()
        item["bytes"] = len(data)
        item["provenance"] = "First-party Gloskin website editorial source; bundled locally for this site migration."
        items.append(item)
    manifest = {
        "bundle_id": "gloskin-editorial-media-v1",
        "revision": "2026-08-19-final",
        "hosting": "local-only-after-import",
        "policy": "generic editorial fallback only; never substitutes factual doctor/product/clinic identity media",
        "items": items,
    }
    (bundle / "manifest.json").write_text(json.dumps(manifest, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")


IA_HELPER = r'''<?php
/**
 * Stored WordPress IA normalizer owned by the 2026-08-19 final migration.
 *
 * @package GloskinSiteCore
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Gloskin_Site_Core_Final_IA_Normalizer {
	const REVISION = '2026-08-19-final';
	const MENU_LOCATION = 'gloskin-primary';
	const MENU_NAME = 'Gloskin Primary';
	const PRESERVED_MENU_NAME = 'Gloskin Primary Preserved 2026-08-19-final';
	const PRESERVED_SOURCE_META = '_gloskin_final_preserved_source_menu_item';
	const PRESERVED_REVISION_META = '_gloskin_final_preserved_menu_revision';

	/** @return array<string,mixed> */
	public function normalize() {
		$page_ids = $this->ensure_pages();
		$menu = $this->normalize_primary_menu( $page_ids );
		return array_merge( array( 'page_ids' => $page_ids ), $menu );
	}

	/** @param array<string,mixed> $audit @return void */
	public function verify( array $audit ) {
		$page_ids = isset( $audit['page_ids'] ) && is_array( $audit['page_ids'] ) ? $audit['page_ids'] : array();
		foreach ( array( 'home', 'treatments', 'promo', 'skincare', 'about' ) as $key ) {
			$page = ! empty( $page_ids[ $key ] ) ? get_post( absint( $page_ids[ $key ] ) ) : null;
			if ( ! ( $page instanceof WP_Post ) || 'page' !== $page->post_type || 'trash' === $page->post_status ) {
				throw new RuntimeException( 'verification_failed: IA page invalid: ' . $key . '.' );
			}
		}
		$home_id = absint( $page_ids['home'] ?? 0 );
		if ( $home_id < 1 || 'page' !== (string) get_option( 'show_on_front', 'posts' ) || $home_id !== (int) get_option( 'page_on_front', 0 ) ) {
			throw new RuntimeException( 'verification_failed: Stored page_on_front is not canonical Home.' );
		}

		$locations = get_theme_mod( 'nav_menu_locations', array() );
		$locations = is_array( $locations ) ? $locations : array();
		$stored_menu_id = absint( $locations[ self::MENU_LOCATION ] ?? 0 );
		$audit_menu_id = absint( $audit['menu_id'] ?? 0 );
		if ( ! $stored_menu_id || $stored_menu_id !== $audit_menu_id ) {
			throw new RuntimeException( 'verification_failed: Stored gloskin-primary assignment differs from migration audit.' );
		}
		$items = wp_get_nav_menu_items( $stored_menu_id );
		$items = is_array( $items ) ? $items : array();
		$actual = array();
		foreach ( $items as $item ) {
			if ( 0 !== absint( $item->menu_item_parent ) ) {
				throw new RuntimeException( 'verification_failed: gloskin-primary contains unexpected submenu.' );
			}
			$actual[] = array( (string) $item->title, $this->menu_path( (string) $item->url ) );
		}
		$expected = array(
			array( 'Perawatan', '/treatments/' ),
			array( 'Promo', '/promo/' ),
			array( 'Skincare', '/skincare/' ),
			array( 'Tentang Gloskin', '/about/' ),
		);
		if ( $expected !== $actual ) {
			throw new RuntimeException( 'verification_failed: Stored gloskin-primary must be exactly Perawatan, Promo, Skincare, Tentang Gloskin.' );
		}
		$preserved_count = absint( $audit['preserved_item_count'] ?? 0 );
		if ( $preserved_count > 0 ) {
			$preserved_id = absint( $audit['preserved_menu_id'] ?? 0 );
			$preserved = $preserved_id ? wp_get_nav_menu_items( $preserved_id ) : array();
			$preserved = is_array( $preserved ) ? $preserved : array();
			if ( count( $preserved ) < $preserved_count ) {
				throw new RuntimeException( 'verification_failed: Editor primary-menu snapshot is incomplete.' );
			}
		}
	}

	/** @return array<string,int> */
	private function ensure_pages() {
		$definitions = array(
			'home' => 'Beranda',
			'treatments' => 'Perawatan',
			'promo' => 'Promo',
			'skincare' => 'Skincare',
			'about' => 'Tentang Gloskin',
		);
		$ids = array();
		foreach ( $definitions as $slug => $title ) {
			$ids[ $slug ] = $this->ensure_page( $slug, $title );
		}
		$this->normalize_front_page( absint( $ids['home'] ) );
		return $ids;
	}

	/** @param string $slug @param string $title @return int */
	private function ensure_page( $slug, $title ) {
		$page = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $page instanceof WP_Post ) {
			if ( 'trash' === $page->post_status ) {
				throw new RuntimeException( 'IA page /' . $slug . '/ exists in Trash; ownership is ambiguous.' );
			}
			return absint( $page->ID );
		}
		$result = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug ), true );
		if ( is_wp_error( $result ) ) { throw new RuntimeException( 'Failed to ensure /' . $slug . '/: ' . $result->get_error_message() ); }
		$id = absint( $result );
		update_post_meta( $id, '_gloskin_provisioned_revision', self::REVISION );
		return $id;
	}

	/** @param int $home_id @return void */
	private function normalize_front_page( $home_id ) {
		$front_id = (int) get_option( 'page_on_front', 0 );
		$front = $front_id > 0 ? get_post( $front_id ) : null;
		if ( $front_id === $home_id ) {
			if ( 'page' !== (string) get_option( 'show_on_front', 'posts' ) ) { update_option( 'show_on_front', 'page' ); }
			return;
		}
		if ( ! ( $front instanceof WP_Post ) || 'page' !== $front->post_type || 'trash' === $front->post_status ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $home_id );
			return;
		}
		throw new RuntimeException( 'Canonical Home safe-stop: current page_on_front is editor-owned "' . (string) $front->post_title . '" (#' . absint( $front->ID ) . '). Configuration was preserved.' );
	}

	/** @param array<string,int> $page_ids @return array<string,int> */
	private function normalize_primary_menu( array $page_ids ) {
		$locations = get_theme_mod( 'nav_menu_locations', array() );
		$locations = is_array( $locations ) ? $locations : array();
		$menu_id = absint( $locations[ self::MENU_LOCATION ] ?? 0 );
		$menu = $menu_id ? wp_get_nav_menu_object( $menu_id ) : false;
		if ( ! $menu ) {
			$created = wp_create_nav_menu( self::MENU_NAME );
			if ( is_wp_error( $created ) ) { throw new RuntimeException( $created->get_error_message() ); }
			$menu_id = absint( $created );
			$locations[ self::MENU_LOCATION ] = $menu_id;
			set_theme_mod( 'nav_menu_locations', $locations );
		}
		$items = wp_get_nav_menu_items( $menu_id );
		$items = is_array( $items ) ? $items : array();
		$preserved_id = $this->preserve_snapshot( $items );
		$target = array(
			'treatments' => array( 'label' => 'Perawatan', 'path' => '/treatments/' ),
			'promo' => array( 'label' => 'Promo', 'path' => '/promo/' ),
			'skincare' => array( 'label' => 'Skincare', 'path' => '/skincare/' ),
			'about' => array( 'label' => 'Tentang Gloskin', 'path' => '/about/' ),
		);
		$existing = array();
		foreach ( $items as $item ) {
			$key = $this->target_key_for_path( $this->menu_path( (string) $item->url ) );
			if ( '' !== $key && ! isset( $existing[ $key ] ) ) { $existing[ $key ] = absint( $item->ID ); }
		}
		$canonical_ids = array();
		$position = 1;
		foreach ( $target as $key => $definition ) {
			$item_id = absint( $existing[ $key ] ?? 0 );
			$result = wp_update_nav_menu_item( $menu_id, $item_id, array(
				'menu-item-title' => $definition['label'],
				'menu-item-object-id' => absint( $page_ids[ $key ] ?? 0 ),
				'menu-item-object' => 'page',
				'menu-item-type' => 'post_type',
				'menu-item-status' => 'publish',
				'menu-item-parent-id' => 0,
				'menu-item-position' => $position,
			) );
			if ( is_wp_error( $result ) || ! $result ) { throw new RuntimeException( 'Failed to normalize primary item ' . $definition['label'] . '.' ); }
			$canonical_ids[] = absint( $result );
			$position++;
		}
		foreach ( $items as $item ) {
			$item_id = absint( $item->ID );
			if ( in_array( $item_id, $canonical_ids, true ) ) { continue; }
			if ( 'nav_menu_item' === get_post_type( $item_id ) ) { wp_delete_post( $item_id, true ); }
		}
		$locations[ self::MENU_LOCATION ] = $menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );
		return array( 'menu_id' => $menu_id, 'preserved_menu_id' => $preserved_id, 'preserved_item_count' => count( $items ) );
	}

	/** @param array<int,WP_Post> $items @return int */
	private function preserve_snapshot( array $items ) {
		if ( ! $items ) { return 0; }
		$preserved = wp_get_nav_menu_object( self::PRESERVED_MENU_NAME );
		if ( ! $preserved ) {
			$created = wp_create_nav_menu( self::PRESERVED_MENU_NAME );
			if ( is_wp_error( $created ) ) { throw new RuntimeException( 'Failed to create editor-menu preservation snapshot.' ); }
			$preserved = wp_get_nav_menu_object( absint( $created ) );
		}
		if ( ! $preserved ) { throw new RuntimeException( 'Editor-menu preservation snapshot cannot be verified.' ); }
		$preserved_id = absint( $preserved->term_id );
		$copies = wp_get_nav_menu_items( $preserved_id );
		$copies = is_array( $copies ) ? $copies : array();
		$source_to_copy = array();
		foreach ( $copies as $copy ) {
			$source_id = absint( get_post_meta( $copy->ID, self::PRESERVED_SOURCE_META, true ) );
			$revision = (string) get_post_meta( $copy->ID, self::PRESERVED_REVISION_META, true );
			if ( $source_id && self::REVISION === $revision ) { $source_to_copy[ $source_id ] = absint( $copy->ID ); }
		}
		$position = 1;
		foreach ( $items as $item ) {
			$source_id = absint( $item->ID );
			$copy_id = absint( $source_to_copy[ $source_id ] ?? 0 );
			$result = wp_update_nav_menu_item( $preserved_id, $copy_id, $this->copy_args( $item, 0, $position ) );
			if ( is_wp_error( $result ) || ! $result ) { throw new RuntimeException( 'Failed to preserve editor menu item: ' . (string) $item->title . '.' ); }
			$copy_id = absint( $result );
			$source_to_copy[ $source_id ] = $copy_id;
			update_post_meta( $copy_id, self::PRESERVED_SOURCE_META, $source_id );
			update_post_meta( $copy_id, self::PRESERVED_REVISION_META, self::REVISION );
			$position++;
		}
		$position = 1;
		foreach ( $items as $item ) {
			$source_id = absint( $item->ID );
			$parent_source = absint( $item->menu_item_parent );
			$copy_id = absint( $source_to_copy[ $source_id ] ?? 0 );
			$copy_parent = $parent_source && isset( $source_to_copy[ $parent_source ] ) ? absint( $source_to_copy[ $parent_source ] ) : 0;
			$result = wp_update_nav_menu_item( $preserved_id, $copy_id, $this->copy_args( $item, $copy_parent, $position ) );
			if ( is_wp_error( $result ) || ! $result ) { throw new RuntimeException( 'Failed to preserve editor menu hierarchy.' ); }
			$position++;
		}
		return $preserved_id;
	}

	/** @return array<string,mixed> */
	private function copy_args( $item, $parent_id, $position ) {
		return array(
			'menu-item-title' => (string) $item->title,
			'menu-item-url' => (string) $item->url,
			'menu-item-description' => isset( $item->description ) ? (string) $item->description : '',
			'menu-item-attr-title' => isset( $item->attr_title ) ? (string) $item->attr_title : '',
			'menu-item-target' => isset( $item->target ) ? (string) $item->target : '',
			'menu-item-classes' => isset( $item->classes ) ? (array) $item->classes : array(),
			'menu-item-xfn' => isset( $item->xfn ) ? (string) $item->xfn : '',
			'menu-item-type' => isset( $item->type ) && '' !== (string) $item->type ? (string) $item->type : 'custom',
			'menu-item-object' => isset( $item->object ) ? (string) $item->object : 'custom',
			'menu-item-object-id' => isset( $item->object_id ) ? absint( $item->object_id ) : 0,
			'menu-item-status' => 'publish',
			'menu-item-parent-id' => absint( $parent_id ),
			'menu-item-position' => absint( $position ),
		);
	}

	/** @return string */
	private function menu_path( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		$site_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		if ( is_string( $host ) && '' !== $host && is_string( $site_host ) && '' !== $site_host && strtolower( $host ) !== strtolower( $site_host ) ) { return ''; }
		$path = wp_parse_url( $url, PHP_URL_PATH );
		return is_string( $path ) ? trailingslashit( '/' . ltrim( $path, '/' ) ) : '';
	}

	/** @return string */
	private function target_key_for_path( $path ) {
		$targets = array( '/treatments/' => 'treatments', '/promo/' => 'promo', '/skincare/' => 'skincare', '/about/' => 'about' );
		return isset( $targets[ $path ] ) ? $targets[ $path ] : '';
	}
}
'''

EDITORIAL_HELPER = r'''<?php
/**
 * Bounded first-party editorial media bundle importer for the final migration.
 *
 * @package GloskinSiteCore
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Gloskin_Site_Core_Editorial_Media_Bundle {
	const BUNDLE_ID = 'gloskin-editorial-media-v1';
	const BUNDLE_DIR = 'gloskin-editorial-media-v1';
	const OPTION = 'gloskin_site_core_editorial_media_v1';
	const KEY_META = '_gloskin_editorial_media_key';
	const SHA_META = '_gloskin_editorial_media_sha256';
	const SOURCE_META = '_gloskin_editorial_media_source_url';
	const SOURCE_PAGE_META = '_gloskin_editorial_media_source_page';
	const REVISION_META = '_gloskin_editorial_media_revision';
	const REVISION = '2026-08-19-final';
	/** @var string */ private $dir;

	/** @param string $plugin_file */
	public function __construct( $plugin_file ) {
		$this->dir = trailingslashit( plugin_dir_path( $plugin_file ) ) . 'migration-runtime/' . self::BUNDLE_DIR;
	}

	/** @return array<string,mixed> */
	public function preflight() {
		$manifest = $this->manifest();
		$required = array( 'home_why', 'home_brand_story', 'treatment_discovery', 'treatment_clinical', 'skincare_editorial', 'about_story' );
		$seen = array();
		foreach ( $manifest['items'] as $item ) {
			$key = sanitize_key( (string) ( $item['key'] ?? '' ) );
			$file = basename( (string) ( $item['file'] ?? '' ) );
			$sha = strtolower( (string) ( $item['sha256'] ?? '' ) );
			$path = $this->dir . '/' . $file;
			if ( '' === $key || '' === $file || 64 !== strlen( $sha ) || ! is_readable( $path ) ) {
				throw new RuntimeException( 'bundle_invalid: Editorial media entry is incomplete: ' . $key );
			}
			$actual = hash_file( 'sha256', $path );
			if ( ! is_string( $actual ) || ! hash_equals( $sha, strtolower( $actual ) ) ) {
				throw new RuntimeException( 'bundle_invalid: Editorial media SHA mismatch: ' . $key );
			}
			if ( isset( $seen[ $key ] ) ) { throw new RuntimeException( 'bundle_invalid: Duplicate editorial media key: ' . $key ); }
			$seen[ $key ] = true;
		}
		foreach ( $required as $key ) {
			if ( empty( $seen[ $key ] ) ) { throw new RuntimeException( 'bundle_invalid: Required editorial media key missing: ' . $key ); }
		}
		return $manifest;
	}

	/** @return array<string,mixed> */
	public function import() {
		$manifest = $this->preflight();
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) || empty( $upload['path'] ) || ! is_dir( $upload['path'] ) || ! is_writable( $upload['path'] ) ) {
			throw new RuntimeException( 'upload_unavailable: Editorial media upload directory is not writable.' );
		}
		$catalog = array();
		$audit = array( 'created' => array(), 'reused' => array() );
		foreach ( $manifest['items'] as $item ) {
			$key = sanitize_key( (string) $item['key'] );
			$sha = strtolower( (string) $item['sha256'] );
			$attachment_id = $this->find_attachment( $key, $sha );
			$bucket = 'reused';
			if ( ! $attachment_id ) {
				$attachment_id = $this->import_one( $item, $upload );
				$bucket = 'created';
			}
			$catalog[ $key ] = array(
				'attachment_id' => $attachment_id,
				'kind' => sanitize_key( (string) ( $item['kind'] ?? 'editorial' ) ),
				'role' => sanitize_text_field( (string) ( $item['role'] ?? '' ) ),
				'sha256' => $sha,
				'source_page' => esc_url_raw( (string) ( $item['source_page'] ?? '' ) ),
				'source_asset_url' => esc_url_raw( (string) ( $item['source_asset_url'] ?? '' ) ),
			);
			$audit[ $bucket ][] = array( 'key' => $key, 'attachment_id' => $attachment_id, 'sha256' => $sha );
		}
		update_option( self::OPTION, $catalog, false );
		$audit['catalog'] = $catalog;
		return $audit;
	}

	/** @param array<string,mixed> $audit @return void */
	public function verify( array $audit ) {
		$catalog = get_option( self::OPTION, array() );
		$catalog = is_array( $catalog ) ? $catalog : array();
		$required = array( 'home_why', 'home_brand_story', 'treatment_discovery', 'treatment_clinical', 'skincare_editorial', 'about_story' );
		foreach ( $required as $key ) {
			$entry = isset( $catalog[ $key ] ) && is_array( $catalog[ $key ] ) ? $catalog[ $key ] : array();
			$attachment_id = absint( $entry['attachment_id'] ?? 0 );
			if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
				throw new RuntimeException( 'verification_failed: Editorial media attachment missing: ' . $key );
			}
			$file = get_attached_file( $attachment_id );
			if ( ! is_string( $file ) || '' === $file || ! is_readable( $file ) ) {
				throw new RuntimeException( 'verification_failed: Editorial media is not locally hosted: ' . $key );
			}
			$expected_sha = strtolower( (string) ( $entry['sha256'] ?? '' ) );
			$stored_sha = strtolower( (string) get_post_meta( $attachment_id, self::SHA_META, true ) );
			if ( 64 !== strlen( $expected_sha ) || ! hash_equals( $expected_sha, $stored_sha ) ) {
				throw new RuntimeException( 'verification_failed: Editorial media provenance SHA mismatch: ' . $key );
			}
			if ( '' === (string) get_post_meta( $attachment_id, self::SOURCE_META, true ) || '' === (string) get_post_meta( $attachment_id, self::SOURCE_PAGE_META, true ) ) {
				throw new RuntimeException( 'verification_failed: Editorial media provenance missing: ' . $key );
			}
		}
		$audited = count( (array) ( $audit['created'] ?? array() ) ) + count( (array) ( $audit['reused'] ?? array() ) );
		if ( $audited < count( $required ) ) { throw new RuntimeException( 'verification_failed: Editorial media audit is incomplete.' ); }
	}

	/** @return array<string,mixed> */
	private function manifest() {
		$path = $this->dir . '/manifest.json';
		if ( ! is_readable( $path ) ) { throw new RuntimeException( 'bundle_unavailable: Editorial media manifest missing.' ); }
		$manifest = json_decode( (string) file_get_contents( $path ), true );
		if ( ! is_array( $manifest ) || self::BUNDLE_ID !== (string) ( $manifest['bundle_id'] ?? '' ) || ! isset( $manifest['items'] ) || ! is_array( $manifest['items'] ) ) {
			throw new RuntimeException( 'bundle_invalid: Editorial media manifest invalid.' );
		}
		return $manifest;
	}

	/** @return int */
	private function find_attachment( $key, $sha ) {
		$ids = get_posts( array(
			'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 1, 'fields' => 'ids',
			'meta_query' => array( 'relation' => 'AND', array( 'key' => self::KEY_META, 'value' => $key ), array( 'key' => self::SHA_META, 'value' => $sha ) ),
		) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		return ! empty( $ids ) ? absint( $ids[0] ) : 0;
	}

	/** @param array<string,mixed> $item @param array<string,mixed> $upload @return int */
	private function import_one( array $item, array $upload ) {
		$key = sanitize_key( (string) $item['key'] );
		$source_file = $this->dir . '/' . basename( (string) $item['file'] );
		$dest_name = wp_unique_filename( (string) $upload['path'], basename( (string) $item['file'] ) );
		$dest_path = trailingslashit( (string) $upload['path'] ) . $dest_name;
		if ( ! copy( $source_file, $dest_path ) ) { throw new RuntimeException( 'upload_unavailable: Failed to copy editorial media ' . $key ); }
		$filetype = wp_check_filetype( $dest_name, null );
		$attachment_id = wp_insert_attachment( array(
			'post_mime_type' => ! empty( $filetype['type'] ) ? (string) $filetype['type'] : 'image/webp',
			'post_title' => sanitize_text_field( (string) ( $item['role'] ?? $key ) ),
			'post_content' => '', 'post_status' => 'inherit',
		), $dest_path );
		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			@unlink( $dest_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			throw new RuntimeException( 'upload_unavailable: Failed to register editorial media ' . $key );
		}
		$attachment_id = absint( $attachment_id );
		$metadata = wp_generate_attachment_metadata( $attachment_id, $dest_path );
		wp_update_attachment_metadata( $attachment_id, $metadata );
		update_post_meta( $attachment_id, self::KEY_META, $key );
		update_post_meta( $attachment_id, self::SHA_META, strtolower( (string) $item['sha256'] ) );
		update_post_meta( $attachment_id, self::SOURCE_META, esc_url_raw( (string) ( $item['source_asset_url'] ?? '' ) ) );
		update_post_meta( $attachment_id, self::SOURCE_PAGE_META, esc_url_raw( (string) ( $item['source_page'] ?? '' ) ) );
		update_post_meta( $attachment_id, self::REVISION_META, self::REVISION );
		return $attachment_id;
	}
}
'''

HOME = r'''<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* One Home hero only. TemplateService supplies the visible campaign H1/copy/CTA
 * and may enhance the same media column with the existing native Media Library
 * video controller. There is no second hero or second video service. */
gloskin_ui1_render_hero( $gloskin_context['hero'] );

/* Approved final prototype hierarchy:
 * Hero -> Why -> Featured Treatments -> Promo -> unified Skincare/Product
 * Discovery -> factual Testimonials -> editor Home brand story -> factual
 * Achievements -> Closing CTA. */
?>
<?php gloskin_ui1_render_why_gloskin( $gloskin_context['page'] ); ?>
<?php if ( $gloskin_context['treatments'] ) : ?>
<section class="gloskin-ui1-section gloskin-ui1-section--soft" data-gloskin-section="home-treatments"><div class="gloskin-ui1-container">
	<?php gloskin_ui1_render_section_heading( __( 'Pilihan Perawatan', 'gloskin-site-core' ), __( 'Kenali ragam perawatan Gloskin dan temukan pilihan yang relevan untuk dibahas saat konsultasi.', 'gloskin-site-core' ) ); ?>
	<?php gloskin_ui1_render_card_grid( $gloskin_context['treatments'], 'treatment' ); ?>
	<p class="gloskin-ui1-section__action"><a class="gloskin-ui1-text-link" href="<?php echo esc_url( home_url( '/treatments/' ) ); ?>"><?php echo esc_html__( 'Jelajahi Perawatan', 'gloskin-site-core' ); ?> →</a></p>
</div></section>
<?php endif; ?>
<?php gloskin_ui1_render_managed_promo_carousel( $gloskin_context['promo'], 'h2', true ); ?>
<section class="gloskin-ui1-section gloskin-ui1-section--soft gloskin-ui1-home-discovery" data-gloskin-section="home-discovery"><div class="gloskin-ui1-container">
	<?php gloskin_ui1_render_section_heading( __( 'Skincare & Produk Gloskin', 'gloskin-site-core' ), __( 'Jelajahi kategori skincare dan produk yang tersedia dalam satu alur discovery.', 'gloskin-site-core' ) ); ?>
	<div class="gloskin-ui1-home-discovery__categories gloskin-ui1-grid gloskin-ui1-grid--categories">
		<?php foreach ( $gloskin_context['skincare'] as $gloskin_mapping ) { gloskin_ui1_render_category_link( $gloskin_mapping ); } ?>
	</div>
	<?php if ( $gloskin_context['products'] ) : ?>
		<div class="gloskin-ui1-home-discovery__products gloskin-ui1-grid gloskin-ui1-grid--cards gloskin-ui1-product-grid" data-gloskin-product-grid>
			<?php foreach ( $gloskin_context['products'] as $gloskin_product ) { gloskin_ui1_render_product_card( $gloskin_product ); } ?>
		</div>
	<?php endif; ?>
	<p class="gloskin-ui1-section__action"><a class="gloskin-ui1-text-link" href="<?php echo esc_url( home_url( '/skincare/' ) ); ?>"><?php echo esc_html__( 'Jelajahi Skincare', 'gloskin-site-core' ); ?> →</a><?php if ( $gloskin_context['products'] ) : ?> <span aria-hidden="true">·</span> <a class="gloskin-ui1-text-link" href="<?php echo esc_url( home_url( '/shop/' ) ); ?>"><?php echo esc_html__( 'Lihat Semua Produk', 'gloskin-site-core' ); ?> →</a><?php endif; ?></p>
</div></section>
<?php gloskin_ui1_render_testimonials( $gloskin_context['testimonials'] ); ?>
<?php if ( gloskin_ui1_has_content( $gloskin_context['page'] ) ) : ?>
<section class="gloskin-ui1-section gloskin-ui1-home-brand-story" data-gloskin-section="home-brand-story"><div class="gloskin-ui1-container gloskin-ui1-home-brand-story__grid">
	<div class="gloskin-ui1-home-brand-story__content"><p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Tentang Gloskin', 'gloskin-site-core' ); ?></p><?php gloskin_ui1_render_page_content( $gloskin_context['page'] ); ?></div>
	<div class="gloskin-ui1-home-brand-story__media"><?php gloskin_ui1_render_editorial_media( 'editorial', 'home_brand_story', 'gloskin-ui1-home-brand-story__image' ); ?></div>
</div></section>
<?php endif; ?>
<?php gloskin_ui1_render_achievements( $gloskin_context['achievements'], 'compact' ); ?>
<section class="gloskin-ui1-section gloskin-ui1-section--cta" data-gloskin-section="home-closing"><div class="gloskin-ui1-container"><?php gloskin_ui1_render_closing_cta( __( 'Konsultasi', 'gloskin-site-core' ), __( 'Siap membicarakan kebutuhan kulit Anda?', 'gloskin-site-core' ), __( 'Pilih klinik Gloskin terdekat atau hubungi tim kami untuk menjadwalkan konsultasi.', 'gloskin-site-core' ), __( 'Pilih Klinik', 'gloskin-site-core' ), home_url( '/clinics/' ), __( 'Hubungi Kami', 'gloskin-site-core' ), home_url( '/contact/' ) ); ?></div></section>
'''

PAGE_TRANSITION = r'''	/* Page-to-page cross-document transition.
	 * Navigation timing is intentionally independent from CSS motion. The jelly
	 * paints immediately, receives up to two RAF opportunities, and document
	 * navigation is attempted exactly once inside a <=120ms intentional budget.
	 * Woo mutation/action controls remain native; ordinary same-origin document
	 * links (including /cart/ and /checkout/ outside the protected commerce
	 * handoff lifecycle) stay eligible. */
	function initPageTransitions() {
		if (typeof window.matchMedia === 'function' &&
			window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
		var overlay = document.querySelector('[data-gloskin-page-transition]');
		if (!overlay) { return; }

		var EXIT_NAV_DELAY_MS = 96;
		var STALE_UI_TIMEOUT_MS = 3000;
		var navigated = false;
		var staleTimer = 0;

		function clearTransitionState() {
			overlay.classList.remove('is-active');
			overlay.style.pointerEvents = '';
			navigated = false;
			if (staleTimer) {
				window.clearTimeout(staleTimer);
				staleTimer = 0;
			}
		}

		/* BFCache only: a restored document must never retain outgoing UI state. */
		window.addEventListener('pageshow', function (e) {
			if (e.persisted) { clearTransitionState(); }
		});

		function hasClass(link, className) {
			return link.classList && link.classList.contains(className);
		}

		function isWooActionLink(link, url) {
			var rawHref = link.getAttribute('href') || '';
			if (rawHref.indexOf('add-to-cart=') !== -1 || rawHref.indexOf('wc-ajax=') !== -1) { return true; }
			if (hasClass(link, 'ajax_add_to_cart') || hasClass(link, 'remove_from_cart_button') || hasClass(link, 'reset_variations')) { return true; }
			if (link.hasAttribute('data-gloskin-wishlist-toggle') || link.hasAttribute('data-gloskin-quickadd-open')) { return true; }
			if (link.closest('[data-gloskin-modal], [data-gloskin-wishlist], .quantity, .variations, form.checkout, form.woocommerce-cart-form, .wc-block-cart-item__quantity')) { return true; }
			return url.searchParams.has('wc-ajax') || url.searchParams.has('add-to-cart');
		}

		function commerceHandoffOwns(link, url) {
			var body = document.body;
			if (!body) { return false; }
			var isJourneyPage = body.classList.contains('woocommerce-cart') || body.classList.contains('woocommerce-checkout');
			if (!isJourneyPage) { return false; }
			var path = url.pathname.replace(/\/+$/, '') || '/';
			return (path === '/cart' || path === '/checkout') && !!link.closest('.woocommerce, .wp-block-woocommerce-cart, .wp-block-woocommerce-checkout');
		}

		document.addEventListener('click', function (e) {
			if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0 || navigated) { return; }
			var node = e.target;
			var link = null;
			while (node && node !== document.body) {
				if (node.tagName === 'A' && node.href) { link = node; break; }
				node = node.parentElement;
			}
			if (!link) { return; }
			var href = link.href;
			if (!/^https?:\/\//.test(href) || link.hasAttribute('download')) { return; }
			var target = link.getAttribute('target') || '';
			if (target === '_blank' || target === '_new' || link.hasAttribute('data-gloskin-no-transition')) { return; }

			var url;
			try {
				url = new URL(href);
				if (url.host !== location.host) { return; }
				if (url.hash && url.pathname === location.pathname && url.search === location.search) { return; }
				if (href === location.href) { return; }
			} catch (ex) { return; }
			if (url.pathname.indexOf('/wp-admin/') !== -1 || url.pathname.indexOf('/wp-login') !== -1 || url.pathname.indexOf('/wp-json/') !== -1) { return; }
			if (isWooActionLink(link, url) || commerceHandoffOwns(link, url)) { return; }

			e.preventDefault();
			navigated = true;
			overlay.classList.add('is-active');
			var startedAt = Date.now();
			var attempted = false;
			var navigateOnce = function () {
				if (attempted) { return; }
				attempted = true;
				location.href = href;
			};

			/* Paint the outgoing jelly first, but never couple navigation to its CSS duration. */
			window.requestAnimationFrame(function () {
				window.requestAnimationFrame(function () {
					var elapsed = Date.now() - startedAt;
					window.setTimeout(navigateOnce, Math.max(0, EXIT_NAV_DELAY_MS - elapsed));
				});
			});

			/* Recovery only. If the same document survives a failed navigation, unlock it.
			 * This timer never performs a second redirect. */
			staleTimer = window.setTimeout(function () {
				if (document.documentElement && overlay.isConnected) { clearTransitionState(); }
			}, STALE_UI_TIMEOUT_MS);
		});
	}
'''

TEMPLATE_EDITORIAL = r'''if ( ! function_exists( 'gloskin_ui1_editorial_media_catalog' ) ) {
	/** @return array<string,array<string,mixed>> */
	function gloskin_ui1_editorial_media_catalog() {
		$catalog = get_option( 'gloskin_site_core_editorial_media_v1', array() );
		return is_array( $catalog ) ? $catalog : array();
	}
}

if ( ! function_exists( 'gloskin_ui1_resolve_editorial_media' ) ) {
	/**
	 * Resolve only generic editorial roles from the migration-owned local bundle.
	 * Doctor, clinic and product identity media are deliberately excluded: those
	 * entities may only display their own factual WordPress/Woo image.
	 *
	 * @return array<string,mixed>
	 */
	function gloskin_ui1_resolve_editorial_media( $kind = 'editorial', $seed = 'gloskin' ) {
		$kind = sanitize_key( (string) $kind );
		if ( in_array( $kind, array( 'doctor', 'clinic', 'product' ), true ) ) { return array(); }
		$catalog = gloskin_ui1_editorial_media_catalog();
		if ( isset( $catalog[ $seed ] ) && is_array( $catalog[ $seed ] ) ) {
			$entry = $catalog[ $seed ];
			$id = absint( $entry['attachment_id'] ?? 0 );
			if ( $id ) { return $entry; }
		}
		$groups = array(
			'hero' => array( 'home_why', 'home_brand_story' ),
			'treatment' => array( 'treatment_discovery', 'treatment_clinical' ),
			'skincare' => array( 'skincare_editorial' ),
			'editorial' => array( 'home_why', 'about_story', 'home_brand_story' ),
		);
		$keys = isset( $groups[ $kind ] ) ? $groups[ $kind ] : $groups['editorial'];
		if ( ! $keys ) { return array(); }
		$offset = abs( (int) crc32( $kind . '|' . (string) $seed ) ) % count( $keys );
		for ( $i = 0; $i < count( $keys ); $i++ ) {
			$key = $keys[ ( $offset + $i ) % count( $keys ) ];
			$entry = isset( $catalog[ $key ] ) && is_array( $catalog[ $key ] ) ? $catalog[ $key ] : array();
			if ( absint( $entry['attachment_id'] ?? 0 ) ) { return $entry; }
		}
		return array();
	}
}

if ( ! function_exists( 'gloskin_ui1_render_editorial_media' ) ) {
	/** Render local editorial media, falling back to abstract media only if the migration bundle is unavailable. */
	function gloskin_ui1_render_editorial_media( $kind = 'editorial', $seed = 'gloskin', $class = '', $eager = false ) {
		$resolved = gloskin_ui1_resolve_editorial_media( $kind, $seed );
		$attachment_id = absint( $resolved['attachment_id'] ?? 0 );
		if ( $attachment_id ) {
			$attrs = array( 'class' => trim( (string) $class ), 'decoding' => 'async', 'loading' => $eager ? 'eager' : 'lazy' );
			if ( $eager ) { $attrs['fetchpriority'] = 'high'; }
			$image = wp_get_attachment_image( $attachment_id, 'large', false, $attrs );
			if ( is_string( $image ) && '' !== $image ) { echo $image; return; } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		gloskin_ui1_render_presentation_media( $kind, $seed, $class );
	}
}
'''

FINAL_CONTRACT = r'''<?php
declare(strict_types=1);
$root = dirname( __DIR__ );
$fail = 0;
function gl_final_ok( bool $ok, string $message ): void { global $fail; echo ( $ok ? 'ok: ' : 'FAIL: ' ) . $message . "\n"; if ( ! $ok ) { $fail++; } }
$migration = file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-revision-20260819-final-migration.php' );
$ia = file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-final-ia-normalizer.php' );
$media = file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-editorial-media-bundle.php' );
$helpers = file_get_contents( $root . '/plugin/gloskin-site-core/templates/parts/template-helpers.php' );
$home = file_get_contents( $root . '/plugin/gloskin-site-core/templates/pages/home.php' );
$manifest = json_decode( (string) file_get_contents( $root . '/plugin/gloskin-site-core/migration-runtime/gloskin-editorial-media-v1/manifest.json' ), true );
$steps = array( 'preflight', 'managed_content', 'demo_seed', 'doctor_photos', 'normalize', 'cleanup', 'verify', 'finalize' );
$positions = array(); foreach ( $steps as $step ) { $positions[] = strpos( $migration, "'key' => '" . $step . "'" ); }
gl_final_ok( ! in_array( false, $positions, true ) && $positions === array_values( $positions ) && $positions === ( $sorted = ( function( $p ){ sort( $p ); return $p; } )( $positions ) ), 'final migration keeps the original eight checkpoint order' );
gl_final_ok( substr_count( $migration, "'key' =>") === 8, 'no migration checkpoint added' );
gl_final_ok( str_contains( $migration, 'editorial_media_service()->preflight()' ) && str_contains( $migration, "['editorial_audit'] = $this->run_managed_content()" ), 'existing managed_content checkpoint owns editorial bundle work' );
gl_final_ok( str_contains( $migration, "['ia_audit'] = $this->run_normalize()" ) && str_contains( $migration, 'final_ia_normalizer()->verify' ), 'existing normalize/verify checkpoints own stored IA' );
gl_final_ok( str_contains( $migration, 'reconcile_resume_checkpoint' ), 'same revision failed state has bounded catch-up resume logic' );
$update_pos = strpos( $migration, 'Gloskin_Site_Core_Lifecycle_Service::VERSION_OPTION' );
$finalize_pos = strrpos( $migration, 'private function run_finalize' );
$flush_pos = strpos( $migration, 'flush_rewrite_rules( false )', $finalize_pos );
$schema_pos = strpos( $migration, 'Gloskin_Site_Core_Lifecycle_Service::SCHEMA_VERSION', $finalize_pos );
gl_final_ok( $finalize_pos !== false && $schema_pos !== false && $flush_pos !== false && $schema_pos < $flush_pos, 'schema 0.3.0 closes before rewrite flush and consumption' );
gl_final_ok( str_contains( $ia, "'Perawatan', '/treatments/'" ) && str_contains( $ia, "'Tentang Gloskin', '/about/'" ), 'stored primary menu verification has exact labels and paths' );
gl_final_ok( str_contains( $ia, 'PRESERVED_MENU_NAME' ) && str_contains( $ia, 'preserve_snapshot' ), 'editor primary menu snapshot is preserved idempotently' );
gl_final_ok( str_contains( $ia, 'Canonical Home safe-stop' ), 'editor alternate Home triggers safe stop' );
gl_final_ok( ! str_contains( $ia, "wp_delete_post( $page" ), 'IA normalizer never deletes supporting pages' );
gl_final_ok( str_contains( $media, "const OPTION = 'gloskin_site_core_editorial_media_v1'" ) && str_contains( $media, 'SOURCE_PAGE_META' ), 'editorial media has local catalog and provenance metadata' );
gl_final_ok( ! str_contains( $media, 'set_post_thumbnail' ), 'editorial bundle never overwrites editor-selected featured media' );
gl_final_ok( str_contains( $helpers, "array( 'doctor', 'clinic', 'product' )" ), 'doctor/product/clinic factual media safety remains strict' );
gl_final_ok( is_array( $manifest ) && count( $manifest['items'] ?? array() ) === 6, 'bounded editorial media manifest contains six sourced assets' );
foreach ( (array) ( $manifest['items'] ?? array() ) as $item ) {
	$file = $root . '/plugin/gloskin-site-core/migration-runtime/gloskin-editorial-media-v1/' . basename( (string) $item['file'] );
	gl_final_ok( is_file( $file ) && hash_file( 'sha256', $file ) === (string) $item['sha256'], 'bundle SHA valid: ' . (string) $item['key'] );
}
gl_final_ok( ! str_contains( $home, 'home-orientation' ), 'early home-orientation is removed' );
$order = array_map( static fn( $needle ) => strpos( $home, $needle ), array( 'render_why_gloskin', 'home-treatments', 'render_managed_promo_carousel', 'home-discovery', 'render_testimonials', 'home-brand-story', 'render_achievements', 'home-closing' ) );
$sorted_order = $order; sort( $sorted_order );
gl_final_ok( ! in_array( false, $order, true ) && $order === $sorted_order, 'Home order matches approved prototype hierarchy' );
gl_final_ok( substr_count( $home, 'data-gloskin-product-grid' ) === 1, 'unified discovery reuses one supplied product collection without a duplicate Woo query' );
exit( $fail ? 1 : 0 );
'''

PAGE_TRANSITION_CONTRACT = r'''#!/usr/bin/env python3
from pathlib import Path
import re
ROOT = Path(__file__).resolve().parents[1]
def read(rel): return (ROOT / rel).read_text(encoding="utf-8")
def require(cond, message):
    if not cond: raise AssertionError(message)
shell = read("plugin/gloskin-site-core/templates/shell.php")
css = read("plugin/gloskin-site-core/assets/css/gloskin-ui1-loader-system.css")
js = read("plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js")
runtime = read("tests/check-runtime.sh")
canonical_path = 'M647 271H415V239H528V120C528 102 527 88 523 80C520 69 501 56 466 44C428 29 392 21 357 22C300 23 255 53 221 112C187 172 170 249 170 345C170 555 235 665 365 673C481 679 530 624 539 510H569V667C531 682 506 690 495 692C462 700 417 704 360 704C275 704 204 672 145 607C86 539 56 453 55 345C54 246 83 161 142 90C204 21 275 -14 360 -14C419 -14 468 -8 509 2C544 13 578 25 613 35V239H647Z'
require(shell.count('class="gloskin-ui1-page-transition" data-gloskin-page-transition') == 1, "exactly one transition root")
require(canonical_path in shell, "exact canonical G path")
require('fill="#fff"' in shell or 'fill="#FFFFFF"' in shell, "canonical G is white")
require('viewBox="82 74 185 232"' in shell and 'translate(65,300) scale(0.3117268,-0.32)' in shell, "canonical G geometry")
require("--gl-transition-bg:#FFF2EB" in css, "warm transition background")
require("--gl-transition-jelly:#CA050E" in css, "canonical jelly red")
require("--gl-transition-g-size:104px" in css, "desktop G/jelly footprint")
require("--gl-transition-g-size:84px" in css, "mobile G/jelly footprint")
require(".gloskin-ui1-page-transition.is-active .gloskin-ui1-page-transition__blob" in css, "will-change only while active")
base_blob = css[css.index('.gloskin-ui1-page-transition__blob{'):css.index('}', css.index('.gloskin-ui1-page-transition__blob{'))]
require('will-change' not in base_blob, "no permanent border-radius will-change")
require("@media (prefers-reduced-motion:reduce){" in css and ".gloskin-ui1-page-transition{transition:none}" in css, "reduced motion")
require(js.count("function initPageTransitions()") == 1, "one initPageTransitions owner")
pt = js[js.index("function initPageTransitions()"):js.index("\n\tfunction init()", js.index("function initPageTransitions()"))]
require("sessionStorage" not in pt, "no sessionStorage")
require("beforeunload" not in pt and "'unload'" not in pt and '"unload"' not in pt, "no unload handlers")
require("barba" not in pt.lower() and "swup" not in pt.lower() and "fetch(" not in pt, "no SPA library/fetch owner")
m = re.search(r"EXIT_NAV_DELAY_MS\s*=\s*(\d+)", pt); require(m is not None, "EXIT_NAV_DELAY_MS exists")
delay = int(m.group(1)); require(80 <= delay <= 120, "EXIT_NAV_DELAY_MS is 80-120ms")
require("--gl-transition-duration" not in pt and "getTransitionDurationMs" not in pt, "navigation timing independent from CSS fade")
require(pt.count("location.href = href") == 1, "one location navigation attempt")
require(pt.count("requestAnimationFrame") >= 2, "one-two RAF paint opportunity")
require("STALE_UI_TIMEOUT_MS = 3000" in pt and "clearTransitionState" in pt and "navigated = false" in pt, "stale timeout clears UI/state")
stale = pt[pt.index("staleTimer = window.setTimeout"):]; require("location.href" not in stale, "stale timeout never redirects")
require("e.persisted" in pt and "'pageshow'" in pt, "BFCache pageshow cleanup")
require("e.metaKey" in pt and "e.ctrlKey" in pt and "e.shiftKey" in pt and "e.altKey" in pt, "modifier bypass")
require("target === '_blank'" in pt, "_blank bypass")
require("url.host !== location.host" in pt, "external bypass")
require("url.hash && url.pathname === location.pathname" in pt, "same-page anchor bypass")
require("ajax_add_to_cart" in pt and "add-to-cart=" in pt and "wc-ajax=" in pt, "AJAX add-to-cart bypass")
require("wishlist" in pt.lower() and ".variations" in pt and ".quantity" in pt and "form.checkout" in pt, "Woo action controls bypass")
require("commerceHandoffOwns" in pt, "existing cart-checkout handoff remains state owner")
require("'/product/'" not in pt and '"/product/"' not in pt, "normal PDP link eligible")
require("page-transition-contract.py" in runtime, "contract registered in runtime suite")
require("gloskin-ui1-commerce-handoff__g" in shell and canonical_path in shell[shell.index("gloskin-ui1-commerce-handoff"):], "commerce handoff reuses canonical white G visually")
print("page-transition-contract: OK (0.7.141 final closure)")
'''


def patch_sources() -> None:
    # New bounded helpers.
    write("plugin/gloskin-site-core/includes/class-gloskin-site-core-final-ia-normalizer.php", IA_HELPER)
    write("plugin/gloskin-site-core/includes/class-gloskin-site-core-editorial-media-bundle.php", EDITORIAL_HELPER)

    migration = "plugin/gloskin-site-core/includes/class-gloskin-site-core-revision-20260819-final-migration.php"
    replace_once(migration,
        "\t\t\t'demo_audit'          => array(),\n\t\t\t'commerce_snapshot'   => array(),",
        "\t\t\t'demo_audit'          => array(),\n\t\t\t'editorial_audit'     => array(),\n\t\t\t'ia_audit'            => array(),\n\t\t\t'commerce_snapshot'   => array(),")
    replace_once(migration,
        "\t\t\tif ( ! in_array( $state['status'], array( 'running', 'failed', 'verifying' ), true ) ) {\n\t\t\t\tthrow new RuntimeException( 'Migrasi belum dimulai.' );\n\t\t\t}\n\n\t\t\t$index = (int) $state['next_step_index'];",
        "\t\t\tif ( ! in_array( $state['status'], array( 'running', 'failed', 'verifying' ), true ) ) {\n\t\t\t\tthrow new RuntimeException( 'Migrasi belum dimulai.' );\n\t\t\t}\n\n\t\t\t$state = $this->reconcile_resume_checkpoint( $state );\n\t\t\t$this->save_state( $state );\n\t\t\t$index = (int) $state['next_step_index'];")
    replace_once(migration,
        "\t\t\tif ( $index >= count( $steps ) ) {\n\t\t\t\t$state['status']       = 'consumed';",
        "\t\t\tif ( $index >= count( $steps ) ) {\n\t\t\t\tif ( (string) get_option( Gloskin_Site_Core_Lifecycle_Service::VERSION_OPTION, '' ) !== (string) Gloskin_Site_Core_Lifecycle_Service::SCHEMA_VERSION ) {\n\t\t\t\t\tthrow new RuntimeException( 'verification_failed: Final migration cannot be consumed before schema closure.' );\n\t\t\t\t}\n\t\t\t\t$state['status']       = 'consumed';")
    replace_once(migration,
        "\t\t\t\tcase 'managed_content':\n\t\t\t\t\t$this->run_managed_content();\n\t\t\t\t\tbreak;",
        "\t\t\t\tcase 'managed_content':\n\t\t\t\t\t$state['editorial_audit'] = $this->run_managed_content();\n\t\t\t\t\tbreak;")
    replace_once(migration,
        "\t\t\t\tcase 'normalize':\n\t\t\t\t\t$this->run_normalize();\n\t\t\t\t\tbreak;",
        "\t\t\t\tcase 'normalize':\n\t\t\t\t\t$state['ia_audit'] = $this->run_normalize();\n\t\t\t\t\tbreak;")
    replace_once(migration,
        "\tprivate function run_preflight() {\n\t\tif ( ! function_exists( 'wp_insert_attachment' ) ) {",
        "\tprivate function run_preflight() {\n\t\t$this->editorial_media_service()->preflight();\n\t\tif ( ! function_exists( 'wp_insert_attachment' ) ) {")
    regex_replace_once(migration,
        r"\t/\*\* @return void \*/\n\tprivate function run_managed_content\(\) \{.*?\n\t\}\n\n\t/\*\* @return array<string,mixed> \*/\n\tprivate function run_demo_seed",
        "\t/** @return array<string,mixed> */\n\tprivate function run_managed_content() {\n\t\tforeach ( array(\n\t\t\tGloskin_Site_Core_Content_Service::PROMO_POST_TYPE,\n\t\t\tGloskin_Site_Core_Content_Service::TESTIMONIAL_POST_TYPE,\n\t\t\tGloskin_Site_Core_Content_Service::ACHIEVEMENT_POST_TYPE,\n\t\t) as $post_type ) {\n\t\t\tif ( ! post_type_exists( $post_type ) ) { throw new RuntimeException( 'CPT tidak terdaftar: ' . $post_type . '.' ); }\n\t\t}\n\t\treturn $this->editorial_media_service()->import();\n\t}\n\n\t/** @return array<string,mixed> */\n\tprivate function run_demo_seed")
    regex_replace_once(migration,
        r"\t/\*\* @return void \*/\n\tprivate function run_normalize\(\) \{.*?\n\t\}\n\n\t/\*\* @return void \*/\n\tprivate function run_cleanup",
        "\t/** @return array<string,mixed> */\n\tprivate function run_normalize() { return $this->final_ia_normalizer()->normalize(); }\n\n\t/** @return void */\n\tprivate function run_cleanup")
    replace_once(migration,
        "\tprivate function run_verify( array $state ) {\n\t\tforeach ( array( Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE, Gloskin_Site_Core_Content_Service::TESTIMONIAL_POST_TYPE, Gloskin_Site_Core_Content_Service::ACHIEVEMENT_POST_TYPE ) as $post_type ) {",
        "\tprivate function run_verify( array $state ) {\n\t\t$this->editorial_media_service()->verify( (array) ( $state['editorial_audit'] ?? array() ) );\n\t\t$this->final_ia_normalizer()->verify( (array) ( $state['ia_audit'] ?? array() ) );\n\t\tforeach ( array( Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE, Gloskin_Site_Core_Content_Service::TESTIMONIAL_POST_TYPE, Gloskin_Site_Core_Content_Service::ACHIEVEMENT_POST_TYPE ) as $post_type ) {")
    replace_once(migration,
        "\t/** @return void */\n\tprivate function run_finalize() { flush_rewrite_rules( false ); }",
        "\t/** @return void */\n\tprivate function run_finalize() {\n\t\tupdate_option( Gloskin_Site_Core_Lifecycle_Service::VERSION_OPTION, Gloskin_Site_Core_Lifecycle_Service::SCHEMA_VERSION, false );\n\t\tif ( (string) get_option( Gloskin_Site_Core_Lifecycle_Service::VERSION_OPTION, '' ) !== (string) Gloskin_Site_Core_Lifecycle_Service::SCHEMA_VERSION ) {\n\t\t\tthrow new RuntimeException( 'verification_failed: Schema closure did not persist.' );\n\t\t}\n\t\tflush_rewrite_rules( false );\n\t}")
    replace_once(migration,
        "\t/** @return string */\n\tprivate function detect_environment() {",
        "\t/** @return array<string,mixed> */\n\tprivate function reconcile_resume_checkpoint( array $state ) {\n\t\t$index = (int) ( $state['next_step_index'] ?? 0 );\n\t\t$rewind = null;\n\t\tif ( $index > 1 && empty( $state['editorial_audit'] ) ) { $rewind = 1; }\n\t\tif ( $index > 4 && empty( $state['ia_audit'] ) ) { $rewind = null === $rewind ? 4 : min( $rewind, 4 ); }\n\t\tif ( null !== $rewind ) {\n\t\t\t$state['next_step_index'] = $rewind;\n\t\t\t$state['processed_steps'] = min( (int) $state['processed_steps'], $rewind );\n\t\t\t$state['current_step'] = $this->step_label( $rewind );\n\t\t\t$state['status'] = 'running';\n\t\t}\n\t\treturn $state;\n\t}\n\n\t/** @return Gloskin_Site_Core_Editorial_Media_Bundle */\n\tprivate function editorial_media_service() {\n\t\trequire_once __DIR__ . '/class-gloskin-site-core-editorial-media-bundle.php';\n\t\treturn new Gloskin_Site_Core_Editorial_Media_Bundle( $this->plugin_file );\n\t}\n\n\t/** @return Gloskin_Site_Core_Final_IA_Normalizer */\n\tprivate function final_ia_normalizer() {\n\t\trequire_once __DIR__ . '/class-gloskin-site-core-final-ia-normalizer.php';\n\t\treturn new Gloskin_Site_Core_Final_IA_Normalizer();\n\t}\n\n\t/** @return string */\n\tprivate function detect_environment() {")

    # Local editorial resolver: strict factual identity media remains untouched.
    helpers = "plugin/gloskin-site-core/templates/parts/template-helpers.php"
    regex_replace_once(
        helpers,
        r"if \( ! function_exists\( 'gloskin_ui1_editorial_media_catalog' \) \) \{.*?\n\}\n\nif \( ! function_exists\( 'gloskin_ui1_render_hero' \) \) \{",
        TEMPLATE_EDITORIAL + "\nif ( ! function_exists( 'gloskin_ui1_render_hero' ) ) {",
    )

    write("plugin/gloskin-site-core/templates/pages/home.php", HOME)

    # Page-transition JS: replace one owner only.
    regex_replace_once(
        "plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js",
        r"\t/\* Page-to-page cross-document transition\..*?\n\tfunction init\(\) \{",
        PAGE_TRANSITION + "\n\tfunction init() {",
    )

    # Canonical transition palette, sizing and active-only will-change.
    css = "plugin/gloskin-site-core/assets/css/gloskin-ui1-loader-system.css"
    replace_once(css, "\t--gl-transition-bg:rgba(255,255,255,0.97);\n\t--gl-transition-jelly:#DE1D34;\n\t--gl-transition-duration:360ms;\n\t--gl-transition-g-size:88px;",
                      "\t--gl-transition-bg:#FFF2EB;\n\t--gl-transition-jelly:#CA050E;\n\t--gl-transition-duration:360ms;\n\t--gl-transition-g-size:104px;")
    replace_once(css, "\tanimation:gloskin-ui1-transition-blob 2.6s ease-in-out infinite;\n\twill-change:border-radius;\n}",
                      "\tanimation:gloskin-ui1-transition-blob 2.6s ease-in-out infinite;\n}\n.gloskin-ui1-page-transition.is-active .gloskin-ui1-page-transition__blob{\n\twill-change:border-radius;\n}")
    replace_once(css, "@media (prefers-reduced-motion:reduce){",
                      "@media (max-width:760px){\n\t:root{--gl-transition-g-size:84px}\n}\n\n@media (prefers-reduced-motion:reduce){")
    # Visual consistency only: existing commerce controller remains owner.
    replace_once(css, "/* Page-to-page cross-document transition overlay.",
                      ".gloskin-ui1-commerce-handoff__g{\n\tposition:absolute;\n\ttop:50%;\n\tleft:50%;\n\tz-index:2;\n\twidth:34px;\n\theight:42px;\n\tpointer-events:none;\n\ttransform:translate(-50%,-50%);\n}\n\n/* Page-to-page cross-document transition overlay.")

    shell = "plugin/gloskin-site-core/templates/shell.php"
    old = "\t\t\techo '<div class=\"gloskin-ui1-commerce-handoff__goo\"><span class=\"gloskin-ui1-commerce-handoff__blob\"></span><span class=\"gloskin-ui1-commerce-handoff__blob\"></span><span class=\"gloskin-ui1-commerce-handoff__blob\"></span><span class=\"gloskin-ui1-commerce-handoff__blob\"></span></div>';\n\t\t\techo '</div>';"
    gsvg = '<svg class="gloskin-ui1-commerce-handoff__g" viewBox="82 74 185 232" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M647 271H415V239H528V120C528 102 527 88 523 80C520 69 501 56 466 44C428 29 392 21 357 22C300 23 255 53 221 112C187 172 170 249 170 345C170 555 235 665 365 673C481 679 530 624 539 510H569V667C531 682 506 690 495 692C462 700 417 704 360 704C275 704 204 672 145 607C86 539 56 453 55 345C54 246 83 161 142 90C204 21 275 -14 360 -14C419 -14 468 -8 509 2C544 13 578 25 613 35V239H647Z" fill="#fff" transform="translate(65,300) scale(0.3117268,-0.32)"/></svg>'
    new = "\t\t\techo '<div class=\"gloskin-ui1-commerce-handoff__goo\"><span class=\"gloskin-ui1-commerce-handoff__blob\"></span><span class=\"gloskin-ui1-commerce-handoff__blob\"></span><span class=\"gloskin-ui1-commerce-handoff__blob\"></span><span class=\"gloskin-ui1-commerce-handoff__blob\"></span></div>';\n\t\t\techo '" + gsvg.replace("'", "\\'") + "';\n\t\t\techo '</div>';"
    replace_once(shell, old, new)

    # Small Home layout polish without another data owner/query.
    refresh_css = "plugin/gloskin-site-core/assets/css/gloskin-ui1-prototype-refresh.css"
    with (ROOT / refresh_css).open("a", encoding="utf-8") as fh:
        fh.write("\n/* Final Home parity: one unified discovery surface + post-testimonial brand story. */\n")
        fh.write(".gloskin-ui1-home-discovery__products{margin-top:clamp(28px,4vw,52px)}\n")
        fh.write(".gloskin-ui1-home-brand-story__grid{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(280px,.95fr);gap:clamp(28px,5vw,72px);align-items:center}\n")
        fh.write(".gloskin-ui1-home-brand-story__media{overflow:hidden;border-radius:28px;aspect-ratio:4/3;background:var(--gloskin-surface-soft)}\n")
        fh.write(".gloskin-ui1-home-brand-story__image{display:block;width:100%;height:100%;object-fit:cover}\n")
        fh.write("@media (max-width:760px){.gloskin-ui1-home-brand-story__grid{grid-template-columns:1fr}.gloskin-ui1-home-brand-story__media{border-radius:22px}}\n")

    write("tests/page-transition-contract.py", PAGE_TRANSITION_CONTRACT)
    write("tests/final-closure-contract.php", FINAL_CONTRACT)
    runtime = "tests/check-runtime.sh"
    replace_once(runtime, "php tests/final-migration-error-contract.php\n", "php tests/final-migration-error-contract.php\nphp tests/final-closure-contract.php\n")


def validate_invariants() -> None:
    kernel = read("plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php")
    plugin = read("plugin/gloskin-site-core/gloskin-site-core.php")
    migration = read("plugin/gloskin-site-core/includes/class-gloskin-site-core-revision-20260819-final-migration.php")
    if not re.search(r"const VERSION\s*=\s*'0\.7\.141'", kernel):
        raise RuntimeError("Kernel version changed")
    if "Version: 0.7.141" not in plugin:
        raise RuntimeError("Plugin version changed")
    if "const REVISION       = '2026-08-19-final';" not in migration:
        raise RuntimeError("REVISION changed")
    if "const STATE_OPTION   = 'gloskin_site_core_revision_20260819f_state';" not in migration:
        raise RuntimeError("STATE_OPTION changed")
    core = read("plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js")
    if "sessionStorage" in core[core.index("function initPageTransitions()"):core.index("\n\tfunction init()", core.index("function initPageTransitions()"))]:
        raise RuntimeError("sessionStorage returned to transition")
    if read("plugin/gloskin-site-core/templates/pages/home.php").count("data-gloskin-product-grid") != 1:
        raise RuntimeError("Home introduced duplicate product grid/query owner")


def main() -> None:
    build_editorial_bundle()
    patch_sources()
    validate_invariants()
    subprocess.run(["php", "-l", str(ROOT / "plugin/gloskin-site-core/includes/class-gloskin-site-core-final-ia-normalizer.php")], check=True)
    subprocess.run(["php", "-l", str(ROOT / "plugin/gloskin-site-core/includes/class-gloskin-site-core-editorial-media-bundle.php")], check=True)
    subprocess.run(["php", "-l", str(ROOT / "plugin/gloskin-site-core/includes/class-gloskin-site-core-revision-20260819-final-migration.php")], check=True)
    print("final closure patch applied")


if __name__ == "__main__":
    main()
