filters_roll = function() {
   //Effect.toggle('filters', 'slide', {duration:0.3});
   if($('filters_box').style.display == 'none') {
      $('filters_box').show();
      $('filters').show();
   }
   else {
      //setTimeout('$(\'filters_box\').hide()', 500);
      $('filters').hide();
      $('filters_box').hide();
   }
}
