<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-utils
 * @subpackage CommonData
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CommonData extends Module {
	/**
	 * For internal use only.
	 */
	public function admin() {
		if($this->is_back()) {
			if($this->parent->get_type()=='Base_Admin')
				$this->parent->reset();
			else
				location(array());
			return;
		}
		Base_ActionBarCommon::add('back',__('Back'),$this->create_back_href());

		$this->browse();
	}

	public function admin_array($name) {
		$this->browse($name);
	}

	/**
	 * For internal use only.
	 */
	public function edit($parent,$key=null){
		if ($this->is_back()) return false;

		$id = Utils_CommonDataCommon::get_id($parent);
		if (!$id) {
			print(__('No such array'));
			return false;
		}

		$f = $this->init_module('Libs/QuickForm',null,'edit');
		$f->addElement('header', null, ($key===null)?__('New node'):__('Edit node'));
		$f->add_table('utils_commondata_tree',array(
						array('name'=>'akey','label'=>__('Key'),
							'rule'=>array('type'=>'callback','param'=>array($parent,$key),
									'func'=>array($this,'check_key'),
									'message'=>__('Specified key already exists')),
							'rule'=>array('type'=>'callback','param'=>array($parent,$key),
									'func'=>array($this,'check_key2'),
									'message'=>__('Specified contains invalid character "/"'))
									
									),
						array('name'=>'value','label'=>__('Value'))
						));
		if($key!==null) {
			$value=Utils_CommonDataCommon::get_value($parent.'/'.$key);
			$f->setDefaults(array('akey'=>$key,'value'=>$value));
		}

		if ($f->validate()) {
			$submited = $f->exportValues();
			if($key!==null)
				Utils_CommonDataCommon::rename_key($parent,$key,$submited['akey']);
			Utils_CommonDataCommon::set_value($parent.'/'.$submited['akey'],$submited['value']);
			return false;
		}
		Base_ActionBarCommon::add('save',__('Save'),$f->get_submit_form_href());
		Base_ActionBarCommon::add('back',__('Cancel'),$this->create_back_href());
		$f->display();
		return true;
	}

	public function check_key($new_key,$arr) {
		if($arr[1]==$new_key) return true;
		return Utils_CommonDataCommon::get_id($arr[0].'/'.$new_key)===false;
	}

	public function check_key2($new_key,$arr) {
	    return strpos($new_key,'/')===false;
	}

	/**
	 * For internal use only.
	 */
	public function browse($name='',$root=true){
		if($this->is_back()) return false;

		$gb = $this->init_module('Utils/GenericBrowser',null,'browse'.md5($name));

		$gb->set_table_columns(array(
						array('name'=>__('Key'),'width'=>20, 'order'=>'akey','search'=>1,'quickjump'=>'akey'),
						array('name'=>__('Value'),'width'=>20, 'order'=>'value','search'=>1)
					));

		print('<h2>'.$name.'</h2><br>');
		$ret = Utils_CommonDataCommon::get_translated_array($name,true,true);
		foreach($ret as $k=>$v) {
			$gb_row = $gb->get_new_row();
			$gb_row->add_data($k,$v['value']); // ****** CommonData value translation
			$gb_row->add_action($this->create_callback_href(array($this,'browse'),array($name.'/'.$k,false)),'View');
			if(!$v['readonly']) {
				$gb_row->add_action($this->create_callback_href(array($this,'edit'),array($name,$k)),'Edit');
				$gb_row->add_action($this->create_confirm_callback_href(__('Delete array').' \''.Epesi::escapeJS($name.'/'.$k,false).'\'?',array('Utils_CommonData','remove_array'), array($name.'/'.$k)),'Delete');
			}
		}
		//$this->display_module($gb);
		$this->display_module($gb,array(true),'automatic_display');

		Base_ActionBarCommon::add('add',__('Add array'),$this->create_callback_href(array($this,'edit'),$name));
		if(!$root)
			Base_ActionBarCommon::add('back',__('Back'),$this->create_back_href());
		return true;
	}

	/**
	 * For internal use only.
	 */
	public static function remove_array($name){
		Utils_CommonDataCommon::remove($name);
	}

}

?>
