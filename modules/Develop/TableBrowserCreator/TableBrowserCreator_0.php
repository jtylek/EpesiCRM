<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.5
 * @license MIT
 * @package epesi-develop
 * @subpackage tablebrowsercreator
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Develop_TableBrowserCreator extends Module {
		
	public function body() {
		if (!Base_AclCommon::i_am_admin()) {
			print('You are not authorised to use this module.');
			return;
		}
		
		global $modules;
		
		if ($_REQUEST['action']=='new') $this->set_module_variable('action','new');
		$action = $this->get_module_variable('action','new');
		if ($action == 'new') {
			$this->create_new_module();
			return;
		}
		$basic_info = $this->get_module_variable('basic_info'); 
		if ($modules[$basic_info['full_name']]){ // TODO might be problem on windows: test == Test
			print(__('Sorry, but table with given name already exists.'));
			$this->unset_module_variable('action');
			return;
		}
		if ($this->is_back()){
			$table = $this->get_module_variable('table');
			$bi = $this->get_module_variable('basic_info');
			$tables_drop_code = "";
			$tables_create_code = "";
			$constraints = "";
			$tables_create_code .= "\t\t\$ret &= DB::CreateTable('sqltablebrowser_".$bi['name']."','";
			$first = true;
			foreach($table as $u){
				$tables_create_code .= ($first?"":",")."\n\t\t\t".$u['tname'].' '.$u['type'].$u['def'];
				$first = false;
			}
			$tables_create_code .= "',\n\t\t\tarray('constraints'=>'".$constraints."'));\n".
					"\t\tif(!\$ret){\n".
	    			"\t\t\tprint('Unable to create table sqltablebrowser_".$bi['name'].".<br>');\n".
	    			"\t\t\treturn false;\n".
	    			"\t\t}\n";
			$tables_drop_code .= "\t\t\$ret &= DB::DropTable('sqltablebrowser_".$bi['name']."');\n";
			$tables_create_code =	"\t\t\$ret = true;\n".
									$tables_create_code.
									"\t\treturn \$ret;\n";
			$tables_drop_code =		"\t\t\$ret = true;\n".
									$tables_drop_code.
									"\t\treturn \$ret;\n";
			$required = "";
			$first = true;
			foreach($bi['required'] as $k=>$v){
				$required .= ($first?"":",")."\n\t\t\tarray('name'=>'".str_replace('_','/',$k)."','version'=>".$v.")";	
				$first = false;	
			}
			$provides = "";
			$first = true;
			foreach($bi['provides'] as $k=>$v){
				$provides .= ($first?"":",")."\n\t\t\tarray('name'=>'".str_replace('_','/',$k)."','version'=>".$v.")";
				$first = false;	
			}
			$module_code = 	"<?php\n".
							"defined(\"_VALID_ACCESS\") || die('Direct access forbidden');\n".
							"\n".
							"class ".str_replace('/','_',$bi['path']).'_'.$bi['name']." extends Module {\n".
							"\tprivate \$lang;\n".
							"\n".
							"\tpublic function body() {\n".
							"\t\tif (\$_REQUEST['module']) {\n".
							"\t\t\t\$m = &\$this->pack_module(\$_REQUEST['module']);\n".
							"\t\t\t\$this -> display_module($m);\n".
							"\t\t\treturn;\n".
							"\t\t}\n".
							"\t\t\$gb = & \$this->init_module('Utils/SQLTableBrowser','browse_sql');\n".
							"\t\t\$gb->set_table_format(array(\n";
			foreach ($table as $v){
				if ($v['display']) $module_code .= "\t\t\t\t\t\t\t\t\t\tarray('label'=>\_"."_('".$v['name']."'), 'width'=>".$v['width'].",'column'=>'".$v['tname']."','order'=>1),\n";
			}
			$module_code .= "\t\t\t\t\t\t\t\t\t\t));\n".
							"\t\t\$f=&\$this->init_module('Libs/QuickForm');\n";
			foreach ($table as $v){
				if ($v['name']=='Id') continue;
				if ($v['type']=='L') {
					$module_code .= "\t\t\$f->addElement('checkbox', '".$v['tname']."', '".$v['name']."');\n";
				} elseif ($v['type']=='D') {
					$module_code .= "\t\t\$f->addElement('datepicker', '".$v['tname']."', '".$v['name']."', array('format'=>'%d-%m-%y'));\n";
				} else
					$module_code .= "\t\t\$f->addElement('text', '".$v['tname']."', '".$v['name']."');\n";
				if ($v['type']=='F') $module_code .= "\t\t\$f->addRule('".$v['tname']."',\__('Must be integer'),'regex','/^-?[0-9][0-9]*\$/');\n";
				if ($v['type']=='I4') $module_code .= "\t\t\$f->addRule('".$v['tname']."',\__('Must be real number'),'regex','/^[0-9][0-9]*\.?[0-9]*\$/');\n";
				if (preg_match('/NOTNULL/',$v['def'])) $module_code .= "\t\t\$f->addRule('".$v['tname']."',\__('This field is required'),'required');\n";
			}
			$module_code .= "\t\t\$gb->set_table_properties(array('table_name'=>'sqltablebrowser_".$bi['name']."','view'=>1,'delete'=>1,'edit'=>1,'add'=>1,'id_row'=>'id','form'=>&\$f));\n".
							"\t\t\$this->display_module(\$gb);\n".
							"\t}\n".
							"\n".
							"}\n".
							"\n".
							"?>";
			$init_code = 	"<?php\n".
							"defined(\"_VALID_ACCESS\") || die('Direct access forbidden');\n".
							"\n".
							"class ".str_replace('/','_',$bi['path']).'_'.$bi['name']."Init extends ModuleInit {\n".
							"\n".
							"\tpublic static function requires() {\n".
							"\t\treturn array(".$required.");\n".
							"\t}\n".
							"\t\n".
							"\tpublic static function provides() {\n".
							"\t\treturn array(".$provides.");\n".
							"\t}\n".
							"\n".
							"}\n".
							"\n".
							"?>";
			$install_code =	"<?php\n".
							"defined(\"_VALID_ACCESS\") || die('Direct access forbidden');\n".
							"\n".
							"class ".str_replace('/','_',$bi['path']).'_'.$bi['name']."Install extends ModuleInstall {\n".
							"\n".
							"\tpublic static function install() {\n".
							$tables_create_code.
							"\t}\n".
							"\t\n".
							"\tpublic static function uninstall() {\n".
							$tables_drop_code.
							"\t}\n".
							"\t\n".
							"}\n".
							"\n".
							"?>";
			$common_code = 	"<?php\n".
							"defined(\"_VALID_ACCESS\") || die('Direct access forbidden');\n".
							"\n".
							"class ".str_replace('/','_',$bi['path']).'_'.$bi['name']."Common {\n".
							"\n".
							"\tpublic static function menu(){\n".
							"\t\treturn array(_M('Tables')=>array('__submenu__'=>1,'".$bi['name']."'=>array('action'=>'view')));\n".
							"\t}\n".
							"\n".
							"}\n".
							"\n".
							"?>";
			$path = explode('/',$bi['path'].'/'.$bi['name']);
			$all = 'modules';
			foreach($path as $v){
				$all .= '/'.$v;
				mkdir($all);
			}
			file_put_contents('modules/'.$bi['path'].'/'.$bi['name'].'/'.$bi['name'].'_0.php',$module_code);
			file_put_contents('modules/'.$bi['path'].'/'.$bi['name'].'/'.$bi['name'].'Init_0.php',$init_code);
			file_put_contents('modules/'.$bi['path'].'/'.$bi['name'].'/'.$bi['name'].'Install.php',$install_code);
			file_put_contents('modules/'.$bi['path'].'/'.$bi['name'].'/'.$bi['name'].'Common_0.php',$common_code);
			$this->unset_module_variable('table_name');
			$this->unset_module_variable('table');
			$this->unset_module_variable('basic_info');
			$this->unset_module_variable('action');
			print('Module has been created.');
			return;
		}
		if ($action=='create_tables') {
			$this->create_table();
			return;
		}
		if ($action=='table_display') {
			$this->table_display_preferences();
			return;
		}
	}
	
	public function create_new_module(){
		$f = $this->init_module('Libs/QuickForm',null,'name_of_the_module');
		$f->addElement('header', null, __('Specify name for new table'));
		$f->addElement('text', 'name', 'Name');
		$f->addRule('name', __('This field is required'), 'required');
		$f->addRule('name', __('Only letters are allowed'), 'regex', '/^[a-zA-Z]*$/');
		$f->addElement('submit','Submit',__('Submit'));
		if ($f->validate()) {
			$data = $f->exportValues();
			$results = array('name'=>$data['name'],'path'=>'Develop/TableBrowserCreator/Modules');
			$results['full_name'] = str_replace('/','_',$results['path']).'_'.$results['name'];
			$results['required'] = array(	'Base/Lang'=>0,
											'Utils/SQLTableBrowser'=>0
											);
			$this->set_module_variable('basic_info',$results);
			$this->set_module_variable('action','create_tables');
			$table = array(0=>array('name'=>'Id','tname'=>'id','type'=>'I4','def'=>' AUTO KEY NOTNULL','display'=>0));
			$this->set_module_variable('table',$table);
			location(array());
		} else $f->display();
	}
	
	private function display_basic_information(){
		$basic_info = $this->get_module_variable('basic_info');
		$form = $this->init_module('Libs/QuickForm',null,'info');
		$form->addElement('header',null,'Basic information');
		$form->addElement('static',null,'Name',$basic_info['name']);
		$form->display();
		
		$gb_t = $this->init_module('Utils/GenericBrowser',null,'tables');
		$gb_t->set_table_columns(array(	array('name'=>'Id.','width'=>'2'),
										array('name'=>'Name','width'=>'5'),
										array('name'=>'Columns','width'=>'30')
										));

		$tables = $this->get_module_variable('tables');
		$id = 1;
		foreach($tables as $k=>$v){
			$cols = '';
			foreach($v as $u) $cols .= ($cols?', ':'').$u[0];
			$gb_t -> add_row($id++, $k, $cols);
		}
		$this->display_module($gb_t);
	}
	
	private function table_display_preferences(){
			
	}
			
	private function create_table(){
		$basic_info = $this->get_module_variable('basic_info');
		$table = $this->get_module_variable('table');
		$table_name = 'tablebrowsercreator_'.$basic_info['name'];
		$this->set_module_variable('table_name',$table_name);

		$form = $this->init_module('Libs/QuickForm',null,'managing_table');
		$action = array();
		$action[] = &HTML_QuickForm::createElement('submit', 'submit', 'Add column to the table');
		$action[] = &HTML_QuickForm::createElement('button', 'finish', 'Finish creating the table',$this->create_back_href());
		$form->addGroup($action, 'action', '', ' ');

		if ($form->validate()){
			$this->set_module_variable('add_new_column',1);
		}
		if ($this->isset_module_variable('add_new_column')){
			$types = array(	'I4'=>'Integer',
							'F'=>'Real number',
							'L'=>'True/false',
							'D'=>'Date',
							//'T'=>'Timestamp',
							'C'=>'Character string');
			$add_col = $this->init_module('Libs/QuickForm',null,'add_new_column');
			$add_col->addElement('header',null,__('Add new column to the table'));
			$add_col->addElement('text','name',__('Column name'));
			$add_col->addRule('name',__('This field is required'),'required');
			$add_col->addRule('name',__('Invalid character'),'regex','/^[a-zA-Z ]*$/');
			$add_col->addElement('select','type',__('Column Type'), $types);
			$add_col->addElement('text','size',__('String length (only for Character string)'));
			$add_col->addRule('size',__('Must be positive integer'),'regex','/^[1-9][0-9]*$/');
			$add_col->addElement('text','defaultval',__('Default (leave blank for no default)'));
			$add_col->addElement('checkbox','notnull',__('Cannot be empty'));
			$add_col->addElement('checkbox','display',__('Display (table view)'));
			$add_col->setDefaults(array('display'=>1));
			$add_col->addElement('text','width',__('Column width (table view)'));
			$add_col->addRule('width',__('Must be positive integer'),'regex','/^[1-9][0-9]*$/');
			$add_col->addElement('submit','submit',__('Submit'));
			if ($add_col->validate()){
				$values = $add_col->exportValues();
				$def = '';
				if ($values['type']=='C') $values['type'] .= '('.($values['size']?$values['size']:64).')';
				if ($values['notnull']) $def .= ' NOTNULL';
				if ($values['defaultval']) $def .= ' DEFAULT '.$values['defaultval'];
				$table[] = array('name'=>$values['name'],'type'=>$values['type'],'def'=>$def,'display'=>$values['display'],'tname'=>strtolower(str_replace(' ','_',$values['name'])),'width'=>$values['width']?$values['width']:'50');
				$this->set_module_variable('table',$table);
				$this->unset_module_variable('add_new_column');
				location(array());
			} 
			$add_col->display();
		} else {
			print('Table name: <b>'.$basic_info['name'].'</b>');
			$gb = $this->init_module('Utils/GenericBrowser',null,'table');
			$gb->set_table_columns(array(	array('name'=>'Column Name','width'=>'8'),
											array('name'=>'Type','width'=>'3'),
											array('name'=>'Attributes','width'=>'10')
											));
			foreach($table as $v){
				$gb->add_row($v['name'],$v['type'],$v['def']);
			}
			$this -> display_module($gb);
			$form -> display();
		}
	}
	
}

?>
