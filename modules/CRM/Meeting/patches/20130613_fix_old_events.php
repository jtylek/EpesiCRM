<?php

@set_time_limit(0);

// both mysql and postgresql should have this function
$substr_func = 'substr';

$time_field = 'f_time';
$date_field = 'f_date';
if (DATABASE_DRIVER == 'postgres') {
    // apply types cast required by postgres
    $time_field .= '::text';
    $date_field .= '::text';
}
$new_time_sql = DB::Concat($date_field, DB::qstr(' '), "$substr_func($time_field,12)");
if (DATABASE_DRIVER == 'postgres') {
    // apply type cast again for postgres
    $new_time_sql = "cast($new_time_sql as timestamp)";
}
$sql = "UPDATE crm_meeting_data_1 SET f_time = $new_time_sql WHERE f_time IS NOT NULL AND LENGTH($time_field)=19";
DB::Execute($sql);

?>