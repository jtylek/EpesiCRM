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
	private $real_key;
	private $group;
	private $persistant_deletion = false;
	private $view = true;
	private $edit = true;
	private $download = true;
	private $view_deleted = true;
	private $inline = false;
	private $add_header = '';

	public function construct($key,$group='',$pd=null,$in=null,$v=null,$e=null,$d=null,$vd=null,$header=null) {
		if(!isset($key)) trigger_error('Key not given to attachment module',E_USER_ERROR);
		$this->lang = & $this->init_module('Base/Lang');
		$this->group = $group;
		$this->real_key = $key;
		$this->key = md5($key);

		if(isset($pd)) $this->persistant_deletion = $pd;
		if(isset($in)) $this->inline = $in;
		if(isset($v)) $this->view = $v;
		if(isset($e)) $this->edit = $e;
		if(isset($d)) $this->download = $d;
		if(isset($vd)) $this->view_deleted = $vd;
		if(isset($header)) $this->add_header = $header;
	}

	public function additional_header($x) {
		$this->add_header = $x;
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
			$f = $this->init_module('Libs/QuickForm',null,'view_deleted');
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
		$cols[] = array('name'=>'Note', 'order'=>'uac.text','width'=>80);
		$cols[] = array('name'=>'Attachment', 'order'=>'ual.original','width'=>5);
		$gb->set_table_columns($cols);

		//tag for get.php
		$this->set_module_variable('download',$this->download);
		$this->set_module_variable('key',$this->key);
		$this->set_module_variable('group',$this->group);
		if($vd)
			$ret = $gb->query_order_limit('SELECT ual.deleted,ual.local,uac.revision as note_revision,uaf.revision as file_revision,ual.id,uac.created_on as note_on,(SELECT l.login FROM user_login l WHERE uac.created_by=l.id) as note_by,uac.text,uaf.original,uaf.created_on as upload_on,(SELECT l2.login FROM user_login l2 WHERE uaf.created_by=l2.id) as upload_by FROM utils_attachment_link ual INNER JOIN (utils_attachment_note uac,utils_attachment_file uaf) ON (uac.attach_id=ual.id AND uaf.attach_id=ual.id) WHERE ual.attachment_key=\''.$this->key.'\' AND ual.local='.DB::qstr($this->group).' AND uac.revision=(SELECT max(x.revision) FROM utils_attachment_note x WHERE x.attach_id=uac.attach_id) AND uaf.revision=(SELECT max(x.revision) FROM utils_attachment_file x WHERE x.attach_id=uaf.attach_id)','SELECT count(*) FROM utils_attachment_link ual WHERE ual.attachment_key=\''.$this->key.'\' AND ual.local='.DB::qstr($this->group));
		else
			$ret = $gb->query_order_limit('SELECT ual.local,uac.revision as note_revision,uaf.revision as file_revision,ual.id,uac.created_on as note_on,(SELECT l.login FROM user_login l WHERE uac.created_by=l.id) as note_by,uac.text,uaf.original,uaf.created_on as upload_on,(SELECT l2.login FROM user_login l2 WHERE uaf.created_by=l2.id) as upload_by FROM utils_attachment_link ual INNER JOIN (utils_attachment_note uac,utils_attachment_file uaf) ON (uac.attach_id=ual.id AND ual.id=uaf.attach_id) WHERE ual.attachment_key=\''.$this->key.'\' AND ual.local='.DB::qstr($this->group).' AND uac.revision=(SELECT max(x.revision) FROM utils_attachment_note x WHERE x.attach_id=uac.attach_id) AND uaf.revision=(SELECT max(x.revision) FROM utils_attachment_file x WHERE x.attach_id=uaf.attach_id) AND ual.deleted=0','SELECT count(*) FROM utils_attachment_link ual WHERE ual.attachment_key=\''.$this->key.'\' AND ual.local='.DB::qstr($this->group).' AND ual.deleted=0');
		while($row = $ret->FetchRow()) {
			$r = $gb->get_new_row();

			$file = $this->get_file($row);//'<a href="modules/Utils/Attachment/get.php?'.http_build_query(array('id'=>$row['id'],'revision'=>$row['file_revision'],'path'=>$this->get_path(),'cid'=>CID)).'">'.$row['original'].'</a>';
			$info = $this->lang->t('Last note by %s<br>Last note on %s<br>Number of note edits: %d<br>Last file uploaded by %s<br>Last file uploaded on %s<br>Number of file uploads: %d',array($row['note_by'],Base_RegionalSettingsCommon::time2reg($row['note_on']),$row['note_revision'],$row['upload_by'],Base_RegionalSettingsCommon::time2reg($row['upload_on']),$row['file_revision']));
			$r->add_info($info);
			if($this->edit) {
				if($this->inline) {
					$r->add_action($this->create_callback_href(array($this,'edit_note'),array($row['id'],$row['text'])),'edit');
					$r->add_action($this->create_callback_href(array($this,'edition_history'),$row['id']),'history');
				} else {
					$r->add_action($this->create_callback_href(array($this,'edit_note_queue'),array($row['id'],$row['text'])),'edit');
					$r->add_action($this->create_callback_href(array($this,'edition_history_queue'),$row['id']),'history');
				}
				$r->add_action($this->create_confirm_callback_href($this->lang->ht('Delete this entry?'),array($this,'delete'),$row['id']),'delete');
			}
			if($this->inline)
				$r->add_action($this->create_callback_href(array($this,'view'),array($row['id'])),'view');
			else
				$r->add_action($this->create_callback_href(array($this,'view_queue'),array($row['id'])),'view');
			$text = $row['text'];
			if(strlen($text)>160)
				$text = array('value'=>substr($text,0,160).'...'.$this->lang->t('[cut]'),'hint'=>$this->lang->t('Click on view icon to see full note'));
			if($vd)
				$r->add_data(($row['deleted']?'yes':'no'),$text,$file);
			else
				$r->add_data($text,$file);
		}
		if($this->inline) {
			print('<a '.$this->create_callback_href(array($this,'attach_file')).'>'.$this->lang->t('Attach note').'</a>');
		} else {
			Base_ActionBarCommon::add('folder','Attach note',$this->create_callback_href(array($this,'attach_file_queue')));
		}

		$this->display_module($gb);
	}

	public function view_queue($id) {
		$this->push_box0('view',array($id),array($this->real_key,$this->group,$this->persistant_deletion,$this->inline,$this->view,$this->edit,$this->download,$this->view_deleted,$this->add_header));
	}

	public function get_file($row) {
		static $th;
		if(!isset($th)) $th = $this->init_module('Base/Theme');

		$lid = 'get_file_'.md5($this->get_path()).'_'.$row['id'].'_'.$row['file_revision'];
		$th->assign('view','<a href="modules/Utils/Attachment/get.php?'.http_build_query(array('id'=>$row['id'],'revision'=>$row['file_revision'],'path'=>$this->get_path(),'cid'=>CID,'view'=>1)).'" target="_blank" id="view_'.$lid.'">'.$this->lang->t('View').'</a><br>');
		$th->assign('download','<a href="modules/Utils/Attachment/get.php?'.http_build_query(array('id'=>$row['id'],'revision'=>$row['file_revision'],'path'=>$this->get_path(),'cid'=>CID)).'" id="download_'.$lid.'">'.$this->lang->t('Download').'</a><br>');
		eval_js('Event.observe(\'view_'.$lid.'\',\'click\', function(){leightbox_deactivate("'.$lid.'")})');
		eval_js('Event.observe(\'download_'.$lid.'\',\'click\', function(){leightbox_deactivate("'.$lid.'")})');

		ob_start();
		$th->display('download');
		$c = ob_get_clean();

		return '<div class="leightbox" id="'.$lid.'">'.
			$c.
			'<a class="lbAction" rel="deactivate">Close</a></div>'.
			'<a class="lbOn" rel="'.$lid.'">'.$row['original'].'</a>';
	}

	public function view($id) {
		if($this->is_back()) {
			if($this->inline) return false;
			return $this->pop_box0();
		}

		$row = DB::GetRow('SELECT ual.deleted,ual.local,uac.revision as note_revision,uaf.revision as file_revision,ual.id,uac.created_on as note_on,(SELECT l.login FROM user_login l WHERE uac.created_by=l.id) as note_by,uac.text,uaf.original,uaf.created_on as upload_on,(SELECT l2.login FROM user_login l2 WHERE uaf.created_by=l2.id) as upload_by FROM utils_attachment_link ual INNER JOIN (utils_attachment_note uac,utils_attachment_file uaf) ON (uac.attach_id=ual.id AND uaf.attach_id=ual.id) WHERE ual.id=%d AND uac.revision=(SELECT max(x.revision) FROM utils_attachment_note x WHERE x.attach_id=uac.attach_id) AND uaf.revision=(SELECT max(x.revision) FROM utils_attachment_file x WHERE x.attach_id=uaf.attach_id)',array($id));

		$info = $this->lang->t('Last note by %s<br>Last note on %s<br>Number of note edits: %d<br>Last file uploaded by %s<br>Last file uploaded on %s<br>Number of file uploads: %d',array($row['note_by'],Base_RegionalSettingsCommon::time2reg($row['note_on']),$row['note_revision'],$row['upload_by'],Base_RegionalSettingsCommon::time2reg($row['upload_on']),$row['file_revision']));
		if($this->inline) {
			if($this->edit) {
				print('<a '.$this->create_callback_href(array($this,'edit_note'),array($id,$row['text'])).'>'.$this->lang->t('Edit').'</a> :: ');
				print('<a '.$this->create_callback_href(array($this,'edition_history'),$id).'>'.$this->lang->t('History').'</a> :: ');
				print('<a '.$this->create_confirm_callback_href($this->lang->ht('Delete this entry?'),array($this,'delete_back'),$id).'>'.$this->lang->t('Delete').'</a> :: ');
			}
			print('<a '.$this->create_back_href().'>'.$this->lang->t('back').'</a><br>');
		} else {
			if($this->edit) {
				Base_ActionBarCommon::add('edit','Edit',$this->create_callback_href(array($this,'edit_note_queue'),array($id,$row['text'])));
				Base_ActionBarCommon::add('history','Edition history',$this->create_callback_href(array($this,'edition_history_queue'),$id));
				Base_ActionBarCommon::add('delete','Delete',$this->create_confirm_callback_href($this->lang->ht('Delete this entry?'),array($this,'delete_back'),$id));
			}
			Base_ActionBarCommon::add('back','Back',$this->create_back_href());
		}

		print($row['text']);
		return true;
	}

	public function delete_back($id) {
		$this->delete($id);
		$this->set_back_location();
		return false;
	}

	public function edition_history_queue($id) {
		$this->push_box0('edition_history',array($id),array($this->real_key,$this->group,$this->persistant_deletion,$this->inline,$this->view,$this->edit,$this->download,$this->view_deleted,$this->add_header));
	}

	public function edition_history($id) {
		if($this->is_back()) {
			if($this->inline) return false;
			return $this->pop_box0();
		}

		if($this->inline)
			print('<a '.$this->create_back_href().'>'.$this->lang->t('back').'</a>');
		else
			Base_ActionBarCommon::add('back','Back',$this->create_back_href());


		$gb = $this->init_module('Utils/GenericBrowser',null,'hn'.$this->key);
		$gb->set_table_columns(array(
				array('name'=>'Revision', 'order'=>'uac.revision','width'=>5),
				array('name'=>'Date', 'order'=>'note_on','width'=>15),
				array('name'=>'Who', 'order'=>'note_by','width'=>15),
				array('name'=>'Note', 'order'=>'uac.text')
			));

		$ret = $gb->query_order_limit('SELECT uac.revision,uac.created_on as note_on,(SELECT l.login FROM user_login l WHERE uac.created_by=l.id) as note_by,uac.text FROM utils_attachment_note uac WHERE uac.attach_id='.$id, 'SELECT count(*) FROM utils_attachment_note uac WHERE uac.attach_id='.$id);
		while($row = $ret->FetchRow()) {
			$r = $gb->get_new_row();
			if($this->edit)
				$r->add_action($this->create_callback_href(array($this,'restore_note'),array($id,$row['revision'])),'restore');
			$r->add_data($row['revision'],$row['note_on'],$row['note_by'],$row['text']);
		}
		$this->display_module($gb);

		$this->set_module_variable('download',$this->download);
		$this->set_module_variable('key',$this->key);
		$this->set_module_variable('group',$this->group);

		$gb = $this->init_module('Utils/GenericBrowser',null,'ha'.$this->key);
		$gb->set_table_columns(array(
				array('name'=>'Revision', 'order'=>'file_revision','width'=>5),
				array('name'=>'Date', 'order'=>'upload_on','width'=>15),
				array('name'=>'Who', 'order'=>'upload_by','width'=>15),
				array('name'=>'Attachment', 'order'=>'uaf.original')
			));

		$ret = $gb->query_order_limit('SELECT uaf.attach_id as id,uaf.revision as file_revision,uaf.created_on as upload_on,(SELECT l.login FROM user_login l WHERE uaf.created_by=l.id) as upload_by,uaf.original FROM utils_attachment_file uaf WHERE uaf.attach_id='.$id, 'SELECT count(*) FROM utils_attachment_file uaf WHERE uaf.attach_id='.$id);
		while($row = $ret->FetchRow()) {
			$r = $gb->get_new_row();
			if($this->edit)
				$r->add_action($this->create_callback_href(array($this,'restore_file'),array($id,$row['file_revision'])),'restore');
			$file = $this->get_file($row);
			$r->add_data($row['file_revision'],$row['upload_on'],$row['upload_by'],$file);
		}
		$this->display_module($gb);

		return true;
	}

	public function restore_note($id,$rev) {
		DB::StartTrans();
		$text = DB::GetOne('SELECT text FROM utils_attachment_note WHERE attach_id=%d AND revision=%d',array($id,$rev));
		$rev2 = DB::GetOne('SELECT max(x.revision) FROM utils_attachment_note x WHERE x.attach_id=%d',array($id));
		DB::Execute('INSERT INTO utils_attachment_note(text,attach_id,revision,created_by) VALUES (%s,%d,%d,%d)',array($text,$id,$rev2+1,Base_UserCommon::get_my_user_id()));
		DB::CompleteTrans();
	}

	public function restore_file($id,$rev) {
		DB::StartTrans();
		$original = DB::GetOne('SELECT original FROM utils_attachment_file WHERE attach_id=%d AND revision=%d',array($id,$rev));
		$rev2 = DB::GetOne('SELECT max(x.revision) FROM utils_attachment_file x WHERE x.attach_id=%d',array($id));
		$rev2 = $rev2+1;
		DB::Execute('INSERT INTO utils_attachment_file(attach_id,original,created_by,revision) VALUES(%d,%s,%d,%d)',array($id,$original,Base_UserCommon::get_my_user_id(),$rev2));
		DB::CompleteTrans();
		$local = $this->get_data_dir().$this->group.'/'.$id.'_';
		copy($local.$rev,$local.$rev2);
	}

	public function attach_file_queue() {
		$this->push_box0('attach_file',array(),array($this->real_key,$this->group,$this->persistant_deletion,$this->inline,$this->view,$this->edit,$this->download,$this->view_deleted,$this->add_header));
	}

	public function pop_box0() {
		$x = ModuleManager::get_instance('/Base_Box|0');
		if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$x->pop_main();
	}

	public function push_box0($func,$args,$const_args) {
		$x = ModuleManager::get_instance('/Base_Box|0');
		if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$x->push_main('Utils/Attachment',$func,$args,$const_args);
	}

	public function attach_file() {
		if(!$this->is_back()) {
			$form = & $this->init_module('Utils/FileUpload',array(false));
			$form->addElement('header', 'upload', $this->lang->t('Attach note').': '.$this->add_header);
			$fck = $form->addElement('fckeditor', 'note', $this->lang->t('Note'));
			$fck->setFCKProps('800','300');
			$form->set_upload_button_caption('Save');
			if($form->getSubmitValue('note')=='' && $form->getSubmitValue('uploaded_file')=='')
				$form->addRule('note',$this->lang->t('Please enter note or choose file'),'required');

			$form->add_upload_element();

			if(!$this->inline) {
				Base_ActionBarCommon::add('save','Save',$form->get_submit_form_href());
				Base_ActionBarCommon::add('back','Back',$this->create_back_href());
			} else {
				$s = HTML_QuickForm::createElement('button',null,$this->lang->t('Save'),$form->get_submit_form_href());
				$c = HTML_QuickForm::createElement('button',null,$this->lang->t('Cancel'),$this->create_back_href());
				$form->addGroup(array($s,$c));
			}
			$this->ret_attach = true;
			$this->display_module($form, array( array($this,'submit_attach') ));
		} else {
			$this->ret_attach = false;
		}

		if($this->inline)
			return $this->ret_attach;
		elseif(!$this->ret_attach)
			return $this->pop_box0();
	}

	public function submit_attach($file,$oryg,$data) {
		DB::Execute('INSERT INTO utils_attachment_link(attachment_key,local) VALUES(%s,%s)',array($this->key,$this->group));
		$id = DB::Insert_ID('utils_attachment_link','id');
		DB::Execute('INSERT INTO utils_attachment_file(attach_id,original,created_by,revision) VALUES(%d,%s,%d,0)',array($id,$oryg,Base_UserCommon::get_my_user_id()));
		DB::Execute('INSERT INTO utils_attachment_note(attach_id,text,created_by,revision) VALUES(%d,%s,%d,0)',array($id,$data['note'],Base_UserCommon::get_my_user_id()));
		if($file) {
			$local = $this->get_data_dir().$this->group;
			@mkdir($local,0777,true);
			rename($file,$local.'/'.$id.'_0');
		}
		$this->ret_attach = false;
	}

	public function edit_note_queue($id,$text) {
		$this->push_box0('edit_note',array($id,$text),array($this->real_key,$this->group,$this->persistant_deletion,$this->inline,$this->view,$this->edit,$this->download,$this->view_deleted,$this->add_header));
	}

	public function edit_note($id,$text) {
		if(!$this->is_back()) {
			$form = & $this->init_module('Utils/FileUpload',array(false));
			$form->addElement('header', 'upload', $this->lang->t('Edit note').': '.$this->add_header);
			$fck = $form->addElement('fckeditor', 'note', $this->lang->t('Note'));
			$form->setDefaults(array('note'=>$text));
			$fck->setFCKProps('800','300');
			$form->set_upload_button_caption('Save');
			if($form->getSubmitValue('note')=='' && $form->getSubmitValue('uploaded_file')=='')
				$form->addRule('note',$this->lang->t('Please enter note or choose file'),'required');

			$form->addElement('header',null,$this->lang->t('Replace attachment with file'));
			$form->add_upload_element();

			if(!$this->inline) {
				Base_ActionBarCommon::add('save','Save',$form->get_submit_form_href());
				Base_ActionBarCommon::add('back','Back',$this->create_back_href());
			} else {
				$s = HTML_QuickForm::createElement('button',null,$this->lang->t('Save'),$form->get_submit_form_href());
				$c = HTML_QuickForm::createElement('button',null,$this->lang->t('Cancel'),$this->create_back_href());
				$form->addGroup(array($s,$c));
			}

			$this->ret_attach = true;
			$this->display_module($form, array( array($this,'submit_edit'),$id,$text));
		} else {
			$this->ret_attach = false;
		}

		if($this->inline)
			return $this->ret_attach;
		elseif(!$this->ret_attach)
			return $this->pop_box0();
	}

	public function submit_edit($file,$oryg,$data,$id,$text) {
		if($data['note']!=$text) {
			DB::StartTrans();
			$rev = DB::GetOne('SELECT max(x.revision) FROM utils_attachment_note x WHERE x.attach_id=%d',array($id));
			DB::Execute('INSERT INTO utils_attachment_note(text,attach_id,revision,created_by) VALUES (%s,%d,%d,%d)',array($data['note'],$id,$rev+1,Base_UserCommon::get_my_user_id()));
			DB::CompleteTrans();
		}
		if($file) {
			DB::StartTrans();
			$rev = DB::GetOne('SELECT max(x.revision) FROM utils_attachment_file x WHERE x.attach_id=%d',array($id));
			$rev = $rev+1;
			DB::Execute('INSERT INTO utils_attachment_file(attach_id,original,created_by,revision) VALUES(%d,%s,%d,%d)',array($id,$oryg,Base_UserCommon::get_my_user_id(),$rev));
			DB::CompleteTrans();
			$local = $this->get_data_dir().$this->group;
			@mkdir($local,0777,true);
			rename($file,$local.'/'.$id.'_'.$rev);
		}
		$this->ret_attach = false;
	}

	public function delete($id) {
		if($this->persistant_deletion) {
			DB::Execute('DELETE FROM utils_attachment_note WHERE attach_id=%d',array($id));
			$rev = DB::GetOne('SELECT count(*) FROM utils_attachment_file WHERE attach_id=%d',array($id));
			$file_base = $this->get_data_dir().$this->group.'/'.$id.'_';
			for($i=0; $i<$rev; $i++)
			    @unlink($file_base.$i);
			DB::Execute('DELETE FROM utils_attachment_file WHERE attach_id=%d',array($id));
			DB::Execute('DELETE FROM utils_attachment_link WHERE id=%d',array($id));
		} else {
			DB::Execute('UPDATE utils_attachment_link SET deleted=1 WHERE id=%d',array($id));
		}
	}
}

?>
