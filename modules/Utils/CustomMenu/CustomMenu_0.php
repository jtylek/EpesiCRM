<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CustomMenu extends Module {
	private $mid = null;
	private $function = null;
	private $arguments = null;
	
	public function body($id,$arguments=null,$function='body') {
		$this->lang = & $this->pack_module('Base/Lang');
		if(!isset($id)) {
			print('Menu Editor: no ID given - unable to edit menus');
			return;
		}
		
		$this->mid = $id;
		$this->function = $function;
		$this->arguments = $arguments;

		$edit = $this->get_module_variable_or_unique_href_variable('edit');
		if(isset($edit)) return $this->edit($edit);
		
		$gb = & $this->init_module('Utils/GenericBrowser','custommenu');
		$ret = $gb->query_order_limit('SELECT path FROM utils_custommenu_entry WHERE id=\''.md5($this->mid).'\'','SELECT count(*) FROM utils_custommenu_entry WHERE id=\''.md5($this->mid).'\'');
		$gb->set_table_columns(array(
			array('name'=>$this->lang->t('Menu entry path'), 'width'=>70,'order'=>'path'),
				));
		while($row=$ret->FetchRow()) {
			$r = & $gb->get_new_row();
			$r->add_data($row['path']);
			$r->add_action($this->create_unique_href(array('edit'=>$row['path'])),'Edit');
			$r->add_action($this->create_confirm_callback_href($this->lang->ht('Are you sure?'),array($this,'delete'),$row['path']),'Delete');
		}
		$this->display_module($gb);
		
//		print('<hr><a '.$this->create_unique_href(array('edit'=>false)).'>New</a>');
		Base_ActionBarCommon::add_icon('add','New menu entry',$this->create_unique_href(array('edit'=>false)));
	}
	
	private function edit($path) {
		if($this->is_back()) {
			$this->unset_module_variable('edit');
			location(array());
			return;
		}
		
		$f = &$this->init_module('Libs/QuickForm'); //TODO: check length of fields
		
		if($path)
			$f->setDefaults(array('path'=>$path));

		$f->addElement('text', 'path', $this->lang->t('Menu entry path'),array('maxlength'=>255));
		$f->addRule('path',$this->lang->t('This field is required'),'required');
		$f->addRule('path',$this->lang->t('Field too long, max 255 chars'),'maxlength',255);
		
		$save_b = & HTML_QuickForm::createElement('submit', null, $this->lang->ht('Save'));
		$back_b = & HTML_QuickForm::createElement('button', null, $this->lang->ht('Cancel'), $this->create_back_href());
		$f->addGroup(array($save_b,$back_b),'submit_button');
		
		if($f->validate()) {
			$ret = $f->exportValue('path');
			if($path)
				DB::Execute('UPDATE utils_custommenu_entry SET path=%s WHERE path=%d',array($ret,$path));
			else {				
				$module = $this->parent->get_type();
				Utils_CustomMenuCommon::add_entry($this->mid,$ret,$module,$this->function, $this->arguments);
			}
			$this->unset_module_variable('edit');
			location(array());
			return;
		}
		$f->display();
	}
	
	
	public function delete($path) {
		Utils_CustomMenuCommon::del_entry($path);
		location(array());
	}

}

?>