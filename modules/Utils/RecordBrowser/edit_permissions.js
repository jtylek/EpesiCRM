utils_recordbrowser__clearance = 0;
utils_recordbrowser__crits_ors = {};
utils_recordbrowser__crits_ands = 0;

utils_recordbrowser__clearance_max = 0;
utils_recordbrowser__crits_ors_max = 0;
utils_recordbrowser__crits_ands_max = 0;

utils_recordbrowser__field_values = {"":{}};
utils_recordbrowser__field_sub_values = {};

utils_recordbrowser__update_field_values = function (row, j) {
	var list = $('crits_'+row+'_'+j+'_value');
	if (!list) return;
	for(i = (list.length-1); i >= 0; i--) {
		list.options[i] = null;
	}
	i = 0;
	selected_field = $('crits_'+row+'_'+j+'_field').value;
	for(k in utils_recordbrowser__field_values[selected_field]) {
		list.options[i] = new Option();
		list.options[i].value = k;
		list.options[i].text = utils_recordbrowser__field_values[selected_field][k];
		i++;
	}
	utils_recordbrowser__update_field_sub_values(row, j);
}

utils_recordbrowser__update_field_sub_values = function (row, j) {
	var list = $('crits_'+row+'_'+j+'_sub_value');
	if (!list) return;
	selected_field = $('crits_'+row+'_'+j+'_field').value;
	selected_value = $('crits_'+row+'_'+j+'_value').value;
	for(i = (list.length-1); i >= 0; i--) {
		list.options[i] = null;
	}
	if (utils_recordbrowser__field_sub_values[selected_field+'__'+selected_value]) {
		i = 0;
		for(k in utils_recordbrowser__field_sub_values[selected_field+'__'+selected_value]) {
			list.options[i] = new Option();
			list.options[i].value = k;
			list.options[i].text = utils_recordbrowser__field_sub_values[selected_field+'__'+selected_value][k];
			i++;
		}
		$('crits_'+row+'_'+j+'_sub_value').style.display='';
	} else {
		$('crits_'+row+'_'+j+'_sub_value').style.display='none';
	}
}

var utils_recordbrowser__crits_initialized;

utils_recordbrowser__init_clearance = function (current, max) {
	if (!utils_recordbrowser__crits_initialized) 
		utils_recordbrowser__clearance = current;
	utils_recordbrowser__clearance_max = max;
	if (utils_recordbrowser__clearance+1==utils_recordbrowser__clearance_max)
		$('add_clearance').style.display = 'none';
	for (i=0; i<max; i++)
		$('div_clearance_'+i).style.display = (i<=utils_recordbrowser__clearance)?'':'none';
}
utils_recordbrowser__add_clearance = function () {
	utils_recordbrowser__clearance++;
	if (utils_recordbrowser__clearance+1==utils_recordbrowser__clearance_max)
		$('add_clearance').style.display = 'none';
	$('div_clearance_'+utils_recordbrowser__clearance).style.display = '';
}

utils_recordbrowser__init_crits_and = function (current, max) {
	if (!utils_recordbrowser__crits_initialized) 
		utils_recordbrowser__crits_ands = current;
	if (utils_recordbrowser__crits_ands+1==utils_recordbrowser__crits_ands_max)
		$('add_and').style.display = 'none';
	utils_recordbrowser__crits_ands_max = max;
	for (i=0; i<max; i++)
		$('div_crits_row_'+i).style.display = (i<=utils_recordbrowser__crits_ands)?'':'none';
}
utils_recordbrowser__add_and = function () {
	utils_recordbrowser__crits_ands++;
	if (utils_recordbrowser__crits_ands+1==utils_recordbrowser__crits_ands_max)
		$('add_and').style.display = 'none';
	$('div_crits_row_'+utils_recordbrowser__crits_ands).style.display = '';
}

utils_recordbrowser__init_crits_or = function (row, current, max) {
	if (!utils_recordbrowser__crits_initialized) 
		utils_recordbrowser__crits_ors[row] = current;
	if (utils_recordbrowser__crits_ors[row]+1==utils_recordbrowser__crits_ors_max)
		$('add_or_'+row).style.display = 'none';
	utils_recordbrowser__crits_ors_max = max;
	for (i=0; i<max; i++)
		$('div_crits_or_'+row+'_'+i).style.display = (i<=utils_recordbrowser__crits_ors[row])?'':'none';
}
utils_recordbrowser__add_or = function (row) {
	utils_recordbrowser__crits_ors[row]++;
	if (utils_recordbrowser__crits_ors[row]+1==utils_recordbrowser__crits_ors_max)
		$('add_or_'+row).style.display = 'none';
	$('div_crits_or_'+row+'_'+utils_recordbrowser__crits_ors[row]).style.display = '';
}

