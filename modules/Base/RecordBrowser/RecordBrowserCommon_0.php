<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_RecordBrowserCommon extends ModuleCommon {
	public function install_new_recordset($tab_name = null) {
		if (!$tab_name) return false;
		DB::CreateTable($tab_name,
					'id I AUTO KEY,'.
					'created_on T NOT NULL,'.
					'created_by I NOT NULL,'.
					'private I4 DEFAULT 0,'.
					'active I1 NOT NULL DEFAULT 1',
					array('constraints'=>', FOREIGN KEY (created_by) REFERENCES user_login(id)'));
		DB::CreateTable($tab_name.'_data',
					$tab_name.'_id I,'.
					'field C(32) NOT NULL,'.
					'value C(256) NOT NULL',			
					array('constraints'=>', PRIMARY KEY (field, '.$tab_name.'_id)'.
										', FOREIGN KEY ('.$tab_name.'_id) REFERENCES '.$tab_name.'(id)'));
		DB::CreateTable($tab_name.'_field',
					'field C(32) UNIQUE NOT NULL,'.
					'type C(32),'.
					'extra I1 DEFAULT 1,'.
					'visible I1 DEFAULT 1,'.
					'required I1 DEFAULT 1,'.
					'active I1 DEFAULT 1,'.
					'position I,'.
					'param C(32)',
					array('constraints'=>''));
		DB::CreateTable($tab_name.'_edit_history',
					'id I AUTO KEY,'.
					$tab_name.'_id I NOT NULL,'.
					'edited_on T NOT NULL,'.
					'edited_by I NOT NULL',
					array('constraints'=>', FOREIGN KEY (edited_by) REFERENCES user_login(id)'.
											', FOREIGN KEY ('.$tab_name.'_id) REFERENCES '.$tab_name.'(id)'));
		DB::CreateTable($tab_name.'_edit_history_data',
					'edit_id I,'.
					'field C(32),'.
					'old_value C(256)',
					array('constraints'=>', FOREIGN KEY (edit_id) REFERENCES '.$tab_name.'_edit_history(id)'));
		DB::CreateTable($tab_name.'_favorite',
					$tab_name.'_id I,'.
					'user_id I',
					array('constraints'=>', FOREIGN KEY (user_id) REFERENCES user_login(id)'.
										', FOREIGN KEY ('.$tab_name.'_id) REFERENCES '.$tab_name.'(id)'));
		DB::Execute('INSERT INTO '.$tab_name.'_field(field, type, extra, visible, position) VALUES(\'id\', \'foreign index\', 0, 0, 1)');
		DB::Execute('INSERT INTO '.$tab_name.'_field(field, type, extra, position) VALUES(\'General\', \'page_split\', 0, 2)');
		DB::Execute('INSERT INTO '.$tab_name.'_field(field, type, extra, position) VALUES(\'Details\', \'page_split\', 0, 3)');
		return true;
	}
	
	public function uninstall_new_recordset($tab_name = null) {
		if (!$tab_name) return false;
		DB::DropTable($tab_name);
		DB::DropTable($tab_name.'_data');
		DB::DropTable($tab_name.'_field');
		DB::DropTable($tab_name.'_edit_history');
		DB::DropTable($tab_name.'_edit_history_data');
		DB::DropTable($tab_name.'_favorite');
		return true;
	}
	
	public function new_record_field($tab_name, $field, $type, $param, $extra = true){
		if ($extra) {
			$pos = DB::GetOne('SELECT MAX(position) FROM '.$tab_name.'_field')+1;
		} else {
			DB::StartTrans();
			$pos = DB::GetOne('SELECT position FROM '.$tab_name.'_field WHERE field=\'Details\'');
			DB::Execute('UPDATE '.$tab_name.'_field SET position = position+1 WHERE position>=%d', array($pos));
			DB::CompleteTrans();
		}
		DB::Execute('INSERT INTO '.$tab_name.'_field(field, type, param, position, extra) VALUES(%s, %s, %s, %d, %d)', array($field, $type, $param, $pos, $extra));
	}
	
	public static function get_records( $tab_name = null, $range = null ) {
		if (!$tab_name) return false;
		$ret = null;
		if(isset($range) && is_array($range) && !empty($range)) {
			$ret = DB::Execute('SELECT id FROM '.$tab_name.' WHERE active=1 and id in ('. join(', ', $range).')'); // TODO: CRAP
		} else
			$ret = DB::Execute('SELECT id FROM '.$tab_name.' WHERE active=1');
		$records = array();
		if($ret)
			while ($row = $ret->FetchRow()) {
				$data = DB::Execute('SELECT * FROM '.$tab_name.'_data WHERE '.$tab_name.'_id=%d and (field=%s or field=%s)', array($row['id'], 'first_name', 'last_name'));
				$records[$row['id']] = array();
				while($field = $data->FetchRow())
					$records[$row['id']][$field['field']] = $field['value'];
			}
		return $records;
	}
	
	public static function get_record( $tab_name, $id) {
		if(isset( $id )) {
			$data = DB::Execute('SELECT * FROM '.$tab_name.'_data WHERE '.$tab_name.'_id=%d', array($id));
			$record = array();
			while($field = $data->FetchRow())
				$record[$field['field']] = $field['value'];
			return $record;
		} else {
			return '';
		}
	}
}
?>