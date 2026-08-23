<?php

Utils_RecordBrowserCommon::new_record_field('task', 
            array(
                'name'     => _M('Deadline Time'),
                'type'     => 'time',
                'required' => false,
                'extra'    => false,
                'visible'  => true,
                'position' => 'Deadline'
            ));

Utils_RecordBrowserCommon::set_display_callback('task','Deadline',array('CRM_TasksCommon','display_deadline'));