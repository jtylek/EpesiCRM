<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CalendarCommon extends ModuleCommon {
	private static $array_name_of_month = array(
		1 => array(
			1=>'Jan', 
			2=>'Feb', 
			3=>'Mar', 
			4=>'Apr', 
			5=>'May', 
			6=>'Jun', 
			7=>'Jul', 
			8=>'Aug', 
			9=>'Sep', 
			10=>'Oct', 
			11=>'Nov', 
			12=>'Dec',
			'01'=>'Jan', 
			'02'=>'Feb', 
			'03'=>'Mar', 
			'04'=>'Apr', 
			'05'=>'May', 
			'06'=>'Jun', 
			'07'=>'Jul', 
			'08'=>'Aug', 
			'09'=>'Sep'
		),
		2 => array(
			1=>'January', 
			2=>'February', 
			3=>'March', 
			4=>'April', 
			5=>'May', 
			6=>'June', 
			7=>'July', 
			8=>'August', 
			9=>'September', 
			10=>'October', 
			11=>'November', 
			12=>'December',
			'01'=>'January', 
			'02'=>'February', 
			'03'=>'March', 
			'04'=>'April', 
			'05'=>'May', 
			'06'=>'June', 
			'07'=>'July', 
			'08'=>'August', 
			'09'=>'September'
		)
	);
	private static $array_name_of_day = array(
		0 => array(
				0=>'S', 1=>'M', 2=>'T', 3=>'W', 4=>'T', 5=>'F', 6=>'S'
			),
		1 => array(
				0=>'Sun', 1=>'Mon', 2=>'Tue', 3=>'Wed', 4=>'Thu', 5=>'Fri', 6=>'Sat'
			),
		2 => array(
				0=>'Sunday', 1=>'Monday', 2=>'Tuesday', 3=>'Wednesday', 4=>'Thursday', 5=>'Friday', 6=>'Saturday'
			)
		);

	public static function user_settings() {
		if(Base_AclCommon::i_am_user())
			$ret = array(
				'Calendar'=>array(
					array('name'=>'first_day','label'=>'First day of week', 'type'=>'select', 'values'=>array(0=>'Sunday', 1=>'Monday', 2=>'Tuesday', 3=>'Wednestday', 4=>'Thursday', 5=>'Friday', 6=>'Saturday'), 'default'=>0),
					array('name'=>'view_style','label'=>'Default view', 'type'=>'select', 'values'=>array(0=>'Agenda', 1=>'Day', 2=>'Week', 3=>'Month', 4=>'Year'), 'default'=>2),
					array('name'=>'show_event_types','label'=>'Show Event Types', 'type'=>'select', 'values'=>array(0=>'No', 1=>'Yes'), 'default'=>0),

					array('name'=>'start_day','label'=>'Start day at', 'type'=>'select', 'values'=>range(0, 11), 'default'=>8),
					array('name'=>'end_day','label'=>'End day at', 'type'=>'select', 'values'=>range(0, 24), 'default'=>17),

					array('name'=>'defautl_today','label'=>'Start by default with today\'s date', 'type'=>'select', 'values'=>array(0=>'No', 1=>'Yes'), 'default'=>0),
					array('name'=>'details_fields_header','label'=>'Display in detailed tooltip', 'type'=>'header'),
					array('name'=>'show_detail_activity','label'=>'Action', 'type'=>'select', 'values'=>array(0=>'No', 1=>'Yes'), 'default'=>1),
					array('name'=>'show_detail_employees','label'=>'Participants', 'type'=>'select', 'values'=>array(0=>'No', 1=>'Yes'), 'default'=>1),
					array('name'=>'show_detail_customers','label'=>'Participants', 'type'=>'select', 'values'=>array(0=>'No', 1=>'Yes'), 'default'=>1),
					array('name'=>'show_detail_description','label'=>'Description', 'type'=>'select', 'values'=>array(0=>'No', 1=>'Yes'), 'default'=>1),
					array('name'=>'show_detail_access','label'=>'Access', 'type'=>'select', 'values'=>array(0=>'No', 1=>'Yes'), 'default'=>0),
					array('name'=>'show_detail_priority','label'=>'Priority', 'type'=>'select', 'values'=>array(0=>'No', 1=>'Yes'), 'default'=>0),
					array('name'=>'show_detail_created_by','label'=>'Crated by', 'type'=>'select', 'values'=>array(0=>'No', 1=>'Yes'), 'default'=>1),
					array('name'=>'show_detail_created_on','label'=>'Created on', 'type'=>'select', 'values'=>array(0=>'No', 1=>'Yes'), 'default'=>1),
					array('name'=>'show_detail_edited_by','label'=>'Edited by', 'type'=>'select', 'values'=>array(0=>'No', 1=>'Yes'), 'default'=>1),
					array('name'=>'show_detail_edited_on','label'=>'Edited on', 'type'=>'select', 'values'=>array(0=>'No', 1=>'Yes'), 'default'=>1)
				)
			);
			if(Base_AclCommon::i_am_admin()) {
				$ret['Calendar'][] = array('name'=>'show_private_header','label'=>'Admin options', 'type'=>'header');
				$ret['Calendar'][] = array('name'=>'show_private','label'=>'Show other\'s private Events', 'type'=>'select', 'values'=>array(0=>'No', 1=>'Yes'), 'default'=>0);
				// TODO: leave it here?
			}
			return $ret;
	}

	public static function caption() {
		return 'Calendar';
	}
	
	public static function applet_caption() {
		return 'Agenda';
	}

	public static function applet_info() {
		return 'Displays Clandar Agenda';
	}
}
?>
