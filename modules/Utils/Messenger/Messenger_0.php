<?php
/**
 * Popup message to the user
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package utils-messenger
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_Messenger extends Module {
	private $mid;
	private $autosave;
	private $def_topic;
	private $def_message;
	private $def_date;
	private $real_id;

	public function pop_box0() {
		$x = ModuleManager::get_instance('/Base_Box|0');
		if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$x->pop_main();
	}

	public function push_box0($func,$args,$const_args) {
		$x = ModuleManager::get_instance('/Base_Box|0');
		if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$x->push_main('Utils/Messenger',$func,$args,$const_args);
	}

	public function construct($id,$def_topic,$def_message,$def_date=null,$autosave=true) {
		$this->lang = & $this->init_module('Base/Lang');
		if(!isset($id)) {
			print($this->lang->t('Messenger: no ID given - unable to attach messages editor'));
			return;
		}
		
		$this->mid = md5($id);
		$this->real_id = $id;
		$this->autosave = $autosave;
		$this->def_topic = $def_topic;
		$this->def_message = $def_message;
		$this->def_date = ($def_date!=null)?$def_date:time();
		
		if($autosave || !$this->isset_module_variable('data')) {
			$data = DB::GetAll('SELECT * FROM utils_messenger_message WHERE page_id=\''.$this->mid.'\'');
			$this->set_module_variable('data',$data);
		}
	}
	
	public function save() {
		if($this->autosave) return;
		DB::Execute('DELETE FROM utils_messenger_message WHERE page_id=\''.$this->mid.'\'');
		$data = $this->get_module_variable('data');
		foreach($data as $row)
			DB::Execute('INSERT INTO utils_messenger_message(page_id) VALUES(%s)',array($this->mid));		
	}
	
	public function edit($row) {
		if($this->is_back())
			$this->pop_box0();

		$f = &$this->init_module('Libs/QuickForm');
		
		if($row) {
			$f->setDefaults(array_merge($row,array('alert_date'=>$row['alert_on'],'alert_time'=>$row['alert_on'])));
		} else {
			$tt = $this->def_date;
			$tt = $tt-$tt%300;
			$f->setDefaults(array('topic'=>$this->def_topic,'message'=>$this->def_message,'alert_date'=>$tt,'alert_time'=>$tt));
		}

		$f->addElement('text', 'topic', $this->lang->t('Topic'),array('maxlength'=>128));
		$f->addRule('topic',$this->lang->t('This field is required'),'required');
		$f->addRule('topic',$this->lang->t('Field too long, max 128 chars'),'maxlength',128);

		$f->addElement('textarea', 'message', $this->lang->t('Message'));
		$f->addElement('datepicker', 'alert_date', $this->lang->t('Alert date'));
		$lang_code = Base_LangCommon::get_lang_code();
		$time_format = Base_RegionalSettingsCommon::time_12h()?'h:i:a':'H:i';
		$f->addElement('date', 'alert_time', $this->lang->t('Alert time'), array('format'=>$time_format, 'optionIncrement'  => array('i' => 5), 'language'=>$lang_code));

		if($f->validate()) {
			$ret = $f->exportValues();
			if($row)
				$ret = array_merge($row,$ret);
			if(Base_RegionalSettingsCommon::time_12h())
				$ret['alert_on'] = strtotime($ret['alert_date'])+($ret['alert_time']['h']%12)*3600+(($ret['alert_time']['a']=='pm')?(3600*12):0)+$ret['alert_time']['i']*60;
			else
				$ret['alert_on'] = strtotime($ret['alert_date'])+$ret['alert_time']['H']*3600+$ret['alert_time']['i']*60;
			$ret['alert_on'] = Base_RegionalSettingsCommon::server_time($ret['alert_on']);
			if($this->autosave) {
				if($row)
					DB::Execute('UPDATE utils_messenger_message SET topic=%s,message=%s,alert_on=%T WHERE page_id=\''.$this->mid.'\' AND id=%d',array($ret['topic'],$ret['message'],$ret['alert_on'],$row['id']));
				else
					DB::Execute('INSERT INTO utils_messenger_message(page_id,message,topic,created_on,created_by,alert_on) VALUES(%s,%s,%s,%T,%d,%T)',array($this->mid,$ret['message'],$ret['topic'],time(),Acl::get_user(),$ret['alert_on']));
			} else {
				$data = $this->get_module_variable('data');
				if($row) {
					foreach($data as & $rr)
						if($rr['id']==$row['id']) {
							$rr = $ret;
							break;
						}
				} else {
					$data[] = $ret;
				}
				$this->set_module_variable('data',$data);
			}
			$this->pop_box0();
		}
		
		Base_ActionBarCommon::add('save','Save',$f->get_submit_form_href());
		Base_ActionBarCommon::add('back','Back',$this->create_back_href());
		$f->display();
	}
	
	public function delete_entry($id) {
		if($this->autosave) {
			DB::Execute('DELETE FROM utils_messenger_message WHERE page_id=%s AND id=%d',array($this->mid,$id));
		} else {
			$data = & $this->get_module_variable('data');
			foreach($data as $k => $rr)
				if($rr['id']==$id) {
					unset($data[$k]);
					break;
				}
		}
		location(array());
	}

	public function body() {
		$gb = & $this->init_module('Utils/GenericBrowser',null,'messages');
		$data = $this->get_module_variable('data');
		$gb->set_table_columns(array(
			array('name'=>$this->lang->t('Topic'), 'width'=>70),
			array('name'=>$this->lang->t('Alert on'), 'width'=>30),
				));
		foreach($data as $row) {
			$r = & $gb->get_new_row();
			$r->add_data(Utils_TooltipCommon::create($row['topic'],$row['message']),Base_RegionalSettingsCommon::time2reg($row['alert_on']));
			$r->add_action($this->create_callback_href(array($this,'push_box0'),array('edit',array($row),array($this->real_id,$this->def_topic,$this->def_message,$this->def_date,$this->autosave))),'Edit');
			$r->add_action($this->create_confirm_callback_href($this->lang->ht('Are you sure?'),array($this,'delete_entry'),$row['id']),'Delete');
		}
		$this->display_module($gb);
		
		Base_ActionBarCommon::add('add','New message',$this->create_callback_href(array($this,'push_box0'),array('edit',array(false),array($this->real_id,$this->def_topic,$this->def_message,$this->def_date,$this->autosave))));	
	}
}

?>