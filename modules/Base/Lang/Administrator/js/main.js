var missing_translations = new Object();
translate_init = function() {
	missing_translations = new Object();
}

translate_add_id = function(id, org) {
	if (document.getElementById(id)) missing_translations[id] = org;
}

translate_first_on_the_list = function() {
	for (var id in missing_translations) {
		if (document.getElementById(id).innerHTML) continue;
		lang_translate(missing_translations[id], id);
		return;
	}
	document.querySelectorAll(".nav_button")[2].querySelector("a").onclick(); // A bit lazy way
}

lang_translate = function (original, span_id) {
	var ret = prompt("Translate: "+original, document.getElementById(span_id).innerHTML);
	if (ret === null) return;
	document.getElementById(span_id).innerHTML = ret;
	document.getElementById(span_id).style.color = "red";
	jQuery.ajax('modules/Base/Lang/Administrator/update_translation.php', {
		method: 'post',
		data:{
			original: original,
			new: ret,
			cid: Epesi.client_id
		},
		success:function() {
			if(document.getElementById(span_id))document.getElementById(span_id).style.color = "black";
		}
	});
}
