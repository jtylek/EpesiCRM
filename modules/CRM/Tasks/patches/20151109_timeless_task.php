<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

$deadline_field_is_not_timestamp = !DB::GetOne('SELECT 1 FROM task_field WHERE field=%s AND type=%s', array('Deadline', 'timestamp'));

if ($deadline_field_is_not_timestamp) {
    PatchUtil::db_alter_column('task_data_1', 'f_deadline', 'T');
    DB::Execute('UPDATE task_field SET type=%s WHERE field=%s', array('timestamp', 'Deadline'));
}

$tab = 'task';
$field = array(
    'name'     => _M('Timeless'),
    'type'     => 'checkbox',
    'required' => false,
    'extra'    => false,
    'position' => 'Deadline',
    'QFfield_callback' => 'CRM_TasksCommon::QFfield_timeless'
);
Utils_RecordBrowserCommon::new_record_field($tab, $field);

$deadline_time_field_exists = DB::GetOne('SELECT 1 FROM task_field WHERE field=%s', array('Deadline Time'));

$sql = false;
if ($deadline_time_field_exists) {
    if (DB::is_mysql()) {
        $sql = 'UPDATE task_data_1 SET f_deadline = TIMESTAMP(f_deadline, TIME(COALESCE(f_deadline_time, FALSE))), f_timeless = (f_deadline_time IS NULL)';
    } else {
        $sql = 'UPDATE task_data_1 SET f_deadline = f_deadline + CAST(COALESCE(f_deadline_time, \'0:00\') as time), f_timeless = (f_deadline_time IS NULL)::int';
    }
} elseif ($deadline_field_is_not_timestamp) {
    if (DB::is_mysql()) {
        $sql = 'UPDATE task_data_1 SET f_deadline = TIMESTAMP(f_deadline, \'12:00:00\'), f_timeless = 1';
    } else {
        $sql = 'UPDATE task_data_1 SET f_deadline = f_deadline + CAST(\'12:00:00\' as time), f_timeless = 1';
    }
}
if ($sql) {
    DB::Execute($sql);
}

if ($deadline_time_field_exists) {
    Utils_RecordBrowserCommon::delete_record_field('task', 'Deadline Time');
}
