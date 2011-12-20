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

init_note_expandable = function(id, skipTimed) {
	var el = $("note_"+id);
	if (el.clientHeight<el.scrollHeight) {
		$("utils_attachment_less_"+id).hide();
		$("utils_attachment_more_"+id).show();
		$("utils_attachment_less_"+id).childNodes[0].src = notes_collapse_icon;
		$("utils_attachment_more_"+id).childNodes[0].src = notes_expand_icon;
		expandable_notes[id] = id;
		expandable_notes_amount++;
		utils_attachment_show_hide_buttons();
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
