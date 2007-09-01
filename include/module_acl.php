<?php
/**
 * Module file
 * 
 * This file defines abstract class Module whose provides basic modules functionality.
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @licence SPL
 * @version 1.0
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

abstract class ModuleAcl {
	abstract public function get_type();
	
	public final function add_aco($name) {
		return Acl::add_aco($this->get_type(),$name);
	}

	public final function del_aco($name) {
		return Acl::del_aco($this->get_type(),$name);
	}
	
	public final function aco_accept_group($name,$group) {
		return Acl::aco_accept_group($this->get_type(),$name,$group);
	}
	
	public final function acl_check($aco,$aro=null,$aro_sec=null) {
		return Acl::acl_check($aco,$aro_sec,$aro);
	}
}
?>
