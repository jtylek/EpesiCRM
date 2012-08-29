<?php
ob_start();
define('CID',false);
require_once('../../../include.php');
ModuleManager::load_modules();

$arrResult = array();
$sourceTable = "";
if(isset($_GET['q']) && $_GET['q'] != "") {
	$txt = $_GET['q'];
	$arrTxt = explode(" ", $txt);
	if(count($arrTxt) > 1){
		$qry = array($arrTxt[0].'%', $arrTxt[1].'%');
	}
	else{
		$qry = array($txt.'%', $txt.'%');
	}	
}
else{
	return;
}

/* Search on contact first */
//DB::Concat('f_first_name','f_last_name') not sure if concat operator is allowed
$resultContact = DB::Execute('SELECT '.DB::Concat('f_first_name',DB::qstr(' '),'f_last_name').' as name, id from contact_data_1 WHERE f_first_name '.DB::like().' %s OR f_last_name '.DB::like().' %s', $qry);
if($resultContact){
	$sourceTable = "contact";	
	while($row = $resultContact->FetchRow()){
		array_push($arrResult, array($row['name'], $row['id'], $sourceTable));
	}
}
/* Follow on the companies */
$resultCompany = DB::Execute('SELECT f_company_name as name, id from company_data_1 WHERE f_company_name '.DB::like().' %s OR f_short_name '.DB::like().' %s', $qry);
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
				<td class='Utils_GenericBrowser__td' style='width:10%'>".ucfirst($rows[2])."</td></tr>";
	}
}
else{
	print "<tr style='background:#FFFFD5;'><td style='background:#FFFFD5;' colspan='3'><b>No record found.</b></td></tr>";	
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