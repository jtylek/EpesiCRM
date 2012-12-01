<?php
ob_start();
define('CID',false);
require_once('../../../include.php');
ModuleManager::load_modules();

if(isset($_GET['crit']) && $_GET['crit'] != "" && isset($_GET['q']) && $_GET['q'] != ""){
$arrResult = array();
$id = $_GET['crit'];
$txt = trim(urldecode($_GET['q']));
$arrTxt = explode(" ", $txt);
$stmt = "";
$arrayQuick = Applets_QuickSearchCommon::getRecordsetAndFields($id);
$format = Applets_QuickSearchCommon::getResultFormat();
print $format."<br>";
$array_keys = array_keys($arrayQuick);
	foreach($array_keys as $key){
		 $sql = substr("select * from ".$key."_data_1 where ". Applets_QuickSearchCommon::constructLikeSQL($arrTxt, $arrayQuick[$key]), 0 , -3);
		 $qry = DB::SelectLimit($sql, 20, 0);	
		 if($qry){
			while($rowValue = $qry->FetchRow()){
				$arrResult[$key] = $rowValue;
			}
		 }
	}
	$array2 = array_values($arrResult);
	print_r($array2[0]);
	//$keyFound = array_search("Bethoveen", array_values($arrResult)[]);
	//print $keyFound;
	//print_r($arrResult[$keyFound]);
	/*if(is_array($arrResult) && !empty($arrResult)){
			foreach($arrResult as $rows){
				print "<tr style='background:#FFFFD5;'><td colspan='2' class='Utils_GenericBrowser__td' style='width:80%;height:20px'><img border='0' src='data/Base_Theme/templates/default/Utils/GenericBrowser/info.png'> <a onclick=\"_chj('__jump_to_RB_table=".$rows[0]."&amp;__jump_to_RB_record=".$rows[0]."&amp;__jump_to_RB_action=view', '', '');\" href=\"javascript: void(0)\">".ucwords($rows[0])."</a></td>
				<td class='Utils_GenericBrowser__td' style='width:10%'>".Utils_RecordBrowserCommon::get_caption($rows[1])."</td></tr>";
			}	
	}*/
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