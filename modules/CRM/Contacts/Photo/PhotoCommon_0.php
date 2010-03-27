<?php
/**
 * Activities history for Company and Contacts
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage contacts-photo
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Contacts_PhotoCommon extends ModuleCommon {

	public static function submit_contact($values, $mode) {
		$ret = CRM_ContactsCommon::submit_contact($values, $mode);
		if ($mode=='display') {
			$in = self::Instance();
			$local = $in->get_data_dir();
			$i = 0;
			$pattern = $local.'/'.$values['id'].'_';
			while (file_exists($pattern.($i+1))) $i++;
			$dest_file = $pattern.$i;
			if (file_exists($dest_file))
				$file = $dest_file;
			else
				$file = Base_ThemeCommon::get_template_file('CRM/Contacts/Photo','placeholder.png');
			$ret['photo_link'] = Module::create_href(array('upload_new_photo'=>$values['id'])).' '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('CRM/Contacts/Photo','Click to change the photo'), false);
			$ret['photo_src'] = $file;
		}
		if (isset($_REQUEST['upload_new_photo']) && $_REQUEST['upload_new_photo']==$values['id']) {
			unset($_REQUEST['upload_new_photo']);
			$x = ModuleManager::get_instance('/Base_Box|0');
			if (!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
			$x->push_main('CRM/Contacts/Photo','body',array($values));
		}
		return $ret;
	}
}

?>