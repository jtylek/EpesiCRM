automulti_remove_button_update = function (element) {
	list = document.getElementsByName(element+"__display")[0];
	i = 0;
	while (i!=list.options.length) {
		if (list.options[i].selected) {
			$("automulti_button_style_"+element).setAttribute("class","button enabled");
			break;
		}
		i++;
	}
	if (i==list.options.length) $("automulti_button_style_"+element).setAttribute("class","button disabled");
}

automulti_remove_button_action = function (element, list_sep) {
	list = document.getElementsByName(element+"__display")[0];
	val_holder = document.getElementsByName(element)[0];
	i = 0;
	val_holder.value = "";
	while (i!=list.options.length) {
		if (list.options[i].selected) {
			list.options[i] = null;
		} else {
			val_holder.value += list_sep;
			val_holder.value += list.options[i].value;
			i++;
		}
	}
	automulti_remove_button_update(element);
}

automulti_on_hide = function (element, list_sep) {
	var new_value=$("__autocomplete_id_"+element+"__search").value.split("__");
	if (new_value && typeof(new_value[1])!="undefined") {
		$("__autocomplete_id_"+element+"__search").value="";
		automulti_add_value(element, list_sep, new_value[0], new_value[1]);
	}
}

automulti_add_value = function (element, list_sep, value, label) {
	list = document.getElementsByName(element+"__display")[0];
	i = 0;
	while (i!=list.options.length) {
		if (list.options[i].value==value) {
			value=null;
			break;
		}
		i++;
	}
	if (value!=null) {
		list.options[i] = new Option();
		list.options[i].value = value;
		list.options[i].text = label;
		val_holder = document.getElementsByName(element)[0];
		val_holder.value += list_sep;
		val_holder.value += value;
	}
}
