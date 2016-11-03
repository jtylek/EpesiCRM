	
	is_collapsed = new Array();
	
	utils_tree_reset = function( id ) {
		is_collapsed[id] = new Array();
	}
	
	utils_tree_expand_all = function(id, sub) {
		for( i = 0; i < sub; i++) {
			is_collapsed[id][i] = 0;
			var el = jq('#utils_tree_'+id+'_'+i);
			if(el.length) {
				el.css('display', "block");
				jq('#utils_tree_opener_img_'+id+'_'+i).attr('src',"modules/Utils/Tree/theme/opener_active_open.gif");
			}
		}
	}
	utils_tree_collapse_all = function(id, sub) {
		for( i = 0; i < sub; i++) {
			is_collapsed[id][i] = 1;
			var el = jq('#utils_tree_'+id+'_'+i);
			if(el.length) {
				el.hide();
				jq('#utils_tree_opener_img_'+id+'_'+i).attr('src',"modules/Utils/Tree/theme/opener_active_closed.gif");
			}
		}
	}
	
	tree_node_visibility_toggle = function( id, sub ) {
	        var el = jq('#utils_tree_'+id+'_'+sub);
		if(el.length) {
			if( is_collapsed[id][sub] == 0 ) {
				is_collapsed[id][sub] = 1;
				el.hide();
				jq('#utils_tree_opener_img_'+id+'_'+sub).attr('src', "modules/Utils/Tree/theme/opener_active_closed.gif");
			} else {
				is_collapsed[id][sub] = 0;
				el.css('display', "block");
				jq('#utils_tree_opener_img_'+id+'_'+sub).attr('src', "modules/Utils/Tree/theme/opener_active_open.gif");
			}
		}
	}
	tree_node_visibility_show = function( id, sub ) {
	        var el = jq('#utils_tree_'+id+'_'+sub);
		if(el.length) {
			is_collapsed[id][sub] = 0;
			el.css('display', "block");
			jq('utils_tree_opener_img_'+id+'_'+sub).attr('src', "modules/Utils/Tree/theme/opener_active_open.gif");
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
	
			