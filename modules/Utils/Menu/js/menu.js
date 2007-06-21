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
is_IE = false;


sub_name = function(menu, submenu) {
	return 'custom_submenu_'+menu+'_'+submenu;
}
opener_name = function(menu, submenu) {
	return 'custom_opener_'+menu+'_'+submenu;
}
iframe_name = function(menu, submenu) {
	return 'custom_iframe_'+menu+'_'+submenu;
}


hideAllNow_f = function(menu, submenu) {
	var tmp_id;
	for(i = 0; i < a_submenu_number[menu]; i++ ) {
		tmp_id = sub_name(menu, i);
		if( is_over[menu][i] == 0 && document.getElementById(tmp_id) && level[menu][submenu] <= level[menu][i] ) {
			document.getElementById(tmp_id).style.visibility = "hidden";
			clearTimeout(timeout[menu][i]);
			if( (level[menu][submenu] < level[menu][i]) && is_IE ) {
				document.getElementById('mask_level'+level[menu][i]).style.display = 'none';
			}
		}
	}
	last_open[menu] = -1;
}

hideAllNow = function(menu, submenu) {
	//timeout_hideAllNow[menu] = setTimeout('hideAllNow_f('+menu+','+submenu+')', 10);
	hideAllNow_f(menu, submenu);
}

super_hideAllNow = function() {
	for(menu = 0; menu < a_menu_number; menu++ ) {
		for(i = 0; i < a_submenu_number[menu]; i++ ) {
			tmp_id = sub_name(menu, i);
			if( is_over[menu][i] == 0 && document.getElementById(tmp_id) && level[menu][submenu] <= level[menu][i] ) {
				document.getElementById(tmp_id).style.visibility = "hidden";
			}
		}
	}
}

var frameId = 0;
var frameObj = new Array();
function cmAllocFrame (lvl) {
	if(frameObj[lvl]) {}
	else {
		frameObj[lvl] = document.createElement ('iframe');
		frameObj[lvl].id = 'cmFrame' + frameId;
		frameObj[lvl].frameBorder = '2';
		frameObj[lvl].style.display = 'none';
		frameObj[lvl].src = 'javascript:false';
		document.body.appendChild (frameObj[lvl]);
		frameObj[lvl].style.filter = 'alpha(opacity=0)';
		frameObj[lvl].style.zIndex = 99;
		frameObj[lvl].style.position = 'absolute';
		frameObj[lvl].style.border = '2px solid red';
		frameObj[lvl].scrolling = 'no';
		frameId++;
	}
	return frameObj[lvl];
}

function getY( oElement ) {
	var iReturnValue = 0;
	while( oElement != null ) {
		iReturnValue += oElement.offsetTop + 1;
		oElement = oElement.offsetParent;
	}
	return iReturnValue - 2;
}
function getX( oElement ) {
	var iReturnValue = 0;
	while( oElement != null ) {
		iReturnValue += oElement.offsetLeft + 1;
		oElement = oElement.offsetParent;
	}
	return iReturnValue - 2;
}
function getWidth (obj)
{
	var width = obj.offsetWidth;
	if (width > 0)
		return width;
	if (!obj.firstChild)
		return 0;
	// use TABLE's length can cause an extra pixel gap
	//return obj.parentNode.parentNode.offsetWidth;

	// use the left and right child instead
	return obj.lastChild.offsetLeft - obj.firstChild.offsetLeft + cmGetWidth (obj.lastChild);
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
	document.getElementById(id).style.zIndex = 1000;
	
	//document.getElementById(id).style.visibility = "visible";
	//var t = cmAllocFrame(level[menu][submenu]);
	
	if( is_IE ) {
		var t = document.getElementById('mask_level'+level[menu][submenu]);
		t.style.zIndex = 9;//document.getElementById(id).style.zIndex - 1;
		t.style.left = getX(document.getElementById(id));
		t.style.top = getY(document.getElementById(id));
		t.style.width = getWidth(document.getElementById(id));
		t.style.height = document.getElementById(id).offsetHeight;
		t.style.display = 'block';
	}
	
	//document.getElementById(id).style.filter = "Alpha()";
	document.getElementById(id).style.opacity = 1;
	document.getElementById(id).style.visibility  = "visible";
	
	
}

custom_hide_f = function(menu, submenu, opacity) {
	var id = sub_name(menu, submenu);
	if( document.getElementById(id) ) {
		if(opacity <= 0) {
			clearTimeout(timeout[menu][submenu]);
			document.getElementById(id).className = "submenu";
			document.getElementById(id).style.opacity = 1;
			document.getElementById(id).style.visibility = "hidden";
			
			if( is_IE ) {
				if( level[menu][submenu] != level[menu][last_open] ) {
					var t = document.getElementById('mask_level'+level[menu][submenu]);
					t.style.display = 'none';
				}
			}
		} else {
			document.getElementById(id).style.opacity = opacity;
			//document.getElementById(id).style.filter = "Alpha(style=0, opacity="+eval(opacity*100)+")";
			timeout[menu][submenu] = setTimeout('custom_hide_f('+menu+', '+submenu+', '+eval(opacity-0.11)+')', 20);
		}
	}
}

custom_hide = function(menu, submenu) {
	timeout[menu][submenu] = setTimeout('custom_hide_f('+menu+', '+submenu+', 1)', 800);
}
//////////////////////////////////////////////////////////////////////////////
CustomMenubar = function(id, _layout) {
	this.id = id;
	a_menu_number = id;
	menu_string[this.id] = '<table cellspacing=0 cellpadding=0 class=root>';
	layout[this.id] = _layout;
	is_IE = false;
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
	this.postfix = '';
	if( navigator.appName.indexOf('Explorer') != -1 ) {
		is_IE = true;
		this.postfix = '_ie';
	}
	
	this.addSplit = function( ) {
		if(layout[this.id] == 'vertical' || this.depth != 0) {
			menu_string[this.id] += '<tr><td class=splitter></td></tr>'; /*<hr class=custom_split>*/
		} else {
			if(this.depth == 0 ) {
				if(this.init == 0) {
					menu_string[this.id] += '<td class="separator">|</td>';
					this.init = 0;
				} else {
					this.init = 0;
				}
				menu_string[this.id] += '<td class=item><a href=# class=root_item_link>-|||-</a></td>';
			}
		}
	}
	this.addLink = function( title, address, icon ) {
		if(layout[this.id] == 'vertical' || this.depth != 0) {
			menu_string[this.id] += '<tr><td class=item>';
			if(icon) {
				menu_string[this.id] += '<img class=link_icon src="'+icon+'">';
			}
			menu_string[this.id] += '<a href="'+address+'" class=root_item_link>' + title + '</a>';
			menu_string[this.id] += '</td></tr>';
		} else {
			if(this.depth == 0 ) {
				if(this.init == 0) {
					menu_string[this.id] += '<td class="separator">|</td>';
					this.init = 0;
				} else {
					this.init = 0;
				}
				
				menu_string[this.id] += '<td class=item>';
				if(icon) {
					menu_string[this.id] += '<img class=link_icon src="'+icon+'">';
				}
				menu_string[this.id] += '<a href="'+address+'" class=root_item_link>' + title + '</a>';
				menu_string[this.id] += '</td>';
			}
		}
	}	
	this.addLink_bullet = function( title, icon ) {
		if(layout[this.id] == 'horizontal' && this.depth == 0) {
			
			menu_string[this.id] += '<td id="'+opener_name(this.id, this.submenu_number)+'" class=item onmouseover="hideAllNow('+this.id+','+this.submenu_number+')">';
			if(icon) {
				menu_string[this.id] += '<img class=link_icon src="'+icon+'">';
			}
			menu_string[this.id] += '<a  href=# class=root_item_link_down>' + title + '</a>';
			menu_string[this.id] += '</td>';
			
			//menu_string[this.id] += '<td class=item onmouseover="hideAllNow('+this.id+','+this.submenu_number+')"><img class=link_bullet src="modules/Utils/Menu/theme/arrow_down.gif"><a href=# class=root_item_link>' + title + '</a></td>';
		} else {
			menu_string[this.id] += '<td id="'+opener_name(this.id, this.submenu_number)+'" class=item onmouseover="hideAllNow('+this.id+','+this.submenu_number+')">';
			if(icon) {
				menu_string[this.id] += '<img class=link_icon src="'+icon+'">';
			}
			menu_string[this.id] += '<a  href=# class=root_item_link_right>' + title + '</a>';
			menu_string[this.id] += '</td>';
			//menu_string[this.id] += '<td class=item onmouseover="hideAllNow('+this.id+','+this.submenu_number+')"><img class=link_bullet src="modules/Utils/Menu/theme/arrow.gif"><a href=# class=root_item_link>' + title + '</a></td>';
		}
	}
	
	this.beginSubmenu = function( title, icon ) {
		if(layout[this.id] == 'vertical' || this.depth != 0) {
			menu_string[this.id] += '<tr><td>';
			menu_string[this.id] += '<table cellspacing=0 cellpadding=0 onmouseout="custom_hide('+this.id+','+this.submenu_number+')" onmouseover="custom_show('+this.id+','+this.submenu_number+')" class=custom_opener>';
			menu_string[this.id] += '<tr>';
			this.addLink_bullet( title, icon );
			menu_string[this.id] += '<td class=item_sub><table cellspacing=0 cellpadding=0 id="'+sub_name(this.id, this.submenu_number)+'" class=submenu>';	
			is_over[this.id][this.submenu_number] = 0;
			level[this.id][this.submenu_number] = this.depth;
			this.submenu_number++;	
			this.depth++;
			a_submenu_number[this.id] = this.submenu_number;
		} else {
			if(this.depth == 0 ) {
				if(this.init == 0) {
					menu_string[this.id] += '<td class="separator">|</td>';
					this.init = 0;
				} else {
					this.init = 0;
				}
				menu_string[this.id] += '<td>';
				menu_string[this.id] += '<table cellspacing=0 cellpadding=0 onmouseout="custom_hide('+this.id+','+this.submenu_number+')" onmouseover="custom_show('+this.id+','+this.submenu_number+')" class=custom_opener>';
				menu_string[this.id] += '<tr>';
				this.addLink_bullet( title );
				menu_string[this.id] += '</tr><tr><td class=item_sub> <table cellspacing=0 cellpadding=0 id="'+sub_name(this.id, this.submenu_number)+'" class=submenu>';	
				is_over[this.id][this.submenu_number] = 0;
				level[this.id][this.submenu_number] = this.depth;
				this.submenu_number++;	
				this.depth++;
				a_submenu_number[this.id] = this.submenu_number;
			}
		}
	}
	
	this.endSubmenu = function() {
		this.depth--;
		if(layout[this.id] == 'vertical' || this.depth != 0) {
			menu_string[this.id] += '</table></td></tr></table></td></tr>';
		} else {
			if(this.depth == 0 ) {
				menu_string[this.id] += '</table></td></tr></table></td>';
			}
		}
	}
	
}
//////////////////////////////////////////////////////////////////////////////
writeOut = function(menu) {
	//var bodies = document.getElementsByTagName('body');
	if( document.getElementById('menu_contener_' + menu) ) {
	//if( bodies[0] ) {
		if( layout[menu] == 'horizontal' ) {
			menu_string[menu] += '</tr>';
		}
		document.getElementById('menu_contener_' + menu).innerHTML = menu_string[menu] + '</table>' ;
		
		if( is_IE ) {
			document.getElementById('menu_contener_' + menu).innerHTML += '<iframe class=custom_iframe id="mask_level0">blah</iframe>';
			document.getElementById('menu_contener_' + menu).innerHTML += '<iframe class=custom_iframe id="mask_level1">blah</iframe>';
			document.getElementById('menu_contener_' + menu).innerHTML += '<iframe class=custom_iframe id="mask_level2">blah</iframe>';
			document.getElementById('menu_contener_' + menu).innerHTML += '<iframe class=custom_iframe id="mask_level3">blah</iframe>';
			document.getElementById('menu_contener_' + menu).innerHTML += '<iframe class=custom_iframe id="mask_level4">blah</iframe>';
		}
		//bodies[0].innerHTML += menu_string[menu] + '</table>' ;
	} else {
		setTimeout("writeOut("+menu+")", 20);
	}
}