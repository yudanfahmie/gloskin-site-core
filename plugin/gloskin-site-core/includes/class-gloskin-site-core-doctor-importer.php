<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Gloskin_Site_Core_Doctor_Importer {
	const STATE_OPTION = 'gloskin_site_core_doctor_migration_v1';
	const LOCK_OPTION = 'gloskin_site_core_doctor_migration_v1_lock';
	const LOCK_TTL = 120;
	const SOURCE_META = '_gloskin_doctor_source_id';
	const BUNDLE_META = '_gloskin_doctor_bundle_id';
	const SOURCE_URL_META = '_gloskin_doctor_source_url';
	const SOURCE_CHECKED_META = '_gloskin_doctor_source_checked_at';
	use Gloskin_Site_Core_Doctor_Importer_State_Trait;
	use Gloskin_Site_Core_Doctor_Importer_Upsert_Trait;
	use Gloskin_Site_Core_Doctor_Importer_Finalize_Trait;
	use Gloskin_Site_Core_Doctor_Importer_Lock_Trait;
}
