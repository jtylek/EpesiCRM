//TODO: ajax and ipad dblclick still from prototype


Utils_Calendar = {
children_events:{},
too_many_events:{},
jq_cache:{},
jq_id:function(myid) {
        if(Utils_Calendar.jq_cache[myid])
                return Utils_Calendar.jq_cache[myid];
        return Utils_Calendar.jq_cache[myid] = jQuery('#' + myid.replace(/([:|\.#])/g,'\\$1'));
},
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
		setTimeout(function() { jQuery.proxy(Utils_Calendar.add_events,Utils_Calendar)(css) }, 100);
	} else {
		Utils_Calendar.add_events_f();
	}
},
add_event:function(dest_id,ev_id,draggable,duration,max_cut) {
	var dest = Utils_Calendar.jq_id(dest_id);
	var ev = Utils_Calendar.jq_id('utils_calendar_event:'+ev_id);
	if(!ev.length) {
		return;
	}
	if(!dest.length) {
		ev.hide();
		return;
	}

	ev.attr('last_cell',dest_id);
	if(Utils_Calendar.page_type=='month') {
		dest.append(ev);
		ev.show();
	} else {
		ev.attr('duration',duration);
		ev.attr('max_cut',max_cut);
		ev.css({position: 'absolute'});
//		ev.style.overflow = 'hidden';

		Utils_Calendar.reload_events.push('utils_calendar_event:'+ev_id);
/*		Utils_Calendar.init_reload_event_tag();
		Utils_Calendar.add_event_tag(dest,ev);
		Utils_Calendar.flush_reload_event_tag();*/
	}

	jQuery(ev).find('img').on('touchstart',function(){jq(this).attr('last_touch_start',(new Date()).getTime());}).on('touchend',function(){ var a = (new Date()).getTime()-jq(this).attr('last_touch_start'); if(a>200 && a<1000) jq(this).click(); });

	if(draggable) {
	        jQuery(ev).draggable({
	                handle:'.handle',
	                revert: function(event, ui) {
						var droppable_id = jq(event).attr('id');
						if(!droppable_id || droppable_id==jq(this).attr('last_cell')) return true;
			            return false;
			        },
//	                zIndex: 1000
                        stack:'.utils_calendar_event',
                    start: Utils_Calendar.start_drag
	        });
	}
},
reload_events:null,
remove_event_tag:function(prev_node,ev) {
	var duration = ev.attr('duration');
	var cell = prev_node;
	var prev_ch;
	var reload = new Array();
	do {
	        var children_events = Utils_Calendar.children_events[cell.attr('id')];
		if(children_events != undefined) {
			prev_ch = children_events;
			var idx = jQuery.inArray(ev.attr('id'),prev_ch);
			if(idx>=0) prev_ch.splice(idx,1);

		        Utils_Calendar.children_events[cell.attr('id')] = prev_ch;
			if(prev_ch.length>0) {
				reload = jQuery.merge(reload,prev_ch);
			}
		}

                var join_rows = cell.attr('join_rows');
		if(join_rows != undefined) {
			duration -= join_rows;
			cell = Utils_Calendar.jq_id(cell.attr('next_row'));
		} else
			duration = 0;
	} while(duration>0);

	Utils_Calendar.reload_event_tag(reload);
},
init_reload_event_tag:function() {
	Utils_Calendar.reload_events = new Array();
},
reload_event_tag:function(reload) {
	jQuery.each(reload,function(i,id) {
		if(id == undefined || Utils_Calendar.reload_events.indexOf(id)>=0) return;
		var element = Utils_Calendar.jq_id(id);
		Utils_Calendar.reload_events.push(id);
		Utils_Calendar.remove_event_tag(Utils_Calendar.jq_id(element.attr('last_cell')),element);
	});
},
flush_reload_event_tag:function() {
	Utils_Calendar.reload_events.sort(function(a,b) {
		var element1 = Utils_Calendar.jq_id(a);
		var element2 = Utils_Calendar.jq_id(b);
		var cell1 = element1.attr('last_cell');
		var dur1 = element1.attr('duration');
		var cell2 = element2.attr('last_cell');
		var dur2 = element2.attr('duration');
		var c1 = parseInt(cell1.substr(7))-parseInt(dur1);
		var c2 = parseInt(cell2.substr(7))-parseInt(dur2);
		return c1-c2;
	});
	jQuery.each(Utils_Calendar.reload_events,function(i,id) {
		if(id == undefined) return;
		var element = Utils_Calendar.jq_id(id);
		Utils_Calendar.add_event_tag(Utils_Calendar.jq_id(element.attr('last_cell')),element);
	});
	delete(Utils_Calendar.reload_events);
},
add_event_tag:function(dest,ev) {
	var ch;
	var offset = 0;
	var duration = ev.attr('duration');
	var h=0;
	var cell = dest;
	var reload = new Array(ev.attr('id'));
	do {
	        var children_events = Utils_Calendar.children_events[cell.attr('id')];
		if(children_events != undefined) {
			ch = children_events;
			reload = jQuery.merge(reload,ch);
		} else {
			ch = new Array();
		}
		if(offset<ch.length) offset = ch.length;
		var ev_id = ev.attr('id');
		if(ch.indexOf(ev_id)<0) ch.push(ev_id);
		Utils_Calendar.children_events[cell.attr('id')] = ch;

                var join_rows = cell.attr('join_rows');
		if(join_rows != undefined) {
			max_cut = parseInt(ev.attr('max_cut'));
			cut = parseInt(join_rows);
			if (cut>1 && max_cut>0 && max_cut<cut) cut = max_cut;
			duration -= cut;
			cell = Utils_Calendar.jq_id(cell.attr('next_row'));
		} else
			duration = 0;
		h++;
	} while(duration>0);
	ev.height((h * (dest.height()+1) - 2)+'px');

	var ev_w = ev.width();
	var offset_step = ev_w/5;

	if(offset_step*offset+ev_w>dest.width()) {
		var err_evs;
		var too_many_events = Utils_Calendar.too_many_events[dest.attr("id")];
		if(too_many_events != undefined){
			err_evs = too_many_events;
		} else {
			var b = document.createElement('a');
			var date = dest.attr('id').substr(7);
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
			ev.parent().append(b);
			jQuery(b).clonePosition(dest);
			b.style.width=(parseInt(b.style.width)-4)+'px';
			err_evs = new Array();
		}
		err_evs.push(ev.attr('id'));
		Utils_Calendar.too_many_events[dest.attr("id")] = err_evs;
		ev.hide();
	} else {
	        var too_many_events = Utils_Calendar.too_many_events[dest.attr("id")];
		if(too_many_events != undefined) {
			var err_evs = too_many_events;
			dest.removeAttr("too_many_events");
			var date = dest.attr('id').substr(7);
			var err = jq('#tooManyEventsCell_'+date).get(0);
			err.parentNode.removeChild(err);
			jQuery.each(err_evs,function(i,id) {
				if(typeof id == undefined) return;
				document.getElementById(id).style.display='block';
			});
		} else {
		}
		ev.css({zIndex:(5+offset)});
		ev.clonePosition(dest, {cloneHeight: false, cloneWidth: false, offsetLeft: (offset_step*offset)});
//		ev.offset(dest.offset());
		ev.show();
	}
	ev.attr('last_cell',dest.attr('id'));

	Utils_Calendar.reload_event_tag(reload);
},
ids:null,
join_rows:function(ids_in) {
	var ids = jQuery.parseJSON(ids_in);
	jQuery.each(ids,function(i,x) {
		if(typeof x == undefined) return;
		var y = Utils_Calendar.jq_id('UCcell_'+x[0]);
		y.attr('join_rows',x[1]);
		y.attr('next_row','UCcell_'+x[2]);
	});
},
activate_dnd:function(ids_in,new_ev,mpath,ecid) {
//	alert('act');
	Utils_Calendar.ids = jQuery.parseJSON(ids_in);
	var droppables = new Array();
	jQuery.each(Utils_Calendar.ids,function(ii,id) {
		var cell_id = 'UCcell_'+id;
		droppables.push(document.getElementById(cell_id));
		var f = '';
		if(typeof id=='string' && id.indexOf('_')>=0) {
			var kkk = id.indexOf('_');
			f = new_ev.replace('__TIME__',id.substr(0,kkk));
			f = f.replace('__TIMELESS__',id.substr(kkk+1));
		} else {
			f = new_ev.replace('__TIME__',id);
			f = f.replace('__TIMELESS__','0');
		}

		jq('#'+cell_id).on('dblclick',function(e){eval(f)});
		jq('#'+cell_id).on('touchend',function(e){
		    var now = new Date().getTime();
		    var lastTouch = jq(this).attr('lastTouch') || 0;
		    var delta = now-lastTouch;
		    jq(this).attr('lastTouch',now);
		    if(delta<500)
    		    eval(f);
		});
	});

	jQuery(droppables).droppable({
			accept: '.utils_calendar_event',
			tolerance: 'pointer',
			drop: function(ev,ui) {
/*				if (!ui.draggable.data("originalPosition")) {
					ui.draggable.data("originalPosition",
					ui.draggable.data("draggable").originalPosition);
				}*/
				var element = jQuery(ui.draggable);
				var droppable = jQuery(this);
				if(droppable.attr('id')==element.attr('last_cell')) return;
				Epesi.updateIndicatorText("Moving event");
				Epesi.procOn++;
				Epesi.updateIndicator();
//				element.css({zIndex:0});
				jq.ajax('modules/Utils/Calendar/update.php',{
					method:'post',
					data:{
						ev_id: element.attr('id').substr(21),
						cell_id: droppable.attr('id').substr(7),
						path: mpath,
						cid: ecid,
						page_type: Utils_Calendar.page_type
					},
					success: function(t) {
						var reject=false;
						eval(t);
						if(!reject) {
							setTimeout(function() {
							if(Utils_Calendar.page_type=='month') {
								droppable.append(element);
                                element.attr('last_cell',droppable.attr('id'));
								element.css('left',0);
								element.css('top',0);
							} else {
								Utils_Calendar.init_reload_event_tag();
								Utils_Calendar.reload_event_tag(new Array(element.attr('id')));
								element.attr('last_cell',droppable.attr('id'));
								Utils_Calendar.remove_event_tag(droppable,element);
								Utils_Calendar.flush_reload_event_tag();
                                element.attr('max_cut',0);
							}
                            element.data("originalPosition", null);
                            element.draggable('destroy');

							element.draggable({
								handle: '.handle',
								revert: function(event, ui) {
									var droppable_id = jq(event).attr('id');
									if(!droppable_id || droppable_id==jq(this).attr('last_cell')) return true;
						            return false;
						        },
//								zIndex: 1000
                                stack: '.utils_calendar_event',
                                start: Utils_Calendar.start_drag
							});
							},1);
						} else {
                            Utils_Calendar.revert_element(element);
						}
						Epesi.procOn--;
						Epesi.updateIndicator();
					},
					error: function(xhr,status,t) {
						alert('Failure ('+status+')');
						Epesi.text(t,'error_box','p');
					}
				});
			}
	});

	//activate trash
	Utils_Calendar.jq_id('UCtrash').droppable({
		accept: '.utils_calendar_event',
		tolerance: 'pointer',
		drop: function(ev,ui) {
/*            if (!ui.draggable.data("originalPosition")) {
                ui.draggable.data("originalPosition",
                    ui.draggable.data("draggable").originalPosition);
            }*/
	        var element = jQuery(ui.draggable);
			element.hide();
//			droppable.appendChild(element);
			setTimeout('Utils_Calendar.delete_event(\''+element.attr('id')+'\', \''+mpath+'\', \''+ecid+'\')',1);
		}
	});
},
delete_event:function(eid,mpath,ecid) {
	if(!confirm('Delete this event?')) {
		var element = Utils_Calendar.jq_id(eid);
        Utils_Calendar.revert_element(element);
        return;
	}
	Epesi.updateIndicatorText("Deleting event");
	Epesi.procOn++;
	Epesi.updateIndicator();
	jq.ajax('modules/Utils/Calendar/update.php',{
			method:'post',
			data:{
				ev_id: eid.substr(21),
				cell_id: 'trash',
				path: mpath,
				cid: ecid
			},
			success: function(t) {
				var reject=false;
				eval(t);
                var element = Utils_Calendar.jq_id(eid);
				if(reject) {
                    Utils_Calendar.revert_element(element);
                } else {
					if(Utils_Calendar.page_type!='month') {
						Utils_Calendar.init_reload_event_tag();
						Utils_Calendar.remove_event_tag(Utils_Calendar.jq_id(element.attr('last_cell')),element);
						Utils_Calendar.flush_reload_event_tag();
					}
                    element.data("originalPosition", null);
				}
				Epesi.procOn--;
				Epesi.updateIndicator();
			},
			error: function(xhr,status,t) {
				alert('Failure ('+status+')');
				Epesi.text(t,'error_box','p');
			}
	});
},
revert_element:function(element) {
    element.show();
    position = element.data("originalPosition");
    if (position) {
        element.animate({
            left: position.left,
            top: position.top
        }, 500, function() {
            element.data("originalPosition", null);
        });
    }
},
start_drag:function(ev,ui) {
    var h = jq(ui.helper);
    if (!h.data("originalPosition")) {
        h.data("originalPosition", ui.position);
    }
},
destroy:function() {
/*	if(Utils_Calendar.ids==null) return;
        jQuery('.utils_calendar_event').draggable('destroy');
	jQuery.each(Utils_Calendar.ids,function(i,id) {
		var cell = Utils_Calendar.jq_id('UCcell_'+id);
		if(cell.length) cell.droppable('destroy');
	});
        Utils_Calendar.jq_id('UCtrash').droppable('destroy');*/
	delete(Utils_Calendar.ids);
	Utils_Calendar.ids=null;
	delete(Utils_Calendar.children_events);
	Utils_Calendar.children_events = {};
	delete(Utils_Calendar.too_many_events);
	Utils_Calendar.too_many_events = {};
	delete(Utils_Calendar.jq_cache);
	Utils_Calendar.jq_cache = {};
}
};
jq(document).on('e:loading', Utils_Calendar.destroy);
