<?php
/**
 * Use this module if you want to add attachments to some page.
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package utils-attachment
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_Attachment extends Module {
	private $lang;
	private $key;

	public function construct($key) {
		if(isset($key)) $this->key = md5($key);
		else trigger_error('Key not given to attachment module, aborting',E_USER_ERROR);
		$this->lang = & $this->init_module('Base/Lang');
	}

	public function body() {
		$gb = $this->init_module('Utils/GenericBrowser',null,$this->key);
		$gb->set_table_columns(array(
				array('name'=>'Comment', 'order'=>'uac.text'),
				array('name'=>'Files')
			));
		$ret = $gb->query_order_limit('SELECT ual.id,uac.created_on,uac.created_by,uac.text FROM utils_attachment_link ual LEFT JOIN utils_attachment_comment uac ON uac.comment_id=ual.id WHERE ual.attachment_key=\''.$this->key.'\' AND uac.revision=(SELECT max(x.revision) FROM utils_attachment_comment x WHERE x.comment_id=uac.comment_id) AND ual.deleted=0','SELECT count(*) FROM utils_attachment_link ual WHERE ual.attachment_key=\''.$this->key.'\' AND ual.deleted=0');
		while($row = $ret->FetchRow()) {
			$r = $gb->get_new_row();
			$r->add_data($row['text'],'');
		}
		$this->display_module($gb);

		Base_ActionBarCommon::add('new','Attach file',$this->create_callback_href(array($this,'attach_file')));
	}

	public function attach_file() {
		$form = & $this->init_module('Utils/FileUpload');
		$form->addElement('header', 'upload', $this->lang->t('Attach file'));
		$form->addElement('textarea', 'comment', $this->lang->t('Comment'));
		$this->display_module($form, array( array($this,'submit_attach') ));
	}

	public function submit_attach($file,$oryg,$data) {
		DB::Execute('INSERT INTO utils_attachments_link(key) VALUES(%s)',array($this->key));
		$id = DB::Insert_ID('utils_attachments_link','id');
		rename($file,$this->get_data_dir().$id);
	}

}

?>
