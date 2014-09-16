<?php
if (!isset($_GET['cid']) || !isset($_GET['token']))
	die('Invalid request: '.print_r($_GET,true));
	

define('CID',$_GET['cid']); 
define('READ_ONLY_SESSION',true);
require_once('../../../include.php');
if(!isset($_SESSION['rb_indexer_token']) || $_SESSION['rb_indexer_token']!=$_GET['token'])
    die('Invalid token');

$total = 0;
if(@file_get_contents(DATA_DIR.'/Utils_RecordBrowser/last')<time()-50) {
    ModuleManager::load_modules();
    Base_AclCommon::set_sa_user();

    Utils_RecordBrowserCommon::indexer(3,$total);
    if($total==0) file_put_contents(DATA_DIR.'/Utils_RecordBrowser/last',time());
}
?>setTimeout('rb_indexer("<?php print($_GET['token']); ?>")',<?php print($total==0?60000:1000);?>);