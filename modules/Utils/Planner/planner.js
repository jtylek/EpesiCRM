function has_class(elem, current_class) {
	if (elem.className) {
		var class_list = elem.className.split(' ');
		current_class = current_class.toUpperCase();
		for (var i=0; i<class_list.length; i++) {
			if ( class_list[i].toUpperCase() == current_class) {
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
	elem = document.getElementById(day+'__'+from_time);
	if (!elem) {
//		alert(day+'__'+from_time+': element not found');
		return;
	}
	if (has_class(elem,'unused'))
		switch_direction = 'used';
	else 
		switch_direction = 'unused';
	if (switchd) switch_direction = switchd;
	if (has_class(elem,'noconflict'))
		elem.className = 'border_radius_3px noconflict '+switch_direction;
	else
		elem.className = 'conflict '+switch_direction;
}

function time_grid_mouse_move(from_time,day) {
	if (switch_direction=='') return;
	elem = document.getElementById(day+'__'+from_time);
	if (has_class(elem,'noconflict'))
		elem.className = 'border_radius_3px noconflict '+switch_direction;
	else
		elem.className = 'border_radius_3px conflict '+switch_direction;
}

function time_grid_change_conflicts(from_time,day,conflict) {
	elem = document.getElementById(day+'__'+from_time);
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

function resource_changed(resource,type) {
	if (!type) {
		opts = new Array();
		i=0;
		while (i!=document.getElementById(resource).options.length){
			opts[i] = document.getElementById(resource).options[i].value;
			i++;
		}
		rvalue = document.getElementById(resource).value;
	} else {
		if (type=='checkbox') {
			opts = 0;
			if (document.getElementById(resource).checked) rvalue = 1;
			else rvalue = 0;
		}
		if (type=='datepicker') {
			opts = 0;
			rvalue = document.getElementById(resource).value;
		}
	}
	jQuery.ajax('modules/Utils/Planner/resource_change.php', {
		method: 'post',
		data:{
			resource:JSON.stringify(resource),
			options:JSON.stringify(opts),
			value:JSON.stringify(rvalue),
			cid: Epesi.client_id
		},
		dataType: 'text',
		success:function(responseText) {
			eval(responseText);
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
	jQuery.ajax('modules/Utils/Planner/grid_change.php', {
		method: 'post',
		data:{
			frames:JSON.stringify(frames),
			cid: Epesi.client_id
		},
		dataType: 'text',
		success:function(responseText) {
			eval(responseText);
		}
	});
}

function time_grid_mouse_up() {
	if (switch_direction=='') return;
	switch_direction = '';
	update_grid();
}
