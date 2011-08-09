var expandable_notes = new Array();

utils_attachment_expand = function(id) {
	if($("note_"+id)) {
		$("note_"+id).style.height = "";
		$("utils_attachment_more_"+id).hide();
		$("utils_attachment_less_"+id).show();
	}
};

utils_attachment_expand_all = function() {
	for(var n in expandable_notes) utils_attachment_expand(n);
};

utils_attachment_collapse = function(id) {
	if($("note_"+id)) {
		$("note_"+id).style.height = "18px";
		$("utils_attachment_more_"+id).show();
		$("utils_attachment_less_"+id).hide();
	}
};

utils_attachment_collapse_all = function() {
	for(var n in expandable_notes) utils_attachment_collapse(n);
};

init_note_expandable = function(id) {
	var el =$("note_"+id);
	if (el.clientHeight<el.scrollHeight) {
		$("utils_attachment_less_"+id).hide();
		$("note_buttons_"+id).style.display="";
		expandable_notes[id] = id;
	} else {
		$("note_buttons_"+id).style.display="none";
	}
};
