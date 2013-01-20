<?php
ob_start();
define('CID',false);
require_once('../../../include.php');
ModuleManager::load_modules();

if(!Base_AclCommon::is_user()){
	return;
}
if(isset($_GET['crit']) && $_GET['crit'] != "" && isset($_GET['q']) && $_GET['q'] != ""){
$arrResult = array();
$id = $_GET['crit'];
$txt = trim(urldecode($_GET['q']));
$arrTxt = explode(" ", $txt);
$arrayQuick = Applets_QuickSearchCommon::getRecordsetAndFields($id);
$format = Applets_QuickSearchCommon::getResultFormat();
$formatString = Applets_QuickSearchCommon::parseFormatString($format);
$arrayKey = array(); 
$array_keys = array_keys($arrayQuick);
	foreach($array_keys as $key){
		 $fieldsArray = Applets_QuickSearchCommon::parse_array($arrayQuick[$key]);
		 $sql = substr("select * from ".$key."_data_1 where ". Applets_QuickSearchCommon::constructLikeSQL($arrTxt, $fieldsArray), 0 , -3);
		 $qry = DB::SelectLimit($sql, 20, 0);	
		 if($qry){
			$arrRow = array();
			$result = "";		
			while($rowValue = $qry->FetchRow()){
				$rowValue["RECORD_SET"] = $key;
				$arrayKey[] = $rowValue;
			}
		 }
	}

	if(is_array($arrayKey) && !empty($arrayKey)){		
			foreach($arrayKey as $rows){
				$result = "";
				$keyRecordset = $rows["RECORD_SET"];
				foreach(($formatString[$keyRecordset]) as $keyIdx){
					 $result .= $rows[$keyIdx]." ";
				}
				//Tooltip Code = <span ".Utils_TooltipCommon::open_tag_attrs($tooltip , false)."><img border='0' src='data/Base_Theme/templates/default/Utils/GenericBrowser/info.png'></span>
				$tooltip = ucwords($result);
				print "<tr style='background:#FFFFD5;'> 
						<td class='Utils_GenericBrowser__td' style='width:80%;height:20px'></td>
						<td class='Utils_GenericBrowser__td' style='width:80%;height:20px'> <a onclick=\"_chj('__jump_to_RB_table=".$keyRecordset."&amp;__jump_to_RB_record=".$rows["id"]."&amp;__jump_to_RB_action=view', '', '');\" href=\"javascript: void(0)\">".ucwords($result)."</a></td>
						<td class='Utils_GenericBrowser__td' style='width:10%'>".Utils_RecordBrowserCommon::get_caption($keyRecordset)."</td></tr>";
			}	
	}
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