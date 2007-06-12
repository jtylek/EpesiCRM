	getY = function( oElement ) {
		var iReturnValue = 0;
		while( oElement != null ) {
			iReturnValue += oElement.offsetTop + 1;
			oElement = oElement.offsetParent;
		}
		return iReturnValue - 2;
	}

	utils_bookmarkbrowser_set_content_height = function(content) {
		if( document.getElementById(content) ) {
			var prev = -19;
			var ch = (document.documentElement.clientHeight < document.body.clientHeight ? document.documentElement.clientHeight : document.body.clientHeight)
			var tmp = 0;
			while(prev != tmp) {
				prev = tmp;
				ch = (document.documentElement.clientHeight < document.body.clientHeight ? document.documentElement.clientHeight : document.body.clientHeight)
				tmp = ch - getY(document.getElementById(content));
				document.getElementById(content).style.height = tmp + 'px';
			}
			tmp -= 40;
			document.getElementById(content).style.height = tmp + 'px';
		} else {
			setTimeout("utils_bookmarkbrowser_set_content_height('"+content+"')", 100);
		}
	}
	utils_bookmark_goto = function(cnt, bkm) {
		document.getElementById(cnt).scrollTop = getY(document.getElementById(bkm)) - getY(document.getElementById(cnt));
		//alert( tmp + 'px | ' + getY(document.getElementById(bkm)));
	}