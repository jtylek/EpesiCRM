<?php
/**
 * Develop_Translations class.
 * 
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-develop
 * @subpackage translations
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Develop_TranslationsInstall extends ModuleInstall {
	public function version() {
		return array('1.0.0');
	}

	public function install() {
		DB::CreateTable('develop_trans_users',
			'id I4 AUTO KEY,'.
			'first_name C(128),'.
			'last_name C(128),'.
			'credits I1,'.
			'credits_website C(128),'.
			'contact_email C(128),'.
			'ip C(64)',
			array());
		DB::CreateTable('develop_trans_contribs',
			'id I4 AUTO KEY,'.
			'user_id I4,'.
			'lang C(32),'.
			'org X,'.
			'trans X,'.
			'used I4,'.
			'discarded I4,'.
			'received_on T NULL',
			array('constraints' => ', FOREIGN KEY (user_id) REFERENCES develop_trans_users(id)'));
		return true;
	}

	public function uninstall() {
		DB::DropTable('develop_trans_contribs');
		DB::DropTable('develop_trans_users');
		return true;
	}
	public function requires($v) {
		return array(
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Base/Box','version'=>0)
		);
	}
	public function simple_setup() {
        return false;
	}
}

?>
