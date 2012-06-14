<?php

if (Variable::get('utils_attachments_google_user', false)=='') {
	Variable::set('utils_attachments_google_user', '');
	Variable::set('utils_attachments_google_pass', '');
}

@DB::CreateTable('utils_attachment_googledocs','
	id I4 AUTO KEY NOTNULL,
	note_id I4 NOTNULL,
	view_link C(255),
	doc_id C(128)',
	array('constraints'=>''));


?>
