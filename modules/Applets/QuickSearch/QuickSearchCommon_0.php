<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_QuickSearchCommon extends ModuleCommon{

	private $name = "";
	
	public static function applet_caption() {
    	return __('Quick Search');

	}

	 public static function admin_caption() {
		return array('label'=>__('QuickSearch'), 'section'=>__('Features Configuration'));
    }
	
	public static function applet_info() {
    	return __('Quick Search'); //here can be associative array
	}
	
	public static function applet_settings(){
		return array( array('name' => 'criteria', 'label' => __('Criteria'), 
					'type' => 'select', 'values' =>array('Names'=>'Names (First, Last, Company and Short)','Email'=>'Email', 'City'=>'City', 'Phone'=>'Phone'), 'default'=>'Names')
					);
	}
	
	public static function matchResult($search, $replace, $query){
		$result = "";
		if($query != null)
			$result = preg_replace('/'.strtolower($search).'/', '<b>'.$replace.'</b>', strtolower($query));
		return $result;
	}	
	
	public static function constructLikeSQL($arrayQry = array(), $field1, $field2){
		$sql = '';
		$count = count($arrayQry);
		if(!is_array($arrayQry)){
			return;
		}
		
		$inc = 0;
		foreach($arrayQry as $qry){
			$inc++;		
			if($inc == $count){
				$sql .= ' ('.$field1.' '.DB::like().' '.DB::Concat(DB::qstr('%'),DB::qstr($qry), DB::qstr('%')).' OR '.$field2.' '.DB::like().' '.DB::Concat(DB::qstr('%'),DB::qstr($qry), DB::qstr('%')).')';				
			}
			else{
				$sql .= ' ('.$field1.' '.DB::like().' '.DB::Concat(DB::qstr('%'),DB::qstr($qry), DB::qstr('%')).' OR '.$field2.' '.DB::like().' '.DB::Concat(DB::qstr('%'),DB::qstr($qry), DB::qstr('%')).') OR';
			}	
		}
		return $sql;
	}	
	
}

?>