utils_recordbrowser__clearance = 0;
utils_recordbrowser__crits_ors = {};
utils_recordbrowser__crits_ands = 0;

utils_recordbrowser__clearance_max = 0;
utils_recordbrowser__crits_ors_max = 0;
utils_recordbrowser__crits_ands_max = 0;

utils_recordbrowser__field_values = {"":{}};
utils_recordbrowser__field_sub_values = {};

utils_recordbrowser__update_field_values = function (row, j) {
	var list = jq('#crits_'+row+'_'+j+'_value').get(0);
	if (!list) return;
	list.options.length = 0;
	i = 0;
	selected_field = jq('#crits_'+row+'_'+j+'_field').val();
	for(k in utils_recordbrowser__field_values[selected_field]) {
		list.options[i] = new Option();
		list.options[i].value = k;
		list.options[i].text = utils_recordbrowser__field_values[selected_field][k];
		i++;
	}
	utils_recordbrowser__update_field_sub_values(row, j);
}

utils_recordbrowser__update_field_sub_values = function (row, j) {
	var list = jq('#crits_'+row+'_'+j+'_sub_value').get(0);
	if (!list) return;
	selected_field = jq('#crits_'+row+'_'+j+'_field').val();
	selected_value = jq('#crits_'+row+'_'+j+'_value').val();
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
		jq('#crits_'+row+'_'+j+'_sub_value').show();
	} else {
		jq('#crits_'+row+'_'+j+'_sub_value').hide();
	}
}

var utils_recordbrowser__crits_initialized;

utils_recordbrowser__init_clearance = function (current, max) {
	if (!utils_recordbrowser__crits_initialized) 
		utils_recordbrowser__clearance = current;
	utils_recordbrowser__clearance_max = max;
	if (utils_recordbrowser__clearance+1==utils_recordbrowser__clearance_max)
		jq('#add_clearance').hide();
	for (i=0; i<max; i++)
		jq('#div_clearance_'+i).css('display', (i<=utils_recordbrowser__clearance)?'':'none');
}
utils_recordbrowser__add_clearance = function () {
	utils_recordbrowser__clearance++;
	if (utils_recordbrowser__clearance+1==utils_recordbrowser__clearance_max)
		jq('#add_clearance').hide();
	jq('#div_clearance_'+utils_recordbrowser__clearance).show();
}

utils_recordbrowser__init_crits_and = function (current, max) {
	if (!utils_recordbrowser__crits_initialized) 
		utils_recordbrowser__crits_ands = current;
	if (utils_recordbrowser__crits_ands+1==utils_recordbrowser__crits_ands_max)
		jq('#add_and').hide();
	utils_recordbrowser__crits_ands_max = max;
	for (i=0; i<max; i++)
		jq('#div_crits_row_'+i).css('display', (i<=utils_recordbrowser__crits_ands)?'':'none');
}
utils_recordbrowser__add_and = function () {
	utils_recordbrowser__crits_ands++;
	if (utils_recordbrowser__crits_ands+1==utils_recordbrowser__crits_ands_max)
		jq('#add_and').hide();
	jq('#div_crits_row_'+utils_recordbrowser__crits_ands).show();
}

utils_recordbrowser__init_crits_or = function (row, current, max) {
	if (!utils_recordbrowser__crits_initialized) 
		utils_recordbrowser__crits_ors[row] = current;
	if (utils_recordbrowser__crits_ors[row]+1==utils_recordbrowser__crits_ors_max)
		jq('#add_or_'+row).hide();
	utils_recordbrowser__crits_ors_max = max;
	for (i=0; i<max; i++)
		jq('#div_crits_or_'+row+'_'+i).css('display',(i<=utils_recordbrowser__crits_ors[row])?'':'none');
}
utils_recordbrowser__add_or = function (row) {
	utils_recordbrowser__crits_ors[row]++;
	if (utils_recordbrowser__crits_ors[row]+1==utils_recordbrowser__crits_ors_max)
		jq('#add_or_'+row).hide();
	jq('#div_crits_or_'+row+'_'+utils_recordbrowser__crits_ors[row]).show();
}

utils_recordbrowser__set_field_access_titles = function (labels_map) {
	if (jq(".permissions_option_title").length) return;

	jq.each(labels_map, function(id, label) {
		jq("#"+id).prepend('<option disabled class="permissions_option_title">' + label + '</option>').height('300px');
	});
}

