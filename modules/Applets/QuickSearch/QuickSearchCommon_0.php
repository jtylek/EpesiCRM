<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_QuickSearchCommon extends ModuleCommon{

	private $name = "";
	
	public static function applet_caption() {
    	return __('Quick Search');

	}

	 public static function admin_caption() {
		return array('label'=>__('Quick Search'), 'section'=>__('Features Configuration'));
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
	
	public static function getQuickSearch(){
		$qry = DB::GetRow("select * from quick_search where search_status = '1' limit 0, 1");
		if($qry) 
			return array('search_alias_name' => $qry[1], 'search_placeholder'=>$qry[4], 'search_id'=>$qry[0]); 
		else
			return false;
	}
	
	public static function getQuickSearchById($search_id){
		$qryId = DB::GetRow("select * from quick_search where search_id = ".$search_id."");
		if($qryId)
			return array('search_fields' => $qryId[4], 'format' => $qryId[6]);
		else
			return false;
	}
	
	public static function QFfield_recordsets(&$form, $field, $label, $mode, $default, $desc, $rb_obj){
        load_js('modules/Applets/QuickSearch/js/quicksearch.js');
        eval_js('call_js('.$fields.');');	
		
		$data = self::get_recordsets();
			ksort($data);
			$recordset_form = $form->addElement('multiselect', $field, $label, $data);
			$recordset_form->on_add_js('call_js();');
			$recordset_form->on_remove_js('call_js();');

	}

	public static function QFfield_recordfields(&$form, $field, $label, $mode, $default, $desc, $rb_obj){
		if($mode != 'view'){
			$recordset_form = $form->addElement('multiselect', $field, $label, null);
			//$recordset_form->on_add_js('call_js();');
			//$recordset_form->on_remove_js('call_js();');
		}
	}
	
	public function get_recordsets(){
		$options = array();
		$rb_tabs = DB::GetAssoc('SELECT tab, tpl FROM recordbrowser_table_properties');
		if($rb_tabs){
			foreach ($rb_tabs as $key => $value){
				$options[$key] =  Utils_RecordBrowserCommon::get_caption($key);
			}
		}
		return $options;
	}
	
	public static function display_recordsets($rb, $nolink){
		//load_js('modules/Applets/QuickSearch/js/quicksearch.js');
        //eval_js('call_js('.$rb.');');
	}
	
	public static function display_recordfields($rb, $nolink){
		return print_r($rb);
	}	

}

?>