<?php
/**
 * @author Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-tests
 * @subpackage menu
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_Menu extends Module {

	public function body( ) {
		print "menu ".$this->get_unique_href_variable('action');
		
		$menu = & $this->init_module("Utils/Menu");
		$menu->add_link("aaa");
		$menu->add_link("aaa");
		$menu->begin_submenu("s");
			$menu->add_link("aaa");
			$menu->add_link("aaa");
			$menu->begin_submenu("sX");
				$menu->add_link("aaa");
				$menu->add_link("aaa");
				$menu->add_link("aaa");
				$menu->begin_submenu("s");
					$menu->add_link("aaa");
					$menu->add_link("aaa");
					$menu->add_link("aaa");
					$menu->add_link("aaa");
					$menu->add_link("aaa");
					$menu->end_submenu();
				$menu->add_link("aaa");
				$menu->begin_submenu("s");
					$menu->add_link("aaa");
					$menu->add_link("aaa");
					$menu->add_link("aaa");
					$menu->add_link("aaa");
					$menu->add_link("aaa");
					$menu->end_submenu();
				$menu->add_link("aaa");
				$menu->end_submenu();
			$menu->add_link("aaa");
			$menu->add_link("aaa");
			$menu->begin_submenu("s");
				$menu->add_link("aaa");
				$menu->add_link("aaa");
				$menu->add_link("aaa");
				$menu->add_link("aaa");
				$menu->add_link("aaa");
				$menu->end_submenu();
			$menu->add_link("aaa");
			$menu->add_link("aaa");
			$menu->add_link("aaa");
			$menu->end_submenu();
		$menu->add_link("aaa");
		$menu->add_link("aaa");
		$menu->begin_submenu("s");
			$menu->add_link("aaa");
		$menu->add_link("aaa");
			$menu->add_link("aaa");
		$menu->end_submenu();
		$menu->add_link("aaa");
		$menu->add_link("aaa");
		$this->display_module( $menu );
		
		$menu2 = & $this->init_module("Utils/Menu", "horizontal");
		$menu2->add_link("bbb");
		$menu2->add_link("bbb");
		$menu2->begin_submenu("s");
			$menu2->add_link("bbb");
			$menu2->add_link("bbb");
			$menu2->begin_submenu("s");
				$menu2->add_link("bbb");
				$menu2->add_link("bbb");
				$menu2->add_link("bbb");
				$menu2->add_link("bbb");
				$menu2->add_link("bbb");
				$menu2->end_submenu();
			$menu2->add_link("bbb");
			$menu2->add_link("bbb");
			$menu2->begin_submenu("s");
				$menu2->add_link("bbb");
				$menu2->add_link("bbb");
				$menu2->add_link("bbb");
				$menu2->add_link("bbb");
				$menu2->add_link("bbb");
				$menu2->end_submenu();
			$menu2->begin_submenu("s");
				$menu2->add_link("bbb");
				$menu2->add_link("bbb");
				$menu2->add_link("bbb");
				$menu2->add_link("bbb");
				$menu2->add_link("bbb");
				$menu2->end_submenu();
			$menu2->add_link("bbb");
			$menu2->add_link("bbb");
			$menu2->add_link("bbb");
			$menu2->end_submenu();
		$menu2->add_link("bbb");
			$menu2->begin_submenu("s");
				$menu2->add_link("bbb");
				$menu2->add_link("bbb");
				$menu2->add_link("bbb");
				$menu2->add_link("bbb");
				$menu2->add_link("bbb");
				$menu2->end_submenu();
		$menu2->add_link("bbb");
		$menu2->begin_submenu("s");
			$menu2->add_link("bbb");
		$menu2->add_link("bbb");
			$menu2->add_link("bbb");
		$menu2->end_submenu();
		$menu2->add_link("bbb");
		$menu2->add_link("bbb");
		$this->display_module( $menu2 );
	}
}
?>


