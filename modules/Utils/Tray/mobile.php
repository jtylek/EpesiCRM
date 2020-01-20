<?php
/**
 * TrayCommon class.
 *
 * @author Georgi Hristov <ghristov@gmx.de>
 * @copyright Copyright &copy; 2014, Xoff Software GmbH
 * @license MIT
 * @version 1.9.0
 * @package epesi-tray
 */

defined("_VALID_ACCESS") || die();

$cols_out = array(
array('name'=>__('Slot'), 'width'=>70),
array('name'=>__('Pending'), 'width'=>35));

$tray_settings = Utils_TrayCommon::get_trays();

$tray_def = array();

foreach ($tray_settings as $module=>$module_settings) {
	foreach ($module_settings as $tab=>$tab_settings) {
		$tray = Utils_TrayCommon::get_tray($tab, $tab_settings);
		if (count($tray['__slots__'])==0) continue;

		$tray_id = Utils_RecordBrowserCommon::get_field_id($tray['__title__']);

		$tray_def += array($tray_id =>array('__title__' => $tray['__title__'], '__weight__'=>isset($tray['__weight__'])?$tray['__weight__']:0));

		foreach ($tray['__slots__'] as $slot_id=>$slot_def) {
			$cap = _V($tray['__title__']).' - '._V($slot_def['__name__']);

			$sort = isset($tab_settings['__mobile__']['sort'])? $tab_settings['__mobile__']['sort']: array();
			$cols = isset($tab_settings['__mobile__']['cols'])? $tab_settings['__mobile__']['cols']: array();

			$open = '<a '.mobile_stack_href(array('Utils_TrayCommon', 'mobile_tray_rb'),array($tab, $slot_def['__crits__'], $sort, $cols),$cap).'>';
			$close = '</a>';

			$tray_def[$tray_id]['__slots__'][$slot_id]['__weight__'] = isset($slot_def['__weight__'])? $slot_def['__weight__']: 0;

			if(IPHONE) {
				$row_info = '';
				$row_info .= '<div>'._V($tray['__title__']).'</div><div>'._V($slot_def['__name__']).' ('.$slot_def['__count__'].')</div>';
				$row = $open.$row_info.$close;
				$tray_def[$tray_id]['__slots__'][$slot_id]['__html__'] = '<li class="arrow">'.$row.'</li>';
			} else {
				$row = array();
				$row = array($open._V($tray['__title__']).'<br>'._V($slot_def['__name__']).$close, $slot_def['__count__']);
				$tray_def[$tray_id]['__slots__'][$slot_id]['__html__'] = $row;
			}
		}
	}
}

Utils_TrayCommon::sort_trays($tray_def);

if(IPHONE) {
	$html = '';
} else
$data_out = array();

foreach ($tray_def as $tray_id=>$def) {
	foreach ($def['__slots__'] as $slot) {
		if(IPHONE) {
			$html .= $slot['__html__'];
		} else {
			$data_out[] = $slot['__html__'];
		}
	}
}

//display table
if(IPHONE) {
	print('<ul>'.$html.'</ul>');
} else {
	Utils_GenericBrowserCommon::mobile_table($cols_out,$data_out,false);
}

?>