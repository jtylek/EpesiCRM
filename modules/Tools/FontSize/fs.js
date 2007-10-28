var Tools_FontSize = {
tags:new Array( 'div','span','td','tr','p','b','table','strong','emphasis','a','h1','h2','h3','pre','sub','sup','i','th','cp','ul','ol','li','dt','dd','body'),
size_method:null,
last_size:0,
get_style:function(oElm, strCssRule){
	var strValue = "";
	if(document.defaultView && document.defaultView.getComputedStyle){
        	strValue = document.defaultView.getComputedStyle(oElm, "").getPropertyValue(strCssRule);
	}
	else if(oElm.currentStyle){
		strValue = oElm.currentStyle[strCssRule.camelize()];
	}
	return strValue;
},
change:function(inc) {
	var dd=0;
	inc = parseInt(inc);
	Tools_FontSize.last_size = inc;
	for (i = 0 ; i < Tools_FontSize.tags.length ; i++ ) {
		var elems = document.getElementsByTagName(Tools_FontSize.tags[i]);
		for (k = 0 ; k < elems.length ; k++) {
			var orig_size = elems[k].getAttribute('original_font_size');
			if(orig_size == null) {
				var size = Tools_FontSize.get_style(elems[k],'font-size')
				if(size!=null && size.indexOf('px')!=-1) {
					orig_size = parseInt(size);
					elems[k].setAttribute('original_font_size',orig_size);
				}
			}
			if(orig_size != null) {
				elems[k].style.fontSize = (parseInt(orig_size)+inc)+'px';
			}
		}
	}
}
};
document.observe("e:load", function(){if(Tools_FontSize.last_size!=0)Tools_FontSize.change(Tools_FontSize.last_size);});
