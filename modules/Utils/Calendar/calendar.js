Utils_Calendar = {
add_event:function(dest_id,ev_id,title) {
	var dest = $(dest_id);
	var ev = $('utils_calendar_event:'+ev_id);
	if(!dest || !ev) return;

	dest.appendChild(ev);
	ev.setAttribute('last_cell',dest_id);
	
	new Draggable(ev, {
		handle: 'handle',
		revert: true
	});
},
activate_dnd:function(ids_in,new_ev,mpath,ecid) {
	var ids = ids_in.evalJSON();
	ids.each(function(id) {
		var cell_id = 'UCcell_'+id[0];
		var f = new_ev.replace('__TIME__',id[0]);
		if(id.length==2) {
			if(id[1]==1) cell_id += '_timeless';
			f = f.replace('__TIMELESS__','1');
		} else {
			f = f.replace('__TIMELESS__','0');
		}
		Droppables.add(cell_id, {
			accept: 'utils_calendar_event',
			onDrop: function(element,droppable,ev) {
				if(droppable.id==element.getAttribute('last_cell')) return;
				droppable.appendChild(element);
				element.setAttribute('last_cell',droppable.id);
				new Ajax.Request('modules/Utils/Calendar/update.php',{
					method:'post',
					parameters:{
						ev_id: element.id.substr(21),
						cell_id: droppable.id.substr(7),
						path: mpath,
						cid: ecid,
						month: (id.length==2 && id[1]==2)?1:0
					},
					onComplete: function(t) {
						eval(t.responseText);
						new Draggable(element, {
							handle: 'handle',
							revert: true
						});
					},
					onException: function(t,e) {
						throw(e);
					},
					onFailure: function(t) {
						alert('Failure ('+t.status+')');
						Epesi.text(t.responseText,'error_box','p');
					}
				});
			}
		});
		Event.observe(cell_id,'dblclick',function(e){eval(f)});
	});
	
	//activate trash
	Droppables.add('UCtrash', {
		accept: 'utils_calendar_event',
		onDrop: function(element,droppable,ev) {
			element.hide();
			if(!confirm('Delete this event?')) {
				element.show();
				return;
			}
			droppable.appendChild(element);
			new Ajax.Request('modules/Utils/Calendar/update.php',{
					method:'post',
					parameters:{
						ev_id: element.id.substr(21),
						cell_id: 'trash',
						path: mpath,
						cid: ecid
					},
					onComplete: function(t) {
						eval(t.responseText);
					},
					onException: function(t,e) {
						throw(e);
					},
					onFailure: function(t) {
						alert('Failure ('+t.status+')');
						Epesi.text(t.responseText,'error_box','p');
					}
			});
		}
	});
}
}
