ms_remove_selected = function(myName, list_sep){
	var tolist = document.getElementsByName(myName+"to[]")[0];
	var fromlist = document.getElementsByName(myName+"from[]")[0];
	var list_result = "";
	var k = 0;
	var i = 0;
	while (k!=tolist.options.length) {
		if (tolist.options[k].selected) {
			while (i!=fromlist.options.length && fromlist.options[i].value<tolist.options[k].value) 
				i++;
			jj = fromlist.length;
			fromlist.options[jj] = new Option();
			for( j = jj; j > i; j-- ) {
				fromlist.options[j].text = fromlist.options[j-1].text;
				fromlist.options[j].value = fromlist.options[j-1].value;
			}
			fromlist.options[i].value = tolist.options[k].value;
			fromlist.options[i].text = tolist.options[k].text;
		} else {
			list_result += list_sep+tolist.options[k].value;
		}
		k++;
	}
	for(i = (tolist.length-1); i >= 0; i--) {
		if(tolist.options[i].selected == true) {
			tolist.options[i] = null;
		}
	}
	document.getElementsByName(myName)[0].value=list_result;
};

ms_add_selected = function(myName, list_sep){ 
	var tolist = document.getElementsByName(myName+"to[]")[0];
	var fromlist = document.getElementsByName(myName+"from[]")[0];
	var list_result = "";
	var k = 0;
	var i = 0;
	while (k!=fromlist.length) {
		if (fromlist.options[k].selected) {
			while(i < tolist.length && tolist.options[i].value<fromlist.options[k].value) 
				i++;
			jj = tolist.length;
			tolist.options[jj] = new Option();
			for( j = jj; j > i; j-- ) {
				tolist.options[j].value = tolist.options[j-1].value;
				tolist.options[j].text = tolist.options[j-1].text;
			}
			tolist.options[i].value = fromlist.options[k].value;
			tolist.options[i].text = fromlist.options[k].text;
		}
		k++;
	}
	for(i = (fromlist.length-1); i >= 0; i--) {
		if(fromlist.options[i].selected == true) {
			fromlist.options[i] = null;
		}
	}
	k = 0;
	while (k!=tolist.length) { 
		list_result += list_sep+tolist.options[k].value; 
		k++; 
	}
	document.getElementsByName(myName)[0].value=list_result; 
};

ms_remove_all = function(myName, list_sep){ 
	var tolist = document.getElementsByName(myName+"to[]")[0];
	var fromlist = document.getElementsByName(myName+"from[]")[0];
	var list_result = "";
	var k = 0;
	var i = 0;
	while (k!=tolist.options.length) {
		if (tolist.options[k].disabled) {
			k++;
			continue;
		}
		while (i!=fromlist.options.length && fromlist.options[i].value<tolist.options[k].value) 
			i++;
		jj = fromlist.length;
		fromlist.options[jj] = new Option();
		for( j = jj; j > i; j-- ) {
			fromlist.options[j].text = fromlist.options[j-1].text;
			fromlist.options[j].value = fromlist.options[j-1].value;
		}
		fromlist.options[i].value = tolist.options[k].value;
		fromlist.options[i].text = tolist.options[k].text;
		k++;
	}
	for(i = (tolist.length-1); i >= 0; i--) {
		if (!tolist.options[i].disabled) tolist.options[i] = null;
	}
	document.getElementsByName(myName)[0].value=list_result;
};

ms_add_all = function(myName, list_sep){ 
	var tolist = document.getElementsByName(myName+"to[]")[0];
	var fromlist = document.getElementsByName(myName+"from[]")[0];
	var k = 0;
	var i = 0;
	var list_result = "";
	while (k!=fromlist.length) {
		if (fromlist.options[k].disabled) {
			k++;
			continue;
		}
		while(i < tolist.length && tolist.options[i].value<fromlist.options[k].value) 
			i++;
		jj = tolist.length;
		tolist.options[jj] = new Option();
		for( j = jj; j > i; j-- ) {
			tolist.options[j].value = tolist.options[j-1].value;
			tolist.options[j].text = tolist.options[j-1].text;
		}
		tolist.options[i].value = fromlist.options[k].value;
		tolist.options[i].text = fromlist.options[k].text;
		k++;
	}
	for(i = (fromlist.length-1); i >= 0; i--) {
		if (!fromlist.options[i].disabled) fromlist.options[i] = null;
	}
	k = 0;
	while (k!=tolist.length) {
		list_result += list_sep+tolist.options[k].value;
		k++;
	}
	document.getElementsByName(myName)[0].value=list_result;
};

/* Mobile checklist fallback - see Libs/QuickForm/theme_adminltedark/
   default.css's own comment on .epesi-ms-checklist for why this exists
   (iOS/Android don't render <select multiple> as an inline listbox under
   touch, no matter what). Builds one checkbox per option (checked = in the
   "to" list) as a sibling of #multiselect's own <table>, hidden by default
   and only shown by that theme's own mobile-breakpoint CSS - so this runs
   unconditionally (cheap - a handful of DOM nodes - and harmless everywhere
   else, including the untouched default theme, where the checklist just
   stays hidden forever with no matching CSS to ever show it).

   Reuses the existing add_selected/remove_selected buttons' own onclick
   handlers (a synthetic click, after marking the one option to move as
   .selected on the appropriate source <select>) for the actual move and
   hidden-input-value rebuild, rather than reimplementing that logic here -
   guarantees the checklist can never drift out of sync with what the
   desktop UI (or a resize back to it) would show. */
function epesi_ms_build_checklist(table) {
	if (table.getAttribute('data-epesi-ms-checklist-built')) return;
	var fromSelect = table.querySelector('.form-element select');
	var toSelect = table.querySelector('.to-element select');
	if (!fromSelect || !toSelect || !fromSelect.id) return;
	var myName = fromSelect.id.replace(/__from$/, '');
	var addBtn = document.getElementById(myName + '__add_selected');
	var removeBtn = document.getElementById(myName + '__remove_selected');
	if (!addBtn || !removeBtn) return;
	table.setAttribute('data-epesi-ms-checklist-built', '1');

	var list = document.createElement('div');
	list.className = 'epesi-ms-checklist';
	list.style.display = 'none';

	function addRow(option, checked) {
		var label = document.createElement('label');
		label.className = 'epesi-ms-check';
		var cb = document.createElement('input');
		cb.type = 'checkbox';
		cb.checked = checked;
		cb.disabled = option.disabled;
		cb.value = option.value;
		cb.addEventListener('change', function() {
			var sourceSelect = cb.checked ? fromSelect : toSelect;
			var targetBtn = cb.checked ? addBtn : removeBtn;
			var i, opt = null;
			for (i = 0; i < sourceSelect.options.length; i++) {
				sourceSelect.options[i].selected = false;
				if (sourceSelect.options[i].value === cb.value) opt = sourceSelect.options[i];
			}
			if (opt) {
				opt.selected = true;
				targetBtn.click();
			}
		});
		var span = document.createElement('span');
		span.textContent = option.text;
		label.appendChild(cb);
		label.appendChild(span);
		list.appendChild(label);
	}

	Array.prototype.slice.call(toSelect.options).forEach(function(o) { addRow(o, true); });
	Array.prototype.slice.call(fromSelect.options).forEach(function(o) { addRow(o, false); });

	table.parentNode.insertBefore(list, table.nextSibling);
}

function epesi_ms_init_checklists() {
	var tables = document.querySelectorAll('#multiselect');
	for (var i = 0; i < tables.length; i++) {
		epesi_ms_build_checklist(tables[i]);
	}
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', epesi_ms_init_checklists);
} else {
	epesi_ms_init_checklists();
}
jQuery(document).on('e:load', function() {
	try { epesi_ms_init_checklists(); } catch (e) {}
});
