<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_QuickSearchCommon extends ModuleCommon{

	private $name = "";
	
	public static function applet_caption() {
    	return __('Quick Search');

	}

	public static function applet_info() {
    	return __('Quick Search'); //here can be associative array
	}
	
	public static function applet_settings(){
		return array( array('name' => 'criteria', 'label' => __('Criteria'), 
					'type' => 'select', 'values' =>array('Names'=>'Names (First, Last, Company and Short)','Email'=>'Email', 'City'=>'City', 'Phone'=>'Phone'), 'default'=>'Names')
					);
	}
	
}

?>