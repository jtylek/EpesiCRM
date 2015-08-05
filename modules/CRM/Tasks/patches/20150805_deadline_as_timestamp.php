<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

PatchUtil::db_alter_column('task_data_1', 'f_deadline', 'T');
$sql = 'UPDATE task_field SET type=%s WHERE field=%s';
DB::Execute($sql, array('timestamp', 'Deadline'));

if (DB::is_mysql()) {
    $sql = 'UPDATE task_data_1 SET f_deadline = TIMESTAMP(f_deadline, TIME(f_deadline_time))';
} else {
    $sql = 'UPDATE task_data_1 SET f_deadline = f_deadline + CAST(f_deadline_time as time)';
}
DB::Execute($sql);

Utils_RecordBrowserCommon::delete_record_field('task', 'Deadline Time');
