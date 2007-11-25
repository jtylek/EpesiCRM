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
	private $persistant_deletion = false;
	private $group;
	private $view = true;
	private $view_deleted = true;
	private $edit = true;
	private $download = true;
	private $inline = false;

	public function construct($key,$group='') {
		if(!isset($key)) trigger_error('Key not given to attachment module',E_USER_ERROR);
		$this->lang = & $this->init_module('Base/Lang');
		$this->group = $group;
		$this->key = md5($key);
	}

	public function inline_attach_file($x=true) {
		$this->inline = $x;
	}

	public function set_persistant_delete($x=false) {
		$this->persistant_deletion = $x;
	}

	public function allow_edit($x=true) {
		$this->edit = $x;
	}

	public function allow_view($x=true) {
		$this->view = $x;
	}

	public function allow_view_deleted($x=true) {
		$this->view_deleted = $x;
	}

	public function allow_download($x=true) {
		$this->download = $x;
	}

	public function body() {
		if(!$this->view) {
			print($this->lang->t('You don\'t have permission to view attachments to this page'));
			return;
		}

		$vd = null;
		if($this->view_deleted && !$this->persistant_deletion) {
			$f = $this->init_module('Libs/QuickForm','view_deleted');
			$f->addElement('checkbox','view_del',$this->lang->t('View deleted attachments'),null,array('onClick'=>$f->get_submit_form_js()));
			$vd = & $this->get_module_variable('view_deleted');
			$f->setDefaults(array('view_del'=>$vd));
			if($f->validate()) {
				$vd = $f->exportValue('view_del');
			}
			$f->display();
		}

		$gb = $this->init_module('Utils/GenericBrowser',null,$this->key);
		$cols = array();
		if($vd)
			$cols[] = array('name'=>'Deleted','order'=>'ual.deleted','width'=>5);
		$cols[] = array('name'=>'Comment', 'order'=>'uac.text','width'=>65);
		$cols[] = array('name'=>'File', 'order'=>'ual.original');
		$gb->set_table_columns($cols);

		//tag for get.php
		$this->set_module_variable('download',$this->download);
		$this->set_module_variable('key',$this->key);
		$this->set_module_variable('group',$this->group);
		if($vd)
			$ret = $gb->query_order_limit('SELECT ual.deleted,ual.local,uac.revision,ual.id,uac.created_on as comment_on,(SELECT l.login FROM user_login l WHERE uac.created_by=l.id) as comment_by,uac.text,ual.original,ual.created_on as upload_on,(SELECT l2.login FROM user_login l2 WHERE ual.created_by=l2.id) as upload_by FROM utils_attachment_link ual LEFT JOIN utils_attachment_comment uac ON uac.attach_id=ual.id WHERE ual.attachment_key=\''.$this->key.'\' AND ual.local='.DB::qstr($this->group).' AND uac.revision=(SELECT max(x.revision) FROM utils_attachment_comment x WHERE x.attach_id=uac.attach_id)','SELECT count(*) FROM utils_attachment_link ual WHERE ual.attachment_key=\''.$this->key.'\' AND ual.local='.DB::qstr($this->group));
		else
			$ret = $gb->query_order_limit('SELECT ual.local,uac.revision,ual.id,uac.created_on as comment_on,(SELECT l.login FROM user_login l WHERE uac.created_by=l.id) as comment_by,uac.text,ual.original,ual.created_on as upload_on,(SELECT l2.login FROM user_login l2 WHERE ual.created_by=l2.id) as upload_by FROM utils_attachment_link ual LEFT JOIN utils_attachment_comment uac ON uac.attach_id=ual.id WHERE ual.attachment_key=\''.$this->key.'\' AND ual.local='.DB::qstr($this->group).' AND uac.revision=(SELECT max(x.revision) FROM utils_attachment_comment x WHERE x.attach_id=uac.attach_id) AND ual.deleted=0','SELECT count(*) FROM utils_attachment_link ual WHERE ual.attachment_key=\''.$this->key.'\' AND ual.local='.DB::qstr($this->group).' AND ual.deleted=0');
		while($row = $ret->FetchRow()) {
			$r = $gb->get_new_row();

			$file = '<a href="modules/Utils/Attachment/get.php?'.http_build_query(array('id'=>$row['id'],'path'=>$this->get_path(),'cid'=>CID)).'">'.$row['original'].'</a>';
			if($row['revision']=='0')
				$info = $this->lang->t('Created by %s<br>Created on %s',array($row['upload_by'],Base_RegionalSettingsCommon::time2reg($row['upload_on'])));
			else
				$info = $this->lang->t('Last comment by %s<br>Last comment on %s<br>File uploaded by %s<br>File uploaded on %s',array($row['comment_by'],Base_RegionalSettingsCommon::time2reg($row['comment_on']),$row['upload_by'],Base_RegionalSettingsCommon::time2reg($row['upload_on'])));
			$r->add_info($info);
			if($this->edit) {
				$r->add_action($this->create_callback_href(array($this,'edit_comment'),array($row['id'],$row['text'])),'edit');
				$r->add_action($this->create_confirm_callback_href($this->lang->ht('Delete this entry?'),array($this,'delete'),$row['id']),'delete');
				$r->add_action($this->create_callback_href(array($this,'edition_history'),$row['id']),'history');
			}
			if($vd)
				$r->add_data(($row['deleted']?'yes':'no'),$row['text'],$file);
			else
				$r->add_data($row['text'],$file);
		}
		if($this->inline) {
			print('<a '.$this->create_callback_href(array($this,'attach_file')).'>'.$this->lang->t('Attach file').'</a>');
		} else {
			Base_ActionBarCommon::add('add','Attach file',$this->create_callback_href(array($this,'attach_file')));
		}

		$this->display_module($gb);
	}

	public function edition_history($id) {
		if($this->is_back()) return false;
		$gb = $this->init_module('Utils/GenericBrowser',null,'h'.$this->key);
		$gb->set_table_columns(array(
				array('name'=>'Revision', 'order'=>'uac.revision'),
				array('name'=>'Date', 'order'=>'comment_on'),
				array('name'=>'Who', 'order'=>'comment_by'),
				array('name'=>'Comment', 'order'=>'uac.text')
			));

		$ret = $gb->query_order_limit('SELECT uac.revision,uac.created_on as comment_on,(SELECT l.login FROM user_login l WHERE uac.created_by=l.id) as comment_by,uac.text FROM utils_attachment_comment uac WHERE uac.attach_id='.$id, 'SELECT count(*) FROM utils_attachment_comment uac WHERE uac.attach_id='.$id);
		while($row = $ret->FetchRow()) {
			$r = $gb->get_new_row();
			$r->add_data($row['revision'],$row['comment_on'],$row['comment_by'],$row['text']);
		}
		if($this->inline)
			print('<a '.$this->create_back_href().'>'.$this->lang->t('back').'</a>');
		else
			Base_ActionBarCommon::add('back','Back',$this->create_back_href());

		$this->display_module($gb);
		return true;
	}

	public function attach_file() {
		$form = & $this->init_module('Utils/FileUpload',array(false));
		$form->addElement('header', 'upload', $this->lang->t('Attach file'));
		$fck = $form->addElement('fckeditor', 'comment', $this->lang->t('Comment'));
		$fck->setFCKProps('800','300',true);
		$this->ret_attach = true;
		$this->display_module($form, array( array($this,'submit_attach') ));
		return $this->ret_attach;
	}

	public function submit_attach($file,$oryg,$data) {
		DB::Execute('INSERT INTO utils_attachment_link(attachment_key,original,created_by,local) VALUES(%s,%s,%d,%s)',array($this->key,$oryg,Base_UserCommon::get_my_user_id(),$this->group));
		$id = DB::Insert_ID('utils_attachment_link','id');
		DB::Execute('INSERT INTO utils_attachment_comment(attach_id,text,created_by,revision) VALUES(%d,%s,%d,0)',array($id,$data['comment'],Base_UserCommon::get_my_user_id()));
		if($file) {
			$local = $this->group.'/'.$id;
			@mkdir($this->get_data_dir().$this->group,0777,true);
			rename($file,$this->get_data_dir().$local);
		}
		$this->ret_attach = false;
	}

	public function edit_comment($id,$text) {
		if($this->is_back()) return false;
		$f = $this->init_module('Libs/QuickForm','edit_comment');
		$f->addElement('header',null,$this->lang->t('Edit comment'));
		$fck = $f->addElement('fckeditor','comment', $this->lang->t('Comment'));
		$fck->setFCKProps('800','300',true);
		$f->setDefaults(array('comment'=>$text));
		if($f->validate()) {
			DB::Execute('INSERT INTO utils_attachment_comment(text,attach_id,revision,created_by) VALUES (%s,%d,((SELECT max(x.revision) FROM utils_attachment_comment x WHERE x.attach_id=%d)+1),%d)',array($f->exportValue('comment'),$id,$id,Base_UserCommon::get_my_user_id()));
			return false;
		} else {
			if($this->inline) {
				$s = $f->createElement('submit',null,$this->lang->t('Save'));
				$c = $f->createElement('button',null,$this->lang->t('Cancel'),$this->create_back_href());
				$f->addGroup(array($s,$c));
			} else {
				Base_ActionBarCommon::add('save',$this->lang->t('Save'),$f->get_submit_form_href());
				Base_ActionBarCommon::add('back',$this->lang->t('Cancel'),$this->create_back_href());
			}
			$f->display();
		}
		return true;
	}

	public function delete($id) {
		if($this->persistant_deletion) {
			DB::Execute('DELETE FROM utils_attachment_comment WHERE attach_id=%d',array($id));
			DB::Execute('DELETE FROM utils_attachment_link WHERE id=%d',array($id));
			unlink($this->get_data_dir().$id);
		} else {
			DB::Execute('UPDATE utils_attachment_link SET deleted=1 WHERE id=%d',array($id));
		}
	}
}

?>
