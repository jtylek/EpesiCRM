<?php
/**
 * Gets host ip or domain
 * @author j@epe.si
 * @copyright 2008 Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-applets
 * @subpackage host
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_Host extends Module {

	public function body() {
	
	}
	
	public function applet() {
		$f = $this->init_module(Libs_QuickForm::module_name());
		$t = $f->createElement('text','t');
		$ok = $f->createElement('submit','ok',__('OK'));
		$f->addGroup(array($t,$ok),'w');
		$f->display();
		
		$msg = & $this->get_module_variable('msg');
		if($f->validate()) {
			$w = $f->exportValues();
			$w = $w['w']['t'];
			if(ip2long($w)===false) {
				$ip = gethostbynamel($w);
				if($ip) {
					$msg = '';
					foreach($ip as $i)
						$msg .= $i.'<br>';
				} else 
					$msg = __('No such domain');
			} else {
				$domain = gethostbyaddr($w);
				if($domain!=$w)
					$msg = $domain;
				else
					$msg = __('No such ip entry');
			}
		}
		print($msg);
	}
}

?>