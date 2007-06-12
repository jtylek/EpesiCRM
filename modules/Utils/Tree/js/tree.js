
are_all_collapsed = new Array()
is_collapsed = new Array()

utils_tree_expand_all = function(id, sub) {
	for( i = 0; i < sub; i++) {
		is_collapsed[id+'_'+i] = 0;
		document.getElementById('utils_tree_'+id+'_'+i).style.display = "block";
		document.getElementById('utils_tree_opener_img_'+id+'_'+i).src = "modules/Utils/Tree/theme/opener_active_open.gif";
	}
	//document.getElementById('tree_expand_all_'+id).innerHTML = 'Collapse All';
}
utils_tree_collapse_all = function(id, sub) {
	for( i = 0; i < sub; i++) {
		is_collapsed[id+'_'+i] = 1;
		document.getElementById('utils_tree_'+id+'_'+i).style.display = "none";
		document.getElementById('utils_tree_opener_img_'+id+'_'+i).src = "modules/Utils/Tree/theme/opener_active_closed.gif";
	}
	//document.getElementById('tree_expand_all_'+id).innerHTML = 'Expand All';
}

tree_node_visibility_toggle = function( id ) {
	if( is_collapsed[id] == 0 ) {
		is_collapsed[id] = 1;
		document.getElementById('utils_tree_'+id).style.display = "none";
		document.getElementById('utils_tree_opener_img_'+id).src = "modules/Utils/Tree/theme/opener_active_closed.gif";
	} else {
		is_collapsed[id] = 0;
		document.getElementById('utils_tree_'+id).style.display = "block";
		document.getElementById('utils_tree_opener_img_'+id).src = "modules/Utils/Tree/theme/opener_active_open.gif";
	}
}

utils_tree_hl = function( i ) { 
	//i.style.background = "white"; i.style.padding = "0px"; i.style.border = "1px solid black"; 
	i.className = 'utils_tree_node_hover';
}
utils_tree_rg = function( i ) { 
	//i.style.background = "transparent"; i.style.padding = "1px"; i.style.border = "none"; 
	i.className = 'utils_tree_node';
};

		