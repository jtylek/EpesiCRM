<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-apps
 * @subpackage staticpage
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_StaticPage extends Module {
	public function body($path) {
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
			print($this->t('No such page').'<br>');
			if(Base_AclCommon::i_am_admin()) print('<a '.$this->create_unique_href(array('edit'=>$path)).'>'.$this->t('Create new one').'</a>');
			return;
		}
		$theme->assign('path',$path);
		$theme->assign('title',$page['title']);
		$content = preg_replace_callback('/href=["\'](.*?)["\']/',array($this,'parse_links_callback'),$page['content']);
		$theme->assign('content',$content);
		$theme->display();
		
		//print('<hr><a '.$this->create_unique_href(array('edit'=>$path)).'>Edit</a>');
		if(Base_AclCommon::i_am_admin())
			Base_ActionBarCommon::add('edit','Edit page',$this->create_unique_href(array('edit'=>$path)));
	}
	
	public function parse_links_callback($x) {
		if(preg_match('/^http[s]?:\/\//i',$x[1]))
			return "href=\"".$x[1].'"';
		return $this->create_unique_href(array('view'=>$x[1]));
	}
	
	private function edit($path) {
		if($this->get_unique_href_variable('delete')) {
			$id = DB::GetOne('SELECT id FROM apps_staticpage_pages WHERE path=%s',$path);
			$this->delete($id);
			//$this->unset_module_variable('view');
			$this->unset_module_variable('edit');
			Base_StatusBarCommon::message($this->t('Page deleted'));
			return;
		}
				
		if($this->is_back()) {
			$this->unset_module_variable('edit');
			location(array());
			return;
		}
		
		$f = &$this->init_module('Libs/QuickForm');

		if($path) {
			if(!($page = DB::Execute('SELECT id,title,content FROM apps_staticpage_pages WHERE path=%s',array($path))) ||
			!($page = $page->FetchRow())) {
				print($this->t('No such page, creating new one'));
				$f->setDefaults(array('path'=>$path));
			} else
				$f->setDefaults(array('path'=>$path,'title'=>$page['title'], 'content'=>$page['content']));
		}
		
		$f->addElement('text', 'path', $this->t('Path'),array('maxlength'=>255));
		$f->addRule('path',$this->t('Field too long, max 255 chars'),'maxlength',255);
		$f->addRule('path',$this->t('This field is required'),'required');
		
		$f->addElement('text', 'title', $this->t('Title'),array('maxlength'=>255));
		$f->addRule('title',$this->t('This field is required'),'required');
		$f->addRule('title',$this->t('Field too long, max 255 chars'),'maxlength',255);
		$fck = & $f->addElement('fckeditor', 'content', $this->t('Content'));
		$fck->setFCKProps('800','300',true);
		
		Base_ActionBarCommon::add('back','Cancel',$this->create_back_href());
		Base_ActionBarCommon::add('save','Save',$f->get_submit_form_href());
//		$save_b = & HTML_QuickForm::createElement('submit', null, $this->ht('Save'));
	//	$back_b = & HTML_QuickForm::createElement('button', null, $this->ht('Cancel'), $this->create_back_href());
		//$f->addGroup(array($save_b,$back_b),'submit_button');

		if(isset($page))
			$menu = &$this->init_module('Utils/CustomMenu',array('staticpage:'.$page['id']));			
		
		if($f->validate()) {
			$ret = $f->exportValues();
			$content = str_replace("\n",'',$ret['content']);
			if(isset($page) && $page) {
				DB::Execute('UPDATE apps_staticpage_pages SET path=%s, title=%s, content=%s WHERE id=%d',array($ret['path'],$ret['title'],$content,$page['id']));
				$menu->save($ret['path']);
				if($this->isset_module_variable('view'))
					$this->set_module_variable('view',$ret['path']);
			} else {
				DB::Execute('INSERT INTO apps_staticpage_pages(path,title,content) VALUES (%s, %s, %s)',array($ret['path'],$ret['title'],$content));
				$this->set_module_variable('view',$ret['path']);
				$this->set_module_variable('menu_edit',array('id'=>DB::Insert_ID('apps_staticpage_pages','id'),'path'=>$ret['path']));
			}
			$this->unset_module_variable('edit');
			Base_StatusBarCommon::message($this->t('Page saved'));
			location(array());
			return;
		}
		$f->display();

		if($path) {
			$this->display_module($menu);
			Base_ActionBarCommon::add('delete','Delete page',$this->create_confirm_unique_href($this->ht('Delete this page?'), array('delete'=>true)));
		}
	}
	
	public function delete($id) {
		Utils_CustomMenuCommon::delete('staticpage:'.$id);
		DB::Execute('DELETE FROM apps_staticpage_pages WHERE id=%d',$id);
		location(array());
	}
	
	private function menu_edit($x) {
		Base_ActionBarCommon::add('save','Save',$this->create_unique_href(array('save'=>true)));
		$menu = &$this->init_module('Utils/CustomMenu',array('staticpage:'.$x['id']));			
		if($this->get_unique_href_variable('save')) {
			$menu->save($x['path']);
			$this->unset_module_variable('menu_edit');
			Base_StatusBarCommon::message($this->t('Menu entries saved'));
			location(array());
		}
		$this->display_module($menu);
	}
	
	public function admin() {
		$edit = $this->get_module_variable_or_unique_href_variable('edit');
		if(isset($edit)) return $this->edit($edit);

		$menu_edit = $this->get_module_variable_or_unique_href_variable('menu_edit');
		if(isset($menu_edit)) return $this->menu_edit($menu_edit);

		if($this->is_back()) {
			$this->unset_module_variable('view');
		} else
			$view = $this->get_module_variable_or_unique_href_variable('view');
		if(isset($view)) { 
			$this->body($view);
//			print('<hr><a '.$this->create_back_href().'>Go back</a>');
			Base_ActionBarCommon::add('back','Go back',$this->create_back_href());
			return;
		}
		
		$gb = & $this->init_module('Utils/GenericBrowser',null,'apps_staticpage_pages');
		$ret = $gb->query_order_limit('SELECT id,path,title FROM apps_staticpage_pages','SELECT count(*) FROM apps_staticpage_pages');
		$gb->set_table_columns(array(
			array('name'=>$this->t('Path'), 'width'=>30,'order'=>'path'),
			array('name'=>$this->t('Title'), 'width'=>50,'order'=>'title'),
				));
		while($row=$ret->FetchRow()) {
			$r = & $gb->get_new_row();
			$r->add_data($row['path'],$row['title']);
			$r->add_action($this->create_unique_href(array('edit'=>$row['path'])),'Edit');
			$r->add_action($this->create_unique_href(array('view'=>$row['path'])),'View');
			$r->add_action($this->create_confirm_callback_href($this->t('Are you sure?'),array($this,'delete'),$row['id']),'Delete');
		}
		$this->display_module($gb);
		
//		print('<hr><a '.$this->create_unique_href(array('edit'=>false)).'>New</a>');
		Base_ActionBarCommon::add('add','New page',$this->create_unique_href(array('edit'=>false)));
	}

	public function caption() {
		$edit = $this->isset_module_variable('edit');
		if($edit)
			return "Editing page";
		return "Page";
	}
}

?>