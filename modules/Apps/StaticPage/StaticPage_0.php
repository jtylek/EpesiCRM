<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_StaticPage extends Module {

	public function body($path) {
		if(!$this->lang) $this->lang = $this->pack_module('Base/Lang');

		if(!isset($path))
			$path = $this->get_module_variable_or_unique_href_variable('view');
		else
			$this->set_module_variable('view',$path);
		
		$edit = $this->get_module_variable_or_unique_href_variable('edit');
		if(isset($edit)) return $this->edit($edit);
		
		$theme = & $this->init_module('Base/Theme');
		if(!isset($path) || 
			!($page = DB::Execute('SELECT title,content FROM apps_staticpage_pages WHERE path=%s',$path)) ||
			!($page = $page->FetchRow())) {
			print($this->lang->t('No such page'));
			if(Base_AclCommon::i_am_admin()) print('<a '.$this->create_unique_href(array('edit'=>$path)).'>Create new one</a>');
			return;
		}
		$theme->assign('path',$path);
		$theme->assign('title',$page['title']);
		$content = preg_replace_callback('/href=["\']([\S ]*)["\']/',array($this,'parse_links_callback'),$page['content']);
		$theme->assign('content',$content);
		$theme->display();
		
		//print('<hr><a '.$this->create_unique_href(array('edit'=>$path)).'>Edit</a>');
		if(Base_AclCommon::i_am_admin())
			Base_ActionBarCommon::add_icon('edit','Edit page',$this->create_unique_href(array('edit'=>$path)));
	}
	
	public function parse_links_callback($x) {
		if(ereg('^http[s]{0,1}://',$x[1]))
			return "href=\"".$x[1].'"';
		return $this->create_unique_href(array('view'=>$x[1]));
	}
	
	private function edit($path) {
		if($this->is_back()) {
			$this->unset_module_variable('edit');
			location(array());
			return;
		}
		
		
		$f = &$this->init_module('Libs/QuickForm');

		if($path) {
			if(!($page = DB::Execute('SELECT id,title,content FROM apps_staticpage_pages WHERE path=%s',$path)) ||
			!($page = $page->FetchRow())) {
				print($this->lang->t('No such page, creating new one'));
				$f->setDefaults(array('path'=>$path));
			} else
				$f->setDefaults(array('path'=>$path,'title'=>$page['title'], 'content'=>$page['content']));
		}
		
		$f->addElement('text', 'path', $this->lang->t('Path'),array('maxlength'=>255));
		$f->addRule('path',$this->lang->t('Field too long, max 255 chars'),'maxlength',255);
		$f->addRule('path',$this->lang->t('This field is required'),'required');
		
		$f->addElement('text', 'title', $this->lang->t('Title'),array('maxlength'=>255));
		$f->addRule('title',$this->lang->t('This field is required'),'required');
		$f->addRule('title',$this->lang->t('Field too long, max 255 chars'),'maxlength',255);
		$fck = & $f->addElement('fckeditor', 'content', $this->lang->t('Content'));
		$fck->setFCKProps('800','300',true);
		
		$save_b = & HTML_QuickForm::createElement('submit', null, $this->lang->ht('Save'));
		$back_b = & HTML_QuickForm::createElement('button', null, $this->lang->ht('Cancel'), $this->create_back_href());
		$f->addGroup(array($save_b,$back_b),'submit_button');
		
		if($f->validate()) {
			$ret = $f->exportValues();
			if($page)
				DB::Execute('UPDATE apps_staticpage_pages SET path=%s, title=%s, content=%s WHERE id=%d',array($ret['path'],$ret['title'],$ret['content'],$page['id']));
			else
				DB::Execute('INSERT INTO apps_staticpage_pages(path,title,content) VALUES (%s, %s, %s)',array($ret['path'],$ret['title'],$ret['content']));
			$this->unset_module_variable('edit');
			Base_StatusBarCommon::message($this->lang->t('Page saved'));
			location(array());
			return;
		}
		$f->display();
		
		$this->pack_module('Utils/CustomMenu',array('staticpage:'.$path, $path));
	}
	
	public function delete($path) {
		DB::Execute('DELETE FROM apps_staticpage_pages WHERE path=%s',$path);
		location(array());
	}
	
	public function admin() {
		$this->lang = $this->pack_module('Base/Lang');
		
		$edit = $this->get_module_variable_or_unique_href_variable('edit');
		if(isset($edit)) return $this->edit($edit);

		if($this->is_back()) {
			$this->unset_module_variable('view');
		} else
			$view = $this->get_module_variable_or_unique_href_variable('view');
		if(isset($view)) { 
			$this->body($view);
//			print('<hr><a '.$this->create_back_href().'>Go back</a>');
			Base_ActionBarCommon::add_icon('back','Go back',$this->create_back_href());
			return;
		}
		
		$gb = & $this->init_module('Utils/GenericBrowser',null,'apps_staticpage_pages');
		$ret = $gb->query_order_limit('SELECT path,title FROM apps_staticpage_pages','SELECT count(*) FROM apps_staticpage_pages');
		$gb->set_table_columns(array(
			array('name'=>$this->lang->t('Path'), 'width'=>30,'order'=>'path'),
			array('name'=>$this->lang->t('Title'), 'width'=>50,'order'=>'title'),
				));
		while($row=$ret->FetchRow()) {
			$r = & $gb->get_new_row();
			$r->add_data($row['path'],$row['title']);
			$r->add_action($this->create_unique_href(array('edit'=>$row['path'])),'Edit');
			$r->add_action($this->create_unique_href(array('view'=>$row['path'])),'View');
			$r->add_action($this->create_confirm_callback_href($this->lang->t('Are you sure?'),array($this,'delete'),$row['path']),'Delete');
		}
		$this->display_module($gb);
		
//		print('<hr><a '.$this->create_unique_href(array('edit'=>false)).'>New</a>');
		Base_ActionBarCommon::add_icon('add','New page',$this->create_unique_href(array('edit'=>false)));
	}

}

?>