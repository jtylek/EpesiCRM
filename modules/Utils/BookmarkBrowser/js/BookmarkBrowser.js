	getY = function( oElement ) {
		var iReturnValue = 0;
		while( oElement != null ) {
			iReturnValue += oElement.offsetTop + 1;
			oElement = oElement.offsetParent;
		}
		return iReturnValue - 2;
	}

	utils_bookmarkbrowser_set_content_height = function(content) {
		if( $(content) ) {
			var prev = -19;
			var ch = (document.documentElement.clientHeight < document.body.clientHeight ? document.documentElement.clientHeight : document.body.clientHeight)
			var tmp = 0;
			while(prev != tmp) {
				prev = tmp;
				ch = (document.documentElement.clientHeight < document.body.clientHeight ? document.documentElement.clientHeight : document.body.clientHeight)
				tmp = ch - getY($(content));
				$(content).style.height = tmp + 'px';
			}
			tmp -= 40;
			$(content).style.height = tmp + 'px';
		} else {
			setTimeout("utils_bookmarkbrowser_set_content_height('"+content+"')", 100);
		}
	}
	
	utils_bookmark_goto = function(cnt, bkm) {
		$(cnt).scrollTop = getY($(bkm)) - getY($(cnt));
		//alert( tmp + 'px | ' + getY($(bkm)));
	}
	utils_bookmarkbrowser_markTab = function(item) {
		item.className = 'utils_bookmarkbrowser_tab_hover';
	}
	utils_bookmarkbrowser_unmarkTab = function(item) {
		item.className = 'utils_bookmarkbrowser_tab';
	}