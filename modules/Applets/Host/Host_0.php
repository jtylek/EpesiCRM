<?php
/**
 * Gets host ip or domain
 * @author pbukowski@telaxus.com
 * @copyright 2008 Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-applets
 * @subpackage host
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_Host extends Module {

	public function body() {
	
	}
	
	public function applet() {
		$f = $this->init_module('Libs/QuickForm');
		$t = $f->createElement('text','t');
		$ok = $f->createElement('submit','ok',$this->ht('OK'));
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
					$msg = $this->t('No such domain');
			} else {
				$domain = gethostbyaddr($w);
				if($domain!=$w)
					$msg = $domain;
				else
					$msg = $this->t('No such ip entry');
			}
		}
		print($msg);
	}
}

?>