<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * Reference module for AI-shared/Dev-Tutorial.md - wraps two
 * RecordBrowser tables registered in TutorialInstall.php. See that install
 * file for the full field-type roster, and TutorialCommon_0.php for the
 * menu()/display_callback()/processing-callback conventions used here.
 */
class Custom_Tutorial extends Module {

	// Default entry point (Records leaf in the sidebar menu).
	public function body() {
		$rb = $this->init_module(Utils_RecordBrowser::module_name(), 'tutorial', 'tutorial');
		// Preset Priority to Medium on "Add new" - only ever affects the
		// 'adding'-mode form defaults (custom_defaults), never an existing
		// record's actual stored value when editing.
		$rb->set_defaults(array('priority' => 'medium'));
		$this->display_module($rb);
	}

	// Categories leaf - the small lookup recordset the 'select'/'multiselect'
	// fields on the main table point at. Browsed alphabetically by name rather
	// than RecordBrowser's default (insertion/id order) - a lookup list like
	// this is scanned by name, not creation order.
	public function categories() {
		$rb = $this->init_module(Utils_RecordBrowser::module_name(), 'tutorial_category', 'tutorial_category');
		$rb->set_default_order(array('name' => 'ASC'));
		$this->display_module($rb);
	}

	// RecordBrowser addon tab, registered on 'tutorial_category' in
	// TutorialInstall.php::install() via Utils_RecordBrowserCommon::new_addon().
	// Called by Utils_RecordBrowser::view_entry() as
	// $this->display_module($addon_module, array($record, $rb_parent), $func) -
	// $category is the category record currently being viewed, $rb_parent is
	// the Utils_RecordBrowser instance displaying it (unused here, but part
	// of the fixed calling convention every addon method receives).
	public function category_records_addon($category, $rb_parent) {
		$rb = $this->init_module(Utils_RecordBrowser::module_name(), 'tutorial');
		// Pre-fill Category on "Add new" clicked from within this addon - same
		// pattern as e.g. CRM_PhoneCall_0.php::body() pre-filling 'employees'
		// via set_defaults() before display_module(). custom_defaults (what
		// this sets) is exactly what feeds the 'adding'-mode form defaults.
		$rb->set_defaults(array('category' => $category['id'], 'priority' => 'medium'));
		$args = array(
			array('category' => $category['id']),  // crits: only this category's records
			array('category' => false),             // cols: hide the now-constant Category column
			array('title' => 'ASC'),                // order
		);
		$this->display_module($rb, $args, 'show_data');
	}
}
?>
