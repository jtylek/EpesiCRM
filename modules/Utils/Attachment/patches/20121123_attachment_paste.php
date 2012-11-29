<?php

@DB::CreateTable('utils_attachment_clipboard','
	id I4 AUTO KEY NOTNULL,
	filename C(255),
	created_by I4,
	created_on T DEFTIMESTAMP',
	array('constraints'=>''));

?>
