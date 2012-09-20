<?php
Utils_RecordBrowserCommon::new_record_field('rc_accounts', array('name' => _M('Use EPESI Archive directories'), 'type'=>'checkbox', 'extra'=>true, 'visible'=>false));
DB::Execute('UPDATE rc_accounts_data_1 SET f_use_epesi_archive_directories=1');