<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-tests
 * @subpackage Attachment
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_Attachment extends Module{
	public function body(){
		print('This is an example Attachment page.');
		$com = & $this->init_module('Utils/Attachment',array('grupa'));
		$this -> display_module($com);
		//------------------------------ print out src
		print('<hr><b>Install</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/Attachment/AttachmentInstall.php');
		print('<hr><b>Main</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/Attachment/Attachment_0.php');
		print('<hr><b>Common</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/Attachment/AttachmentCommon_0.php');

	}
}

?>
