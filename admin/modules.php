<?php
require_once('auth.php');

//create default module form
$form = new HTML_QuickForm('modulesform','post',$_SERVER['PHP_SELF'].'?'.http_build_query($_GET));
$form->addElement('header', null, 'Uninstall module');

foreach(ModuleManager::$modules as $name=>$v)
	$form->addElement('checkbox',$name,$name.' (ver '.$v.')');

$form->addElement('submit', 'submit_button', 'Uninstall');

//validation or display
if ($form->validate()) {
	//uninstall
	$vals = $form->exportValues();
	$modules_prio_rev = array();
	$ret = DB::Execute('SELECT * FROM modules ORDER BY priority DESC');
	while($row = $ret->FetchRow())
		if(isset($vals[$row['name']]) && $vals[$row['name']]) {
			if (!ModuleManager::uninstall($row['name'])) {
				die('Unable to remove module '.$row['name']);
			}
			if(count(ModuleManager::$modules)==0)
				die('No modules installed');
		}
	print('<hr><a href="modules.php">back</a>');
} else {
	$form->display();
	print('<hr><a href="index.php">back</a>');
}
?>