<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_QuickSearchCommon extends ModuleCommon{

	private $recordsetsArray = null;
	
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
		$data = self::get_recordsets();	
		//print "<br>MODE on QFfield_recordsets == ". $mode; 
		if($mode == 'add'){
			ksort($data);
			eval_js('call_js()');
			$recordset_form = $form->addElement('multiselect', $field, $label, $data);
			$recordset_form->on_add_js('call_js();');
			$recordset_form->on_remove_js('call_js();');
		}
		else if($mode == 'edit' || $mode == 'view'){
			$recordset_form = $form->addElement('multiselect', $field, $label, $data);
			$form->setDefaults(array($field=>self::parse_array($default)));		
		}
	}

	public static function QFfield_recordfields(&$form, $field, $label, $mode, $default, $desc, $rb_obj){
		//print "<br>MODE on QFfield_recordfields == ". $mode; 
		if($mode == 'add'){
			$recordset_form = $form->addElement('multiselect', $field, $label, null);
		}
		else if($mode == 'edit' || $mode == 'view'){
			$arrayAllValues = array();
			$dataField = self::getRecordsetsOnly($default);
			foreach($dataField as $tbName){			
				$arrayFields = Utils_RecordBrowserCommon::init($tbName);
				foreach($arrayFields as $key => $value){
					$arrayAllValues[$tbName.":".$value['id']] = Utils_RecordBrowserCommon::get_caption($tbName)." - ".$value['name'];
					
				}
			}		
			$recordset_form = $form->addElement('multiselect', $field, $label, $arrayAllValues);
			$form->setDefaults(array($field => $default));
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
		$strRecordsets = self::arrayToString($rb['recordsets']);
		return $strRecordsets;
	}
	
	public static function display_recordfields($rb, $nolink){
		$strFields = self::arrayToString($rb['select_field']);
		return $strFields;
	}	
	
	public static function parse_values($values, $mode){
		//print "MODE ===== ". $mode;
		switch($mode){
			case 'adding':
			case 'editing':
				$values['recordsets'] = explode(';', $values['recordsets']);
				$values['select_field'] = explode(';', $values['select_field']);;
				break;
			case 'add':
			case 'edit':
				$values['recordsets'] = implode(';', $values['recordsets']);
				$values['select_field'] = implode(';', $values['select_field']);
				break;				
			case 'display':
				$values = "display";
				break;
			case 'view':
				$values['recordsets'] = explode(';', $values['recordsets']);
				$values['select_field'] = explode(';', $values['select_field']);
				break;	
			default:	
				break;
		}
		return $values;
	}
	
	public function arrayToString($arr){		
		$strArray = explode(";",$arr);
		$strFinalArray = "";
		foreach($strArray as $str){
			if(stripos($str, "[A]") !== false){
				$strFinal[] = substr($str, 0 , -3);
			}
			else{
				$strFinal[] = $str;	
			}
		}
		$strFinal = implode(';', $strFinal);
		
		return $strFinal;		
	}
	
	public function stringToArray($str){
		$arrRecordsets = array();
		if($str != ""){
			$arrRecordset = explode(";", $str);
			$arrRecordsets = self::parse_array($arrRecordset);
		}
		return $arrRecordsets;
	}
	
	// array($tbName => array("field1", "field2", "field3"))
	public static function search_query($qry = array(), $values){
		if(is_array($qry)){
				
		}
	}
	
	public static function getQueryById($id){
	
	}
	// fields = company:address;company:f_name;contacts:last_name;contacts:phone
	public static function parse_recordset($recordset, $fieldset){
		$recordsetArray = explode(";", $recordset);		
		$arrayRecordsetAndFields = array();
		foreach($recordsetArray as $recordsetName){
				$recordsetName = self::getRecordsetNameString($recordsetName);
				$array_fields = self::parse_fields($recordsetName, $fieldset);
				$arrayRecordsetAndFields[] = array($recordsetName => $array_fields);
		}
		return $arrayRecordsetAndFields;
	}
	
	public static function parse_fields($recordset,$fields){
		$fieldArray = explode(";", $fields);
		$getFieldArray = array();
		foreach($fieldArray as $fieldName){
			$getRecordsetName = self::getRecordsetNameString($fieldName);
			$getFieldName = self::getFieldNameString($fieldName);
			if($getRecordsetName == $recordset){
					$getFieldArray[] = $getFieldName;
			}
		}
		return $getFieldArray;		
	}
	
	public function getRecordsetsOnly($arrayField){
		$arrayRecordsetList = array();
		if(is_array($arrayField)){
			foreach($arrayField as $fieldName){
				$recordsetName = self::getRecordsetNameString($fieldName);
				if(!in_array($recordsetName, $arrayRecordsetList)){
					$arrayRecordsetList[] = $recordsetName;
				}
			}
		}
		return $arrayRecordsetList;
	}
	
	public function getRecordsetNameString($string){
		if($string != ""){ 
			if(stripos($string, "[A]") !== false)
				return substr($string, 0, stripos($string, "[A]"));
			else
				return substr($string, 0, strpos($string, ':'));
		}
		else{
			return "";
		}
	}
	
	public function getFieldNameString($string){
		if($string != ""){ 
			return substr($string, strpos($string, ':') + 1, strlen($string));
		}
		else{
			return "";
		}	
	}
	
	public function parse_array($arr){
		$arrRecordsets = array();
		if(is_array($arr)){
			foreach($arr as $recordset){
				if(stripos($recordset, "[A]") !== false){
					$arrRecordsets[] = substr($recordset, 0 , -3);
				}
				else{
					$arrRecordsets[] = $recordset;	
				}
			}	
		}
		return $arrRecordsets;
	}
	
	public static function getIdOnActiveQuickSearch(){
		$qry = DB::GetRow("select id from quick_search_data_1 where f_status = 1 and active = 1");
		if($qry){
			return (int) $qry[0]; 
		}else{
			return false;
		}
	}
}

?>