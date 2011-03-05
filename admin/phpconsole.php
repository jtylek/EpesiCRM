<?php
require_once('functions.php');
require_once('include.php');

pageheader();
starttable();

require_once('auth.php');

print("<center><b>PHP Console</b><hr />");
$form = new HTML_QuickForm('loginform','post',$_SERVER['PHP_SELF'].'?'.http_build_query($_GET));
$form->addElement('textarea','input','Input',array('style'=>'width:100%;height:200px'));
$form->addRule('input','Field required','required');
$form->addElement('submit',null,'Evaluate');
if($form->validate()) {
	$input = $form->exportValue('input');
	ModuleManager::load_modules();
	ob_start();
	eval($input);
	$output = ob_get_clean();
	error_log('<?php exit(); ?>'."\n".Base_UserCommon::get_my_user_login().":\nInput:\n".$input."\n-----------------------------------------\nOutput:\n".$output."\n=========================================\n\n",3,DATA_DIR.'/phpconsole_log.php');
	print("Output:<br />");	
	print($output);
	print("<hr />");
}
$form->display();
print("</center>");

closetable();
pagefooter();
?>