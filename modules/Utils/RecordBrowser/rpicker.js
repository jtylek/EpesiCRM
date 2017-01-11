rpicker_init = function(element, id){
	id = id.replace('/', '\\/');
	var checkbox = jq('#leightbox_rpicker_'+element+'_'+id);
	var list = document.getElementsByName(element+'to[]')[0];
	if (!list)
		list = jq('#'+element).get(0);
	var k = 0;
	checkbox.prop("checked", false);
	if(list) while (k!=list.length) {
		if (list.options[k].value == id) {
			checkbox.prop("checked", true);
			break;
		}
		k++;
	}
	checkbox.click(function(e){
		rpicker_move(element,id,checkbox.getAttribute('formated_name'));
	});
}

rpicker_move = function(element, id, cstring, where){
	var checkbox = jq('#leightbox_rpicker_'+element+'_'+id);
	if (typeof(where)=="undefined")
		where = checkbox.is(":checked");
	else if (checkbox.length)
		checkbox.prop("checked", where);
	var tolist = document.getElementsByName(element+'to[]')[0];
	var fromlist = document.getElementsByName(element+'from[]')[0];
	if (!tolist) {
		var list = jq('#'+element).get(0);
		if (where) {
			automulti_add_value(element, '__SEP__', id, cstring);
		} else {
			var k = 0;
			while (k!=list.length) {
				if (list.options[k].value == id) {
					x = 0;
					while (x!=list.length) list.options[x].selected = (k==x++);
					automulti_remove_button_action(element, '__SEP__');
					return;
				}
				k++;
			}
		}
	} else {
		if (where) {
			k = 0;
			i = false;
			while (k!=fromlist.length) {
				fromlist.options[k].selected = false;
				if (fromlist.options[k].value == id) {
					fromlist.options[k].selected = true;
					i = true;
					break;
				}
				k++;
			}
			if (!i) {
				k = 0;
				while (k!=tolist.length) {
					if (tolist.options[k].value == id) {
						return;
					}
					k++;
				}
				fromlist.options[k] = new Option();
				fromlist.options[k].selected = true;
				fromlist.options[k].text = cstring;
				fromlist.options[k].value = id;
			}
			eval('add_selected_'+element+'()');
		} else {
			k = 0;
			while (k!=tolist.length) {
				if (tolist.options[k].value == id) {
					x = 0;
					while (x!=tolist.length) tolist.options[x].selected = (k==x++);
					eval('remove_selected_'+element+'()');
					return;
				}
				k++;
			}
		}
	}
}

rpicker_chained = function(element) {
	jq('[rel="rpicker_leightbox_'+element+'"]').click(function(){	
		jq('#'+element+'__chained_vals')
			.val(jq('#'+element).closest('form').serialize())
			.closest('form').submit();
	});
}

