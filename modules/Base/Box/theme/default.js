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
   if($('EmptyDiv').style.display == 'none') {
      $('EmptyDiv').show();
      document.getElementById("ShadowBar").style.display = 'none';
      //document.getElementById("CRM_Calendar__filter").style.top = '149px';
   }
   else {
      setTimeout('$(\'EmptyDiv\').hide()',500);
      document.getElementById("ShadowBar").style.display = 'block';
      //document.getElementById("CRM_Calendar__filter").style.top = '74px';
   }
}

base_box_roll_search_login_bar = function() {
   if($('search-login-bar').style.display == 'none') {
      $('search-login-bar').style.display = 'block';
      $('login-search-td').style.width = '297px';
      $('quick-logout').style.display = 'none';
      $('module-indicator').style.width = '287px';
   }
   else {
      $('search-login-bar').style.display = 'none';
      $('login-search-td').style.width = '10px';
      $('module-indicator').style.width = '263px';
      $('quick-logout').style.display = 'block';
   }
}
