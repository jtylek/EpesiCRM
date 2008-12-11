<?php
/**
 * Apps/MailClient and other CRM functions connector
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package crm-mailclient
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_MailClient extends Module {

	public function construct() {
		$this->lang = $this->init_module('Base/Lang');
	}

	public function body() {
	
	}

	public function contact_addon($arg){
		$gb = $this->init_module('Utils/GenericBrowser',null,'addon');
		$cols = array();
		$cols[] = array('name'=>$this->lang->t('Date'), 'order'=>'delivered_on','width'=>5);
		$cols[] = array('name'=>$this->lang->t('Type'), 'order'=>'sent','width'=>5);
		$cols[] = array('name'=>$this->lang->t('Subject'), 'width'=>65);
		$cols[] = array('name'=>$this->lang->t('Attachments'), 'order'=>'uaf.original','width'=>25);
		$gb->set_table_columns($cols);

		$query = 'SELECT sticky,id,delivered_on,subject,sent FROM crm_mailclient_mails WHERE contact_id='.DB::qstr($arg['id']).' AND deleted=0';
		$query_lim = 'SELECT count(id) FROM crm_mailclient_mails WHERE contact_id='.DB::qstr($arg['id']).' AND deleted=0';
		$gb->set_default_order(array($this->lang->t('Date')=>'DESC'));

		$query_order = $gb->get_query_order('sticky DESC');
		$qty = DB::GetOne($query_lim);
		$query_limits = $gb->get_limit($qty);
		$ret = DB::SelectLimit($query.$query_order,$query_limits['numrows'],$query_limits['offset']);

		while($row = $ret->FetchRow()) {
			$r = $gb->get_new_row();

			$delivered_on = Base_RegionalSettingsCommon::time2reg($row['delivered_on'],0);
			$delivered_on_time = Base_RegionalSettingsCommon::time2reg($row['delivered_on'],1);
			$text = $row['subject'];
			if($row['sticky']) $text = '<img src="'.Base_ThemeCommon::get_template_file($this->get_type(),'sticky.png').'" hspace=3 align="left"> '.$text;

			$attachments = '';
			$ret2 = DB::Execute('SELECT * FROM crm_mailclient_attachments WHERE mail_id=%d',array($row['id']));
			while($attach_row = $ret2->FetchRow()) {
				$href = array('msg_id'=>$row['id']);
				if($attach_row['cid'])
					$href['attachment_cid']=$attach_row['cid'];
				else
					$href['attachment_name']=$attach_row['name'];
				$attachments .= '<a href="'.$this->get_module_dir().'preview.php?'.http_build_query($href).'" target="_blank">'.$attach_row['name'].'</a><br>';
			}

			$arr = array();
			$arr[] = Utils_TooltipCommon::create($delivered_on,$delivered_on_time);
			$arr[] = $this->lang->t($row['sent']?'sent':'received');
			$arr[] = '<a '.$this->create_callback_href(array($this,'show_message'),array($row['id'])).'>'.$text.'</a>';
			$arr[] = $attachments;
			$r->add_data_array($arr);
		}

		$this->display_module($gb);
	}
	
	public function show_message($id) {
		
		return true;
	}

}

?>