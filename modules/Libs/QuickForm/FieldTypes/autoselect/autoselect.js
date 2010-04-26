autoselect_on_hide = function (element,mode) {
	var new_value=$("__autocomplete_id_"+element+"__search").value.split("__");
	if (new_value && typeof(new_value[1])!="undefined") {
		$("__autocomplete_id_"+element+"__search").value="";
		autoselect_add_value(element, new_value[0], new_value[1]);
	} else new_value=false;
	if (mode==1 || new_value) {
		$('__'+element+'_select_span').style.display="";
		focus_by_id(element);
		$('__'+element+'_autocomplete_span').style.display="none";
		var evt = document.createEvent('HTMLEvents');
		evt.initEvent('change', true, true);
		$(element).dispatchEvent(evt);
		$("__autocomplete_id_"+element+"__search").value="";
	}
}

autoselect_add_value = function (element, value, label) {
	list = document.getElementsByName(element)[0];
	i = 0;
	while (i!=list.options.length) {
		if (list.options[i].value==value) {
			list.value = value;
			value=null;
			break;
		}
		i++;
	}
	if (value!=null) {
		list.options[i] = new Option();
		list.options[i].value = value;
		list.options[i].text = label;
		list.value = value;
	}
}

autoselect_start_searching = function (element, keyCode) {
	if (keyCode<48 || keyCode>105) return;
	$('__'+element+'_autocomplete_span').style.display="";
	$('__autocomplete_id_'+element+'__search').focus();
	$('__'+element+'_select_span').style.display="none";
}