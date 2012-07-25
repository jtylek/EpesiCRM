timeout = new Array();
timeout_hideAllNow = new Array();
last_open = new Array();
level = new Array();
level[-1] = -1;
a_submenu_number = new Array();
a_menu_number = 0;
is_over = new Array();
menu_string = new Array();
layout = new Array();


sub_name = function(menu, submenu) {
	return 'custom_submenu_'+menu+'_'+submenu;
}
opener_name = function(menu, submenu) {
	return 'custom_opener_'+menu+'_'+submenu;
}

hideAllNow = function(menu, submenu) {
	var tmp_id;
	for(i = 0; i < a_submenu_number[menu]; i++ ) {
		tmp_id = sub_name(menu, i);
		if( is_over[menu][i] == 0 && $(tmp_id) && level[menu][submenu] <= level[menu][i] ) {
			$(tmp_id).style.display = "none";
			clearTimeout(timeout[menu][i]);
		}
	}
	last_open[menu] = -1;
}

selected_menu_item = function(menu, a_tag) {
	new Effect.Morph(a_tag, {
		style: 'background-color: #4FD64F;',
		duration: 0.3
	});
	new Effect.Morph(a_tag, {
		style: 'background-color: #4F864F;',
		duration: 0.3,
		delay: 0.3,
		afterFinish: function() {a_tag.style.backgroundColor = "";}
	});
	setTimeout("hideAllNow(\'"+menu+"\', 0);", 600);
}

custom_show = function(menu, submenu) {
	if(timeout[menu][submenu] != null) {
		clearTimeout(timeout[menu][submenu]);
	}

	if(level[menu][submenu] >= level[menu][last_open]) {
		last_open[menu] = submenu;
	}
	var id = sub_name(menu, submenu);
	var opener = opener_name(menu, submenu);
	var elem = $(id);

	elem.style.opacity = 1;
	elem.style.display  = "block";
	if (elem.getAttribute('mi') == null) {
		elem.setAttribute('mi','1');
		if(Epesi.ie)
        		elem.style.position = 'fixed';
		else
        		elem.style.position = 'absolute';
		elem.style.zIndex = 1000;
		//elem.clonePosition(elem.parentNode,{setWidth:false, setHeight:false, offsetTop:0});
		// above line, when commented out, fixed the major menu issue with rtl direction, both lrt and rtl works perfectly well with this line out, on IE, Firefox and Chrome
	}
}

custom_hide_f = function(menu, submenu, opacity) {
	var id = sub_name(menu, submenu);
	if( $(id) ) {
		if(opacity <= 0) {
			clearTimeout(timeout[menu][submenu]);
			$(id).className = "submenu";
			$(id).style.opacity = 1;
			$(id).style.display = "none";
		} else {
			$(id).style.opacity = opacity;
			timeout[menu][submenu] = setTimeout('custom_hide_f(\''+menu+'\', '+submenu+', '+eval(opacity-0.10)+')', 15);
		}
	}
}

custom_hide = function(menu, submenu) {
	timeout[menu][submenu] = setTimeout('custom_hide_f(\''+menu+'\', '+submenu+', 1)', 300);
}
//////////////////////////////////////////////////////////////////////////////
CustomMenubar = function(id, _layout) {
	this.id = id;
	a_menu_number = id;
	menu_string[this.id] = '<table cellspacing=0 cellpadding=0 class=root>';
	layout[this.id] = _layout;
	if( layout[this.id] == 'horizontal' ) {
		menu_string[this.id] += '<tr>';
	}
	this.submenu_number = 0;
	this.depth = 0;
	last_open[this.id] = -1;
	level[this.id] = new Array();
	is_over[this.id] = new Array();
	timeout[this.id] = new Array();
	this.init = 1;

	this.addSplit = function( ) {
		if(layout[this.id] == 'vertical' || this.depth != 0) {
			menu_string[this.id] += '<tr><td class="splitter"></td></tr>'; /*<hr class=custom_split>*/
		} else {
			if(this.depth == 0 ) {
				if(this.init == 0) {
					menu_string[this.id] += '<td class="separator">&nbsp;</td>';
					this.init = 0;
				} else {
					this.init = 0;
				}
				menu_string[this.id] += '<td class=item><a class=root_item_link>-|||-</a></td>';
			}
		}
	}
	this.addLink = function( title, address, icon, target) {
		if(layout[this.id] == 'vertical' || this.depth != 0) {
			menu_string[this.id] += '<tr><td class=item>';
			if(icon) {
				menu_string[this.id] += '<div class=link_icon_box><img class=link_icon src="'+icon+'"></div>';
			}
			menu_string[this.id] += '<a href="'+address+'"';
			if (target)
				menu_string[this.id] += ' target="'+target+'"';
			menu_string[this.id] += ' class=root_item_link onclick="selected_menu_item(\''+this.id+'\', this)">' + title + '</a>';
			menu_string[this.id] += '</td></tr>';
		} else {
			if(this.depth == 0 ) {
				if(this.init == 0) {
					menu_string[this.id] += '<td class="separator">&nbsp;</td>';
					this.init = 0;
				} else {
					this.init = 0;
				}

				menu_string[this.id] += '<td class=item>';
				if(icon) {
					menu_string[this.id] += '<div class=link_icon_box><img class=link_icon src="'+icon+'"></div>';
				}
				if( this.depth == 0) {
					menu_string[this.id] += '<a href="'+address+'" class=root_item_link_none onclick="selected_menu_item(\''+this.id+'\', this)">' + title + '</a>';
				} else {
					menu_string[this.id] += '<a href="'+address+'" class=root_item_link onclick="selected_menu_item(\''+this.id+'\', this)">' + title + '</a>';
				}
				menu_string[this.id] += '</td>';
			}
		}
	}
	this.addLink_bullet = function( title, icon ) {
		menu_string[this.id] += '<td id="'+opener_name(this.id, this.submenu_number)+'" class=item onmouseover="hideAllNow(\''+this.id+'\','+this.submenu_number+')">';
		if(icon) {
			menu_string[this.id] += '<div class=link_icon_box><img class=link_icon src="'+icon+'"></div>';
		}
		if(layout[this.id] == 'horizontal' && this.depth == 0) {
			menu_string[this.id] += '<a class=root_item_link_down><div class=root_item_link_down_arrow_box><div class=root_item_link_down_arrow_icon></div><div class=root_item_link_down_arrow>' + title + '</div></div></a>';
		} else {
			menu_string[this.id] += '<a class=root_item_link_right><div class=root_item_link_right_arrow>' + title + '</div></a>';
		}
		menu_string[this.id] += '</td>';

		//<div class=root_item_link_right_arrow>' + title + '</div>
	}

	this.beginSubmenu = function( title, icon ) {
		if(layout[this.id] == 'vertical' || this.depth != 0) {
			menu_string[this.id] += '<tr><td>';
			menu_string[this.id] += '<table cellspacing=0 cellpadding=0 onmouseout="custom_hide(\''+this.id+'\','+this.submenu_number+')" onmouseover="custom_show(\''+this.id+'\','+this.submenu_number+')" class=custom_opener>';
			menu_string[this.id] += '<tr>';
			this.addLink_bullet( title, icon );
			menu_string[this.id] += '<td class="item_sub">';
			// t2 begin
			menu_string[this.id] += '<table cellspacing="0" cellpadding="0" class="submenu" id="'+sub_name(this.id, this.submenu_number)+'">';
			// --
		} else {
			if(this.depth == 0 ) {
				if(this.init == 0) {
					menu_string[this.id] += '<td class="separator">&nbsp;</td>';
					this.init = 0;
				} else {
					this.init = 0;
				}
				menu_string[this.id] += '<td>';
				menu_string[this.id] += '<table cellspacing=0 cellpadding=0 onmouseout="custom_hide(\''+this.id+'\','+this.submenu_number+')" onmouseover="custom_show(\''+this.id+'\','+this.submenu_number+')" class=custom_opener>';
				menu_string[this.id] += '<tr>';
				this.addLink_bullet( title, icon );
				menu_string[this.id] += '</tr><tr><td class=item_sub><table cellspacing=0 cellpadding=0 id="'+sub_name(this.id, this.submenu_number)+'" class=submenu>';
			}
		}
		is_over[this.id][this.submenu_number] = 0;
		level[this.id][this.submenu_number] = this.depth;
		this.submenu_number++;
		this.depth++;
		a_submenu_number[this.id] = this.submenu_number;
	}

	this.endSubmenu = function() {
		this.depth--;
		if(layout[this.id] == 'vertical' || this.depth != 0) {
			// --
			menu_string[this.id] += '</table>';
			// t2 end
			menu_string[this.id] += '</td></tr></table></td></tr>';
		} else {
			if(this.depth == 0 ) {
				menu_string[this.id] += '</table></td></tr></table></td>';
			}
		}
	}

}
//////////////////////////////////////////////////////////////////////////////
writeOut = function(menu) {
	if( layout[menu] == 'horizontal' ) {
		menu_string[menu] += '</tr>';
	}
	$('menu_contener_' + menu).innerHTML = menu_string[menu] + '</table>' ;
}
