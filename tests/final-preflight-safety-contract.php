<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$state = file_get_contents( $root . '/plugin/gloskin-site-core/includes/gloskin-site-core-doctor-importer-state-trait.php' );
$validator = file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-final-package-validator.php' );
$final = file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-revision-20260819-final-migration.php' );
$batch = file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-production-batch.php' );
$kernel = file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php' );
$plugin = file_get_contents( $root . '/plugin/gloskin-site-core/gloskin-site-core.php' );

function fp_ok( bool $condition, string $message ): void {
	if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
	echo "ok: {$message}\n";
}

foreach ( array( $state, $validator, $final, $batch, $kernel, $plugin ) as $src ) {
	fp_ok( false !== $src, 'required source readable' );
}

$validate_pos = strpos( $state, '$payload = $this->bundle->load(); // A. gloskin-doctors-v1.' );
$bc_pos = strpos( $state, 'validate_after_roster_bundle(); // B then C.' );
$lock_pos = strpos( $state, '$token   = $this->acquire_lock();' );
$upsert_pos = strpos( $state, '$doctor_id = $this->upsert_doctor' );
fp_ok( false !== $validate_pos && false !== $bc_pos && false !== $lock_pos && false !== $upsert_pos && $validate_pos < $bc_pos && $bc_pos < $lock_pos && $lock_pos < $upsert_pos, 'Final Migration roster path validates A -> B -> C before lock/upsert' );
fp_ok( false !== strpos( $state, "class_exists( 'Gloskin_Site_Core_Revision_20260819_Final_Migration', false )" ), 'cross-package validation is scoped to loaded Final Migration ownership' );
fp_ok( (bool) preg_match( "/const PHOTO_BUNDLE_ID\\s*=\\s*'gloskin-doctor-photos-v2'/", $validator ) && (bool) preg_match( '/const PHOTO_EXPECTED\s*=\s*12/', $validator ), 'photo validator owns exact immutable bundle/count' );
fp_ok( false !== strpos( $validator, "const PHOTO_BUNDLE_REVISION = '2026-08-19-remastered';" ), 'doctor photo bundle revision remains exact and validated' );
fp_ok( false !== strpos( $validator, 'is_readable( $manifest_path )' ) && false !== strpos( $validator, "hash_file( 'sha256', $path )" ) && false !== strpos( $validator, 'basename( $file ) !== $file' ), 'photo validation proves manifest/readability/path/SHA before mutation' );
fp_ok( false !== strpos( $validator, "'image/webp' !==" ) && false !== strpos( $validator, 'filesize( $path )' ) && false !== strpos( $validator, 'getimagesize( $path )' ), 'photo validation proves primary bytes/dimensions/mime' );
fp_ok( false !== strpos( $validator, 'Gloskin_Site_Core_Editorial_Media_Bundle' ) && false !== strpos( $validator, '->preflight()' ), 'editorial validation reuses canonical dimensions/mime/provenance preflight' );

$advance = strpos( $final, 'private function advance_doctor_roster()' );
$continue = false !== $advance ? strpos( $final, "(int) \$before['index'] > 0", $advance ) : false;
$failed = false !== $advance ? strpos( $final, "array( 'running', 'failed', 'verifying' )", $advance ) : false;
fp_ok( false !== $advance && false !== $continue && false !== $failed, 'partial roster state selects Continue and preserves existing cursor' );
fp_ok( false !== strpos( $final, "const REVISION       = '2026-08-19-final';" ), 'REVISION unchanged' );
fp_ok( false !== strpos( $final, "const STATE_OPTION   = 'gloskin_site_core_revision_20260819f_state';" ), 'STATE_OPTION unchanged' );
fp_ok( false !== strpos( $final, 'start is a handshake only; persisted checkpoint/cursor/audit remain authoritative.' ), 'Final Migration start handshake does not reset persisted state' );

$dead = $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-doctor-migration-admin.php';
fp_ok( ! file_exists( $dead ), 'legacy doctor migration admin source retired' );
fp_ok( false === strpos( $batch, 'Gloskin_Site_Core_Doctor_Migration_Admin' ) && false === strpos( $batch, 'class-gloskin-site-core-doctor-migration-admin.php' ), 'production batch has zero old-admin runtime consumer' );
fp_ok( false !== strpos( $batch, 'class-gloskin-site-core-doctor-importer.php' ), 'doctor importer remains available for Final Migration compatibility' );
fp_ok( false !== strpos( $kernel, "const VERSION = '0.7.143';" ) && false !== strpos( $plugin, 'Version: 0.7.143' ), 'production behavior bump is 0.7.143' );

echo "final-preflight-safety-contract.php: OK\n";
