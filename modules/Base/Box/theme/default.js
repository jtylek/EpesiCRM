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
      document.getElementById("gray_transparent").style.display = 'block';
      //document.getElementById("CRM_Calendar__filter").style.top = '149px';
   }
   else {
      setTimeout('$(\'EmptyDiv\').hide()',500);
      document.getElementById("ShadowBar").style.display = 'block';
      document.getElementById("gray_transparent").style.display = 'none';
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

function show_hide_clock() {
   var s1 = document.getElementById("digitalclock").style;
   var s2 = document.getElementById("clock_td").style;
   if(s1.display == 'none') {
      s1.display = 'block';
      s2.width = '200px';
   }
   else {
      s1.display = 'none';
      s2.width = '0px';
   }
}


function calctime() {

   var weekday=new Array(7);
   weekday[0]="Sunday";
   weekday[1]="Monday";
   weekday[2]="Tuesday";
   weekday[3]="Wednesday";
   weekday[4]="Thursday";
   weekday[5]="Friday";
   weekday[6]="Saturday";

   var currenttime = new Date();
   var hours = currenttime.getHours();
   var minutes = currenttime.getMinutes();
   var seconds = currenttime.getSeconds();
   var day = currenttime.getDay();
   var date = currenttime.getDate();
   var month = currenttime.getMonth();
   var year = currenttime.getFullYear();
   var timesuffix = "AM";

   if(hours > 11) {
      timesuffix = "PM";
      hours = hours - 12;
   }
   if(hours == 0) {
      hours = 12;
   }
   if(hours < 10) {
      hours = "0" + hours;
   }
   if(minutes < 10) {
      minutes = "0" + minutes;
   }
   if(seconds < 10) {
      seconds = "0" + seconds;
   }
   month++;
   if(month < 10) {
      month = "0" + month;
   }
   if(date < 10) {
      date = "0" + date;
   }

   var clocklocation = document.getElementById("digitalclock");
    clocklocation.innerHTML = hours + ":" + minutes + ":" + seconds + " " + timesuffix + "&nbsp;&nbsp;&nbsp;" + weekday[day] + " " + month + "/" + date + "/" + year;
   setTimeout("calctime()", 1000);
}

calctime();
