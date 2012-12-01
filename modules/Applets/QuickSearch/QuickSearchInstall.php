<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_QuickSearchInstall extends ModuleInstall{

	public function install() {
		$ret = true;
		$recordsetName = "quick_search";
		$fields = array(
					array('name'=>__('Preset name'), 
							'type'=>'text', 
							'param'=>'255', 
							'visible'=>true, 
							'required'=>true),
							
					array('name'=>__('Placeholder'), 'type'=>'text', 'param'=>'255', 'visible'=>true, 'required'=>true),
					
					array('name' => __('Recordsets'),
							'type'=>'long text', 
							'QFfield_callback'=>array('Applets_QuickSearchCommon', 'QFfield_recordsets'), 
							'display_callback'=>array('Applets_QuickSearchCommon', 'display_recordsets'), 
							'required'=>true, 
							'extra'=>false, 
							'visible'=>true),
						
					array('name' => __('Select field'),
							'type'=>'long text', 
							'QFfield_callback'=>array('Applets_QuickSearchCommon', 'QFfield_recordfields'), 
							'display_callback'=>array('Applets_QuickSearchCommon', 'display_recordfields'), 
							'required'=>true, 
							'extra'=>false, 
							'visible'=>true),	
							
					array('name'=>__('Result Format'), 
							'type'=>'long text', 
							'param'=>'255', 
							'required'=>true, 
							'visible'=>true)			
				);				
		Utils_RecordBrowserCommon::install_new_recordset($recordsetName,$fields);	
		Utils_RecordBrowserCommon::set_caption($recordsetName, __('Quick Search'));
		Utils_RecordBrowserCommon::set_favorites($recordsetName, false);
		Utils_RecordBrowserCommon::register_processing_callback($recordsetName, array('Applets_QuickSearchCommon', 'parse_values'));

		Utils_RecordBrowserCommon::add_access($recordsetName, 'view', 'ACCESS:employee', array('(!permission'=>2, '|employees'=>'USER'));
		Utils_RecordBrowserCommon::add_access($recordsetName, 'add', 'ACCESS:employee');
		Utils_RecordBrowserCommon::add_access($recordsetName, 'edit', 'ACCESS:employee', array('(permission'=>0, '|employees'=>'USER', '|customers'=>'USER'));
		Utils_RecordBrowserCommon::add_access($recordsetName, 'delete', 'ACCESS:employee', array(':Created_by'=>'USER_ID'));
		Utils_RecordBrowserCommon::add_access($recordsetName, 'delete', array('ACCESS:employee','ACCESS:manager'));
	
		return $ret;
	}

	public function uninstall() {
		$ret = true;
		Utils_RecordBrowserCommon::uninstall_recordset('quick_search');
		Utils_RecordBrowserCommon::unregister_processing_callback($recordsetName, array('Applets_QuickSearchCommon', 'parse_values'));

		return $ret;
	}
	public function version() {
		return array("1.0");
	}

	public static function simple_setup() {
		return array('package'=>__('EPESI Core'), 'option'=>__('Additional applets'));
	}

	public function requires($v) {
		return array(
			array('name'=>'Utils/RecordBrowser', 'version'=>0),
			array('name'=>'Base/Acl','version'=>0),
			array('name'=>'Base/User','version'=>0),
			array('name'=>'Utils/BBCode', 'version'=>0), 
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Libs/QuickForm','version'=>0),
			array('name'=>'Base/Theme','version'=>0),
			array('name'=>'Base/Error','version'=>0),
			array('name'=>'Base/Dashboard','version'=>0));
	}

	public static function info() {
		$html="Use for quick search on contacts and companies";
		return array(
			'Description'=>$html,
			'Author'=>'bistmaster@hotmail.com',
			'License'=>'MIT');
	}	
}

?>