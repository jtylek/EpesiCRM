<?php
/**
 * TODO: add menu entries, php editor
 * @author Arkadiusz Bisaga <abisaga@telaxus.com> and Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 0.8
 * @package epesi-develop
 * @subpackage modulecreator
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Develop_ModuleCreator extends Module {
	private $wizard;
		
	public function body() {
		$this->wizard = $this->init_module('Utils/Wizard',null,'main');
		
		$f = $this->wizard->begin_page('basic',false);
		$this->wizard->set_caption(__('Basic module informations'));
		if($f) $this->basic_step($f,$this->wizard->get_data());
		
		$f = $this->wizard->begin_page('requires',false);
		$this->wizard->set_caption(__('Module requirements'));
		if($f) $this->requires_step($f,$this->wizard->get_data());
		
		$f = $this->wizard->begin_page('tables',false);
		$this->wizard->set_caption(__('Module tables definitions'));
		if($f) $this->tables_step($f,$this->wizard->get_data());
		$this->wizard->next_page(array($this,'tables_step_next'));
		
		$data = $this->wizard->get_data();
		$reqs = array();
		$tables = $this->get_tables_names();
		$max = 0;
		foreach($tables as $k=>$name) {
			$this->wizard_add_table($k);
			if($k>$max)$max=$k;
		}
		$this->wizard_add_table($max+1);
		
		$f = $this->wizard->begin_page('overview',false);
		$this->wizard->set_caption(__('Module overview'));
		if($f) $this->overview_step($f,$this->wizard->get_data());

		$this->display_module($this->wizard, array(array($this,'create_module')));
	}
	
	public function basic_step($f,$data) {
		$f->addElement('header', null, __('Create New Module'));
		$f->addElement('header', 'h1', __('Module directory'));
		$f->addElement('select', 'path', __('Path'),array('Custom'=>'Custom','Premium'=>'Premium', '__other__'=>'Other'),array('onChange'=>$f->get_submit_form_js(false)));
		if(!isset($data['basic']))
			$f->setDefaults(array('path'=>'Custom'));
		else
			$f->setDefaults(array($data['basic']));
		
		$path_other = $f->exportValue('path')=='__other__';
		if($path_other)
			$f->addElement('text', 'path_other');
		
		$f->addElement('text', 'name', __('Name'));
		$f->addElement('header', 'h2', __('Module info'));
		$f->addElement('text', 'author', __('Author'));
		$f->addElement('textarea', 'description', __('Description'));
		$f->addElement('text', 'licence', __('License'));
		
		$path = $f->exportValue('path');
		if($path_other)
			$f->addElement('checkbox','simple',__('Simple setup'));
		elseif($path=='Apps' || $path=='Applets' || $path=='Tools')
			$f->addElement('static',null,__('Simple setup'),'[x]');
		else
			$f->addElement('static',null,__('Simple setup'),'[ ]');
		
		$f->addRule('name', __('This field is required'), 'required');
		$mail = DB::GetOne('SELECT up.mail FROM user_password up WHERE up.user_login_id=%d',array(Acl::get_user()));
		$f->setDefaults(array('licence'=>'MIT','author'=>$mail));
	}
	
	public function requires_step($f,$data) {
		$f->addElement('header', 'select_required_modules', 'Select Required Modules');
		$module_dirs = ModuleManager::list_modules();
		$structure = array();
		foreach($module_dirs as $entry=>$versions) {
				$installed = ModuleManager::is_installed($entry);
				$versions[-1]='not required';
				ksort($versions);

				$path = explode('_',$entry);
				$c = & $structure;
				for($i=0;$i<count($path)-1;$i++){
					if(!key_exists($path[$i], $c)) {
						$c[$path[$i]] = array();
						$c[$path[$i]]['name'] = $path[$i];
						$c[$path[$i]]['sub'] = array();
					}
					$c = & $c[$path[$i]]['sub'];
				}
				if($f->getSubmitValue('submited')) {
					$ele = '';
					$f->addElement('select', 'required['.$entry.']', $path[count($path)-1], $versions);				
				} else {
					$ele = $f->createElement('select', 'required['.$entry.']', $path[count($path)-1], $versions);
					if(isset($data['requires']))
						$ele->setValue($data['requires']['required'][$entry]);
					$ele = $ele->toHtml();
				}
				$c[$path[count($path)-1]] = array();
				$c[$path[count($path)-1]]['name'] = '<table width=100%><tr><td width=100% align=left>'.$path[count($path)-1].'</td><td align=right>' . $ele . '</td></tr></table>';
				$c[$path[count($path)-1]]['sub'] = array();
		}

		$tree = $this->init_module('Utils/Tree');
		$tree->set_structure($structure);
		$tree->set_inline_display();
		$f->addElement('html', '<tr><td colspan=2>'.$this->get_html_of_module($tree).'</td></tr>');
	}
	
	public function tables_step($f,$data) {
		$f->addElement('header',null,__('Add tables'));
		$gb = $this->init_module('Utils/GenericBrowser',null,'tables');
		$gb->set_table_columns(array(	array('name'=>'Table name','width'=>'8'),
						array('name'=>'Columns','width'=>'20')
					));
		
		$tables = $this->get_tables();
		$max = 0;
		$form_name = $f->get_path();
		foreach($tables as $k=>$arr) {
			$cols = array();
			if(isset($arr['cols']))
				foreach($arr['cols'] as $c)
					$cols[] = $c['name'];
			$row = $gb->get_new_row();
			$row->add_data($arr['name'],isset($arr['cols'])?implode($cols,', '):'');

			$row->add_action(' href="javascript:void(0)" onClick="$(\'modcr_tab_edit\').value=\''.$k.'\';'.$f->get_submit_form_js().'"','Edit');

			$row->add_action(' href="javascript:void(0)" onClick="if(confirm(\'Are you sure?\')){$(\'modcr_tab_del\').value=\''.$k.'\';'.$f->get_submit_form_js().'}"','Delete');
			
			if($max<$k)$max=$k;
		}
		
		$gb->set_inline_display();
		$f->addElement('html','<tr><td colspan=2><center>'.$this->get_html_of_module($gb).'</center></td></tr>');
		
		$f->addElement('hidden','edit',null,array('id'=>'modcr_tab_edit'));
		$f->addElement('hidden','delete',null,array('id'=>'modcr_tab_del'));
		$f->setDefaults(array('edit'=>'','delete'=>''));
		
		$f->addElement('radio','go','',__('Add table'),'table_'.($max+1));
		$f->addElement('radio','go','',__('Tables done'),'overview');
		$f->setDefaults(array('go'=>'overview'));
	}

	public function tables_step_next($data) {
		if(isset($data['delete']) && $data['delete']!='') {
			$this->wizard->delete_page('table_'.$data['delete']);
			foreach($data as $k=>$arr)
				if(preg_match('/^tablecol_'.addcslashes($data['delete'],'/').'_([0-9]+)$/',$k))
					$this->wizard->delete_page($k);
			return 'tables';
		}
		if(isset($data['edit']) && $data['edit']!='') return 'table_'.$data['edit'];
		return $data['go'];
	}

	public function wizard_add_table($i) {
		$f = $this->wizard->begin_page('table_'.$i,false);
		if($f) {
			$this->wizard->set_caption(__('Specify new table name'),1);
			$this->table_add($f,$this->wizard->get_data());
		}
		
		$f = $this->wizard->begin_page('tableoverview_'.$i,false);
		$data = & $this->wizard->get_data();
		if(isset($data['table_'.$i]['name']))
			$this->wizard->set_caption(__('Define table %s',array($data['table_'.$i]['name'])),1);
		if($f) $this->table_add_overview($f,$data,$i);
		$this->wizard->next_page(array($this,'table_add_overview_next'));
		
		$data = $this->wizard->get_data();
		$reqs = array();
		$max=0;
		foreach($data as $k=>$arr)
			if(preg_match('/^tablecol_'.addcslashes($i,'/').'_([0-9]+)/',$k,$reqs)) {
				$f = $this->wizard->begin_page('tablecol_'.$i.'_'.$reqs[1],false);
				if($f) $this->table_add_col($f,$this->wizard->get_data());
				$this->wizard->next_page('tableoverview_'.$i);
				if($reqs[1]>$max) $max=$reqs[1];
			}
			
		$f = $this->wizard->begin_page('tablecol_'.$i.'_'.($max+1),false);
		if($f) $this->table_add_col($f,$this->wizard->get_data());
		$this->wizard->next_page('tableoverview_'.$i);
	}		
	
	public function table_add($f,$data) {
		$f->addElement('header',null,__('New table name'));
		$f->addElement('text','name',__('Name'));
		$f->addRule('name',__('Field required'),'required');
	}
	
	public function table_add_col($add_col,$data) {
		$types = array(	'I1'=>'Tiny integer',
				'I2'=>'Small integer',
				'I4'=>'Integer',
				'I8'=>'Big integer',
				'F'=>'Float',
				'L'=>'Boolean',
				'D'=>'Date',
				'T'=>'Timestamp',
				'C'=>'Varchar',
				'X'=>'Text',
				'X2'=>'Long text',
				'B'=>'BLOB');
		$add_col->addElement('header',null,'Add new column to the table');
		$add_col->addElement('text','name','Column name');
		//TODO: $add_col->addRule('name','Column already exists');
		$add_col->addRule('name','Invalid character, use small letters, underscore and digits only','regex','/^[a-z_]{1}[a-z0-9_]*$/');
		$add_col->addRule('name','This field is required','required');
		$add_col->addElement('select','type','Column Type', $types);
		$add_col->addElement('text','size','Variable size (only for varchars, default 32)');
		$add_col->addElement('text','defaultval','Default (leave blank for no default)');
		$add_col->addElement('checkbox','autoinc','Autoincrement');
		$add_col->addElement('checkbox','key','Primary Key');
		$add_col->addElement('checkbox','notnull','Not Null');
		$add_col->addElement('checkbox','defdate','Use current as default timestamp/date<br> (changes field on every update!)');
		
		$tables_db = DB::MetaTables();
		$tables = array();
		foreach($tables_db as $t)
			$tables[$t] = $t;
		
		$path = ($data['basic']['path']=='__other__')?$data['basic']['path_other']:$data['basic']['path'];
		$class_name = trim(str_replace('/','_',$path).'_'.$data['basic']['name'],'_');
		$tables_this = $this->get_tables();
		foreach($tables_this as $t=>$v)
			$tables[strtolower($class_name).'_'.$v['name']] = $v['name'];
		ksort($tables); 
		$tables = array_merge(array(0=>'---'),$tables);
		
		$add_col->addElement('select','referencetable','Reference: Table name',$tables,'onChange="'.$add_col->get_submit_form_js(false).'"');
		$ref = $add_col->exportValue('referencetable');
		if($ref) {
			$cols_this = false;
			$cols=array();
			foreach($tables_this as $v)
				if(strtolower($class_name).'_'.$v['name']==$ref) {
					foreach($v['cols'] as $c)
						$cols[$c['name']]=$c['name'];
					$cols_this=true;
					break;
				}
			if(!$cols_this) {
				$cols_db = DB::MetaColumns($ref);	
				foreach($cols_db as $k=>$v)
					$cols[$k] = $k;
			}
			$add_col->addElement('select','referencecolumn','Reference: Column name', $cols);
		}
	}
	
	public function table_add_overview($f,$data,$i) {
		$f->addElement('header',null,__('Adding table: %s',array($data['table_'.$i]['name'])));
		$gb = $this->init_module('Utils/GenericBrowser',null,'table');
		$gb->set_table_columns(array(	array('name'=>'Column Name','width'=>'8'),
						array('name'=>'Type','width'=>'3'),
						array('name'=>'Default','width'=>'8'),
						array('name'=>'Attributes','width'=>'10'),
						array('name'=>'Constraints','width'=>'20')
					));
		$reqs = array();
		$max=0;
		$cols = array();
		foreach($data as $k=>$arr)
			if(preg_match('/^tablecol_'.addcslashes($i,'/').'_([0-9]+)$/',$k,$reqs)) {
				$row = $gb->get_new_row();
				$row->add_data($arr['name'],$arr['type'],$arr['defaultval'],
					rtrim((isset($arr['autoinc'])?'autoincrement, ':'').
					(isset($arr['key'])?'key, ':'').
					(isset($arr['notnull'])?'not null, ':'').
					((($arr['type']=='D' || $arr['type']=='T') && isset($arr['defdate']))?(($arr['type']=='D')?'default date':'default timestamp'):''),', ')
					,isset($arr['referencecolumn'])?$arr['referencetable'].' ('.$arr['referencecolumn'].')':'');

				$row->add_action(' href="javascript:void(0)" onClick="$(\'modcr_tabcol_edit\').value=\''.$i.'_'.$reqs[1].'\';'.$f->get_submit_form_js().'"','Edit');

				$row->add_action(' href="javascript:void(0)" onClick="if(confirm(\'Are you sure?\')){$(\'modcr_tabcol_del\').value=\''.$i.'_'.$reqs[1].'\';'.$f->get_submit_form_js().'}"','Delete');

				$cols[$reqs[1]] = $arr['name'];
				if($reqs[1]>$max) $max=$reqs[1];
			}
		$gb->set_inline_display();
		$f->addElement('html','<tr><td colspan=2><center>'.$this->get_html_of_module($gb).'</center></td></tr>');
		
		$f->addElement('hidden','edit',null,array('id'=>'modcr_tabcol_edit'));
		$f->addElement('hidden','delete',null,array('id'=>'modcr_tabcol_del'));
		$f->setDefaults(array('edit'=>'','delete'=>''));
		
		$f->addElement('radio','go','',__('Add column'),'tablecol_'.$i.'_'.($max+1));
		$f->addElement('radio','go','',__('Table done'),'tables');
		$f->setDefaults(array('go'=>'tables'));
	}
	
	public function table_add_overview_next($data) {
		if(isset($data['delete']) && $data['delete']!='') {
			$this->wizard->delete_page('tablecol_'.$data['delete']);
			$r = array();
			if(preg_match('/^([0-9]+)_([0-9]+)$/',$data['delete'],$r))
				return 'tableoverview_'.$r[1];
			return 'tables';
		}
		if(isset($data['edit']) && $data['edit']!='') return 'tablecol_'.$data['edit'];
		return $data['go'];
	}

	public function overview_step($f,$data) {
		if(!isset($data['basic']) || !isset($data['requires'])) return;
		$f->addElement('header',null,__('Module overview'));
		
		$f->addElement('static',null,__('Name'),$data['basic']['name']);
		$path = ($data['basic']['path']=='__other__')?$data['basic']['path_other']:$data['basic']['path'];
		$f->addElement('static',null,__('Path'),$path);
		$f->addElement('static',null,__('Full name'),trim(str_replace('/','_',$path).'_'.$data['basic']['name'],'_'));
		
		$deps = array();
		if(isset($data['requires']['required']))
			foreach($data['requires']['required'] as $k=>$v)
				if($v!=-1) $deps[] = $k;
		$f->addElement('static',null,__('Dependencies'),implode($deps,', '));
		
		$tables = $this->get_tables_names();
		$f->addElement('static',null,__('Tables'),implode($tables,', '));
	}
	
	public function create_module($data){
		$path_r = ($data['basic']['path']=='__other__')?$data['basic']['path_other']:$data['basic']['path'];
		$class_name = trim(str_replace('/','_',$path_r).'_'.$data['basic']['name'],'_');
		$package_name = 'epesi-'.$path_r;
		$subpackage_name = $data['basic']['name'];
		$path = explode('/',$path_r.'/'.$data['basic']['name']);
		$all = 'modules';
		foreach($path as $v){
			if(!is_writable($all)) {
				print('Directory not writable: '.$all.'.');
				return false;
			}
			$all .= '/'.$v;
			if(file_exists($all)) {
				if(!is_dir($all)) {
					print('File already exists (not directory): '.$all.'.');
					return false;
				} 
			} else
				mkdir($all);
		}
		
		$tables = array();
		$reqs=array();
		foreach($data as $k=>$arr) {
			if(preg_match('/^table_([0-9]+)$/',$k,$reqs)) {
				$tables[$reqs[1]]['name'] = strtolower($class_name.'_'.$arr['name']);
			}
			if(preg_match('/^tablecol_([0-9]+)_([0-9]+)$/',$k,$reqs)) {
				$def = '';
				if ($arr['type']=='C') $arr['type'] .= '('.($arr['size']?$arr['size']:32).')';
				if (isset($arr['autoinc'])) $def .= ' AUTO';
				if (isset($arr['key'])) $def .= ' KEY';
				if (isset($arr['notnull'])) $def .= ' NOTNULL';
				if (isset($arr['defdate'])) {
					if ($arr['type']=='D') $def .= ' DEFDATE';
					if ($arr['type']=='T') $def .= ' DEFTIMESTAMP';
				} else {
					if ($arr['defaultval']) $def .= ' DEFAULT '.$arr['defaultval'];
				}
				if (!$arr['referencetable']) $cons = '';
					else $cons = ', FOREIGN KEY ('.$arr['name'].') REFERENCES '.$arr['referencetable'].'('.$arr['referencecolumn'].')';
				$tables[$reqs[1]]['cols'][] = array($arr['name'],$arr['type'],$def,$cons);
			}
		}
		
		
		$tables_drop = array();
		$tables_create_code = "";
		$constraints = "";
		foreach($tables as $v){
			$tables_create_code .= "\t\t\$ret &= DB::CreateTable('".$v['name']."','";
			if(isset($v['cols'])) {
				$first = true;
				$constraints = '';
				foreach($v['cols'] as $u){
					$tables_create_code .= ($first?"":",")."\n\t\t\t".$u[0].' '.$u[1].$u[2];
					$first = false;
					$constraints .= $u[3];
				}
			}
			$tables_create_code .= "',\n\t\t\tarray('constraints'=>'".$constraints."'));\n".
					"\t\tif(!\$ret){\n".
				"\t\t\tprint('Unable to create table ".$v['name'].".<br>');\n".
				"\t\t\treturn false;\n".
				"\t\t}\n";
			$tables_drop[] = "\$ret &= DB::DropTable('".$v['name']."');";
		}
		if (empty($tables)) {
			$tables_create_code = "\t\treturn true;\n";
			$tables_drop_code = "\t\treturn true;\n";
		} else {
			$tables_create_code =	"\t\t\$ret = true;\n".
									$tables_create_code.
									"\t\treturn \$ret;\n";
			$tables_drop_code =		"\t\t\$ret = true;\n\t\t".
									implode("\n\t\t",array_reverse($tables_drop)).
									"\n\t\treturn \$ret;\n";
		}
		$required = "";
		$first = true;
		if(isset($data['requires']['required']))
			foreach($data['requires']['required'] as $k=>$v)
				if($v!='-1') {
					$required .= ($first?"":",")."\n\t\t\tarray('name'=>'".str_replace('_','/',$k)."','version'=>".$v.")";	
					$first = false;	
				}
			
		$description = addslashes($data['basic']['description']);
		$author = addslashes($data['basic']['author']);
		$licence = addslashes($data['basic']['licence']);
		$header = "/**\n".
			  " * ".str_replace("\n","\n * ",$description)."\n".
			  " * @author ".$author."\n".
			  " * @copyright Telaxus LLC\n".
			  " * @license ".$licence."\n".
			  " * @version 0.1\n".
			  " * @package ".$package_name."\n".
			  " * @subpackage ".$subpackage_name."\n".
			  " */\n";
			
		$module_code = 	"<?php\n".
				$header.
				"defined(\"_VALID_ACCESS\") || die('Direct access forbidden');\n".
				"\n".
				"class ".$class_name." extends Module {\n".
				"\n".
				"\tpublic function body() {\n".
				"\t\n".
				"\t}\n".
				"\n".
				"}\n".
				"\n".
				"?>";

		$install_code =	"<?php\n".
				$header.
				"defined(\"_VALID_ACCESS\") || die('Direct access forbidden');\n".
				"\n".
				"class ".$class_name."Install extends ModuleInstall {\n".
				"\n".
				"\tpublic function install() {\n".
				$tables_create_code.
				"\t}\n".
				"\t\n".
				"\tpublic function uninstall() {\n".
				$tables_drop_code.
				"\t}\n".
				"\t\n".
				"\tpublic function version() {\n".
				"\t\treturn array(\"0.1\");\n".
				"\t}\n".
				"\t\n".
				"\tpublic function requires(\$v) {\n".
				"\t\treturn array(".$required.");\n".
				"\t}\n".
				"\t\n".
				"\tpublic static function info() {\n".
				"\t\treturn array(\n\t\t\t'Description'=>'".str_replace("\n",'<br>',$description)."',\n\t\t\t'Author'=>'".$author."',\n\t\t\t'License'=>'".$licence."');\n".
				"\t}\n".
				"\t\n".
				"\tpublic static function simple_setup() {\n".
				"\t\treturn ".(((isset($data['basic']['simple']) && $data['basic']['simple']) || $data['basic']['path']=='Apps' || $data['basic']['path']=='Applets' || $data['basic']['path']=='Tools')?'true':'false').";\n".
				"\t}\n".
				"\t\n".
				"}\n".
				"\n".
				"?>";
		$common_code = 	"<?php\n".
				$header.
				"defined(\"_VALID_ACCESS\") || die('Direct access forbidden');\n".
				"\n".
				"class ".$class_name."Common extends ModuleCommon {\n".
				"\n".
				"}\n".
				"\n".
				"?>";
		$bb = 'modules/'.$path_r.'/'.$data['basic']['name'].'/'.$data['basic']['name'];
		file_put_contents($bb.'_0.php',$module_code);
		file_put_contents($bb.'Install.php',$install_code);
		file_put_contents($bb.'Common_0.php',$common_code);
		print('Module has been created.');
		return true;
	}
	
	private function get_tables_names() {
		$tables = array();
		$data = $this->wizard->get_data();
		$reqs = array();
		foreach($data as $k=>$arr)
			if(preg_match('/^table_([0-9]+)$/',$k,$reqs))
				$tables[$reqs[1]] = $arr['name'];
		return $tables;
	}

	private function get_tables() {
		$tables = array();
		$data = $this->wizard->get_data();
		$reqs = array();
		foreach($data as $k=>$arr) {
			if(preg_match('/^table_([0-9]+)$/',$k,$reqs))
				$tables[$reqs[1]]['name'] = $arr['name'];
			if(preg_match('/^tablecol_([0-9]+)_([0-9]+)$/',$k,$reqs)) {
				$tables[$reqs[1]]['cols'][] = $arr;
			}
		}
		return $tables;
	}

	public function caption() {
			return __('Module creator');
			}
			
	public static function simple_setup() {
				return true;
				}
			}

?>
