<?php
/**
 * Pushes each user's Epesi meetings to their own Google Calendar (one-way,
 * Epesi -> Google). See AI-shared/Epesi-Google-Calendar-sync.md for the
 * design this was built from.
 * @author Claude Code
 * @copyright Janusz Tylek
 * @license MIT
 * @version 0.1
 * @package epesi-CRM
 * @subpackage GoogleCalendarSync
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_GoogleCalendarSyncInstall extends ModuleInstall {

	public function install() {
		DB::CreateTable('crm_googlecalendarsync_accounts', '
			id I4 AUTO KEY,
			epesi_user_id I4 NOTNULL,
			google_email C(255),
			access_token_enc X,
			refresh_token_enc X,
			token_expires T,
			calendar_id C(255) DEFAULT \'primary\',
			enabled I1 DEFAULT 1,
			last_synced_on T,
			last_error X,
			created_on T DEFTIMESTAMP,
			updated_on T',
			array('constraints'=>''));
		DB::CreateIndex('crm_gcs_accounts_user', 'crm_googlecalendarsync_accounts', 'epesi_user_id', array('UNIQUE'=>1));

		DB::CreateTable('crm_googlecalendarsync_map', '
			id I4 AUTO KEY,
			meeting_id I4 NOTNULL,
			epesi_user_id I4 NOTNULL,
			google_event_id C(255) NOTNULL,
			content_hash C(64) NOTNULL,
			last_synced_on T',
			array('constraints'=>''));
		DB::CreateIndex('crm_gcs_map_meeting_user', 'crm_googlecalendarsync_map', 'meeting_id,epesi_user_id', array('UNIQUE'=>1));
		DB::CreateIndex('crm_gcs_map_user', 'crm_googlecalendarsync_map', 'epesi_user_id');

		Base_AclCommon::add_permission(_M('Google Calendar Sync'), array('ACCESS:employee'));

		return true;
	}

	public function uninstall() {
		Base_AclCommon::delete_permission('Google Calendar Sync');
		DB::DropTable('crm_googlecalendarsync_map');
		DB::DropTable('crm_googlecalendarsync_accounts');
		return true;
	}

	public function version() {
		return array("0.1");
	}

	public function requires($v) {
		return array(
			array('name'=>Utils_RecordBrowserInstall::module_name(),'version'=>0),
			array('name'=>CRM_MeetingInstall::module_name(),'version'=>0),
			array('name'=>CRM_ContactsInstall::module_name(),'version'=>0),
			array('name'=>Libs_QuickFormInstall::module_name(),'version'=>0),
		);
	}

	public static function info() {
		return array(
			'Description'=>'Syncs Epesi meetings to each user\'s own Google Calendar (one-way, Epesi -> Google).',
			'Author'=>'Claude Code',
			'License'=>'MIT');
	}

	// On hold as of 2026-08-28 - OAuth scope setup proved fiddly enough in
	// practice (see README.md's "On hold" note) that this shouldn't be
	// surfaced to a fresh-install admin picking modules from the guided
	// Modules Administration & Store screen yet. Commented out rather than
	// returning false so it's obviously a deliberate pause, not "no
	// package" - is_callable() on a commented-out method is false, which
	// Setup_0.php::simple_setup() treats identically to an explicit false
	// (module skipped from that screen entirely). Advanced Setup is
	// unaffected - it lists every module via get_module_dirs() directly,
	// never checks simple_setup() - so the module stays reachable there for
	// a deliberate manual install. Uncomment once the OAuth flow is solid.
	//
	// public static function simple_setup() {
	// 	// Own top-level package (not folded into 'CRM' like most CRM_* modules) -
	// 	// this is a standalone opt-in integration, not core CRM functionality.
	// 	return array('package'=>__('Google Calendar Sync'), 'version'=>'0.1', 'icon'=>true);
	// }

}

?>
