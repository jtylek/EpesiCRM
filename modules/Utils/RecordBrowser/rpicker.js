rpicker_init = function(element, id, icon_on, icon_off){
	img = document.getElementsByName('leightbox_rpicker_'+element+'_'+id)[0];
	tolist = document.getElementsByName(element+'to[]')[0];
	k = 0;
	img.src = icon_off;
	while (k!=tolist.length) {
		if (tolist.options[k].value == id) {
			img.src = icon_on;
			break;
 		}
		k++;
	}
}

rpicker_addto = function(element, id, icon_on, icon_off, cstring){
	tolist = document.getElementsByName(element+'to[]')[0];
	fromlist = document.getElementsByName(element+'from[]')[0];
	img = document.getElementsByName('leightbox_rpicker_'+element+'_'+id)[0];
	list = '';
	k = 0;
	while (k!=tolist.length) {
		if (tolist.options[k].value == id) {
			x = 0;
			while (x!=tolist.length) tolist.options[x].selected = (k==x++);
			eval('remove_selected_'+element+'()');
			img.src = icon_off;
			return;
	 	}
		k++;
	}
	k = 0;
	i = false;
	while (k!=fromlist.length) {
		fromlist.options[k].selected = false;
		if (fromlist.options[k].value == id) {
			fromlist.options[k].selected = true;
			i = true;
	 	}
		k++;
	}
	if (!i) {
		fromlist.options[k] = new Option();
		fromlist.options[k].selected = true;
		fromlist.options[k].text = cstring;
		fromlist.options[k].value = id;
	}
	img.src = icon_on;
	eval('add_selected_'+element+'()');
}
