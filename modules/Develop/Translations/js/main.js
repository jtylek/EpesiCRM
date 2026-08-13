develop_move_to_lang = function (tid, to_lang, el) {
	new Ajax.Request('modules/Develop/Translations/move_to_lang.php', {
		method: 'post',
		parameters:{
		    tid: tid,
		    to_lang: to_lang,
			cid: Epesi.client_id
		},
		onSuccess:function(t) {
			el.parentNode.innerHTML = '';
		}
	});
}

develop_use_trans = function (tid, status, el) {
	new Ajax.Request('modules/Develop/Translations/use_translation.php', {
		method: 'post',
		parameters:{
		    id: tid,
		    status: status,
			cid: Epesi.client_id
		},
		onSuccess:function(t) {
		    if (status)
			    el.parentNode.innerHTML = '<b>'+el.parentNode.innerHTML+'</b>';
			else
			    el.parentNode.innerHTML = '<del>'+el.parentNode.innerHTML+'</del>';
		}
	});
}

/* OLD CODE */

translation_tools_rolldown = function() {
	$("translation_rolldown_button").style.display="none";
	$("translation_rollup_button").style.display="";
	$("translations_tools").style.height="32px";
	$("translations_tools").style.width="36px";
	$("translations_tools_table_and_utils").style.display="none";
}

translation_tools_rollup = function() {
	$("translation_rolldown_button").style.display="";
	$("translation_rollup_button").style.display="none";
	$("translations_tools").style.height="200px";
	$("translations_tools").style.width="100%";
	$("translations_tools_table_and_utils").style.display="";
}

translate_first_on_the_list = function() {
	var el = $('add_strings_to_translate');
	var cnodes = el.parentNode.childNodes;
	for (j=0; j<cnodes.length; j++) {
		if (cnodes[j].id && cnodes[j].id!='add_strings_to_translate') {
			var c_el = $(cnodes[j].id.replace('translate_row_','translated_'));
			if (c_el && c_el.innerHTML=='') {
				eval(cnodes[j].childNodes[1].childNodes[0].getAttribute("onclick"));
				return;
			}
		}
	}
}

var current_translations_filter;
var current_translations_search;

lang_translate_tool = function (original, span_id) {
	var ret = prompt("Translate: "+original, $(span_id).innerHTML);
	if (ret === null) return;
	if 	((current_translations_filter==1 && ret=='') ||
		 (current_translations_filter==2 && ret!='')) {
		$(span_id).parentNode.parentNode.style.display="none";
	}
	$(span_id).innerHTML = ret;
	$(span_id).style.color = "red";
	new Ajax.Request('modules/Develop/Translations/update_translation.php', {
		method: 'post',
		parameters:{
			original: Object.toJSON(original),
			new_string: Object.toJSON(ret),
			cid: Epesi.client_id
		},
		onSuccess:function(t) {
			$(span_id).style.color = "black";
		}
	});
}

translation_tools_filter_options = function() {
	current_translations_filter = $("translations_filter").value;
	var el = $('add_strings_to_translate');
	var cnodes = el.parentNode.childNodes;
	var patt = new RegExp(current_translations_search,'gi');
	var tags = new RegExp('<\/?[a-z][^>]*>','gi');
	for (j=0; j<cnodes.length; j++) {
		if (cnodes[j].id && cnodes[j].id!='add_strings_to_translate') {
			var c_el = $(cnodes[j].id.replace('translate_row_','translated_'));
			if (c_el) {
				skip_this = false;
				if (current_translations_search) {
					var fnodes = cnodes[j].childNodes;
					var skip_this = true;
					for (jj=0; jj<3; jj++) {
						var cur_node = fnodes[jj];
						if (cur_node.innerHTML && cur_node.innerHTML.replace(tags,'').match(patt)) {
							skip_this = false;
						}
					}
				}
				if 	(skip_this ||
					 (current_translations_filter==1 && c_el.innerHTML=='') ||
					 (current_translations_filter==2 && c_el.innerHTML!='')) {
					cnodes[j].style.display="none";
				} else {
					cnodes[j].style.display="";
				}
			}
		}
	}
}

translation_tools_filter_by_string = function(string) {
	current_translations_search = string;
	translation_tools_filter_options();
}

check_strings_to_translate = function() {
	setTimeout('prepare_to_translate()', 5000);
}

prepare_to_translate = function() {
	new Ajax.Request('modules/Develop/Translations/check_list.php', {
		method: 'post',
		parameters:{
			cid: Epesi.client_id
		},
		onSuccess:function(t) {
			if (!t.responseText) return;
			strings = t.responseText.evalJSON();
			var el = $('add_strings_to_translate');
			if (!el) return;
			for (i=0;i<strings.length;i++) {
				var cnodes = el.parentNode.childNodes;
				var id_to_use = 'translate_row_'+strings[i]['id'];
				var skip = false;
				for (j=0; j<cnodes.length; j++)
					if (cnodes[j].id==id_to_use) skip = true;
				if (skip) continue;
				var new_el = document.createElement("tr");
				el.parentNode.insertBefore(new_el, el);
				new_el.id = id_to_use;
				new_el.innerHTML = strings[i]['html'];
				if ($('translated_'+strings[i]['id'])) {
					if 	((current_translations_filter==1 && $('translated_'+strings[i]['id']).innerHTML=='') ||
						 (current_translations_filter==2 && $('translated_'+strings[i]['id']).innerHTML!='')) {
						new_el.style.display="none";
					}
				}
			}
		}
	});
	check_strings_to_translate();
}

change_language = function(message, lang) {
	if (confirm(message)) {
		return true;
	} else {
		$("trans_tools_lang_code").value = lang;
		return false;
	}
}
