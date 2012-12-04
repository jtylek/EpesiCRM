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
$formatString = Applets_QuickSearchCommon::parseFormatString($format);
$array_keys = array_keys($arrayQuick);
	foreach($array_keys as $key){
		 $fieldsArray = Applets_QuickSearchCommon::parse_array($arrayQuick[$key]);
		 $sql = substr("select * from ".$key."_data_1 where ". Applets_QuickSearchCommon::constructLikeSQL($arrTxt, $fieldsArray), 0 , -3);
		 $qry = DB::SelectLimit($sql, 20, 0);	
		 if($qry){
			$arrRow = array();
			while($rowValue = $qry->FetchRow()){
				/* TODO: Work on this method */
				Applets_QuickSearchCommon::parseResult($rowValue, $formatString);
				//$arrRow["result"] = $rowValue["result"] = $format;
				//$rowValue["source"] = $key;
				$arrRow["source"] = $key;
				$arrResult[] = $arrRow;
			}
		 }
	}
	print Applets_QuickSearchCommon::displayResult($array = array());
	/*if(is_array($arrResult) && !empty($arrResult)){
			foreach($arrResult as $rows){
				$gb->add_row('Row '.$rows["f_first_name"]);
				//print "<tr style='background:#FFFFD5;'><td colspan='2' class='Utils_GenericBrowser__td' style='width:80%;height:20px'><img border='0' src='data/Base_Theme/templates/default/Utils/GenericBrowser/info.png'> <a onclick=\"_chj('__jump_to_RB_table=".$rows[0]."&amp;__jump_to_RB_record=".$rows[0]."&amp;__jump_to_RB_action=view', '', '');\" href=\"javascript: void(0)\">".ucwords($rows["f_first_name"])."</a></td>
				//<td class='Utils_GenericBrowser__td' style='width:10%'>".Utils_RecordBrowserCommon::get_caption($rows["source"])."</td></tr>";
			}	
		print $gb	
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