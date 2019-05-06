var switched_elem = 0;
var switched_id = 0;

mouse_over_grid = function(element, rid) {
	e=$('grid_edit_'+element+'_'+rid);
	if (switched_elem!=element || switched_id!=rid) 
		if(e)e.style.display='inline';
}

mouse_out_grid = function(element, rid) {
	e=$('grid_edit_'+element+'_'+rid);
	if(e)e.style.display='none';
}

grid_enable_field_edit = function(element_name, recid, tab, form_name) {
	mouse_out_grid(element_name, recid);
	if (switched_elem && switched_id) {
		e=$('grid_save_'+switched_elem+'_'+switched_id);
		if (e) {
			e.style.display='none';
			elemf = $('grid_form_field_'+switched_elem+'_'+switched_id);
			elemv = $('grid_value_field_'+switched_elem+'_'+switched_id);
			if (elemf) {
				elemf.style.display = 'none';
				elemf.innerHTML = 'Loading...';
			}
			if (elemv) elemv.style.display = 'inline';
		}
	}
	elemf = $('grid_form_field_'+element_name+'_'+recid);
	elemv = $('grid_value_field_'+element_name+'_'+recid);
	switched_elem = element_name;
	switched_id = recid;
	elemf.style.display = 'inline';
	elemv.style.display = 'none';
	new Ajax.Request('modules/Utils/RecordBrowser/grid.php', {
		method: 'post',
		parameters:{
			element:Object.toJSON(element_name),
			id:Object.toJSON(recid),
			tab:Object.toJSON(tab),
			form_name:Object.toJSON(form_name),
			mode:Object.toJSON('edit'),
			cid: Epesi.client_id
		},
		onSuccess:function(t) {
			if (element_name==switched_elem && recid==switched_id)
				eval(t.responseText);
			e=$('grid_save_'+element_name+'_'+recid);
			if(e)e.style.display='inline';
		}
	});
}

grid_submit_field = function(element_name, recid, tab) {
	elemf = $('grid_form_field_'+element_name+'_'+recid);
	elemv = $('grid_value_field_'+element_name+'_'+recid);

	switched_elem = '';
	switched_id = '';
	new Ajax.Request('modules/Utils/RecordBrowser/grid.php', {
		method: 'post',
		parameters:{
			element:Object.toJSON(element_name),
			value:Object.toJSON($(grid_edit_form_name).serialize()),
			form_name:Object.toJSON(grid_edit_form_name),
			id:Object.toJSON(recid),
			tab:Object.toJSON(tab),
			mode:Object.toJSON('submit'),
			cid: Epesi.client_id
		},
		onSuccess:function(t) {
			eval(t.responseText);
		}
	});
	elemf.style.display = 'none';
	elemv.style.display = 'inline';
	elemf.innerHTML = 'Loading...';
	elemv.innerHTML = 'Processing...';
	e=$('grid_save_'+element_name+'_'+recid);
	if(e)e.style.display='none';
}

grid_disable_edit = function(element_name, recid) {
	if (switched_elem==element_name && switched_id==recid) {
		elemf = $('grid_form_field_'+element_name+'_'+recid);
		elemv = $('grid_value_field_'+element_name+'_'+recid);

		switched_elem = '';
		switched_id = '';
		elemf.style.display = 'none';
		elemv.style.display = 'inline';
		elemf.innerHTML = 'Loading...';
		e=$('grid_save_'+element_name+'_'+recid);
		if(e)e.style.display='none';
	}
}
