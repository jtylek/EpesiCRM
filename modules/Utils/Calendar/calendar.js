Utils_Calendar = {
add_event:function(id,ev_id,title) {
	var dest = $(id);
	if(!dest) return;

	ev = document.createElement('div');
	ev.id = 'utils_calendar_event:'+ev_id;

	ev_handle = document.createElement('div');
	ev_handle.className = 'handle';
	ev_handle.innerHTML = title;
	ev.appendChild(ev_handle);

	ev_text = document.createElement('div');
	ev_text.innerHTML = 'description';
	ev.appendChild(ev_text);

	dest.appendChild(ev);
},
activate_dnd:function(ids_in) {
	var ids = ids_in.evalJSON();
	ids.each(function(id) {
		Sortable.create(id,{dropOnEmpty:true,tag:'div',containment:ids,constraint:false, ghosting: false, handle: 'handle',onUpdate:function(c){
		}});
	});
}
}