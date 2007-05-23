<?php
/**
 * WizardTest class.
 * 
 * @author Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-utils
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides functions for presenting data in a table (suports sorting 
 * by different columns and splitting results -- showing 10 rows per page).
 * @package tcms-utils
 * @subpackage generic-browse
 */
class Tests_Menu extends Module {

	public function body( $arg ) {
		print "menu";
		
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
		
		$menu2 = $this->init_module("Utils/Menu", "horizontal");
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


