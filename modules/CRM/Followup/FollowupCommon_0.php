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

	public static function add_tracing_notes($dest_rset, $dest_id, $dest_label, $linkto_rset, $linkto_id, $linkto_label) {
		$after = __('Follow-up after').': ';
		$follow = __('Follow-up').': ';
		switch ($dest_rset) {
			case 'phonecall':
				$fwd_note_path = 'phonecall/'.$dest_id; 
				$bck_note = $after.'[phone='.$dest_id.']'.$dest_label.'[/phone]'; 
				break;
			case 'meeting': 
				$fwd_note_path = 'crm_meeting/'.$dest_id; 
				$bck_note = $after.'[meeting='.$dest_id.']'.$dest_label.'[/meeting]'; 
				break;
			case 'task': 
				$fwd_note_path = 'task/'.$dest_id; 
				$bck_note = $after.'[task='.$dest_id.']'.$dest_label.'[/task]'; 
				break;
		}
		switch ($linkto_rset) {
			case 'phonecall': 
				$bck_note_path = 'phonecall/'.$linkto_id; 
				$fwd_note = $follow.'[phone='.$linkto_id.']'.$linkto_label.'[/phone]'; 
				break;
			case 'meeting': 
				$bck_note_path = 'crm_meeting/'.$linkto_id; 
				$fwd_note = $follow.'[meeting='.$linkto_id.']'.$linkto_label.'[/meeting]'; 
				break;
			case 'task': 
				$bck_note_path = 'task/'.$linkto_id; 
				$fwd_note = $follow.'[task='.$linkto_id.']'.$linkto_label.'[/task]'; 
				break;
		}
		Utils_AttachmentCommon::add($fwd_note_path,0,Acl::get_user(),$fwd_note);
		Utils_AttachmentCommon::add($bck_note_path,0,Acl::get_user(),$bck_note);
	}
	
	public static function drawLeightbox($prefix) {
		if(MOBILE_DEVICE) return;
		$meetings = (ModuleManager::is_installed('CRM/Meeting')>=0);
		$tasks = (ModuleManager::is_installed('CRM/Tasks')>=0);
		$phonecall = (ModuleManager::is_installed('CRM/PhoneCall')>=0);
		self::check_location();
		if (!isset(self::$leightbox_ready[$prefix])) {
			self::$leightbox_ready[$prefix] = true;

			$theme = Base_ThemeCommon::init_smarty();
			eval_js_once($prefix.'_followups_deactivate = function(){leightbox_deactivate(\''.$prefix.'_followups_leightbox\');}');
	
			if ($meetings) {
				$theme->assign('new_meeting',array('open'=>'<a id="'.$prefix.'_new_meeting_button" onclick="'.$prefix.'_set_action(\'new_meeting\');'.$prefix.'_submit_form();">','text'=>__( 'New Meeting'),'close'=>'</a>'));
				eval_js('Event.observe(\''.$prefix.'_new_meeting_button\',\'click\', '.$prefix.'_followups_deactivate)');
			}

			if ($tasks) {
				$theme->assign('new_task',array('open'=>'<a id="'.$prefix.'_new_task_button" onclick="'.$prefix.'_set_action(\'new_task\');'.$prefix.'_submit_form();">','text'=>__( 'New Task'),'close'=>'</a>'));
				eval_js('Event.observe(\''.$prefix.'_new_task_button\',\'click\', '.$prefix.'_followups_deactivate)');
			}

			if ($phonecall) {
				$theme->assign('new_phonecall',array('open'=>'<a id="'.$prefix.'_new_phonecall_button" onclick="'.$prefix.'_set_action(\'new_phonecall\');'.$prefix.'_submit_form();">','text'=>__( 'New Phonecall'),'close'=>'</a>'));
				eval_js('Event.observe(\''.$prefix.'_new_phonecall_button\',\'click\', '.$prefix.'_followups_deactivate)');
			}

			$theme->assign('just_close',array('open'=>'<a id="'.$prefix.'_just_close_button" onclick="'.$prefix.'_set_action(\'none\');'.$prefix.'_submit_form();">','text'=>__( 'Save'),'close'=>'</a>'));
			eval_js('Event.observe(\''.$prefix.'_just_close_button\',\'click\', '.$prefix.'_followups_deactivate)');

			eval_js($prefix.'_submit_form = function () {'.
						'$(\''.$prefix.'_follow_up_form\').submited.value=1;Epesi.href($(\''.$prefix.'_follow_up_form\').serialize(), \'processing...\');$(\''.$prefix.'_follow_up_form\').submited.value=0;'.
					'}');
			eval_js($prefix.'_set_action = function (arg) {'.
						'document.forms["'.$prefix.'_follow_up_form"].action.value = arg;'.
					'}');
			eval_js($prefix.'_set_id = function (id) {'.
						'document.forms["'.$prefix.'_follow_up_form"].id.value = id;'.
						'$("'.$prefix.'_closecancel").value=3;'.
						'$("'.$prefix.'_note").value="";'.
					'}');
			$theme->assign('form_open','<form id="'.$prefix.'_follow_up_form" name="'.$prefix.'_follow_up_form" method="POST">'.
							'<input type="hidden" name="submited" value="0" />'.
							'<input type="hidden" name="form_name" value="'.$prefix.'_follow_up_form" />'.
							'<input type="hidden" name="id" value="" />'.
							'<input type="hidden" name="action" value="" />');
			$theme->assign('form_closecancel',	array(
							'label'=>__('Status'),
							'html'=>'<select name="closecancel" id="'.$prefix.'_closecancel" value="0">'.
								'<option value="0">'.__( 'Open').'</option>'.
								'<option value="1">'.__( 'In Progress').'</option>'.
								'<option value="2">'.__( 'On Hold').'</option>'.
								'<option value="3" selected="1">'.__( 'Close').'</option>'.
								'<option value="4">'.__( 'Canceled').'</option>'.
							'</select>'));
			$theme->assign('form_note',			array(
							'label'=>__('Note'),
							'html'=>'<textarea name="note" id="'.$prefix.'_note"></textarea>'));
			$theme->assign('form_close','</form>');
			ob_start();
			Base_ThemeCommon::display_smarty($theme,'CRM_Followup','leightbox');
			$profiles_out = ob_get_clean();

			Libs_LeightboxCommon::display($prefix.'_followups_leightbox',$profiles_out,__( 'Follow-up'));
		}
	}

}

?>
