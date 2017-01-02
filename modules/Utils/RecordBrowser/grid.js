var switched_elem = 0;
var switched_id = 0;

mouse_over_grid = function(element, rid) {
	e=jq('#grid_edit_'+element+'_'+rid);
	if (switched_elem!=element || switched_id!=rid) 
		if(e.length>0)e.css('display','inline');
}

mouse_out_grid = function(element, rid) {
	e=jq('#grid_edit_'+element+'_'+rid);
	if(e.length>0)e.hide;
}

grid_enable_field_edit = function(element_name, recid, tab, form_name) {
	mouse_out_grid(element_name, recid);
	if (switched_elem && switched_id) {
		e=jq('#grid_save_'+switched_elem+'_'+switched_id);
		if (e.length>0) {
			e.hide();
			elemf = jq('#grid_form_field_'+switched_elem+'_'+switched_id);
			elemv = jq('#grid_value_field_'+switched_elem+'_'+switched_id);
			if (elemf.length>0) {
				elemf.hide();
				elemf.html('Loading...');
			}
			if (elemv.length>0) elemv.css('display','inline');
		}
	}
	elemf = jq('#grid_form_field_'+element_name+'_'+recid);
	elemv = jq('#grid_value_field_'+element_name+'_'+recid);
	switched_elem = element_name;
	switched_id = recid;
	elemf.css('display', 'inline');
	elemv.hide();
	jq.ajax('modules/Utils/RecordBrowser/grid.php', {
		method: 'post',
		data:{
			element:JSON.stringify(element_name),
			id:JSON.stringify(recid),
			tab:JSON.stringify(tab),
			form_name:JSON.stringify(form_name),
			mode:JSON.stringify('edit'),
			cid: Epesi.client_id
		},
		success:function(t) {
			if (element_name==switched_elem && recid==switched_id)
				eval(t);
			e=jq('#grid_save_'+element_name+'_'+recid);
			if(e.length>0) e.hide();
		}
	});
}

grid_submit_field = function(element_name, recid, tab) {
	elemf = jq('#grid_form_field_'+element_name+'_'+recid);
	elemv = jq('#grid_value_field_'+element_name+'_'+recid);

	switched_elem = '';
	switched_id = '';
	jq.ajax('modules/Utils/RecordBrowser/grid.php', {
		method: 'post',
		data:{
			element:JSON.stringify(element_name),
			value:JSON.stringify(jq('#'+grid_edit_form_name).serialize()),
			form_name:JSON.stringify(grid_edit_form_name),
			id:JSON.stringify(recid),
			tab:JSON.stringify(tab),
			mode:JSON.stringify('submit'),
			cid: Epesi.client_id
		},
		success:function(t) {
			eval(t);
		}
	});
	elemf.hide();
	elemv.css('display','inline');
	elemf.html('Loading...');
	elemv.html('Processing...');
	e=jq('#grid_save_'+element_name+'_'+recid);
	if(e.length) e.css("display",'none');
}

grid_disable_edit = function(element_name, recid) {
	if (switched_elem==element_name && switched_id==recid) {
		elemf = jq('#grid_form_field_'+element_name+'_'+recid);
		elemv = jq('#grid_value_field_'+element_name+'_'+recid);

		switched_elem = '';
		switched_id = '';
		elemf.hide();
		elemv.css("display", 'inline');
		elemf.html('Loading...');
		e=jq('#grid_save_'+element_name+'_'+recid);
		if(e.length) e.css("display",'none');
	}
}
