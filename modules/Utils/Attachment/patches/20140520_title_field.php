<?php
DB::Execute('UPDATE utils_attachment_field SET visible = 0 WHERE field=%s',array('Title'));
