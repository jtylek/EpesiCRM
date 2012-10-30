<?php
/**
 * AclInit class.
 * 
 * This class provides initialization data for Acl module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage acl
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Acl extends Module {
	public function admin() {
		if ($this->is_back()) {
			$this->parent->reset();
		}
		Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());

		$all_clearances = array_flip(Base_AclCommon::get_clearance(true));
		Base_ThemeCommon::load_css('Base_Acl', 'edit_permissions');

		$gb = $this->init_module('Utils_GenericBrowser', 'acl_editor', 'acl_editor');
		$gb->set_table_columns(array(
			array('name'=>'&nbsp;', 'width'=>20)
		));
		$perms = DB::GetAssoc('SELECT id, name FROM base_acl_permission ORDER BY name ASC');
		Base_ActionBarCommon::add('add', __('Add rule'), $this->create_callback_href(array($this, 'edit_rule'), array(null, null)));
		foreach ($perms as $p_id=>$p_name) {
			$gb_row = $gb->get_new_row();
			$gb_row->add_data(
				array('value'=>_V($p_name), 'class'=>'Base_Acl__permission', 'attrs'=>'colspan="2"')
			);
			$gb_row->no_actions();
			$perms = DB::GetAssoc('SELECT id, id FROM base_acl_rules WHERE permission_id=%d', array($p_id));
			foreach ($perms as $r_id) {
				$clearances = DB::GetAssoc('SELECT id, clearance FROM base_acl_rules_clearance WHERE rule_id=%d', array($r_id));
				foreach ($clearances as $k=>$v)
					if (isset($all_clearances[$v])) $clearances[$k] = $all_clearances[$v];
					else unset($clearances[$k]);

				$gb_row = $gb->get_new_row();
				$gb_row->add_action($this->create_confirm_callback_href(__('Are you sure you want to delete this rule?'), array($this, 'delete_rule'), array($r_id)), 'delete', __('Delete Rule'));
				$gb_row->add_action($this->create_callback_href(array($this, 'edit_rule'), array($r_id, $p_id)), 'edit', __('Edit Rule'));
				$gb_row->add_data(
					'<span class="Base_Acl__permissions_clearance">'.implode(' <span class="joint">'.__('and').'</span> ',$clearances).'</span>'
				);
			}
		}
		$this->display_module($gb);
		eval_js('base_acl__initialized = false;');
	}
	public function edit_rule($r_id, $p_id=null) {
		if ($this->is_back())
			return false;
		$counts = 5;
		$all_clearances = array(''=>'---')+array_flip(Base_AclCommon::get_clearance(true));
		$perms = array(''=>'---')+DB::GetAssoc('SELECT id, name FROM base_acl_permission ORDER BY name ASC');
		$current_clearance = 0;

		$form = $this->init_module('Libs_QuickForm');
		$theme = $this->init_module('Base_Theme');

		$theme->assign('labels', array(
			'and' => '<span class="joint">'.__('and').'</span>',
			'or' => '<span class="joint">'.__('or').'</span>',
			'caption' => $r_id?__('Edit permission rule'):__('Add permission rule'),
			'clearance' => __('Clearance requried'),
			'fields' => __('Fields allowed'),
			'crits' => __('Criteria required'),
			'add_clearance' => __('Add clearance'),
			'add_or' => __('Add criteria (or)'),
			'add_and' => __('Add criteria (and)')
 		));

		$form->addElement('select', 'permission', __('Permission'), $perms);
		if ($p_id) {
			$form->setDefaults(array('permission'=>$p_id));
			$form->freeze('permission');
		} else {
			$form->addRule('required', 'permission', __('Field required'));
		}

		for ($i=0; $i<$counts; $i++)
			$form->addElement('select', 'clearance_'.$i, __('Clearance'), $all_clearances);
		
		$i = 0;
		$clearances = DB::GetAssoc('SELECT id, clearance FROM base_acl_rules_clearance WHERE rule_id=%d', array($r_id));
		foreach ($clearances as $v) {
			$form->setDefaults(array('clearance_'.$i=>$v));
			$i++;
		}
		$current_clearance = max($i-1, 0);
		
		if ($form->validate()) {
			$vals = $form->exportValues();
			$clearances = array();
			for ($i=0; $i<$counts; $i++)
				if ($vals['clearance_'.$i]) $clearances[] = $vals['clearance_'.$i];
			if ($r_id!==null) {
				DB::Execute('DELETE FROM base_acl_rules_clearance WHERE rule_id=%d', array($r_id));
			} else {
				if (!$p_id) $p_id = $vals['permission'];
				DB::Execute('INSERT INTO base_acl_rules (permission_id) VALUES (%d)', array($p_id));
				$r_id = DB::Insert_ID('base_acl_rules', 'id');
			}
			foreach ($clearances as $c)
				DB::Execute('INSERT INTO base_acl_rules_clearance (rule_id, clearance) VALUES (%d, %s)', array($r_id, $c));
			return false;
		}

		$form->assign_theme('form', $theme);
		$theme->assign('counts', $counts);
		
		$theme->display('edit_permissions');

		load_js('modules/Base/Acl/edit_permissions.js');
		eval_js('base_acl__init_clearance('.$current_clearance.', '.$counts.')');
		eval_js('base_acl__initialized = true;');

		Base_ActionBarCommon::add('save', __('Save'), $form->get_submit_form_href());
		Base_ActionBarCommon::add('delete', __('Cancel'), $this->create_back_href());

		return true;
	}
	public function delete_rule($r_id) {
		DB::Execute('DELETE FROM base_acl_rules_clearance WHERE rule_id=%d', array($r_id));
		DB::Execute('DELETE FROM base_acl_rules WHERE id=%d', array($r_id));
		return false;
	}
}

?>
