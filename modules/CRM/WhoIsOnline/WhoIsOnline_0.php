<?php
/**
 * Shows who is logged to epesi.
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage whoisonline
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_WhoIsOnline extends Module {

	public function body() {
	}
	
	public function applet($v,$o) {
		$all = Tools_WhoIsOnlineCommon::get();
		$map = array();
		foreach($all as $id=>$x) {
			$c = CRM_ContactsCommon::get_contact_by_user_id(Base_UserCommon::get_user_id($x));
			if($c) {
				$all[$id] = CRM_ContactsCommon::contact_format_no_company($c);
    			$map[$id] = $c['last_name'];
    		} else
    		    $map[$id] = $x;
		}
		asort($map);

		$c = count($all);
		if($c==1)
    		$o['title'] = __('%d user online',array($c));
    	else
    		$o['title'] = __('%d users online',array($c));

        print('<ul>');
    	foreach($map as $id=>$x)
    	    print('<li>'.$all[$id].'</li>');
    	print('</ul>');
	}

}

?>