var switched_elem = 0;
var switched_id = 0;

mouse_over_grid = function(element, rid) {
	e=document.getElementById('grid_edit_'+element+'_'+rid);
	if (switched_elem!=element || switched_id!=rid)
		if(e)e.style.display='inline';
}

mouse_out_grid = function(element, rid) {
	e=document.getElementById('grid_edit_'+element+'_'+rid);
	if(e)e.style.display='none';
}

grid_enable_field_edit = function(element_name, recid, tab, form_name) {
	mouse_out_grid(element_name, recid);
	if (switched_elem && switched_id) {
		e=document.getElementById('grid_save_'+switched_elem+'_'+switched_id);
		if (e) {
			e.style.display='none';
			elemf = document.getElementById('grid_form_field_'+switched_elem+'_'+switched_id);
			elemv = document.getElementById('grid_value_field_'+switched_elem+'_'+switched_id);
			if (elemf) {
				elemf.style.display = 'none';
				elemf.innerHTML = 'Loading...';
			}
			if (elemv) elemv.style.display = 'inline';
		}
	}
	elemf = document.getElementById('grid_form_field_'+element_name+'_'+recid);
	elemv = document.getElementById('grid_value_field_'+element_name+'_'+recid);
	switched_elem = element_name;
	switched_id = recid;
	elemf.style.display = 'inline';
	elemv.style.display = 'none';
	jQuery.ajax('modules/Utils/RecordBrowser/grid.php', {
		method: 'post',
		data:{
			element:JSON.stringify(element_name),
			id:JSON.stringify(recid),
			tab:JSON.stringify(tab),
			form_name:JSON.stringify(form_name),
			mode:JSON.stringify('edit'),
			cid: Epesi.client_id
		},
		dataType: 'text',
		success:function(responseText) {
			if (element_name==switched_elem && recid==switched_id)
				eval(responseText);
			e=document.getElementById('grid_save_'+element_name+'_'+recid);
			if(e)e.style.display='inline';
		}
	});
}

grid_submit_field = function(element_name, recid, tab) {
	elemf = document.getElementById('grid_form_field_'+element_name+'_'+recid);
	elemv = document.getElementById('grid_value_field_'+element_name+'_'+recid);

	switched_elem = '';
	switched_id = '';
	jQuery.ajax('modules/Utils/RecordBrowser/grid.php', {
		method: 'post',
		data:{
			element:JSON.stringify(element_name),
			value:JSON.stringify(jQuery(document.getElementById(grid_edit_form_name)).serialize()),
			form_name:JSON.stringify(grid_edit_form_name),
			id:JSON.stringify(recid),
			tab:JSON.stringify(tab),
			mode:JSON.stringify('submit'),
			cid: Epesi.client_id
		},
		dataType: 'text',
		success:function(responseText) {
			eval(responseText);
		}
	});
	elemf.style.display = 'none';
	elemv.style.display = 'inline';
	elemf.innerHTML = 'Loading...';
	elemv.innerHTML = 'Processing...';
	e=document.getElementById('grid_save_'+element_name+'_'+recid);
	if(e)e.style.display='none';
}

grid_disable_edit = function(element_name, recid) {
	if (switched_elem==element_name && switched_id==recid) {
		elemf = document.getElementById('grid_form_field_'+element_name+'_'+recid);
		elemv = document.getElementById('grid_value_field_'+element_name+'_'+recid);

		switched_elem = '';
		switched_id = '';
		elemf.style.display = 'none';
		elemv.style.display = 'inline';
		elemf.innerHTML = 'Loading...';
		e=document.getElementById('grid_save_'+element_name+'_'+recid);
		if(e)e.style.display='none';
	}
}
