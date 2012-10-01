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

class CRM_Contacts_Photo extends Module {
    private $submitted = false;

    public function body($record) {
        $form = $this->init_module('Utils/FileUpload',array(false));
        $form->addElement('header', 'upload', __('Upload new photo').': '.$record['last_name'].' '.$record['first_name']);

        $form->set_upload_button_caption(__('Save'));

        $form->add_upload_element();

        Base_ActionBarCommon::add('save', __('Save'), $form->get_submit_form_href());
        Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
        if(CRM_Contacts_PhotoCommon::get_photo($record['id'])) {
            Base_ActionBarCommon::add('delete', __('Clear'), $this->create_confirm_callback_href(__('Are you sure?'), array($this, 'clear_photo'), array($record['id'])));
        }

        $this->display_module($form, array( array($this,'submit_attach'), $record));

        if ($this->is_back() || $this->submitted) {
            $x = ModuleManager::get_instance('/Base_Box|0');
            if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
            return $x->pop_main();
        }
    }

    public function submit_attach($file,$oryg,$data,$record) {
        if(! $oryg) {
            $this->submitted = true;
            return;
        }
        /* check extension */
        $possible_extensions = array('jpg', 'jpeg', 'png');
        $extension = strtolower(end(explode('.', $oryg)));
        if( ! in_array($extension, $possible_extensions) ) {
            echo __('Filename extension should be one of these (letter size doesn\'t matter): ').implode(', ', $possible_extensions);
            return;
        }

        if ($file) {
            $local = $this->get_data_dir();
            $filebase = md5($record['first_name'] . $record['last_name']) . $record['id'];
            $pattern = $local . $filebase;
            $i = 0;

            while (file_exists($pattern.$i.'.'.$extension)) $i++;
            $dest_file = $pattern.$i.'.'.$extension;

            $thumb = Utils_ImageCommon::create_thumb($file,600,600);
            @unlink($file);

            rename($thumb['thumb'],$dest_file);

            CRM_Contacts_PhotoCommon::add_photo($record['id'], $filebase.$i.'.'.$extension);
        }
        $this->submitted = true;
    }

    public function clear_photo($contact_id) {
        CRM_Contacts_PhotoCommon::del_photo($contact_id);
        $this->submitted = true;
    }
}

?>
