utils_attachment_get_link = function(attach_file_id,cidd,pathh,desc) {
	new Ajax.Request('modules/Utils/Attachment/create_remote.php', {
		method: 'post',
		parameters: {
			file: attach_file_id,
			cid: cidd,
			path: pathh,
			description: desc
		},
		onComplete: function(t) {
			prompt('Url to this file (valid for 1 week)',t.responseText);
		},
		onException: function(t,e) {
			throw(e);
		},
		onFailure: function(t) {
			alert('Failure ('+t.status+')');
			Epesi.text(t.responseText,'error_box','p');
		}
	});
};
