<?php
/**
 * Mail archive applet etc.
 * @author pbukowski@telaxus.com
 * @copyright Telaxus LLC
 * @license MIT
 * @version 0.1
 * @package epesi-CRM
 * @subpackage MailArchive
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_MailArchiveCommon extends ModuleCommon {
    public static function applet_caption() {
        return __('Archive Mail');
    }

    public static function applet_info() {
        return __('Archive last received or sent e-mails');
    }

    public static function applet_settings() {
        $ret = Utils_RecordBrowserCommon::get_records('rc_accounts',array('epesi_user'=>Acl::get_user()));
        $conf = array();
        foreach($ret as $row) {
            $conf[] = array('name'=>'header_'.$row['id'],'type'=>'header','label'=>$row['account_name']);
            $conf[] = array('name'=>'period_'.$row['id'],'label'=>__('Period'),'type'=>'select','default'=>4,'values'=>array(0=>__('Today'),1=>__('Today and yesterday'),2=>__('3 days'),3=>__('4 days'),4=>__('5 days'),5=>__('6 days'),6=>__('7 days')));
            $folders = CRM_MailCommon::get_folders($row);
            foreach($folders as $name)
                $conf[] = array('name'=>'account_'.$row['id'].'_'.md5($name), 'label'=>$name, 'type'=>'checkbox', 'default'=>preg_match('/^(Junk|Trash|Spam|Draft)/i',$name)?0:1);
        }
        if(count($conf)==1)
            return array(array('type'=>'static','label'=>__('No accounts configured, go Menu->My settings->Control panel->E-mail accounts')));
        return $conf;
    }

	public static function menu() {
		return array(_M('CRM')=>array(_M('Mail Archive')=>array(),'__submenu__'=>1));
	}

    public static function link_to_crits() {
        $recordsets = DB::GetCol('SELECT f_recordset FROM rc_related_data_1 WHERE active=1');
        $crits = array(
            '' => array(),
        );
        foreach ($recordsets as $rec)
            $crits[$rec] = array();
        return $crits;
    }
}

?>
