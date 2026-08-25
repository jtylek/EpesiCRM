getelem = function(form,elem) {
f=document.getElementById(form);
if(f){
e=f.elements[elem];
}
return e;
};

settextvalue = function(form,elem,value) {
e=getelem(form,elem);
if(e){
e.value=value;
}};

setselectvalue = function(form,elem,value) {
e=getelem(form,elem);
if(e){
for(i=0; i<e.length; i++)if(e.options[i].value==value){e.options[i].selected=true;break;};
}};

setcheckvalue = function(form,elem,value) {
e=getelem(form,elem);
if(e){
e.checked=value;
}};

setradiovalue =  function(form,elem,value) {
e=getelem(form,elem);
if(e){
for(i=0; i<e.length; i++){e[i].checked=false;if(e[i].value==value)e[i].checked=true;};
}};

seterror=function(err_id, error){
t=document.getElementById(err_id);
if(t) {
if (error!="") t.innerHTML = error+"<br>";
else t.innerHTML = error;
}else{
if(error!="")
alert("Error field not defined in smarty template, unable to fill '"+err_id+"' with error: '"+error+"'");
}
};

// Required-field errors (.form_error / .error spans, populated by seterror()
// above) render as a solid overlay on top of the field itself in the
// AdminLTE theme (see RecordBrowser's View_entry.css / GenericBrowser's
// default.css) - clearing the span here on the field's own edit is what
// makes that overlay disappear again, since its CSS keys off :not(:empty).
// Delegated on document (not attached per-field at render time) because
// this is old-style AJAX-push content - process.php's response replaces/
// re-adds form fields via generated JS, not a one-time page load.
epesi_clear_field_error = function(field) {
	var containers = [field.parentElement, field.parentElement && field.parentElement.parentElement];
	for (var i = 0; i < containers.length; i++) {
		var c = containers[i];
		if (!c) continue;
		var err = c.querySelector(':scope > .form_error, :scope > .error');
		if (err && err.innerHTML !== '') {
			err.innerHTML = '';
			return;
		}
	}
};

document.addEventListener('input', function(e) {
	if (e.target.matches && e.target.matches('input, textarea')) epesi_clear_field_error(e.target);
});

document.addEventListener('change', function(e) {
	if (e.target.matches && e.target.matches('select, input[type=checkbox], input[type=radio]')) epesi_clear_field_error(e.target);
});
