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
		$rb_tabs = DB::GetAssoc('SELECT tab, caption FROM recordbrowser_table_properties ORDER BY caption');

		$form = $this->init_module('Libs/QuickForm');

		$users = DB::GetAssoc('SELECT id, id FROM user_login WHERE active=1');
		foreach ($users as $k=>$u)
			$users[$k] = CRM_ContactsCommon::get_user_label($u, true);
		asort($users);
		$users = array(''=>'['.$this->t('All').']')+$users;
		$form->addElement('select', 'user', $this->t('User'), $users);
		
		$form->addElement('multiselect', 'recordsets', $this->t('Record Type'), $rb_tabs);

		$form->addElement('checkbox', 'new', $this->t('New record'));
		$form->addElement('checkbox', 'edit', $this->t('Record edit'));
		$form->addElement('checkbox', 'delete_restore', $this->t('Record Delete/restore'));
		$form->addElement('checkbox', 'note', $this->t('Notes'));
		$form->addElement('checkbox', 'file', $this->t('Files'));

		$form->addElement('datepicker', 'start_date', $this->t('Start Date'));
		$form->addElement('datepicker', 'end_date', $this->t('End Date'));

		$form->addElement('submit', 'submit', $this->t('Show'));

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
			array('name'=>$this->t('Date'), 'width'=>40),
			array('name'=>$this->t('User'), 'width'=>40),
			array('name'=>$this->t('Type'), 'width'=>40),
			array('name'=>$this->t('Label')),
			array('name'=>$this->t('Actions taken'), 'width'=>40)
		));
		$tables = array();

		$af_where = array();
		foreach($rb_tabs as $k=>$t)
			$af_where[] = 'ual.local LIKE '.DB::Concat(DB::qstr($k.'/'),DB::qstr('%'));
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
				$e_where[] = ' ehd.field!="id"';
			}
		} else {
			if (isset($filters['delete_restore'])) {
				$e_where[] = ' ehd.field="id"';
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
			$tables[] = 'SELECT uaf.revision AS id,uaf.created_on AS edited_on,uaf.created_by AS edited_by, ual.local AS r_id, "" AS tab, "file" AS action FROM utils_attachment_file uaf LEFT JOIN utils_attachment_link ual ON uaf.attach_id=ual.id WHERE original!="" AND '.$af_where;
		// **** notes ****
		if (isset($filters['note']))
			$tables[] = 'SELECT uan.revision AS id,uan.created_on AS edited_on,uan.created_by AS edited_by, ual.local AS r_id, "" AS tab, "note" AS action FROM utils_attachment_note uan LEFT JOIN utils_attachment_link ual ON uan.attach_id=ual.id WHERE '.$an_where;
		// **** edit ****
		if (isset($filters['edit']) || isset($filters['delete_restore']))
			foreach($rb_tabs as $k=>$t)
				$tables[] = 'SELECT id, edited_on, edited_by, '.$k.'_id as r_id, "'.$k.'" as tab, "edit" as action FROM '.$k.'_edit_history eh LEFT JOIN '.$k.'_edit_history_data ehd ON ehd.edit_id=eh.id'.$e_where;
		// **** create ****
		if (isset($filters['new']))
			foreach($rb_tabs as $k=>$t)
				$tables[] = 'SELECT 0 AS id, created_on AS edited_on, created_by AS edited_by, id as r_id, "'.$k.'" as tab, "create" as action FROM '.$k.'_data_1'.$c_where;
	
		if (!empty($tables)) {
			$tables = implode(' UNION ', $tables);
			$limit = DB::GetOne('SELECT COUNT(*) FROM ('.$tables.') AS tmp ORDER BY edited_on DESC');
			$limit = $gb->get_limit($limit);
			$ret = DB::SelectLimit('SELECT * FROM ('.$tables.') AS tmp ORDER BY edited_on DESC', $limit['numrows'], $limit['offset']);
			Libs_LeightboxCommon::display('activity_report_edit_history', '<center><span id="activity_report_leightbox_content" /></center>');
			eval_js('fill_activity_report_leightbox = function(tab, r_id, id) {'.
				'$("activity_report_leightbox_content").innerHTML = "Loading...";'.
				'new Ajax.Request("modules/Apps/ActivityReport/edit_history.php", {'.
				'	method: "post",'.
				'	parameters:{'.
				'		tab: tab,'.
				'		r_id: r_id,'.
				'		id: id,'.
				'		cid: Epesi.client_id'.
				'	},'.
				'	onSuccess:function(t) {'.
				'		$("activity_report_leightbox_content").innerHTML = t.responseText;'.
				'	}'.
				'});'.
			'}');
			while ($row=$ret->FetchRow()) {
				$user = CRM_ContactsCommon::get_user_label($row['edited_by']);
				$action = '';
				$link = '';
				switch ($row['action']) {
					case 'edit': 	$details = DB::GetAssoc('SELECT field, old_value FROM '.$row['tab'].'_edit_history_data WHERE edit_id=%d', array($row['id']));
									if (isset($details['id'])) {
										$action = $details['id']=='DELETED'?$this->t('Deleted'):$this->t('Restored');
									} else {
										$action = $this->t('Edited');
										$action = '<a '.Libs_LeightboxCommon::get_open_href('activity_report_edit_history').' onmouseup="fill_activity_report_leightbox(\''.$row['tab'].'\','.$row['r_id'].','.$row['id'].')" '.Utils_TooltipCommon::ajax_open_tag_attrs(array('Utils_RecordBrowserCommon', 'get_edit_details'), array($row['tab'], $row['r_id'], $row['id'])).'>'.$action.'</a>';
									}
									$link = Utils_RecordBrowserCommon::create_default_linked_label($row['tab'], $row['r_id'], false, false);
									break;
					case 'create': 	$action = $this->t('Created');
									$link = Utils_RecordBrowserCommon::create_default_linked_label($row['tab'], $row['r_id'], false, false);
									break;
					case 'file': 	$action = $this->t('Attachment: ');
									$action .= $row['id']==0?'New':'Updated';
									$id = explode('/',$row['r_id']);
									$row['tab'] = $id[0];
									$link = Utils_RecordBrowserCommon::create_default_linked_label($row['tab'], $id[1], false, false);
									break;
					case 'note': 	$action = $this->t('Note: ');
									$action .= $row['id']==0?'New':'Updated';
									$id = explode('/',$row['r_id']);
									$row['tab'] = $id[0];
									$link = Utils_RecordBrowserCommon::create_default_linked_label($row['tab'], $id[1], false, false);
									break;
				}
				$gb->add_row(
					Base_RegionalSettingsCommon::time2reg($row['edited_on']),
					$user, 
					$rb_tabs[$row['tab']], 
					$link, 
					$action);
			}
		}
		$this->display_module($gb);
	}
	
	public function caption() {
		return 'Activity Report';
	}
}

?>