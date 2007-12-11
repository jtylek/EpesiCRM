Utils_Calendar = {
add_event:function(dest_id,ev_id,title) {
	var dest = $(dest_id);
	var ev = $('utils_calendar_event:'+ev_id);
	if(!dest || !ev) return;

	dest.appendChild(ev);
	new Draggable(ev.id, {
		handle: 'handle',
		revert: true
	});
},
activate_dnd:function(ids_in) {
	var ids = ids_in.evalJSON();
	ids.each(function(id) {
		Droppables.add(id, {
			accept: 'utils_calendar_event',
			onDrop: function(element,droppable,ev) {
				droppable.appendChild(element);
			}
		});
	});
}
}
