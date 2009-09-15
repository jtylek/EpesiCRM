function has_class(elem, class) {
	if (elem.className) {
		var class_list = elem.className.split(' ');
		class = class.toUpperCase();
		for (var i=0; i<class_list.length; i++) {
			if ( class_list[i].toUpperCase() == class) {
				return true;
			}
		}
	}
	return false;
}
function disableSelection(target){
	if (typeof target.onselectstart!="undefined")
		target.onselectstart=function(){return false}
	else if (typeof target.style.MozUserSelect!="undefined")
		target.style.MozUserSelect="none"
	else
		target.onmousedown=function(){return false}
	target.style.cursor = "default"
}

var switch_direction = '';
function time_grid_mouse_down(from_time,day,switchd) {
	elem = $(day+'__'+from_time);
	if (!elem) {
		alert(day+'__'+from_time+': element not found');
		return;
	}
	if (has_class(elem,'unused'))
		switch_direction = 'used';
	else 
		switch_direction = 'unused';
	if (switchd) switch_direction = switchd;
	if (has_class(elem,'noconflict'))
		elem.className = 'noconflict '+switch_direction;
	else
		elem.className = 'conflict '+switch_direction;
}

function time_grid_mouse_move(from_time,day) {
	if (switch_direction=='') return;
	elem = $(day+'__'+from_time);
	if (has_class(elem,'noconflict'))
		elem.className = 'noconflict '+switch_direction;
	else
		elem.className = 'conflict '+switch_direction;
}

function time_grid_change_conflicts(from_time,day,conflict) {
	elem = $(day+'__'+from_time);
	if (!elem) return;
	if (conflict)
		switch_conflict = 'conflict';
	else 
		switch_conflict = 'noconflict';
	if (has_class(elem,'unused'))
		elem.className = 'unused '+switch_conflict;
	else
		elem.className = 'used '+switch_conflict;
}

function resource_changed(resource) {
	opts = new Array();
	i=0;
	while (i!=$(resource).options.length){
		opts[i] = $(resource).options[i].value;
		i++;
	}
	new Ajax.Request('modules/Utils/Planner/resource_change.php', {
		method: 'post',
		parameters:{
			resource:Object.toJSON(resource),
			options:Object.toJSON(opts),
			value:Object.toJSON($(resource).value),
			cid: Epesi.client_id
		},
		onSuccess:function(t) {
			eval(t.responseText);
		}
	});
}

function update_grid() {
	frames = new Array();
	frames_elems = document.getElementsByClassName('used');
	for(i = 0; i < frames_elems.length; i++) {
//		id = frames_elems[i].id.split("__");
//		if (typeof(frames[id[0]])=="undefined") frames[id[0]] = new Array();
//		frames[id[0]][id[1]] = true;
		frames[i] = frames_elems[i].id;
	}
	new Ajax.Request('modules/Utils/Planner/grid_change.php', {
		method: 'post',
		parameters:{
			frames:Object.toJSON(frames),
			cid: Epesi.client_id
		},
		onSuccess:function(t) {
			eval(t.responseText);
		}
	});
}

function time_grid_mouse_up() {
	if (switch_direction=='') return;
	switch_direction = '';
	update_grid();
}
