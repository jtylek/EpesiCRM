<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Custom_TutorialCommon extends ModuleCommon {

	// Sidebar menu: a "Tutorial" submenu with two leaves routed through the
	// single Custom_Tutorial instance class (see Custom_Tutorial::body()/
	// categories() in Tutorial_0.php). No module opts into the menu via a
	// registration call - declaring this static method is the whole mechanism
	// (see AI-shared/Dev-Tutorial.md §7).
	public static function menu() {
		return array(_M('Tutorial') => array(
			'__submenu__' => 1,
			_M('Records') => array(),
			_M('Categories') => array('__function__' => 'categories'),
		));
	}

	// AdminLTE sidebar/ActionBar icon - Bootstrap Icons class name, resolved
	// on demand by Base_AdminlteIcons::resolve(). Falls back to a plain gear
	// on themes/screens that don't call this.
	public static function adminlte_icon() {
		return 'bi-mortarboard-fill';
	}

	// Crits callback for the 'Manager' field's 'crm_contact' picker
	// (TutorialInstall.php) - restricts the picker to this instance's own
	// staff. Identical in shape to CRM_PhoneCallCommon::employees_crits() /
	// CRM_MeetingCommon::employees_crits() / CRM_TasksCommon::employees_crits() -
	// each module that wants an "our own employees" picker defines its own
	// copy rather than calling a shared one, so this one is no different.
	public static function employees_crits() {
		return array('(company_name'=>CRM_ContactsCommon::get_main_company(), '|related_companies'=>array(CRM_ContactsCommon::get_main_company()));
	}

	// display_callback for tutorial_category's Name field - makes the grid
	// value a link to the record instead of plain text. create_linked_text()
	// is the right helper here (as opposed to create_linked_label[_r]) since
	// the raw field value is already the exact text we want linked.
	public static function display_category_name($record, $nolink = false, $desc = null, $tab = null) {
		return Utils_RecordBrowserCommon::create_linked_text($record[$desc['id']], 'tutorial_category', $record['id'], $nolink);
	}

	// display_callback for tutorial's Title field - same reasoning as
	// display_category_name() above.
	public static function display_title($record, $nolink = false, $desc = null, $tab = null) {
		return Utils_RecordBrowserCommon::create_linked_text($record[$desc['id']], 'tutorial', $record['id'], $nolink);
	}

	// display_callback for the 'calculated' Summary field - the record's own
	// column is a throwaway 1-char placeholder (see TutorialInstall.php); the
	// value shown is computed entirely from other fields already present in
	// $record ('title'/'priority' - field ids are the lowercased, underscored
	// form of each field's declared name).
	public static function display_summary($record, $nolink = false, $desc = null, $tab = null) {
		$title = $record['title'] ?? '';
		if ($title === '') {
			return '';
		}
		$priority = $record['priority'] ?? '';
		$priority_label = $priority !== '' ? Utils_CommonDataCommon::get_value('Tutorial_Priority/' . $priority, true) : '';
		return $priority_label !== '' ? $title . ' (' . $priority_label . ')' : $title;
	}

	// Processing callback for the 'tutorial' table, registered in
	// TutorialInstall.php::install(). Populates the hidden Internal Token
	// field exactly once, when a record is first created (or cloned) -
	// 'editing' mode receives the already-stored value and leaves it alone,
	// so the token is stable across edits. See AI-shared/Dev-Tutorial.md
	// §11.5 for the full add/edit/display mode reference and a trap to know
	// about (raw record vs. form submission shape differs by mode).
	public static function submit_tutorial($values, $mode, $tab = null) {
		if (in_array($mode, array('adding', 'cloning')) && empty($values['internal_token'])) {
			$values['internal_token'] = strtoupper(bin2hex(random_bytes(8)));
		}
		return $values;
	}
}
?>
