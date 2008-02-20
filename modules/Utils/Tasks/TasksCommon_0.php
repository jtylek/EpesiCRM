<?php
/**
 * Tasks
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package utils-tasks
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_TasksCommon extends ModuleCommon {
	public static function delete_page($id) {
		$mid = md5($id);
		Utils_AttachmentCommon::persistent_mass_delete(null,'CRM/Tasks/'.$mid);
		DB::Execute('DELETE FROM utils_tasks_assigned_contacts WHERE task_id in (SELECT id FROM utils_tasks_task WHERE page_id=%s)',array($mid));
		DB::Execute('DELETE FROM utils_tasks_related_contacts WHERE task_id in (SELECT id FROM utils_tasks_task WHERE page_id=%s)',array($mid));
		DB::Execute('DELETE FROM utils_tasks_task WHERE page_id=%s',array($mid));
	}
}

?>