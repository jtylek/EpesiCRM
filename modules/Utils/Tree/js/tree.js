
are_all_collapsed = new Array()
is_collapsed = new Array()

tree_toggle_expand_all = function(id, sub) {
	if( are_all_collapsed[id] == 1 ) {
		are_all_collapsed[id] = 0;
		for( i = 0; i < sub; i++) {
			//var node = document.getElementById('tree_'+id+'_'+i);
			is_collapsed['tree_'+id+'_'+i] = 1;
			document.getElementById('tree_'+id+'_'+i).style.display = "block";
			//document.getElementById('tree_'+id+'_'+i).style.position = "relative";
			//document.getElementById('tree_'+id+'_'+i).style.visibility = "visible";
			document.getElementById('utils_tree_opener_'+id+'_'+i).className = "utils_tree_opener_active_open";
		}
		document.getElementById('tree_expand_all_'+id).innerHTML = 'Collapse All';
	} else {
		are_all_collapsed[id] = 1;
		for( i = 0; i < sub; i++) {
			//var node = document.getElementById('tree_'+id+'_'+i);
			is_collapsed['tree_'+id+'_'+i] = 0;
			document.getElementById('tree_'+id+'_'+i).style.display = "none";
			//document.getElementById('tree_'+id+'_'+i).style.position = "absolute";
			//document.getElementById('tree_'+id+'_'+i).style.visibility = "hidden";
			document.getElementById('utils_tree_opener_'+id+'_'+i).className = "utils_tree_opener_active_closed";
		}
		document.getElementById('tree_expand_all_'+id).innerHTML = 'Expand All';
	}
}

tree_node_visibility_toggle = function( id ) {
	var node = document.getElementById('tree_'+id);
	if( is_collapsed[id] == 0 ) {
		is_collapsed[id] = 1;
		//node.style.visibility = "visible";
		//node.style.position = "relative";
		node.style.display = "block";
		document.getElementById('utils_tree_opener_'+id).className = "utils_tree_opener_active_open";
	} else {
		is_collapsed[id] = 0;
		//node.style.visibility = "hidden";
		//node.style.position = "absolute";
		node.style.display = "none";
		document.getElementById('utils_tree_opener_'+id).className = "utils_tree_opener_active_closed";
	}
}
