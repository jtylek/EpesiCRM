getHeight = function(someObject){
	var w;
	if(document.defaultView &&
		document.defaultView.getComputedStyle) {
		w=document.defaultView.getComputedStyle(someObject ,'').getPropertyValue('height');
	}else if(someObject.offsetHeight){
		w=someObject.offsetHeight;
	}
	if(typeof w=="string") w=parseInt(w);
	return w;
};

base_box__set_content_height = function(content) {
	var frame = document.getElementById(content);
	if(!frame)return;
	var htmlheight = getHeight(document.getElementsByTagName('body')[0]);

	var windowheight = 0;
	if( typeof( window.innerHeight ) == 'number' ) { //non ie
		windowheight = window.innerHeight;
	} else if( document.documentElement && document.documentElement.clientHeight ) { //ie6
		windowheight = document.documentElement.clientHeight;
	}

	var contentheight = getHeight(frame);
	var h = windowheight-(htmlheight-contentheight);
	if(h<200) h=200;
	if(h!=parseInt(frame.style.height)+20)
		frame.style.height = (h-20) + "px";
};

correctPNG = function() // correctly handle PNG transparency in Win IE 5.5 & 6.
{
   var arVersion = navigator.appVersion.split("MSIE")
   var version = parseFloat(arVersion[1])
   if ((version >= 5.5) && (typeof document.body.filters == 'object')) 
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