<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license SPL
 * @package epesi-tests
 * @subpackage Attachment
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_Attachment extends Module{
	public function body(){
		print('This is an example Attachment page.');
		$com = & $this->init_module('Utils/Attachment',array('test','grupa'));
/*		$com -> set_moderator(true);
		$com -> set_per_page(3);
		$com -> reply_on_Attachment_page(false);
		$com -> tree_structure(true);
*/
		$this -> display_module($com);
		Utils_RecordBrowserCommon::new_addon('company', 'CRM/Contacts', 'company_attachment_addon', 'Notes & docs');
		Utils_RecordBrowserCommon::new_addon('contact', 'CRM/Contacts', 'contact_attachment_addon', 'Notes & docs');

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
