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
activate_dnd:function(sec,ids_in,new_ev) {
	var ids = ids_in.evalJSON();
	ids.each(function(id) {
		var cell_id = sec+'_'+id[0];
		var f = new_ev.replace('__TIME__',id[0]);
		if(id.length==2) {
			cell_id += '_timeless';
			f = f.replace('__TIMELESS__','1');
		} else {
			f = f.replace('__TIMELESS__','0');
		}
		Droppables.add(cell_id, {
			accept: 'utils_calendar_event',
			onDrop: function(element,droppable,ev) {
				droppable.appendChild(element);
			}
		});
		Event.observe(cell_id,'dblclick',function(e){eval(f)});
	});
}
}
