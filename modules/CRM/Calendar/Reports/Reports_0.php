<?php
/**
 * Simple reports for CRM Calendar
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage calendar-reports
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Calendar_Reports extends Module {

	public function body() {
		$t = time();
		$start = & $this->get_module_variable('agenda_start',date('Y-m-d', $t - (7 * 24 * 60 * 60)));
		$end = & $this->get_module_variable('agenda_end',date('Y-m-d',$t));

		$form = $this->init_module('Libs/QuickForm',null,'reports_frm');

		$form->addElement('datepicker', 'start', $this->t('From'));
		$form->addElement('datepicker', 'end', $this->t('To'));
		$form->addElement('submit', 'submit_button', $this->ht('Show'));
		$form->addRule('start', 'Field required', 'required');
		$form->addRule('end', 'Field required', 'required');
		$form->setDefaults(array('start'=>$start,'end'=>$end));

		if($form->validate()) {
			$data = $form->exportValues();
			$start = $data['start'];
			$end = $data['end'];
			$end = date('Y-m-d',strtotime($end)+86400);
		}
		$form->display();
		print('<br><br><br><br><br>');


		$tb = & $this->init_module('Utils/TabbedBrowser');
		$tb->set_tab($this->t("Time by color"), array($this,'time_by_color'),array($start,$end));
		$this->display_module($tb);
		$this->tag();

//		print($start.' = '.$end);
	}
	
	public function time_by_color($start,$end) { //TODO: recurring events
		$start_reg = Base_RegionalSettingsCommon::reg2time($start);
		$end_reg = Base_RegionalSettingsCommon::reg2time($end);
		
		$filter = CRM_FiltersCommon::get();
		if($filter=='()')
			$fil = ' AND 1=0';
		else if($filter)
			$fil = ' AND (SELECT id FROM crm_calendar_event_group_emp cg WHERE cg.id=e.id AND cg.contact IN '.$filter.' LIMIT 1) IS NOT NULL';
		else
			$fil = '';
		$my_id = CRM_FiltersCommon::get_my_profile();
		if(!Base_AclCommon::i_am_admin())
			$fil .= ' AND (e.access<2 OR (SELECT id FROM crm_calendar_event_group_emp cg2 WHERE cg2.id=e.id AND cg2.contact='.$my_id.' LIMIT 1) IS NOT NULL)';
		if (DATABASE_DRIVER=='postgres') {
			$method_begin = '(SELECT TIMESTAMP \'epoch\' + ';
			$method_end = ' * INTERVAL \'1 second\')';
		} else {
			$method_begin = 'FROM_UNIXTIME(';
			$method_end = ')';
		}
		$ret = DB::Execute('SELECT e.color,SUM(e.ends - e.starts) as duration FROM crm_calendar_event e WHERE deleted='.CRM_CalendarCommon::$trash.' AND ('.
			'(e.timeless=0 AND ((e.recurrence_type is null AND ((e.starts>=%d AND e.starts<%d) OR (e.ends>=%d AND e.ends<%d) OR (e.starts<%d AND e.ends>=%d))) OR (e.recurrence_type is not null AND ((e.starts>=%d AND e.starts<%d) OR (e.recurrence_end>=%D AND e.recurrence_end<%D) OR (e.starts<%d AND e.recurrence_end>=%D) OR (e.starts<%d AND e.recurrence_end is null))))) '.
			'OR '.
			'(e.timeless=1 AND ((e.recurrence_type is null AND DATE('.$method_begin.'e.starts'.$method_end.')>=%D AND DATE('.$method_begin.'e.starts'.$method_end.')<%D) OR (e.recurrence_type is not null AND ((DATE('.$method_begin.'e.starts'.$method_end.')<=%D AND e.recurrence_end>=%D) OR (DATE('.$method_begin.'e.starts'.$method_end.')>=%D AND DATE('.$method_begin.'e.starts'.$method_end.')<=%D) OR (e.recurrence_end>=%D AND e.recurrence_end<=%D) OR (e.starts<%d AND e.recurrence_end is null)))))) '.$fil.' GROUP BY e.color',array($start_reg,$end_reg,$start_reg,$end_reg,$start_reg,$end_reg,$start_reg,$end_reg,$start,$end,$start_reg,$end,$end_reg,$start,$end,$start,$end,$start,$end,$start,$end,strtotime($end)));


		$f = $this->init_module('Libs/OpenFlashChart');
		$title = new title( "Time by color" );
		$f->set_title( $title );

		$av_colors = array('#339933','#339933','#999933', '#993333', '#336699', '#808080','#339999','#993399');
		$max = 0;
		while($row = $ret->FetchRow()) {
			$bar = new bar_glass();
			$bar->set_colour($av_colors[$row['color']]);
			$duration = (float)$row['duration']/3600;
			$bar->set_key(number_format($duration,2),3);
			$bar->set_values( array($duration) );
			if($max<$duration) $max = $duration;
			$f->add_element( $bar );
		}
		$y_ax = new y_axis();
		$y_ax->set_range(0,$max);
		$y_ax->set_steps($max/10);
		$f->set_y_axis($y_ax);

		$f->set_width(950);
		$f->set_height(400);
		$this->display_module($f);

	}

}

?>