getY = function( oElement ) {
	var iReturnValue = 0;
	while( oElement != null ) {
		iReturnValue += oElement.offsetTop + 1;
		oElement = oElement.offsetParent;
	}
	return iReturnValue - 2;
}

base_box__set_content_height = function(content) {
	if( document.getElementById(content) && document.documentElement.clientHeight > document.body.clientHeight ) {
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
		setTimeout("base_box__set_content_height('"+content+"')", 100);
	}
}

correctPNG = function() // correctly handle PNG transparency in Win IE 5.5 & 6.
{
   var arVersion = navigator.appVersion.split("MSIE")
   var version = parseFloat(arVersion[1])
   if ((version >= 5.5) && (document.body.filters)) 
   {
      for(var i=0; i<document.images.length; i++)
      {
         var img = document.images[i]
         var imgName = img.src.toUpperCase()
         if (imgName.substring(imgName.length-3, imgName.length) == "PNG")
         {
            var imgID = (img.id) ? "id='" + img.id + "' " : ""
            var imgClass = (img.className) ? "class='" + img.className + "' " : ""
            var imgTitle = (img.title) ? "title='" + img.title + "' " : "title='" + img.alt + "' "
            var imgStyle = "display:inline-block;" + img.style.cssText 
            if (img.align == "left") imgStyle = "float:left;" + imgStyle
            if (img.align == "right") imgStyle = "float:right;" + imgStyle
            if (img.parentElement.href) imgStyle = "cursor:hand;" + imgStyle
            var strNewHTML = "<span " + imgID + imgClass + imgTitle
            + " style=\"" + "width:" + img.width + "px; height:" + img.height + "px;" + imgStyle + ";"
            + "filter:progid:DXImageTransform.Microsoft.AlphaImageLoader"
            + "(src=\'" + img.src + "\', sizingMethod='scale');\"></span>" 
            img.outerHTML = strNewHTML
            i = i-1
         }
      }
   }    
}