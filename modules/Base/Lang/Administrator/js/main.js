var missing_translations = new Object();
translate_init = function() {
	missing_translations = new Object();
}

translate_add_id = function(id, org) {
	if ($(id)) missing_translations[id] = org;
}

translate_first_on_the_list = function() {
	for (var id in missing_translations) {
		if ($(id).innerHTML) continue;
		lang_translate(missing_translations[id], id);
		return;
	}
	document.querySelectorAll(".nav_button")[2].down("a").onclick(); // A bit lazy way
}

lang_translate = function (original, span_id) {
	var ret = prompt("Translate: "+original, $(span_id).innerHTML);
	if (ret === null) return;
	$(span_id).innerHTML = ret;
	$(span_id).style.color = "red";
	new Ajax.Request('modules/Base/Lang/Administrator/update_translation.php', {
		method: 'post',
		parameters:{
			original: original,
			new: ret,
			cid: Epesi.client_id
		},
		onSuccess:function(t) {
			if($(span_id))$(span_id).style.color = "black";
		}
	});
}
