additional_info_roll = function(theme_dir) {
   Effect.toggle('AdditionalInfo', 'slide', {duration:0.3});
   document.getElementById("AdditionalInfo").display = 'block';

   var x = theme_dir + '/Utils_Tasks__roll-';
   if(document.getElementById("AdditionalInfoImg").src.indexOf(x + 'down.png') >= 0)
      document.getElementById("AdditionalInfoImg").src = x + 'up.png';
   else
      document.getElementById("AdditionalInfoImg").src = x + 'down.png';
}
