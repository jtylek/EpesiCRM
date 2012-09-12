<?php
ob_start();
define('CID',false);
require_once('../../../include.php');
ModuleManager::load_modules();

$arrResult = array();
$sourceTable = "";
$num_rows = 20;
$num_offset = 0;
$arrTxt = array();
$sqlLikeContact = "";
$sqlLikeCompany = "";
if((isset($_GET['q']) && $_GET['q'] != "")  && (isset($_GET['crit']) && $_GET['crit'] != "")) {
	$txt = trim(urldecode($_GET['q']));
	$crit = trim(urldecode($_GET['crit']));
	$arrTxt = explode(" ", $txt);
	$countArray = (is_array($arrTxt)) ? count($arrTxt) : 0;
	if($countArray > 0){
		switch(strtoupper($crit)){
			case "PHONE":
				$sqlLikeContact = constructLikeSQL($arrTxt, 'f_work_phone' , 'f_mobile_phone');
				$sqlLikeCompany = constructLikeSQL($arrTxt, 'f_phone' , 'f_fax');		
				break;
			case "EMAIL":
				$sqlLikeContact = constructLikeSQL($arrTxt, 'f_email' , 'f_email');
				$sqlLikeCompany = constructLikeSQL($arrTxt, 'f_email' , 'f_email');				
				break;
			case "CITY":
				$sqlLikeContact = constructLikeSQL($arrTxt, 'f_city' , 'f_city');
				$sqlLikeCompany = constructLikeSQL($arrTxt, 'f_city' , 'f_city');			
				break;
			case "NAMES":
				$sqlLikeContact = constructLikeSQL($arrTxt, 'f_first_name' , 'f_last_name');
				$sqlLikeCompany = constructLikeSQL($arrTxt, 'f_company_name' , 'f_short_name');			
				break;
			default:	
				$sqlLikeContact = constructLikeSQL($arrTxt, 'f_first_name' , 'f_last_name');
				$sqlLikeCompany = constructLikeSQL($arrTxt, 'f_company_name' , 'f_short_name');			
				break;		
		}		
		$sqlContact = 'SELECT '.DB::Concat('f_first_name',DB::qstr(' '),'f_last_name').' as name, id from contact_data_1 WHERE '.$sqlLikeContact.' ORDER by name ASC';
		$sqlCompany = 'SELECT f_company_name as name, id from company_data_1 WHERE '.$sqlLikeCompany.' ORDER by name ASC'; 
	}
	else{
		return;
	}
}
else{
	return;
}
/* Mark as bold when search result is found */
function matchResult($search, $replace, $query){
	$result = "";
	if($query != null)
		$result = preg_replace('/'.strtolower($search).'/', '<b>'.$replace.'</b>', strtolower($query));
	return $result;
}

function constructLikeSQL($arrayQry = array(), $field1, $field2){
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

$resultContact = DB::SelectLimit($sqlContact, $num_rows, $num_offset);
if($resultContact){
	$sourceTable = "contact";	
	while($row = $resultContact->FetchRow()){
		$appendName = matchResult($arrTxt[0], $arrTxt[0], $row['name']);
		array_push($arrResult, array($appendName, $row['id'], $sourceTable));
	}
}
/* Follow on the companies */
$resultCompany = DB::SelectLimit($sqlCompany, $num_rows, $num_offset);
$sourceTable = "";
if($resultCompany){	
	$sourceTable = "company";
	while($row = $resultCompany->FetchRow()){
		$appendName = matchResult($arrTxt[0], $arrTxt[0], $row['name']);
		array_push($arrResult, array($appendName, $row['id'], $sourceTable));
	}
}
if(is_array($arrResult) && !empty($arrResult)){
			foreach($arrResult as $rows){
				print "<tr style='background:#FFFFD5;'><td colspan='2' class='Utils_GenericBrowser__td' style='width:80%;height:20px'><img border='0' src='data/Base_Theme/templates/default/Utils/GenericBrowser/info.png'> <a onclick=\"_chj('__jump_to_RB_table=".$rows[2]."&amp;__jump_to_RB_record=".$rows[1]."&amp;__jump_to_RB_action=view', '', '');\" href=\"javascript: void(0)\">".ucwords($rows[0])."</a></td>
				<td class='Utils_GenericBrowser__td' style='width:10%'>".Utils_RecordBrowserCommon::get_caption($rows[2])."</td></tr>";
	}
}
else{
	print "<tr style='background:#FFFFD5;'><td style='background:#FFFFD5;' colspan='3' align='center'>".__('No records found')."</td></tr>";	
}

$content = ob_get_contents();
ob_end_clean();

require_once('libs/minify/HTTP/Encoder.php');
$he = new HTTP_Encoder(array('content' => $content));
if (MINIFY_ENCODE)
	$he->encode();
$he->sendAll();
exit();

?>