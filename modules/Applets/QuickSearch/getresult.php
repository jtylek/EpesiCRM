<?php
ob_start();
define('CID',false);
require_once('../../../include.php');
ModuleManager::load_modules();

$arrResult = array();
$sourceTable = "";
if(isset($_GET['q']) && $_GET['q'] != "") {
	$txt = trim($_GET['q']);
	$arrTxt = explode(" ", $txt);
	if(count($arrTxt) > 1){
		if(count($arrTxt) == 2){
			$qry = array(DB::Concat(DB::qstr('%'),DB::qstr($arrTxt[0]), DB::qstr('%')), DB::Concat(DB::qstr('%'),DB::qstr($arrTxt[1]), DB::qstr('%')));
		}else{
			$qry = array('0', '0');
		}
	}
	else{
		$qry = array(DB::Concat(DB::qstr('%'),DB::qstr($txt), DB::qstr('%')), DB::Concat(DB::qstr('%'),DB::qstr($txt), DB::qstr('%')));
	}	
}
else{
	return;
}

$resultContact = DB::Execute('SELECT '.DB::Concat('f_first_name',DB::qstr(' '),'f_last_name').' as name, id from contact_data_1 WHERE f_first_name '.DB::like().' '.$qry[0].' OR f_last_name '.DB::like().' '.$qry[1].'');
if($resultContact){
	$sourceTable = "contact";	
	while($row = $resultContact->FetchRow()){
		array_push($arrResult, array($row['name'], $row['id'], $sourceTable));
	}
}
/* Follow on the companies */
$resultCompany = DB::Execute('SELECT f_company_name as name, id from company_data_1 WHERE f_company_name '.DB::like().' '.$qry[0].' OR f_short_name '.DB::like().' '.$qry[1].'');
$sourceTable = "";
if($resultCompany){	
	$sourceTable = "company";
	while($row = $resultCompany->FetchRow()){		
		array_push($arrResult, array($row['name'], $row['id'], $sourceTable));
	}
}
if(is_array($arrResult) && !empty($arrResult)){
			foreach($arrResult as $rows){
				print "<tr style='background:#FFFFD5;'><td colspan='2' class='Utils_GenericBrowser__td' style='width:80%;height:20px'><img border='0' src='data/Base_Theme/templates/default/Utils/GenericBrowser/info.png'> <a onclick=\"_chj('__jump_to_RB_table=".$rows[2]."&amp;__jump_to_RB_record=".$rows[1]."&amp;__jump_to_RB_action=view', '', '');\" href=\"javascript: void(0)\">".$rows[0]."</a></td>
				<td class='Utils_GenericBrowser__td' style='width:10%'>".Utils_RecordBrowserCommon::get_caption($rows[2])."</td></tr>";
	}
}
else{
	print "<tr style='background:#FFFFD5;'><td style='background:#FFFFD5;' colspan='3'><b>".__('No Record Found')."</b></td></tr>";	
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