<?php
/**
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage followup
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_FollowupCommon extends ModuleCommon {
	public static $leightbox_ready = array();
	public static $last_location = null;
	
	public static function check_location() {
		if (isset($_REQUEST['__location']) && self::$last_location!=$_REQUEST['__location']) {
			self::$last_location = $_REQUEST['__location'];
			self::$leightbox_ready = array();	
		}
	}
	
	public static function drawLeightbox($prefix) {
		if(MOBILE_DEVICE) return;
		$events = (ModuleManager::is_installed('CRM/Calendar')>=0);
		$tasks = (ModuleManager::is_installed('CRM/Tasks')>=0);
		$phonecall = (ModuleManager::is_installed('CRM/PhoneCall')>=0);
		self::check_location();
		if (!isset(self::$leightbox_ready[$prefix])) {
			self::$leightbox_ready[$prefix] = true;

			$theme = Base_ThemeCommon::init_smarty();
			eval_js_once($prefix.'_followups_deactivate = function(){leightbox_deactivate(\''.$prefix.'_followups_leightbox\');}');
	
			if ($events) {
				$theme->assign('new_event',array('open'=>'<a id="'.$prefix.'_new_event_button" onclick="'.$prefix.'_set_action(\'new_event\');'.$prefix.'_submit_form();">','text'=>Base_LangCommon::ts('CRM/PhoneCall', 'New Event'),'close'=>'</a>'));
				eval_js('Event.observe(\''.$prefix.'_new_event_button\',\'click\', '.$prefix.'_followups_deactivate)');
			}

			if ($tasks) {
				$theme->assign('new_task',array('open'=>'<a id="'.$prefix.'_new_task_button" onclick="'.$prefix.'_set_action(\'new_task\');'.$prefix.'_submit_form();">','text'=>Base_LangCommon::ts('CRM/PhoneCall', 'New Task'),'close'=>'</a>'));
				eval_js('Event.observe(\''.$prefix.'_new_task_button\',\'click\', '.$prefix.'_followups_deactivate)');
			}

			if ($phonecall) {
				$theme->assign('new_phonecall',array('open'=>'<a id="'.$prefix.'_new_phonecall_button" onclick="'.$prefix.'_set_action(\'new_phonecall\');'.$prefix.'_submit_form();">','text'=>Base_LangCommon::ts('CRM/PhoneCall', 'New Phone Call'),'close'=>'</a>'));
				eval_js('Event.observe(\''.$prefix.'_new_phonecall_button\',\'click\', '.$prefix.'_followups_deactivate)');
			}

			$theme->assign('just_close',array('open'=>'<a id="'.$prefix.'_just_close_button" onclick="'.$prefix.'_set_action(\'none\');'.$prefix.'_submit_form();">','text'=>Base_LangCommon::ts('CRM/PhoneCall', 'No Follow up'),'close'=>'</a>'));
			eval_js('Event.observe(\''.$prefix.'_just_close_button\',\'click\', '.$prefix.'_followups_deactivate)');

			eval_js($prefix.'_submit_form = function () {'.
						'$(\''.$prefix.'_follow_up_form\').submited.value=1;Epesi.href($(\''.$prefix.'_follow_up_form\').serialize(), \'processing...\');$(\''.$prefix.'_follow_up_form\').submited.value=0;'.
					'}');
			eval_js($prefix.'_set_action = function (arg) {'.
						'document.forms["'.$prefix.'_follow_up_form"].action.value = arg;'.
					'}');
			eval_js($prefix.'_set_id = function (id) {'.
						'document.forms["'.$prefix.'_follow_up_form"].id.value = id;'.
					'}');
			$theme->assign('form_open','<form id="'.$prefix.'_follow_up_form" name="'.$prefix.'_follow_up_form" method="POST">'.
							'<input type="hidden" name="submited" value="0" />'.
							'<input type="hidden" name="form_name" value="'.$prefix.'_follow_up_form" />'.
							'<input type="hidden" name="id" value="" />'.
							'<input type="hidden" name="action" value="" />');
			$theme->assign('form_closecancel',	array(
							'label'=>Base_LangCommon::ts('CRM_Followup','Status'),
							'html'=>'<select name="closecancel" value="0">'.
								'<option value="2">'.Base_LangCommon::ts('CRM/PhoneCall', 'Close').'</option>'.
								'<option value="3">'.Base_LangCommon::ts('CRM/PhoneCall', 'Canceled').'</option>'.
							'</select>'));
			$theme->assign('form_note',			array(
							'label'=>Base_LangCommon::ts('CRM_Followup','Note'),
							'html'=>'<textarea name="note"></textarea>'));
			$theme->assign('form_close','</form>');
			ob_start();
			Base_ThemeCommon::display_smarty($theme,'CRM_Followup','leightbox');
			$profiles_out = ob_get_clean();

			Libs_LeightboxCommon::display($prefix.'_followups_leightbox',$profiles_out,Base_LangCommon::ts('CRM/PhoneCall', 'Follow up'));
		}
	}

}

?>
