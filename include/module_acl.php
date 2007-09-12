<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @license SPL
 * @version 1.0
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

abstract class ModuleAcl {
	abstract public function get_type();
	
	public final function add_aco($name,$group=null) {
		$ret = Acl::add_aco($this->get_type(),$name,$group);
	}

	public final function del_aco($name) {
		return Acl::del_aco($this->get_type(),$name);
	}
	
	public final function acl_check($aco,$aro=null,$aro_sec=null) {
		return Acl::check($this->get_type(),$aco,$aro_sec,$aro);
	}
}
?>
