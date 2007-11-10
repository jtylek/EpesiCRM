// chowa ta postac klasy jako tabeli...
var CRMCalendarDND = {
	containments: new Array(),
	elements: new Array(),
	add_containment: function(id) {
		CRMCalendarDND.containments.push(id);
	},
	add_element: function(id) {
		new Draggable(id, 
			{ constraint:'vertical'}
		);
	},
	create_droppable: function(id) {
		Droppables.add(id,
			{
				containment:CRMCalendarDND.containments, overlap:'vertical',
				onDrop: function(e) {
					//alert(e.id+' on '+id);
					
					//$(id).appendChild(e.id);
					$(e.id).style.zIndex = 10000;
				}
			}
		);
	},
	create_droppables: function() {
		for(i = 0; i < CRMCalendarDND.containments.size(); i++) {
			CRMCalendarDND.create_droppable(CRMCalendarDND.containments[i]);
		}
		CRMCalendarDND.droppables = new Array();
	},
	str_to_num: function(str) {
		if(str.charAt(0) == '0') 
			str = str.charAt(1);
		return parseInt(str);
	},
	create_containment: function(id) {
		Sortable.create(id,
	     	{
	     		dropOnEmpty:true, containment:CRMCalendarDND.containments, constraint:false, treeTag:'td', tag:'div', tree: true, 
	     		handle: 'event_drag_handle',
	     		onDrop:function(c){
	     			//alert(c.id+' on '+id);
			    	new Ajax.Request("modules/CRM/Calendar/Event/Personal/update.php",
			        	{
							method: "post",
							parameters: { data: c.id+'='+id },
							onSuccess: function(trans) {
								eid = c.id.split('_');
								eid = eid[1];
								eid = eid.split('X');
								edate = eid[0];
								eid = eid[1];
								new_h = CRMCalendarDND.str_to_num(id.substring(16,18));
								
								$(c.id).style.zIndex = 10000;
								if($(c.id+'_f')) {
									$(c.id+'_f').style.zIndex = 10001;
								}
								if(id.substring(16,18) != 'tt') {
									times = document.getElementsByName('event'+eid+'start');
									//alert(times[i].innerHTML.substr(0,2));
									for(i = 0; i < times.length; i++) {
										beg_h = CRMCalendarDND.str_to_num(times[i].innerHTML.substr(0,2));
										times[i].innerHTML = id.substring(16,18) + ':'+edate.substring(10,12);
									}
									if(beg_h == '') beg_h = 1;
									times = document.getElementsByName('event'+eid+'finish');
									for(i = 0; i < times.length; i++) {
										old_e = CRMCalendarDND.str_to_num(times[i].innerHTML.substr(0,2));
										dur = old_e - beg_h;
										var n_h = eval(new_h + eval(old_e - beg_h));
										if(isNaN(n_h)) {
											times[i].innerHTML = id.substring(16,18)+':00';
										} else {
											if(n_h < 10) n_h = '0' + n_h + '';
											times[i].innerHTML = n_h+times[i].innerHTML.substr(2);
										}
									}
									times = document.getElementsByName('event'+eid+'divider');
									for(i = 0; i < times.length; i++) {
										times[i].innerHTML = ' - ';
									}
									times = document.getElementsByName('event'+eid+'after');
									for(i = 0; i < times.length; i++) {
										times[i].innerHTML = ': ';
									}
									
								} else {
									times = document.getElementsByName('event'+eid+'start');
									for(i = 0; i < times.length; i++) {
										times[i].innerHTML = '';
									}
									times = document.getElementsByName('event'+eid+'finish');
									for(i = 0; i < times.length; i++) {
										times[i].innerHTML = '';
									}
									times = document.getElementsByName('event'+eid+'divider');
									for(i = 0; i < times.length; i++) {
										times[i].innerHTML = '';
									}
									times = document.getElementsByName('event'+eid+'after');
									for(i = 0; i < times.length; i++) {
										times[i].innerHTML = '';
									}
								}
							}
						}
					);
				}
			}
		);
	},
	create_containments: function() {
		for(i = 0; i < CRMCalendarDND.containments.size(); i++) {
			CRMCalendarDND.create_containment(CRMCalendarDND.containments[i]);
		}
		CRMCalendarDND.containments = new Array();
	}
};