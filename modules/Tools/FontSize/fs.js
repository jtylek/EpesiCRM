var Tools_FontSize = {
tags:new Array( 'div','span','td','tr','p','b','table','strong','emphasis','a','h1','h2','h3','pre','sub','sup','i','th','cp','ul','ol','li','dt','dd','body'),
size_method:null,
get_style:function(oElm, strCssRule){
	var strValue = "";
	if(document.defaultView && document.defaultView.getComputedStyle){
        	strValue = document.defaultView.getComputedStyle(oElm, "").getPropertyValue(strCssRule);
	}
	else if(oElm.currentStyle){
		strCssRule = strCssRule.replace(/\-(\w)/g, function (strMatch, p1){
			return p1.toUpperCase();
		});
		strValue = oElm.currentStyle[strCssRule];
	}
	return strValue;
},
change:function(inc) {
	var old = Array();
	for (i = 0 ; i < Tools_FontSize.tags.length ; i++ ) {
		var elems = document.getElementsByTagName(Tools_FontSize.tags[i]);
		old[i] = Array();
		for (k = 0 ; k < elems.length ; k++) {
			var size = Tools_FontSize.get_style(elems[k],'font-size');
			if(size!=null && size.indexOf('px')!=-1) {
				var new_size = (parseInt(size)+inc);
				if(new_size<5) { //too small, back and return
					for(j=0;j<=i; j++)
						for(m=0; typeof old[j][m] != 'undefined'; m++)
							elems[k].style.fontSize = old[j][m];
					return;
				}
				elems[k].style.fontSize = (new_size)+'px';
			}
			old[i][k]=size;
		}
	}
}
};
