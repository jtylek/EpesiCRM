var switched_elem = 0;
var switched_id = 0;

grid_enable_field_edit = function(element_name, recid, tab) {
	if (switched_elem && switched_id) {
		elemf = $('grid_form_field_'+switched_elem+'_'+switched_id);
		elemv = $('grid_value_field_'+switched_elem+'_'+switched_id);
		elemf.style.display = 'none';
		elemv.style.display = 'inline';
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
			mode:Object.toJSON('edit'),
			cid: Epesi.client_id
		},
		onSuccess:function(t) {
			eval(t.responseText);
		}
	});
}

grid_submit_field = function(element_name, recid, tab) {
	elemf = $('grid_form_field_'+element_name+'_'+recid);
	elemv = $('grid_value_field_'+element_name+'_'+recid);
	elemf.style.display = 'none';
	elemv.style.display = 'inline';
	elemv.innerHTML = 'Processing...';

	switched_elem = '';
	switched_id = '';
	new Ajax.Request('modules/Utils/RecordBrowser/grid.php', {
		method: 'post',
		parameters:{
			element:Object.toJSON(element_name),
			value:Object.toJSON($(element_name).value),
			id:Object.toJSON(recid),
			tab:Object.toJSON(tab),
			mode:Object.toJSON('submit'),
			cid: Epesi.client_id
		},
		onSuccess:function(t) {
			eval(t.responseText);
		}
	});
}
