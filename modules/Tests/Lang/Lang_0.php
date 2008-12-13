<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-tests
 * @subpackage lang
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_Lang extends Module{
	public function body(){
		print('This is an example page that uses Base/Lang module.<br>');
		print($this->t('This text can be translated.').'<br>');
		print($this->t('This text can be translated.').'<br>');
		print($this->ht('This text can be translated, but not with mainatance mode.').'<br>');		
		print(Base_LangCommon::ts('','This text can be translated, but will not be considered as part of module.').'<br>');		
		print($this->t('Here you can have some numbers: %d, %d, %d but you can still translate whole text.',array(2,6,3)).'<br>');
		print('<hr>');
		print('Translations for the following line were installed along with this module.<br>');
		print($this->t('Hello world!'));
		//------------------------------ print out src
		print('<hr><b>Install</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/Lang/LangInstall.php');
		print('<hr><b>Main</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/Lang/Lang_0.php');
		print('<hr><b>Common</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/Lang/LangCommon_0.php');
	}
}

?>
