<?php
/** Gloskin Contact settings/inbox admin owner composed from narrow internal traits. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Gloskin_Site_Core_Contact_Admin {
	const INBOX_SLUG = 'gloskin-contact-inbox';
	const INBOX_CAPABILITY = 'edit_others_posts';
	const SETTINGS_ACTION = 'gloskin_contact_settings_save';
	const SETTINGS_NONCE = 'gloskin_contact_settings_save';
	const TEST_ACTION = 'gloskin_contact_email_test';
	const TEST_NONCE = 'gloskin_contact_email_test';
	const STATUS_ACTION = 'gloskin_contact_status';
	const STATUS_NONCE = 'gloskin_contact_status';
	const DELETE_ACTION = 'gloskin_contact_delete';
	const DELETE_NONCE = 'gloskin_contact_delete';
	use Gloskin_Site_Core_Contact_Admin_Setup_Trait;
	use Gloskin_Site_Core_Contact_Admin_Render_Trait;
	use Gloskin_Site_Core_Contact_Admin_Settings_Actions_Trait;
	use Gloskin_Site_Core_Contact_Admin_Test_Trait;
	use Gloskin_Site_Core_Contact_Admin_Inbox_List_Trait;
	use Gloskin_Site_Core_Contact_Admin_Inbox_Actions_Trait;
	use Gloskin_Site_Core_Contact_Admin_Readiness_Trait;
}
