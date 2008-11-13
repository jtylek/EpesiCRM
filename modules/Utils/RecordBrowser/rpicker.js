rpicker_init = function(element, id){
	checkbox = $('leightbox_rpicker_'+element+'_'+id);
	tolist = document.getElementsByName(element+'to[]')[0];
	k = 0;
	checkbox.selected = false;
	while (k!=tolist.length) {
		if (tolist.options[k].value == id) {
			checkbox.checked = true;
			break;
 		}
		k++;
	}
}

rpicker_move = function(element, id, cstring, where){
	if ($('leightbox_rpicker_'+element+'_'+id)) $('leightbox_rpicker_'+element+'_'+id).checked=where;
	tolist = document.getElementsByName(element+'to[]')[0];
	fromlist = document.getElementsByName(element+'from[]')[0];
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
		tolist = document.getElementsByName(element+'to[]')[0];
		fromlist = document.getElementsByName(element+'from[]')[0];
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
