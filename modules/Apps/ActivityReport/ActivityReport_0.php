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
		
/*		foreach ($rb_tabs as $n=>$c)
			$form->addElement('checkbox', 'recordset_'.$n, $this->t($c));*/

		$form->addElement('multiselect', 'recordsets', $this->t('Record Type'), $rb_tabs);

		$form->display();

		$gb = $this->init_module('Utils/GenericBrowser',null,'activity_report');
		$gb->set_table_columns(array(
			array('name'=>$this->t('Date'), 'width'=>40),
			array('name'=>$this->t('User'), 'width'=>40),
			array('name'=>$this->t('Type'), 'width'=>40),
			array('name'=>$this->t('Label')),
			array('name'=>$this->t('Actions taken'), 'width'=>40)
		));
		$tables = array();

		$where = array();
		foreach($rb_tabs as $k=>$t)
			$where[] = 'ual.local LIKE '.DB::Concat(DB::qstr($k.'/'),DB::qstr('%'));

		// **** files ****
		$tables[] = 'SELECT uaf.revision AS id,uaf.created_on AS edited_on,uaf.created_by AS edited_by, ual.local AS r_id, "" AS tab, "file" AS action FROM utils_attachment_file uaf LEFT JOIN utils_attachment_link ual ON uaf.attach_id=ual.id WHERE original!="" AND ('.implode(' OR ',$where).')';
		// **** notes ****
		$tables[] = 'SELECT uan.revision AS id,uan.created_on AS edited_on,uan.created_by AS edited_by, ual.local AS r_id, "" AS tab, "note" AS action FROM utils_attachment_note uan LEFT JOIN utils_attachment_link ual ON uan.attach_id=ual.id WHERE '.implode(' OR ',$where);

		// **** edit ****
		foreach($rb_tabs as $k=>$t)
			$tables[] = 'SELECT id, edited_on, edited_by, '.$k.'_id as r_id, "'.$k.'" as tab, "edit" as action FROM '.$k.'_edit_history';
		// **** create ****
		foreach($rb_tabs as $k=>$t)
			$tables[] = 'SELECT 0 AS id, created_on AS edited_on, created_by AS edited_by, id as r_id, "'.$k.'" as tab, "create" as action FROM '.$k.'_data_1';
			
		$tables = implode(' UNION ', $tables);
		$limit = DB::GetOne('SELECT COUNT(*) FROM ('.$tables.') AS tmp ORDER BY edited_on DESC');
		$limit = $gb->get_limit($limit);
		$ret = DB::SelectLimit('SELECT * FROM ('.$tables.') AS tmp ORDER BY edited_on DESC', $limit['numrows'], $limit['offset']);
		while ($row=$ret->FetchRow()) {
			$user = CRM_ContactsCommon::get_user_label($row['edited_by']);
			$action = '';
			switch ($row['action']) {
				case 'edit': 	$action = $this->t('Edited');
								$action = '<span '.Utils_TooltipCommon::ajax_open_tag_attrs(array('Utils_RecordBrowserCommon', 'get_edit_details'), array($row['tab'], $row['r_id'], $row['id'])).'>'.$action.'</span>';
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
		$this->display_module($gb);
	}
	
	public function caption() {
		return 'Activity Report';
	}
}

?>