<?php
/**
 * Kernel-owned module for Shop discovery, native Contact operations and final-migration dependencies.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Gloskin_Site_Core_Production_Batch {
	/** @var array<int,object> */
	private static $services = array();

	/** @var bool */
	private static $booted = false;

	/** @param string $plugin_file Main plugin file. @return void */
	public static function boot( $plugin_file ) {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		require_once __DIR__ . '/class-gloskin-site-core-contact-mailer.php';
		foreach ( array( 'bootstrap', 'settings', 'form', 'submit', 'security', 'persist', 'mail' ) as $part ) {
			require_once __DIR__ . '/gloskin-site-core-contact-service-' . $part . '-trait.php';
		}
		require_once __DIR__ . '/class-gloskin-site-core-contact-service.php';

		$contact = new Gloskin_Site_Core_Contact_Service( $plugin_file );
		$contact->register();
		self::$services[] = $contact;

		if ( ! is_admin() ) {
			require_once __DIR__ . '/class-gloskin-site-core-woocommerce-adapter-shop-catalog.php';
			foreach ( array( 'route', 'rest', 'query' ) as $part ) { require_once __DIR__ . '/gloskin-site-core-shop-discovery-' . $part . '-trait.php'; }
			require_once __DIR__ . '/class-gloskin-site-core-shop-discovery.php';
			$shop = new Gloskin_Site_Core_Shop_Discovery( $plugin_file );
			$shop->register();
			self::$services[] = $shop;
		}

		if ( is_admin() ) {
			foreach ( array( 'setup', 'render', 'settings-actions', 'test', 'inbox-list', 'inbox-actions', 'readiness' ) as $part ) {
				require_once __DIR__ . '/gloskin-site-core-contact-admin-' . $part . '-trait.php';
			}
			require_once __DIR__ . '/class-gloskin-site-core-contact-admin.php';
			require_once __DIR__ . '/class-gloskin-site-core-doctor-bundle.php';
			foreach ( array( 'state', 'upsert', 'finalize', 'lock' ) as $part ) { require_once __DIR__ . '/gloskin-site-core-doctor-importer-' . $part . '-trait.php'; }
			require_once __DIR__ . '/class-gloskin-site-core-doctor-importer.php';
			/* Doctor roster importer remains available for Final Migration ownership.
		 * No second doctor-migration admin action is registered here. */

			$contact_admin = new Gloskin_Site_Core_Contact_Admin( $plugin_file );
			$contact_admin->register();
			self::$services[] = $contact_admin;

		}
	}
}
