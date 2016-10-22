utils_attachment_get_link = function(attach_file_id,cidd,desc) {
	jq.ajax('modules/Utils/Attachment/create_remote.php', {
		method: 'post',
		data: {
			file: attach_file_id,
			cid: cidd,
			description: desc
		},
		success: function(t) {
			prompt('Url to this file (valid for 1 week)',t);
		},
		error: function(xhr,status,t) {
			alert('Failure ('+status+')');
			Epesi.text(t,'error_box','p');
		}
	});
};
