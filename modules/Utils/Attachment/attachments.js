var uploader;

Utils_Attachment__restore_existing = function (id) {
	$('restore_existing_'+id).style.display="none";
	$('delete_existing_'+id).style.display="";
	$('existing_file_'+id).className = 'file';
	var files = $('delete_files').value.split(';');
	for (var i in files) {
		if (files[i]==id) files.splice(i,1);
	}
	$('delete_files').value = files.join(';');
}

Utils_Attachment__delete_existing = function (id) {
	$('restore_existing_'+id).style.display="";
	$('delete_existing_'+id).style.display="none";
	$('existing_file_'+id).className = 'file deleted';
	$('delete_files').value = $('delete_files').value + ';' + id; 
}

Utils_Attachment__delete_clipboard = function (id) {
	var files = $('clipboard_files').value.split(';');
	for (var i in files) {
		if (files[i]==id) files.splice(i,1);
	}
	$('clipboard_files').value = files.join(';');
}

Utils_Attachment__add_clipboard = function (id) {
	$('clipboard_files').value = $('clipboard_files').value + ';' + id; 
}

Utils_Attachment__add_file_to_list = function (name, size, id, upload, clipboard) {
	var button = '';
	if (clipboard) {
		Utils_Attachment__add_clipboard(id);
		button = '<a href="javascript:void(0);" onclick="this.onclick=null;Utils_Attachment__delete_clipboard(\''+id+'\');Effect.Fade(\'clipboard_file_'+id+'\',{duration:0.5});"><img src="'+Utils_Attachment__delete_button+'" /></a>';
		id = 'clipboard_file_'+id;
	} else {
		if (upload) {
			button = '<a href="javascript:void(0);" onclick="this.onclick=null;uploader.removeFile(uploader.getFile(\''+id+'\'));Effect.Fade(\''+id+'\',{duration:0.5});"><img src="'+Utils_Attachment__delete_button+'" /></a>';
		} else {
			button = '<a href="javascript:void(0);" id="delete_existing_'+id+'" onclick="Utils_Attachment__delete_existing('+id+');"><img src="'+Utils_Attachment__delete_button+'" /></a>';
			button += '<a href="javascript:void(0);" id="restore_existing_'+id+'" onclick="Utils_Attachment__restore_existing('+id+');" style="display:none;"><img src="'+Utils_Attachment__restore_button+'" /></a>';
			id = 'existing_file_'+id;
		}
	}
	$('filelist').innerHTML += '<div class="file" id="' + id + '"><div class="indicator">'+button+'</div><div class="filename">' + name + (size!=null?' (' + plupload.formatSize(size) + ')':'')+'</div></div>';
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
		files.each(function(s, i) { 
			Utils_Attachment__add_file_to_list(s.name, s.size, s.id, s);
		});
	});

	uploader.bind('UploadProgress', function(up, file) {
		$(file.id).getElementsByTagName('div')[0].innerHTML = '<b>' + file.percent + "%</b>";
	});
	uploader.bind('UploadComplete', function(up,files){
	        up.files.length = 0;
		Utils_Attachment__submit_note();
	});
	uploader.bind('FileUploaded', function(up, file, response) {
		response = response.response.evalJSON();
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
				new Ajax.Request("modules/Utils/Attachment/paste.php", {
					method: "post",
					parameters:{
						cid: Epesi.client_id,
						data: event.target.result
					},
					onSuccess:function(t) {
                                        	Epesi.procOn--;
                                        	Epesi.updateIndicator();
						var file = t.responseText.evalJSON();
						Utils_Attachment__add_file_to_list(file.name, null, file.id, false, true);
					}
				});
			};
			reader.readAsDataURL(blob); 
			break;
		}
	}
}

utils_attachment_password = function(label,id) {
    var pass = prompt(label);
    if (pass != null) {
        new Ajax.Request("modules/Utils/Attachment/check_decrypt.php", {
            method: "post",
            parameters:{
                cid: Epesi.client_id,
                id: id,
                pass: pass
            },
            onSuccess:function(t) {
                result = t.responseText.evalJSON();
                if(typeof result.error != "undefined") return alert(result.error);
                Event.fire(document,'e:loading');
                if(typeof result.js != "undefined") {
                    eval(result.js);
                }
                $("note_value_"+id).innerHTML = result.note;
                Event.fire(document,'e:load');
            }
        });
    }
}