Utils_Calendar = {
day_href:null,
page_type:null,
go_to_day:function(date) {
	eval(Utils_Calendar.day_href.replace('__DATE__',date));
},
add_events_f:null,
add_events:function(css) {
	var loaded = false;
	for(var i=0; i<document.styleSheets.length; i++) {
		try {
			typeof(document.styleSheets[i].cssRules);
		} catch(err) {
			continue;
		}
		var v = document.styleSheets[i].href;
		if(typeof(v)=='string' && v.indexOf(css)!=-1) {
			loaded = true;
		}
	}
	if (!loaded) {
		setTimeout(Utils_Calendar.add_events.bind(Utils_Calendar,css),100);
	} else {
		Utils_Calendar.add_events_f();	
	}	
},
add_event:function(dest_id,ev_id,draggable,duration,max_cut) {
	var dest = $(dest_id);
	var ev = $('utils_calendar_event:'+ev_id);
	if(!ev) {
		return;
	}
	if(!dest) {
		ev.hide();
		return;
	}

	ev.setAttribute('last_cell',dest_id);
	if(Utils_Calendar.page_type=='month') {
		dest.appendChild(ev);
		ev.show();
	} else {
		ev.setAttribute('duration',duration);
		ev.setAttribute('max_cut',max_cut);
		ev.style.position = 'absolute';
//		ev.style.overflow = 'hidden';

		Utils_Calendar.reload_events.push(ev.id);
/*		Utils_Calendar.init_reload_event_tag();
		Utils_Calendar.add_event_tag(dest,ev);
		Utils_Calendar.flush_reload_event_tag();*/
	}

	if(draggable)
		new Draggable(ev, {
			handle: 'handle',
			revert: true,
			quiet: true
		});
},
reload_events:null,
remove_event_tag:function(prev_node,ev) {
	var duration = ev.getAttribute('duration');
	var cell = prev_node;
	var prev_ch;
	var reload = new Array();
	do {
		if(cell.hasAttribute('events_children')) {
			prev_ch = cell.getAttribute('events_children').evalJSON().without(ev.id);
			if(prev_ch.length==0) {
				cell.removeAttribute('events_children');
			} else {
				reload = reload.concat(prev_ch);
				cell.setAttribute('events_children',Object.toJSON(prev_ch));
			}
		}

		if(cell.hasAttribute('join_rows')) {
			duration -= cell.getAttribute('join_rows');
			cell = $(cell.getAttribute('next_row'));
		} else
			duration = 0;
	} while(duration>0);

	Utils_Calendar.reload_event_tag(reload);
},
init_reload_event_tag:function() {
	Utils_Calendar.reload_events = new Array();
},
reload_event_tag:function(reload) {
	reload.each(function(id) {
		if(Utils_Calendar.reload_events.indexOf(id)>=0) return;
		var element = $(id);
		Utils_Calendar.reload_events.push(id);
		Utils_Calendar.remove_event_tag($(element.getAttribute('last_cell')),element);
	});
},
flush_reload_event_tag:function() {
	Utils_Calendar.reload_events = Utils_Calendar.reload_events.sortBy(function(id) {
		var element = $(id);
		var cell = element.getAttribute('last_cell');
		var dur = element.getAttribute('duration');
		return parseInt(cell.substr(7))-parseInt(dur);
	});
	Utils_Calendar.reload_events.each(function(id) {
		var element = $(id);
		Utils_Calendar.add_event_tag($(element.getAttribute('last_cell')),element);
	});
	delete(Utils_Calendar.reload_events);
},
add_event_tag:function(dest,ev) {
	var ch;
	var offset = 0;
	var duration = ev.getAttribute('duration');
	var h=0;
	var cell = dest;
	var reload = new Array(ev.id);
	do {
		if(cell.hasAttribute('events_children')) {
			ch = cell.getAttribute('events_children').evalJSON();
			reload = reload.concat(ch);
		} else {
			ch = new Array();
		}
		if(offset<ch.length) offset = ch.length;
		if(ch.indexOf(ev.id)<0) ch.push(ev.id);
		cell.setAttribute('events_children',Object.toJSON(ch));

		if(cell.hasAttribute('join_rows')) {
			max_cut = parseInt(ev.getAttribute('max_cut'));
			cut = parseInt(cell.getAttribute('join_rows'));
			if (cut>1 && max_cut>0 && max_cut<cut) cut = max_cut;
			duration -= cut;
			cell = $(cell.getAttribute('next_row'));
		} else
			duration = 0;
		h++;
	} while(duration>0);
	ev.style.height = (h * dest.getHeight() - 2)+'px';

	var ev_w = ev.getWidth();
	var offset_step = ev_w/5;

	if(offset_step*offset+ev_w>dest.getWidth()) {
		var err_evs;
		if(dest.hasAttribute("too_many_events")){
			err_evs = dest.getAttribute("too_many_events").evalJSON();
		} else {
			var b = document.createElement('a');
			var date = dest.id.substr(7);
			b.id = 'tooManyEventsCell_'+date;
			var i = date.indexOf('_');
			if(i>0) date = date.substr(0,i);
			b.href = 'javascript:Utils_Calendar.go_to_day("'+date+'")';
			b.innerHTML = 'Too many events - switch to Day view';
			b.style.position = 'absolute';
			b.style.backgroundColor='#FFCCCC';
			b.style.color='red';
			b.style.height='29px';
			b.style.lineHeight='29px';
			b.style.fontSize='15px';
			b.style.fontWeight='bold';
			b.style.border='2px solid red';
			b.style.zIndex=20;
			ev.parentNode.appendChild(b);
			b.clonePosition(dest);
			b.style.width=(parseInt(b.style.width)-4)+'px';
			err_evs = new Array();
		}
		err_evs.push(ev.id);
		dest.setAttribute("too_many_events",Object.toJSON(err_evs));
		ev.style.display='none';
	} else {
		if(dest.hasAttribute("too_many_events")) {
			var err_evs = dest.getAttribute("too_many_events").evalJSON();
			dest.removeAttribute("too_many_events");
			var date = dest.id.substr(7);
			var err = $('tooManyEventsCell_'+date);
			err.parentNode.removeChild(err);
			err_evs.each(function(id) {
				$(id).style.display='block';
			});
		} else {
		}
		ev.style.zIndex=5+offset;
		ev.clonePosition(dest, {setHeight: false, setWidth: false, offsetLeft: (offset_step*offset)});
		ev.show();
	}
	ev.setAttribute('last_cell',dest.id);


	Utils_Calendar.reload_event_tag(reload);
},
ids:null,
join_rows:function(ids_in) {
	var ids = ids_in.evalJSON();
	ids.each(function(x) {
		var y = $('UCcell_'+x[0]);
		y.setAttribute('join_rows',x[1]);
		y.setAttribute('next_row','UCcell_'+x[2]);
	});
},
activate_dnd:function(ids_in,new_ev,mpath,ecid) {
//	alert('act');
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
				Epesi.updateIndicatorText("Moving event");
				Epesi.procOn++;
				Epesi.updateIndicator();
				new Ajax.Request('modules/Utils/Calendar/update.php',{
					method:'post',
					parameters:{
						ev_id: element.id.substr(21),
						cell_id: droppable.id.substr(7),
						path: mpath,
						cid: ecid,
						page_type: Utils_Calendar.page_type
					},
					onComplete: function(t) {
						var reject=false;
						eval(t.responseText);
						if(!reject) {
							if(Utils_Calendar.page_type=='month') {
								droppable.appendChild(element);
                                element.setAttribute('last_cell',droppable.id);
							} else {
								Utils_Calendar.init_reload_event_tag();
								Utils_Calendar.reload_event_tag(new Array(element.id));
								//Utils_Calendar.remove_event_tag($(element.getAttribute('last_cell')),element);
								//Utils_Calendar.add_event_tag(droppable,element);
								element.setAttribute('last_cell',droppable.id);
								Utils_Calendar.remove_event_tag(droppable,element);
								setTimeout(Utils_Calendar.flush_reload_event_tag,300);
								//Utils_Calendar.flush_reload_event_tag();
                                element.setAttribute('max_cut',0);
							}

							new Draggable(element, {
								handle: 'handle',
								revert: true
							});
						}
						Epesi.procOn--;
						Epesi.updateIndicator();
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
		Event.observe(cell_id,'touchend',function(e){
		    var now = new Date().getTime();
		    var lastTouch = $(this).readAttribute('lastTouch') || 0;
		    var delta = now-lastTouch;
		    $(this).writeAttribute('lastTouch',now);
		    if(delta<500)
    		    eval(f);
		});
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
	Epesi.updateIndicatorText("Deleting event");
	Epesi.procOn++;
	Epesi.updateIndicator();
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
				var element = $(eid);
				if(reject) element.show();
				else {
					if(Utils_Calendar.page_type!='month') {
						Utils_Calendar.init_reload_event_tag();
						Utils_Calendar.remove_event_tag($(element.getAttribute('last_cell')),element);
						Utils_Calendar.flush_reload_event_tag();
					}
				}
				Epesi.procOn--;
				Epesi.updateIndicator();
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
	if(Utils_Calendar.ids==null) return;
//	alert('destroy');

	delete(Droppables.drops);
	Droppables.drops = [];
	Utils_Calendar.ids.each(function(id) {
//		Droppables.remove('UCcell_'+id[0]);
		var cell = $('UCcell_'+id);
		if(cell) cell.removeAttribute('events_children');
	});
	delete(Utils_Calendar.ids);
	Utils_Calendar.ids=null;

//	Droppables.remove('UCtrash');
}
};
document.observe('e:loading', Utils_Calendar.destroy);
