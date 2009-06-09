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
};
document.observe("e:load", correctPNG);

base_box_roll_topbar = function() {
   Effect.toggle('ActionBar','slide',{duration:0.3});
   if($('ShadowBar').style.display == 'none') {
      document.getElementById("ShadowBar").style.display = 'block';
      setTimeout('document.getElementById("content_body").style.top = "0px"',500);
   }
   else {
      document.getElementById("ShadowBar").style.display = 'none';
      document.getElementById("content_body").style.top = "50px";
   }
}

full_screen = function(URL) {
   //window.open(URL,"","fullscreen,scrollbars");
   var width = screen.width;
   var height = screen.height;
   win = window.open(URL, 'epesi', 'fullscreen=yes, scrollbars, menubar=no, toolbar=no, location=no, directories=no, resizable=yes, status=no, left=0, top=0, width=' + width + ', height=' + height);
   win.focus();
}
