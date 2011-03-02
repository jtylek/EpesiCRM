<?php
require_once('auth.php');
require_once('functions.php');
pageheader();
// print('<CENTER'>);
starttable();

//create default module form
$form = new HTML_QuickForm('modulesform','post',$_SERVER['PHP_SELF'].'?'.http_build_query($_GET));
$form->addElement('html', '<CENTER><div class="header">Select modules to uninstall</div></CENTER>');
$form->addElement('html', '<HR>');

foreach(ModuleManager::$modules as $name=>$v)
	$form->addElement('checkbox',$name,$name.' (ver '.$v.')');

$form->addElement('submit', 'submit_button', 'Uninstall Selected');

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
	print('Selected modules were uninstalled.');
} else {
	$form->display();
}
closetable();
pagefooter();
?>
