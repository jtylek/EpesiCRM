filters_roll = function() {

   //<img id="roll" src="" onClick="var x='{$theme_dir}/Utils_RecordBrowser__roll-';if(this.src.indexOf(x+'up.png')>=0)this.src=x+'down.png';else this.src=x+'up.png'; filters_roll();" width="14" height="14" alt="=" border="0"></div>

   //Effect.toggle('filters', 'slide', {duration:0.3});
   if($('filters_box').style.display == 'none') {
      $('filters_box').show();
      $('filters').show();
      $('filters_button_text').innerHTML = 'Hide filters';
      $('filters_button_icon_down').id = 'filters_button_icon_up';
   }
   else {
      //setTimeout('$(\'filters_box\').hide()', 500);
      $('filters').hide();
      $('filters_box').hide();
      $('filters_button_text').innerHTML = 'Show filters';
      $('filters_button_icon_up').id = 'filters_button_icon_down';
   }
}
