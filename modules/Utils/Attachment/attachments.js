var uploader;

Utils_Attachment__restore_existing = function (id) {
	jq('#restore_existing_'+id).hide();
	jq('#delete_existing_'+id).show();
	jq('#existing_file_'+id).attr('class','file');
	var files = jq('#delete_files').val().split(';');
	for (var i in files) {
		if (files[i]==id) files.splice(i,1);
	}
	jq('#delete_files').val(files.join(';'));
}

Utils_Attachment__delete_existing = function (id) {
	jq('#restore_existing_'+id).show();
	jq('#delete_existing_'+id).hide();
	jq('#existing_file_'+id).attr('class','file deleted');
	jq('#delete_files').val(jq('#delete_files').val() + ';' + id); 
}

Utils_Attachment__delete_clipboard = function (id) {
	var files = jq('#clipboard_files').val().split(';');
	for (var i in files) {
		if (files[i]==id) files.splice(i,1);
	}
	jq('#clipboard_files').val(files.join(';'));
}

Utils_Attachment__add_clipboard = function (id) {
	jq('#clipboard_files').val(jq('#clipboard_files').val() + ';' + id); 
}

Utils_Attachment__add_file_to_list = function (name, size, id, upload, clipboard) {
	var button = '';
	if (clipboard) {
		Utils_Attachment__add_clipboard(id);
		button = '<a href="javascript:void(0);" onclick="this.onclick=null;Utils_Attachment__delete_clipboard(\''+id+'\');jq(\'#clipboard_file_'+id+'\').fadeOut();"><img src="'+Utils_Attachment__delete_button+'" /></a>';
		id = 'clipboard_file_'+id;
	} else {
		if (upload) {
			button = '<a href="javascript:void(0);" onclick="this.onclick=null;uploader.removeFile(uploader.getFile(\''+id+'\'));jq(\'#'+id+'\').fadeOut();"><img src="'+Utils_Attachment__delete_button+'" /></a>';
		} else {
			button = '<a href="javascript:void(0);" id="delete_existing_'+id+'" onclick="Utils_Attachment__delete_existing('+id+');"><img src="'+Utils_Attachment__delete_button+'" /></a>';
			button += '<a href="javascript:void(0);" id="restore_existing_'+id+'" onclick="Utils_Attachment__restore_existing('+id+');" style="display:none;"><img src="'+Utils_Attachment__restore_button+'" /></a>';
			id = 'existing_file_'+id;
		}
	}
	jq('#filelist').append('<div class="file" id="' + id + '"><div class="indicator">'+button+'</div><div class="filename">' + name + (size!=null?' (' + plupload.formatSize(size) + ')':'')+'</div></div>');
}

Utils_Attachment__init_uploader = function (max_fs) {
	uploader = new plupload.Uploader({
		runtimes : 'html5,flash',
		browse_button : 'pickfiles',
		container: 'multiple_attachments',
		max_file_size : max_fs,
		url : 'modules/Utils/Attachment/upload.php?CID='+Epesi.client_id,
		//resize : {width : 320, height : 240, quality : 90},
		preinit: uploader_attach_cb,
		flash_swf_url : 'modules/Utils/Attachment/js/lib/plupload.flash.swf'
	});

	function uploader_attach_cb() {
	uploader.bind('Error', function(up, e) {
	        alert(e.message);
	});

	uploader.bind('FilesAdded', function(up, files) {
		files.forEach(function(s, i) {
			Utils_Attachment__add_file_to_list(s.name, s.size, s.id, s);
		});
	});

	uploader.bind('UploadProgress', function(up, file) {
		jq('#'+file.id).find('div').first().html('<b>' + file.percent + "%</b>");
	});
	uploader.bind('UploadComplete', function(up,files){
	        up.files.length = 0;
		Utils_Attachment__submit_note();
	});
	uploader.bind('FileUploaded', function(up, file, response) {
		response = jq.parseJSON(response.response);
		if (response.error != undefined && response.error.code){
			alert(file.name+': '+response.error.message);
		}
	});
	}

	uploader.init();
}

document.onpaste = function(event) {
	if (jq("#clipboard_files").length==0) return;
    var items = event.clipboardData.items;
    var s = JSON.stringify(items);
	for (var i in items) {
		if (items[i].type=='image/png') {
			var blob = items[i].getAsFile();
			var reader = new FileReader();
			reader.onload = function(event) {
                        	Epesi.procOn++;
                        	Epesi.updateIndicator();
				jq.ajax("modules/Utils/Attachment/paste.php", {
					method: "post",
					data:{
						cid: Epesi.client_id,
						data: event.target.result
					},
					success:function(t) {
                                        	Epesi.procOn--;
                                        	Epesi.updateIndicator();
						var file = jq.parseJSON(t);
						Utils_Attachment__add_file_to_list(file.name, null, file.id, false, true);
					}
				});
			};
			reader.readAsDataURL(blob); 
			break;
		}
	}
}

utils_attachment_password = function(label,label_button,id,reload) {
    var elem = jq('#note_value_'+id);
    elem.html('<div>'+label+'<input type="password" id="attachment_pass_'+id+'" name="pass_'+id+'" style="max-width:200px"></input><input class="button" style="max-width:200px;height:auto;" type="button" value="'+label_button+'" id="attachment_submit_pass_'+id+'"/></div>');
    jq('#attachment_submit_pass_'+id).click(function() { utils_attachment_submit_password(id,reload); });
    jq('#attachment_pass_'+id).keypress(function (e) {  if (e.which == 13) { utils_attachment_submit_password(id,reload);e.preventDefault(); }});
}

function utils_attachment_submit_password(id,reload) {
    var pass = jq('#attachment_pass_'+id).val();
    if (pass!=null && pass!='') {
      jq.ajax("modules/Utils/Attachment/check_decrypt.php", {
        method: "post",
        data:{
            cid: Epesi.client_id,
            id: id,
            pass: pass
        },
        success:function(t) {
            result = jq.parseJSON(t);
            if(typeof result.error != "undefined") return alert(result.error);
            if(reload) {
                _chj("","","queue");
            } else {
                jq(document).trigger('e:loading');
                if(typeof result.js != "undefined") {
                    eval(result.js);
                }
                jq("#note_value_"+id).text(result.note);
                jq(document).trigger('e:load');
            }
        }
      });
    }
    return false;
}