	
	is_collapsed = new Array();
	
	utils_tree_reset = function( id ) {
		is_collapsed[id] = new Array();
	}
	
	utils_tree_expand_all = function(id, sub) {
		for( i = 0; i < sub; i++) {
			is_collapsed[id][i] = 0;
			if($('utils_tree_'+id+'_'+i)) {
				$('utils_tree_'+id+'_'+i).style.display = "block";
				$('utils_tree_opener_img_'+id+'_'+i).src = "modules/Utils/Tree/theme/opener_active_open.gif";
			}
		}
		//$('tree_expand_all_'+id).innerHTML = 'Collapse All';
	}
	utils_tree_collapse_all = function(id, sub) {
		for( i = 0; i < sub; i++) {
			is_collapsed[id][i] = 1;
			if($('utils_tree_'+id+'_'+i)) {
				$('utils_tree_'+id+'_'+i).style.display = "none";
				$('utils_tree_opener_img_'+id+'_'+i).src = "modules/Utils/Tree/theme/opener_active_closed.gif";
			}
		}
		//$('tree_expand_all_'+id).innerHTML = 'Expand All';
	}
	
	tree_node_visibility_toggle = function( id, sub ) {
		if($('utils_tree_'+id+'_'+sub)) {
			if( is_collapsed[id][sub] == 0 ) {
				is_collapsed[id][sub] = 1;
				$('utils_tree_'+id+'_'+sub).style.display = "none";
				$('utils_tree_opener_img_'+id+'_'+sub).src = "modules/Utils/Tree/theme/opener_active_closed.gif";
			} else {
				is_collapsed[id][sub] = 0;
				$('utils_tree_'+id+'_'+sub).style.display = "block";
				$('utils_tree_opener_img_'+id+'_'+sub).src = "modules/Utils/Tree/theme/opener_active_open.gif";
			}
		}
	}
	tree_node_visibility_show = function( id, sub ) {
		if($('utils_tree_'+id+'_'+sub)) {
			is_collapsed[id][sub] = 0;
			$('utils_tree_'+id+'_'+sub).style.display = "block";
			$('utils_tree_opener_img_'+id+'_'+sub).src = "modules/Utils/Tree/theme/opener_active_open.gif";
		}
	}
	
	utils_tree_open = function(id, path) {
		for(i = 0; i < path.size(); i++) {
			tree_node_visibility_show(id, path[i]);
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
	
			