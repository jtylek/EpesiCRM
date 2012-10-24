<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-apps
 * @subpackage activityreport
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_ActivityReport extends Module {
	public function body() {
	    if (!Base_AclCommon::check_permission('View Activity Report')) return;
		$rb_tabs = DB::GetAssoc('SELECT tab, caption FROM recordbrowser_table_properties ORDER BY caption');
		foreach ($rb_tabs as $k=>$v)
			$rb_tabs[$k] = Utils_RecordBrowserCommon::get_caption($k);

		$form = $this->init_module('Libs/QuickForm');

		$users_count = (DB::GetOne('SELECT COUNT(id) FROM user_login') > Base_User_SettingsCommon::get('Utils_RecordBrowser','enable_autocomplete'));
		if ($users_count) {
			$crits = array('!login'=>'');
			$fcallback = array('CRM_ContactsCommon','contact_format_no_company');
			$form->addElement('autoselect', 'user', __('User'), array(), array(array('CRM_ContactsCommon','autoselect_contact_suggestbox'), array($crits, $fcallback)), $fcallback);
		} else {
			$users = DB::GetAssoc('SELECT id, id FROM user_login');
			foreach ($users as $k=>$u)
				$users[$k] = Base_UserCommon::get_user_label($u, true);
			asort($users);
			$users = array(''=>'['.__('All').']')+$users;
			$form->addElement('select', 'user', __('User'), $users);
		}
		
		$form->addElement('multiselect', 'recordsets', __('Record Type'), $rb_tabs);

		$form->addElement('checkbox', 'new', __('New record'));
		$form->addElement('checkbox', 'edit', __('Record edit'));
		$form->addElement('checkbox', 'delete_restore', __('Record Delete/restore'));
		$form->addElement('checkbox', 'note', __('Notes'));
		$form->addElement('checkbox', 'file', __('Files'));

		$form->addElement('datepicker', 'start_date', __('Start Date'));
		$form->addElement('datepicker', 'end_date', __('End Date'));

		//$form->addElement('submit', 'submit', __('Show'));
		Base_ActionBarCommon::add('search', __('Show'), $form->get_submit_form_href());

		$filters = $this->get_module_variable('filters', array('user'=>'', 'new'=>1, 'edit'=>1, 'delete_restore'=>1, 'recordsets'=>array_keys($rb_tabs), 'start_date'=>date('Y-m-01'), 'end_date'=>date('Y-m-d')));
		
		if ($form->validate()) {
			$filters = $form->exportValues();
			$this->set_module_variable('filters', $filters);
		}
		
		$form->setDefaults($filters);

		$theme = $this->init_module('Base/Theme');
		$form->assign_theme('form',$theme);
		$theme->display();
		
		$filters['recordsets'] = array_flip($filters['recordsets']);
		foreach ($rb_tabs as $k=>$v)
			if (!isset($filters['recordsets'][$k])) unset($rb_tabs[$k]);

		$gb = $this->init_module('Utils/GenericBrowser',null,'activity_report');
		$gb->set_table_columns(array(
			array('name'=>__('Date'), 'width'=>40),
			array('name'=>__('User'), 'width'=>40),
			array('name'=>__('Type'), 'width'=>40),
			array('name'=>__('Label')),
			array('name'=>__('Actions taken'), 'width'=>40)
		));
		$tables = array();

		if ($users_count) {
			$filters['user'] = CRM_ContactsCommon::get_contact($filters['user']);
			$filters['user'] = $filters['user']['login'];
		}

		$af_where = array();
		foreach($rb_tabs as $k=>$t)
			$af_where[] = 'ual.local '.DB::like().' '.DB::Concat(DB::qstr($k.'/'),DB::qstr('%'));
		$af_where = ' ('.implode(' OR ',$af_where).')';

		$e_where = array();
		$c_where = '';
		if ($filters['user']) {
			$e_where[] = ' edited_by = '.$filters['user'];
			$c_where = ' created_by = '.$filters['user'];
			$af_where .= ' AND created_by = '.$filters['user'];
		}
		if (isset($filters['edit'])) {
			if (!isset($filters['delete_restore'])) {
				$e_where[] = ' ehd.field!='.DB::qstr('id');
			}
		} else {
			if (isset($filters['delete_restore'])) {
				$e_where[] = ' ehd.field='.DB::qstr('id');
			}
		}
		$an_where = $af_where;
		if ($filters['start_date']) {
			$date = DB::qstr(date('Y-m-d', strtotime($filters['start_date'])));
			$af_where .= ' AND uaf.created_on >= '.$date;
			$an_where .= ' AND uan.created_on >= '.$date;
			$c_where .= ($c_where?' AND':'').' created_on >= '.$date;
			$e_where[] = ' edited_on >= '.$date;
		}
		if ($filters['end_date']) {
			$date = DB::qstr(date('Y-m-d 23:59:59', strtotime($filters['end_date'])));
			$af_where .= ' AND uaf.created_on <= '.$date;
			$an_where .= ' AND uan.created_on <= '.$date;
			$c_where .= ($c_where?' AND':'').' created_on <= '.$date;
			$e_where[] = ' edited_on <= '.$date;
		}
		
		if (!empty($e_where)) $e_where = ' WHERE'.implode(' AND',$e_where);
		else $e_where = '';
		if ($c_where) $c_where = ' WHERE'.$c_where;

		// **** files ****
		if (isset($filters['file']))
			$tables[] = 'SELECT uaf.revision AS id,uaf.created_on AS edited_on,uaf.created_by AS edited_by, ual.local AS r_id, '.DB::qstr('').' AS tab, '.DB::qstr('file').' AS action FROM utils_attachment_file uaf LEFT JOIN utils_attachment_link ual ON uaf.attach_id=ual.id WHERE original!='.DB::qstr('').' AND '.$af_where;
		// **** notes ****
		if (isset($filters['note']))
			$tables[] = 'SELECT uan.revision AS id,uan.created_on AS edited_on,uan.created_by AS edited_by, ual.local AS r_id, '.DB::qstr('').' AS tab, '.DB::qstr('note').' AS action FROM utils_attachment_note uan LEFT JOIN utils_attachment_link ual ON uan.attach_id=ual.id WHERE '.$an_where;
		// **** edit ****
		if (isset($filters['edit']) || isset($filters['delete_restore']))
			foreach($rb_tabs as $k=>$t)
				$tables[] = 'SELECT id, edited_on, edited_by, '.$k.'_id as r_id, '.DB::qstr($k).' as tab, '.DB::qstr('edit').' as action FROM '.$k.'_edit_history eh LEFT JOIN '.$k.'_edit_history_data ehd ON ehd.edit_id=eh.id'.$e_where;
		// **** create ****
		if (isset($filters['new']))
			foreach($rb_tabs as $k=>$t)
				$tables[] = 'SELECT 0 AS id, created_on AS edited_on, created_by AS edited_by, id as r_id, '.DB::qstr($k).' as tab, '.DB::qstr('create').' as action FROM '.$k.'_data_1'.$c_where;
	
		if (!empty($tables)) {
			$tables = implode(' UNION ', $tables);
			$limit = DB::GetOne('SELECT COUNT(*) FROM ('.$tables.') AS tmp');
			$limit = $gb->get_limit($limit);
			$ret = DB::SelectLimit('SELECT * FROM ('.$tables.') AS tmp ORDER BY edited_on DESC', $limit['numrows'], $limit['offset']);
			while ($row=$ret->FetchRow()) {
				$user = Base_UserCommon::get_user_label($row['edited_by']);
				$action = '';
				$link = '';
				switch ($row['action']) {
					case 'edit': 	$details = DB::GetAssoc('SELECT field, old_value FROM '.$row['tab'].'_edit_history_data WHERE edit_id=%d', array($row['id']));
									if (isset($details['id'])) {
										$action = $details['id']=='DELETED'?__('Deleted'):__('Restored');
									} else {
										$action = __('Edited');
										$action = '<a '.Utils_TooltipCommon::tooltip_leightbox_mode().' '.Utils_TooltipCommon::ajax_open_tag_attrs(array('Utils_RecordBrowserCommon', 'get_edit_details_label'), array($row['tab'], $row['r_id'], $row['id']), 500).'>'.$action.'</a>';
									}
									$r_id = $row['r_id'];
									break;
					case 'create': 	$action = __('Created');
									$r_id = $row['r_id'];
									break;
					case 'file': 	$action = __('Attachment').': ';
									$action .= $row['id']==0?__('New'):__('Updated');
									$id = explode('/',$row['r_id']);
									$row['tab'] = $id[0];
									$r_id = $id[1];
									break;
					case 'note': 	$action = __('Note').': ';
									$action .= $row['id']==0?__('New'):__('Updated');
									$id = explode('/',$row['r_id']);
									$row['tab'] = $id[0];
									$r_id = $id[1];
									break;
				}
				if (!Utils_RecordBrowserCommon::get_access($row['tab'], 'view', Utils_RecordBrowserCommon::get_record($row['tab'], $r_id))) {
					$link = __('Access restricted');
					$action = strip_tags($action);
				} else {
					$link = Utils_TooltipCommon::create('<img src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','info.png').'">',Utils_RecordBrowserCommon::get_html_record_info($row['tab'], $r_id), false);
					$link .= '&nbsp;';
					$link .= Utils_RecordBrowserCommon::create_default_linked_label($row['tab'], $r_id, false, false);
				}
				$gb->add_row(
					Base_RegionalSettingsCommon::time2reg($row['edited_on']),
					$user, 
					$rb_tabs[$row['tab']], 
					$link, 
					$action);
			}
		}
		Base_ThemeCommon::load_css('Utils_RecordBrowser','changes_list');
		$this->display_module($gb);
	}
	
	public function caption() {
		return __('Activity Report');
	}
}

?>