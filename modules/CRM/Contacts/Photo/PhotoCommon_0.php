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
    const table_name = 'contact_photos';

	public static function submit_contact($values, $mode) {
		if ($mode=='display') {
			$ret = array();
			$in = self::Instance();
            $filename = self::get_photo($values['id']);
            if($filename) {
        		$file = $in->get_data_dir() . $filename;
            } else {
				$file = Base_ThemeCommon::get_template_file('CRM/Contacts/Photo','placeholder.png');
				$ret['photo_note'] = __('Click to change');
            }
			$ret['photo_link'] = Module::create_href(array('upload_new_photo'=>$values['id'])).' '.Utils_TooltipCommon::open_tag_attrs(__('Click to change the photo'), false);
			$ret['photo_src'] = $file;
		} else {
			$ret = $values;
		}
		if (isset($_REQUEST['upload_new_photo']) && $_REQUEST['upload_new_photo']==$values['id']) {
			unset($_REQUEST['upload_new_photo']);
			$x = ModuleManager::get_instance('/Base_Box|0');
			if (!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
			$x->push_main('CRM/Contacts/Photo','body',array($values));
		}
		return $ret;
	}

    public static function add_photo($contact_id, $filename) {
        self::del_photo($contact_id, $filename);
        DB::Execute('INSERT INTO '.self::table_name.' VALUES (%d,%s) ON DUPLICATE KEY UPDATE filename=%s', array($contact_id, $filename, $filename));
    }

    public static function get_photo($contact_id) {
        return DB::GetOne('SELECT filename FROM '.self::table_name.' WHERE contact_id=%d', array($contact_id));
    }

    public static function del_photo($contact_id) {
        $filename = self::get_photo($contact_id);
        if(! $filename) return;

        $in = self::Instance();
        unlink($in->get_data_dir() . $filename);
        DB::Execute('DELETE FROM `'.self::table_name.'` WHERE `contact_id`=%d', array($contact_id));
    }
}

?>