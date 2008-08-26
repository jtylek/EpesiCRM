<?php
/**
 * RecordBrowser install class.
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.99
 * @package tcms-extra
 */

defined("_VALID_ACCESS") || die();

class Utils_RecordBrowserInstall extends ModuleInstall {
	public function install() {
		Base_ThemeCommon::install_default_theme('Utils/RecordBrowser');
		DB::CreateTable('recordbrowser_table_properties',
						'tab C(64) KEY,'.
						'quickjump C(64) DEFAULT \'\','.
						'tpl C(255) DEFAULT \'\','.
						'favorites I1 DEFAULT 0,'.
						'recent I2 DEFAULT 0,'.
						'full_history I1 DEFAULT 1,'.
						'caption C(32) DEFAULT \'\','.
						'icon C(255) DEFAULT \'\','.
						'access_callback C(128) DEFAULT \'\','.
						'data_process_method C(255) DEFAULT \'\'',
						array('constraints'=>''));
		DB::CreateTable('recordbrowser_datatype',
						'type C(32) KEY,'.
						'module C(64),'.
						'func C(128)',
						array('constraints'=>''));
		DB::CreateTable('recordbrowser_addon',
					'tab C(64),'.
					'module C(128),'.
					'func C(128),'.
					'label C(64)',
					array('constraints'=>', PRIMARY KEY(module, func)'));
		return true;
	}
	
	public function uninstall() {
		DB::DropTable('recordbrowser_addon');
		DB::DropTable('recordbrowser_table_properties');
		DB::DropTable('recordbrowser_datatype');
		Base_ThemeCommon::uninstall_default_theme('Utils/RecordBrowser');
		return true;
	}
	
	public function requires($v) {
		return array(
			array('name'=>'Utils/CommonData', 'version'=>0), 
			array('name'=>'Utils/CurrencyField', 'version'=>0), 
			array('name'=>'Utils/Tooltip', 'version'=>0), 
			array('name'=>'Utils/BookmarkBrowser', 'version'=>0), 
			array('name'=>'Utils/GenericBrowser', 'version'=>0), 
			array('name'=>'Utils/TabbedBrowser', 'version'=>0), 
			array('name'=>'Utils/Watchdog', 'version'=>0), 
			array('name'=>'Base/User/Login', 'version'=>0), 
			array('name'=>'Base/User', 'version'=>0)
		);
	}
	
	public function provides($v) {
		return array();
	}
	
	public static function info() {
		return array('Author'=>'<a href="mailto:abisaga@telaxus.com">Arkadiusz Bisaga</a> (<a href="http://www.telaxus.com">Telaxus LLC</a>)', 'License'=>'TL', 'Description'=>'Module to browse and modify records.');
	}
	
	public static function simple_setup() {
		return false;
	}
	
	public function version() {
		return array('1.0', '2.0 beta');
	}
	
	public static function el($s) {
		return;
		static $start = 0;
		if ($start == 0) $start=microtime(true);
		error_log(number_format(microtime(true)-$start,3).': '.$s."\n",3,'data/RBupgrade.txt');
	}
	
	public function upgrade_1(){
		set_time_limit(0);
		ini_set("memory_limit","512M");
		$tabs = DB::GetAssoc('SELECT tab, tab FROM recordbrowser_table_properties');
		self::el('Starting...');
		foreach ($tabs as $t) {
			self::el($t.': Working');
			@DB::DropTable($t.'_data_1');
			DB::CreateTable($t.'_data_1',
						'id I AUTO KEY,'.
						'created_on T NOT NULL,'.
						'created_by I NOT NULL,'.
						'private I4 DEFAULT 0,'.
						'active I1 NOT NULL DEFAULT 1',
						array('constraints'=>''));
			self::el($t.': Created base table');
			$cols = DB::Execute('SELECT field, type, param FROM '.$t.'_field WHERE type!=%s AND type!=%s', array('foreign index','page_split'));
			while ($c = $cols->FetchRow()) {
				switch ($c['type']) {
					case 'text': $f = 'VARCHAR('.$c['param'].')'; break;
					case 'select': $f = 'TEXT'; break;
					case 'multiselect': $f = 'TEXT'; break;
					case 'commondata': $f = 'VARCHAR(128)'; break;
					case 'integer': $f = 'INTEGER'; break;
					case 'date': $f = 'DATE'; break;
					case 'timestamp': $f = 'TIMESTAMP'; break;
					case 'long text': $f = 'TEXT'; break;
					case 'hidden': $f = (isset($c['param'])?$c['param']:''); break;
					case 'calculated': $f = (isset($c['param'])?$c['param']:''); break;
					case 'checkbox': $f = 'BOOLEAN'; break;
					case 'currency': $f = 'VARCHAR(128)'; break;
				}
				if (!isset($f)) trigger_error('Database column for type '.$c['type'].' undefined.',E_USER_ERROR);
				if ($f!=='') DB::Execute('ALTER TABLE '.$t.'_data_1 ADD COLUMN f_'.strtolower(str_replace(' ','_',$c['field'])).' '.$f);
			}
			self::el($t.': Created all table fields');
			$params = DB::GetAssoc('SELECT field, type FROM '.$t.'_field');
			$multi = array();
			$rest = '';
			foreach($params as $k=>$v) {
				if ($v=='multiselect') $multi[] = $k;
				else $rest .= ' OR field=\''.$k.'\'';
			} 
			$recs = DB::Execute('SELECT * FROM '.$t);
			self::el($t.': Moving records... ');
			while ($r = $recs->FetchRow()) {
				DB::Execute('INSERT INTO '.$t.'_data_1 (id, active, created_by, created_on) VALUES (%d, %d, %d, %T)', array($r['id'], $r['active'], $r['created_by'], $r['created_on']));
				self::el($t.': Moving record '.$r['id']);
				foreach($multi as $v) {
					$vals = DB::GetAssoc('SELECT value, value FROM '.$t.'_data WHERE field=%s AND '.$t.'_id=%d',array($v,$r['id']));
					if (empty($vals)) continue;
					DB::Execute('UPDATE '.$t.'_data_1 SET f_'.strtolower(str_replace(' ','_',$v)).'='.DB::qstr('__'.implode('__',$vals).'__').' WHERE id='.$r['id']);
				}
				$vals = DB::GetAssoc('SELECT field, value FROM '.$t.'_data WHERE '.$t.'_id='.$r['id'].' AND (false'.$rest.')');
				$update = '';
				foreach ($vals as $k=>$v) {
					DB::Execute('UPDATE '.$t.'_data_1 SET f_'.strtolower(str_replace(' ','_',$k)).'='.DB::qstr($v).' WHERE id='.$r['id']);					
				}
				self::el($t.': Moved record '.$r['id']);
			}
			self::el($t.': Done');
		}
		self::el($t.': Upgrade done');
		return true;
	}

	public function downgrade_1(){
		$tabs = DB::GetAssoc('SELECT tab, tab FROM recordbrowser_table_properties');
		foreach ($tabs as $t)
			DB::DropTable($t.'_data_1');
		return true;
	}
}

?>
