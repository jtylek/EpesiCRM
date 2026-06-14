path_string = new Array();
path_title = new Array();
hide_to = new Array();
opened = new Array();

is_IE = false;


getY = function( obj ) {
	var iReturnValue = 0;
	while( obj != null ) {
		iReturnValue += obj.offsetTop;
		obj = obj.offsetParent;
	}
	return iReturnValue;
}
getX = function( obj ) {
	var iReturnValue = 0;
	while( obj != null ) {
		iReturnValue += obj.offsetLeft;
		obj = obj.offsetParent;
	}
	return iReturnValue;
}

show_path_children = function( id ) {
	for( i in opened ) {
		if( $(opened[i]) ) {
			$(opened[i]).style.visibility = "hidden";
		}
	}
	if( is_IE ) {
		var t = $('utils_path_mask');
		if( t ) {
			t.style.display = 'none';
		}
	}
	opened[id] = 'path_'+id;
	clearTimeout(hide_to[id]);
	var parent = $('path_link_' + id);
	var node = $('path_'+id);
	if( node && parent) {
		var tmp = getY( parent ) + parent.offsetHeight + parent.style.top;
		node.style.top = tmp + 'px';
		var tmp = getX( parent );
		node.style.left = tmp + 'px';
		node.style.zIndex = 1000;
		
		if(node.offsetWidth < parent.offsetWidth) {
			node.width = parent.offsetWidth + 'px';
		}
		if( is_IE ) {
			var t = $('utils_path_mask');
			if( t ) {
				t.style.zIndex = 9;//$(id).style.zIndex - 1;
				t.style.left = getX(node);
				t.style.top = getY(node);
				t.style.width = getWidth(node);
				t.style.height = node.offsetHeight;
				t.style.display = 'block';
			} else {
				alert("no mask");
			}
		}
		node.style.visibility = "visible";
	}
}

hide_path_children_f = function( id ) {
	var node = $('path_'+id);
	if( node ) {
		node.style.visibility = "hidden";
		if( is_IE ) {
			var t = $('utils_path_mask');
			if( t ) {
				t.style.display = 'none';
			}
		}
	}
}
hide_path_children = function( id ) {
	hide_to[id] = setTimeout('hide_path_children_f("'+id+'")', 600);
}

utils_path_writeOut = function( id ) {
	if( $('path_conteiner_' + id) ) {
		if( navigator.appName.indexOf('Explorer') != -1 ) {
			is_IE = true;
			$('path_conteiner_' + id).innerHTML += '<iframe class=utils_path_custom_iframe id=utils_path_mask>blah</iframe>';
		}
	} else {
		setTimeout("utils_path_writeOut("+id+")", 20);
	}
}