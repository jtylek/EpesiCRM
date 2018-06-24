<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-apps
 * @subpackage shoutbox
 */

ob_start();
define('CID',false);
define('READ_ONLY_SESSION',true);
require_once('../../../include.php');
ModuleManager::load_modules();

if(!Base_AclCommon::is_user())
	exit();

$myid = Base_AclCommon::get_user();
$uid = (isset($_GET['uid']) && is_numeric($_GET['uid']))?$_GET['uid']:null;
$shoutbox_admin = Base_AclCommon::check_permission('Shoutbox Admin');

//get last 20 messages
$arr = DB::GetAll('SELECT * FROM apps_shoutbox_messages WHERE '.($uid?'(base_user_login_id='.$myid.' AND to_user_login_id='.$uid.') OR (base_user_login_id='.$uid.' AND to_user_login_id='.$myid.')':'to_user_login_id is null OR to_user_login_id='.$myid.' OR base_user_login_id='.$myid).' ORDER BY posted_on DESC LIMIT 20');
//print it out
foreach($arr as $row) {
	$daydiff = floor((time()-strtotime($row['posted_on']))/86400);
	switch (true) {
		case ($daydiff<1): $fcolor = '#000000'; break;
		case ($daydiff<3): $fcolor = '#444444'; break;
		case ($daydiff<7): $fcolor = '#888888'; break;
		default : $fcolor = '#AAAAAA';
	}
	$user_label = Apps_ShoutboxCommon::create_write_to_link($row['base_user_login_id']);
	if ($row['to_user_login_id'])
		$user_label .= ' -> '.Apps_ShoutboxCommon::create_write_to_link($row['to_user_login_id']);

    $message = (($row['to_user_login_id']==$myid && $uid===null)?'<b>':'').Utils_BBCodeCommon::parse($row['message']).(($row['to_user_login_id']==$myid && $uid===null)?'</b>':'');
    $time = Base_RegionalSettingsCommon::time2reg($row['posted_on'],2);
    $color = array('default','primary','success','danger','warning','info');
	$color = $color[$row['base_user_login_id'] % 6];

    $html = <<<HTML
        <li class="list-group-item py-5">
            <div class="media">
                <!--<div class="media-object avatar avatar-md mr-4" style="background-image: url(demo/faces/male/16.jpg)"></div>-->
                <div class="media-body">
                    <div class="media-heading">
                        <small class="float-right text-muted">$time</small>
                        <h5>$user_label</h5>
                    </div>
                    <div>
                        $message
                    </div>
                </div>
            </div>
        </li>
HTML;

//    <div class="bs-callout bs-callout-$color">
//		<h4>$user_label<span class="label label-default pull-right">$time</span></h4>
//    	<span class="shoutbox_textbox"style="color:$fcolor;">$message</span>
//    </div>

    print($html);
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
