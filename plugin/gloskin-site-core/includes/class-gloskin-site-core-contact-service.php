<?php
/** First-party Gloskin Contact owner composed from narrow internal traits. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Gloskin_Site_Core_Contact_Service {
	const MESSAGE_POST_TYPE = 'gloskin_contact_message';
	const SETTINGS_OPTION = 'gloskin_site_core_contact_settings';
	const FORM_ACTION = 'gloskin_contact_submit';
	const FORM_NONCE = 'gloskin_contact_submit';
	const RATE_LIMIT = 5;
	const RATE_TTL = 900;
	const MIN_FILL_SECONDS = 3;
	use Gloskin_Site_Core_Contact_Service_Bootstrap_Trait;
	use Gloskin_Site_Core_Contact_Service_Settings_Trait;
	use Gloskin_Site_Core_Contact_Service_Form_Trait;
	use Gloskin_Site_Core_Contact_Service_Submit_Trait;
	use Gloskin_Site_Core_Contact_Service_Security_Trait;
	use Gloskin_Site_Core_Contact_Service_Persist_Trait;
	use Gloskin_Site_Core_Contact_Service_Mail_Trait;
}
