<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
trait Gloskin_Site_Core_Doctor_Importer_Lock_Trait {
	/** @return string */
	private function acquire_lock() {
		$token = wp_generate_uuid4();
		$now = time();
		$lock = get_option( self::LOCK_OPTION, array() );
		if ( is_array( $lock ) && ! empty( $lock['expires'] ) && absint( $lock['expires'] ) < $now ) {
			delete_option( self::LOCK_OPTION );
		}
		$created = add_option( self::LOCK_OPTION, array( 'token' => $token, 'expires' => $now + self::LOCK_TTL ), '', 'no' );
		if ( ! $created ) {
			throw new RuntimeException( __( 'Doctor migration sedang dijalankan proses lain.', 'gloskin-site-core' ) );
		}
		return $token;
	}

	/** @param string $token Lock token. @return void */
	private function release_lock( $token ) {
		$lock = get_option( self::LOCK_OPTION, array() );
		if ( is_array( $lock ) && isset( $lock['token'] ) && hash_equals( (string) $lock['token'], (string) $token ) ) {
			delete_option( self::LOCK_OPTION );
		}
	}

	/** @param array<string,mixed> $state State. @return void */
	private function save_state( $state ) {
		if ( false === get_option( self::STATE_OPTION, false ) ) {
			add_option( self::STATE_OPTION, $state, '', 'no' );
		} else {
			update_option( self::STATE_OPTION, $state, false );
		}
	}
}
