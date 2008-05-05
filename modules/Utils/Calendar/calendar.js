Utils_Calendar = {
add_event:function(dest_id,ev_id,draggable) {
	var dest = $(dest_id);
	var ev = $('utils_calendar_event:'+ev_id);
	if(!dest || !ev) {
		return;
	}

	dest.appendChild(ev);
	ev.setAttribute('last_cell',dest_id);

	if(draggable)
		new Draggable(ev, {
			handle: 'handle',
			revert: true,
			quiet: true
		});
},
add_ext_event:function(dest_id,tm,max_tm,ev_id) {
	var dest = $(dest_id);
	var ev = $('utils_calendar_event:'+ev_id);
	if(!dest || !ev) {
		return;
	}

	dest.appendChild(ev);
	var height = dest.getHeight();
	ev.style.position = 'relative';
	ev.style.top = tm*height/max_tm;
	ev.setAttribute('last_cell',dest_id);

	if(draggable)
		new Draggable(ev, {
			handle: 'handle',
			revert: true,
			quiet: true
		});
},
ids:null,
ids_ext:null,
activate_dnd:function(ids_in,ids_ext_in,new_ev,mpath,ecid,page_type) {
	Utils_Calendar.ids = ids_in.evalJSON();
	Utils_Calendar.ids.each(function(id) {
		var cell_id = 'UCcell_'+id;
		var f = '';
		if(typeof id=='string' && id.indexOf('_')>=0) {
			var kkk = id.indexOf('_');
			f = new_ev.replace('__TIME__',id.substr(0,kkk));
			f = f.replace('__TIMELESS__',id.substr(kkk+1));
		} else {
			f = new_ev.replace('__TIME__',id);
			f = f.replace('__TIMELESS__','0');
		}
		Droppables.add(cell_id, {
			accept: 'utils_calendar_event',
			onDrop: function(element,droppable,ev) {
				if(droppable.id==element.getAttribute('last_cell')) return;
				new Ajax.Request('modules/Utils/Calendar/update.php',{
					method:'post',
					parameters:{
						ev_id: element.id.substr(21),
						cell_id: droppable.id.substr(7),
						path: mpath,
						cid: ecid,
						month: (page_type=='month')?1:0
					},
					onComplete: function(t) {
						var reject=false;
						eval(t.responseText);
						if(reject) return;
						droppable.appendChild(element);
						element.setAttribute('last_cell',droppable.id);
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

	Utils_Calendar.ids_ext = ids_ext_in.evalJSON();
	Utils_Calendar.ids_ext.each(function(id) {
		var cell_id = 'UCcell_'+id[0];
		var f = new_ev.replace('__TIMELESS__','0');
//		f = f.replace('__TIME__',id);
//		alert(cell_id+' '+id[1]);
/*		Droppables.add(cell_id, {
			accept: 'utils_calendar_event',
			onDrop: function(element,droppable,ev) {
				if(droppable.id==element.getAttribute('last_cell')) return;
				new Ajax.Request('modules/Utils/Calendar/update.php',{
					method:'post',
					parameters:{
						ev_id: element.id.substr(21),
						cell_id: droppable.id.substr(7),
						path: mpath,
						cid: ecid,
						month: (page_type=='month')?1:0
					},
					onComplete: function(t) {
						var reject=false;
						eval(t.responseText);
						if(reject) return;
						droppable.appendChild(element);
						element.setAttribute('last_cell',droppable.id);
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
		});*/
		var cell = $(cell_id);
		var pos_y = cell.cumulativeOffset();
		pos_y = pos_y[1];
		var height = cell.getHeight();
		Event.observe(cell_id,'dblclick',function(e){var pos=e.clientY+window.scrollY-pos_y;eval(f.replace('__TIME__',id[0]+pos*id[1]/height));});
	});

	//activate trash
	Droppables.add('UCtrash', {
		accept: 'utils_calendar_event',
		onDrop: function(element,droppable,ev) {
			element.hide();
//			droppable.appendChild(element);
			setTimeout('Utils_Calendar.delete_event(\''+element.id+'\', \''+mpath+'\', \''+ecid+'\')',1);
		}
	});
},
delete_event:function(eid,mpath,ecid) {
	if(!confirm('Delete this event?')) {
		$(eid).show();
		return;
	}
	new Ajax.Request('modules/Utils/Calendar/update.php',{
			method:'post',
			parameters:{
				ev_id: eid.substr(21),
				cell_id: 'trash',
				path: mpath,
				cid: ecid
			},
			onComplete: function(t) {
				var reject=false;
				eval(t.responseText);
				if(reject) $(eid).show();
			},
			onException: function(t,e) {
				throw(e);
			},
			onFailure: function(t) {
				alert('Failure ('+t.status+')');
				Epesi.text(t.responseText,'error_box','p');
			}
	});
},
destroy:function() {
//	alert('destroy');
	if(Utils_Calendar.ids==null) return;
	Utils_Calendar.ids.each(function(id) {
		var cell_id = 'UCcell_'+id[0];
		Droppables.remove(cell_id);
	});
	Utils_Calendar.ids_ext.each(function(id) {
		var cell_id = 'UCcell_'+id[0];
		Droppables.remove(cell_id);
	});

	Droppables.remove('UCtrash');
}
};
document.observe('e:loading', Utils_Calendar.destroy);
