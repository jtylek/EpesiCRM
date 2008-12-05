<?php
/**
 * Example event module
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-tests
 * @subpackage codepress
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_Codepress extends Module {

	public function body() {
		$qf = $this->init_module('Libs/QuickForm');
		$x = $qf->addElement('codepress','cd','CD');
		$x->setRows(15);
		$x->setCols(100);
		$x->setLineNumbers(false);
		//$x->setLang('php'); //default
		//$x->setAutocomplete(true); //default
		$qf->setDefaults(array('cd'=>file_get_contents($this->get_module_dir().'Codepress_0.php')));
		//$qf->freeze(array('cd'));
		$qf->addElement('submit',null,'ok');
//		$qf->addElement('button',null,'toggle','onClick="CodePress.update()"');
		if($qf->validate())
			print('<div align="left"><pre>'.htmlspecialchars($qf->exportValue('cd')).'</pre></div>');
		else
			$qf->display();
	}

}

?>