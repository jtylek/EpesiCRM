<?php
/**
 * RecordBrowser install class.
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage RecordBrowser
 */

defined("_VALID_ACCESS") || die();

class Utils_RecordBrowserInstall extends ModuleInstall {
	public function install() {
		$this->create_data_dir();
		
		Base_ThemeCommon::install_default_theme(Utils_RecordBrowserInstall::module_name());
		DB::CreateTable('recordbrowser_table_properties',
						'id I2 AUTO KEY,'.
						'tab C(64),'.
						'quickjump C(64) DEFAULT \'\','.
						'tpl C(255) DEFAULT \'\','.
						'favorites I1 DEFAULT 0,'.
						'recent I2 DEFAULT 0,'.
						'full_history I1 DEFAULT 1,'.
						'caption C(32) DEFAULT \'\','.
						'icon C(255) DEFAULT \'\','.
						'description_callback C(128) DEFAULT \'\','.
                        'jump_to_id I1 DEFAULT 1,'.
                        'search_include I1 DEFAULT 0,'.
                        'search_priority I1 DEFAULT 0,'.
                        'printer C(255) DEFAULT \'\'',
						array('constraints'=>', UNIQUE(tab)'));
		DB::CreateTable('recordbrowser_datatype',
						'type C(32) KEY,'.
						'module C(64),'.
						'func C(128)',
						array('constraints'=>''));
		DB::CreateTable('recordbrowser_addon',
					'tab C(64),'.
					'module C(128),'.
					'func C(128),'.
					'pos I,'.
					'enabled I1,'.
					'label C(128)',
					array('constraints'=>', PRIMARY KEY(tab, module, func)'));
		DB::CreateTable('recordbrowser_browse_mode_definitions',
					'tab C(64),'.
					'module C(128),'.
					'func C(128)',
					array('constraints'=>', PRIMARY KEY(tab, module, func)'));
		DB::CreateTable('recordbrowser_processing_methods',
					'tab C(64),'.
					'func C(255)',
					array('constraints'=>', PRIMARY KEY(tab, func)'));
		DB::CreateTable('recordbrowser_access_methods',
						'tab C(64),'.
						'func C(255),'.
						'priority I DEFAULT 10',
						array('constraints'=>', PRIMARY KEY(tab, func)'));
		DB::CreateTable('recordbrowser_clipboard_pattern', 'tab C(64) KEY, pattern X, enabled I4');

		DB::CreateTable('recordbrowser_words_index', 'id I AUTO KEY,word C(3)',
					array('constraints'=>', UNIQUE(word)'));
		DB::CreateTable('recordbrowser_words_map', 'word_id I, tab_id I2, record_id I, field_id I2, position I',
					array('constraints'=>', FOREIGN KEY (word_id) REFERENCES recordbrowser_words_index(id) ON DELETE CASCADE ON UPDATE CASCADE, FOREIGN KEY (tab_id) REFERENCES recordbrowser_table_properties(id) ON DELETE CASCADE ON UPDATE CASCADE'));
		DB::CreateIndex('rb_words_map__word_idx','recordbrowser_words_map','word_id');
		DB::CreateIndex('rb_words_map__tab_idx','recordbrowser_words_map','tab_id');
		DB::CreateIndex('rb_words_map__record_tab_idx','recordbrowser_words_map','record_id,tab_id');
		Base_PrintCommon::register_printer(new Utils_RecordBrowser_RecordPrinter());
		return true;
	}
	
	public function uninstall() {
        DB::DropTable('recordbrowser_clipboard_pattern');
		DB::DropTable('recordbrowser_browse_mode_definitions');
		DB::DropTable('recordbrowser_addon');
		DB::DropTable('recordbrowser_table_properties');
		DB::DropTable('recordbrowser_datatype');
		DB::DropTable('recordbrowser_access_methods');
        Base_PrintCommon::unregister_printer('Utils_RecordBrowser_RecordPrinter');
		Base_ThemeCommon::uninstall_default_theme(Utils_RecordBrowserInstall::module_name());
		return true;
	}
	
	public function requires($v) {
		return array(
			array('name'=>Utils_CommonDataInstall::module_name(), 'version'=>0),
			array('name'=>Utils_CurrencyFieldInstall::module_name(), 'version'=>0),
			array('name'=>Utils_ShortcutInstall::module_name(), 'version'=>0),
			array('name'=>Utils_BBCodeInstall::module_name(), 'version'=>0),
			array('name'=>Utils_TooltipInstall::module_name(), 'version'=>0),
			array('name'=>Utils_RecordBrowser_FiltersInstall::module_name(), 'version'=>0),
			array('name'=>Utils_RecordBrowser_RecordPickerFSInstall::module_name(), 'version'=>0),
			array('name'=>Utils_RecordBrowser_RecordPickerInstall::module_name(), 'version'=>0),
			array('name'=>Utils_GenericBrowserInstall::module_name(), 'version'=>0),
			array('name'=>Utils_TabbedBrowserInstall::module_name(), 'version'=>0),
			array('name'=>Utils_WatchdogInstall::module_name(), 'version'=>0),
			array('name'=>Base_User_LoginInstall::module_name(), 'version'=>0),
			array('name'=>Base_UserInstall::module_name(), 'version'=>0),
			array('name'=>Utils_QueryBuilderInstall::module_name(), 'version'=>0)
		);
	}
	
	public static function info() {
		return array('Author'=>'<a href="mailto:abisaga@telaxus.com">Arkadiusz Bisaga</a> (<a href="http://www.telaxus.com">Telaxus LLC</a>)', 'License'=>'TL', 'Description'=>'Module to browse and modify records.');
	}
	
	public function simple_setup() {
		return __('EPESI Core');
	}
	
	public function version() {
		return array('2.0');
	}
	
}

?>
