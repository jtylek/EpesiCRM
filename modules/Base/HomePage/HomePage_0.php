<?php
/**
 * HomePage class.
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage homepage
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_HomePage extends Module {
	private $m;
	public function body() {
		$this->show_home_page(Base_HomePageCommon::get_my_homepage());
	}
	public function show_home_page($page) {
		if (!isset($page[0])) return;
		if (!isset($page[1])) $page[1] = 'body';
		if (!isset($page[2])) $page[2] = null;
		if (!isset($page[3])) $page[3] = null;
		$this->m = $this->pack_module($page[0], $page[2], $page[1], $page[3]);
	}
	public function caption() {
		if (isset($this->m)) return $this->m->caption();
	}

	public function admin() {
		if ($this->is_back()) {
			$this->parent->reset();
		}
		Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
		Base_ActionBarCommon::add('add', __('Add Home Page'), $this->create_callback_href(array($this, 'edit_home_page')));

		$gb = $this->init_module('Utils/GenericBrowser', null, 'home_page_admin');
		$gb->set_table_columns(array(
			array('name'=>'Home Page'),
			array('name'=>'Clearance')
		));
		$pages = DB::Execute('SELECT * FROM base_home_page ORDER BY priority');
		$next = null;
		while ($row = $pages->FetchRow()) {
			$gbr = $gb->get_new_row();
			$clearances = DB::GetAssoc('SELECT id, clearance FROM base_home_page_clearance WHERE home_page_id=%d', array($row['id']));
			$gbr->add_data($row['home_page'], Base_AclCommon::display_clearances($clearances));
			if ($next) $next->add_action($this->create_callback_href(array($this, 'change_priority'),array($last_row['id'],$last_row['priority'], +1)),'Move down', null, 'move-down');
            if ($row['priority']>1) $gbr->add_action($this->create_callback_href(array($this, 'change_priority'),array($row['id'],$row['priority'], -1)),'Move up', null, 'move-up');
			$gbr->add_action($this->create_callback_href(array($this, 'delete_home_page'),array($row['id'])),'Delete');
			$gbr->add_action($this->create_callback_href(array($this, 'edit_home_page'),array($row['id'])),'Edit');
			$next = $gbr;
			$last_row = $row;
		}
		$this->display_module($gb);
		eval_js('base_home_page__initialized = false;');
	}
	public function delete_home_page($id) {
        DB::StartTrans();
		$prio = DB::GetOne('SELECT priority FROM base_home_page WHERE id=%d', array($id));
		DB::Execute('UPDATE base_home_page SET priority=priority-1 WHERE priority>%d', array($prio));
		DB::Execute('DELETE FROM base_home_page_clearance WHERE home_page_id=%d', array($id));
		DB::Execute('DELETE FROM base_home_page WHERE id=%d', array($id));
        DB::CompleteTrans();
	}
	public function change_priority($id, $priority, $move) {
        DB::StartTrans();
        DB::Execute('UPDATE base_home_page SET priority=%d WHERE priority=%d', array($priority, $priority+$move));
        DB::Execute('UPDATE base_home_page SET priority=%d WHERE id=%d', array($priority+$move, $id));
        DB::CompleteTrans();
        return false;
	}
	public function edit_home_page($id=null) {
		if ($this->is_back())
			return false;

		$counts = 5;
		$all_clearances = array(''=>'---')+array_flip(Base_AclCommon::get_clearance(true));
		$home_pages = array(''=>'---');
		$current_clearance = 0;

		$form = $this->init_module('Libs_QuickForm');
		$theme = $this->init_module('Base_Theme');

		$theme->assign('labels', array(
			'and' => '<span class="joint">'.__('and').'</span>',
			'or' => '<span class="joint">'.__('or').'</span>',
			'caption' => $id?__('Edit Home Page'):__('Add Home Page'),
			'clearance' => __('Clearance requried'),
			'fields' => __('Fields allowed'),
			'crits' => __('Criteria required'),
			'add_clearance' => __('Add clearance'),
			'add_or' => __('Add criteria (or)'),
			'add_and' => __('Add criteria (and)')
 		));

		$tmp = Base_HomePageCommon::get_home_pages();
		$home_pages = array();
		foreach ($tmp as $k=>$v) 
			$home_pages[$k] = _V($k); // ****** - translating home_page options
		$form->addElement('select', 'home_page', __('Target Home Page'), array(''=>'---') + $home_pages);
		if ($id) {
			$page = DB::GetOne('SELECT home_page FROM base_home_page WHERE id=%d', array($id));
			$form->setDefaults(array('home_page'=>$page));
		}
		$form->addRule('home_page', __('Field required'), 'required');

		for ($i=0; $i<$counts; $i++)
			$form->addElement('select', 'clearance_'.$i, __('Clearance'), $all_clearances);
		
		$i = 0;
		$clearances = DB::GetAssoc('SELECT id, clearance FROM base_home_page_clearance WHERE home_page_id=%d', array($id));
		foreach ($clearances as $v) {
			$form->setDefaults(array('clearance_'.$i=>$v));
			$i++;
		}
		$current_clearance = max($i-1, 0);
		
		if ($form->validate()) {
			DB::StartTrans();
			$vals = $form->exportValues();
			$clearances = array();
			for ($i=0; $i<$counts; $i++)
				if ($vals['clearance_'.$i]) $clearances[] = $vals['clearance_'.$i];
			if ($id!==null) {
				DB::Execute('DELETE FROM base_home_page_clearance WHERE home_page_id=%d', array($id));
				DB::Execute('UPDATE base_home_page SET home_page=%s WHERE id=%d', array($vals['home_page'], $id));
			} else {
				$prio = DB::GetOne('SELECT MAX(priority) FROM base_home_page') + 1;
				DB::Execute('INSERT INTO base_home_page (home_page,priority) VALUES (%s, %d)', array($vals['home_page'], $prio));
				$id = DB::Insert_ID('base_home_page', 'id');
			}
			foreach ($clearances as $c)
				DB::Execute('INSERT INTO base_home_page_clearance (home_page_id, clearance) VALUES (%d, %s)', array($id, $c));
			DB::CompleteTrans();
			return false;
		}
		$form->add_error_closing_buttons();

		$form->assign_theme('form', $theme);
		$theme->assign('counts', $counts);
		
		$theme->display('edit_home_pages');

		load_js('modules/Base/HomePage/edit_home_pages.js');
		eval_js('base_home_page__init_clearance('.$current_clearance.', '.$counts.')');
		eval_js('base_home_page__initialized = true;');

		Base_ActionBarCommon::add('save', __('Save'), $form->get_submit_form_href());
		Base_ActionBarCommon::add('delete', __('Cancel'), $this->create_back_href());

		return true;
	}
}

?>
