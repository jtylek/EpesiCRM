rpicker_init = function(element, id){
	var checkbox = $('leightbox_rpicker_'+element+'_'+id);
	var list = document.getElementsByName(element+'to[]')[0];
	if (!list)
		list = $(element);
	var k = 0;
	checkbox.checked = false;
	while (k!=list.length) {
		if (list.options[k].value == id) {
			checkbox.checked = true;
			break;
		}
		k++;
	}
	checkbox.observe('click', function(e){
		rpicker_move(element,id,checkbox.getAttribute('formated_name'));
	});
}

rpicker_move = function(element, id, cstring, where){
	var checkbox = $('leightbox_rpicker_'+element+'_'+id);
	if (typeof(where)=="undefined")
		where = checkbox.checked;
	else if (checkbox)
		checkbox.checked = where;
	var tolist = document.getElementsByName(element+'to[]')[0];
	var fromlist = document.getElementsByName(element+'from[]')[0];
	if (!tolist) {
		var list = $(element);
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
