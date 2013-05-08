var expandable_notes = new Array();
var expandable_notes_amount = 0;
var expanded_notes = 0;

utils_attachment_show_hide_buttons = function () {
	if (expandable_notes.length==0) {
		$("expand_all_button").show();
		$("collapse_all_button").hide();
		return;
	}	
	if (expanded_notes>=expandable_notes_amount) {
		$("expand_all_button").hide();
		$("collapse_all_button").show();
	} else {
		$("expand_all_button").show();
		$("collapse_all_button").hide();
	}
}

utils_attachment_expand = function(id) {
	if($("note_"+id)) {
		$("note_"+id).style.height = "";
		$("note_"+id).className = "note_field expanded";
		$("utils_attachment_more_"+id).hide();
		$("utils_attachment_less_"+id).show();
		if (expandable_notes[id])
			expanded_notes++;
	}
	utils_attachment_show_hide_buttons();
};

utils_attachment_expand_all = function() {
	for(var n in expandable_notes) utils_attachment_expand(n);
	expanded_notes = expandable_notes_amount;
	utils_attachment_show_hide_buttons();
};

utils_attachment_collapse = function(id) {
	if($("note_"+id)) {
		$("note_"+id).style.height = "18px";
		$("note_"+id).className = "note_field";
		$("utils_attachment_more_"+id).show();
		$("utils_attachment_less_"+id).hide();
		if (expandable_notes[id])
			expanded_notes--;
	}
	utils_attachment_show_hide_buttons();
};

utils_attachment_collapse_all = function() {
	for(var n in expandable_notes) utils_attachment_collapse(n);
	expanded_notes = 0;
	utils_attachment_show_hide_buttons();
};

init_note_expandable = function(id, skipTimed, files) {
	var el = $("note_"+id);
	if (el.clientHeight<el.scrollHeight || files) {
		$("utils_attachment_less_"+id).hide();
		$("utils_attachment_more_"+id).show();
		$("utils_attachment_less_"+id).childNodes[0].src = notes_collapse_icon;
		$("utils_attachment_more_"+id).childNodes[0].src = notes_expand_icon;
		expandable_notes[id] = id;
		expandable_notes_amount++;
		utils_attachment_show_hide_buttons();
        el.onclick = function () { if (el.style.height != "") utils_attachment_expand(id) };
	} else {
		$("utils_attachment_less_"+id).hide();
		$("utils_attachment_more_"+id).show();
		$("utils_attachment_less_"+id).childNodes[0].src = notes_collapse_icon_off;
		$("utils_attachment_more_"+id).childNodes[0].src = notes_expand_icon_off;
		if (!skipTimed) {
			setTimeout('init_note_expandable('+id+', 1);', 500);
		}
	}
};

init_expand_note_space = function() {
	var i = 0;
	var el = null;
	var add_span = 0;
	while ($("attachments_new_note").childNodes.length > i) {
		var has_attrib = (typeof($("attachments_new_note").childNodes[i].getAttribute) != 'undefined');
		if (!has_attrib || $("attachments_new_note").childNodes[i].getAttribute('colspan') == null) {
			if (has_attrib) {
				add_span++;
			}
			$("attachments_new_note").removeChild($("attachments_new_note").childNodes[i]);
		} else {
			if ($("attachments_new_note").childNodes[i].getAttribute('notearea')) el = $("attachments_new_note").childNodes[i];
			i++;
		}
	}
	if (add_span) el.setAttribute('colspan', parseInt(el.getAttribute('colspan')) + add_span - 1);
}

utils_attachment_add_note = function () {
	utils_attachments_cancel_note_edit();
	$("attachments_new_note").style.display="";
	$('note_id').value = null;

	scrollBy(0, -2000);
	scrollBy(0, getTotalTopOffet($("attachments_new_note"))-160);
}

utils_attachments_cancel_note_edit = function () {
	$("attachments_new_note").style.display="none";
	CKEDITOR.instances.ckeditor_note.setData('');
	$('delete_files').value = '';
	$('clipboard_files').value = '';
	$('filelist').innerHTML = '';
	$("note_sticky").checked = false;
	$("note_permission").value = 0;
	if (utils_attachment_last_edited_note) {
		utils_attachment_last_edited_note.style.display="";
		utils_attachment_last_edited_note = null;
	}
}

var utils_attachment_last_edited_note = null;
utils_attachment_edit_note = function(id) {
	utils_attachment_add_note();
	$('note_id').value = id;
	
	new Ajax.Request("modules/Utils/Attachment/get_note.php", {
		method: "post",
		parameters:{
			cid: Epesi.client_id,
			id: id
		},
		onSuccess:function(t) {
			result = t.responseText.evalJSON();
			CKEDITOR.instances.ckeditor_note.setData(result.note);
			$("note_sticky").checked = result.sticky=='1'?true:false;
			$("note_permission").value = result.permission;
			for (var id in result.files) {
				Utils_Attachment__add_file_to_list(result.files[id], null, id);
			}
		}
	});

	utils_attachment_last_edited_note = $('attachments_note_'+id);
	utils_attachment_last_edited_note.style.display="none";

	scrollBy(0, -2000);
	scrollBy(0, getTotalTopOffet($("attachments_new_note"))-160);
}
