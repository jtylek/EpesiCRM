autoselect_on_hide = function (element,mode) {
	var new_value=document.getElementById("__autocomplete_id_"+element+"__search").value.split('__');
	if (new_value && typeof(new_value[1])!="undefined") {
		document.getElementById("__autocomplete_id_"+element+"__search").value="";
		autoselect_add_value(element, new_value[0], new_value[1]);
	} else new_value=false;
	if (mode==1 || new_value) {
		document.getElementById('__'+element+'_select_span').style.display="";
		focus_by_id(element);
		document.getElementById('__'+element+'_autocomplete_span').style.display="none";
		var evt = document.createEvent('HTMLEvents');
		evt.initEvent('change', true, true);
		document.getElementById(element).dispatchEvent(evt);
		document.getElementById("__autocomplete_id_"+element+"__search").value="";
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
	// keyCode is only checked when actually given (the keydown listener below
	// passes ev.keyCode, to ignore Tab/Shift/arrow keys etc.) - the touchstart
	// listener calls this with no keyCode at all to always proceed.
	if (keyCode!==undefined && (keyCode<48 || keyCode>105)) return;
	document.getElementById('__'+element+'_autocomplete_span').style.display="";
	document.getElementById('__autocomplete_id_'+element+'__search').focus();
	document.getElementById('__'+element+'_select_span').style.display="none";
}

autoselect_stop_searching = function (element) {
	document.getElementById('__'+element+'_autocomplete_span').style.display="none";
//	$('__autocomplete_id_'+element+'__search').focus();
	document.getElementById('__'+element+'_select_span').style.display="";
}
