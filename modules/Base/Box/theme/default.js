/**
 * Resize function without multiple trigger
 *
 * Usage:
 * $(window).smartresize(function(){
 *     // code here
 * });
 */
(function($,sr){
    // debouncing function from John Hann
    // http://unscriptable.com/index.php/2009/03/20/debouncing-javascript-methods/
    var debounce = function (func, threshold, execAsap) {
      var timeout;

        return function debounced () {
            var obj = this, args = arguments;
            function delayed () {
                if (!execAsap)
                    func.apply(obj, args);
                timeout = null;
            }

            if (timeout)
                clearTimeout(timeout);
            else if (execAsap)
                func.apply(obj, args);

            timeout = setTimeout(delayed, threshold || 100);
        };
    };

    // smartresize
    jQuery.fn[sr] = function(fn){  return fn ? this.bind('resize', debounce(fn)) : this.trigger(sr); };

})(jQuery,'smartresize');

var setContentHeight = function () {
  var $BODY = $('body'),
  $MENU_TOGGLE = $('#menu_toggle'),
  $SIDEBAR_MENU = $('#sidebar-menu'),
  $SIDEBAR_FOOTER = $('.sidebar-footer'),
  $LEFT_COL = $('.left_col'),
  $RIGHT_COL = $('.right_col'),
  $NAV_MENU = $('.nav_menu'),
  $FOOTER = $('footer');

  // reset height
  $RIGHT_COL.css('min-height', $(window).height());

  var bodyHeight = $BODY.outerHeight(),
    footerHeight = $BODY.hasClass('footer_fixed') ? -10 : $FOOTER.height(),
    leftColHeight = $LEFT_COL.eq(1).height() + $SIDEBAR_FOOTER.height(),
    contentHeight = bodyHeight < leftColHeight ? leftColHeight : bodyHeight;

  // normalize content
  contentHeight -= $NAV_MENU.height() + footerHeight;

  $RIGHT_COL.css('min-height', contentHeight);
};


// recompute content when resizing
$(window).smartresize(function(){
  setContentHeight();
});

// fixed sidebar
if ($.fn.mCustomScrollbar) {
  $('.menu_fixed').mCustomScrollbar({
    autoHideScrollbar: true,
    theme: 'minimal',
    mouseWheel:{ preventDefault: true }
  });
}


$(window).on('e:load',function() {
  var $BODY = $('body'),
  $MENU_TOGGLE = $('#menu_toggle'),
  $SIDEBAR_MENU = $('#sidebar-menu'),
  $SIDEBAR_FOOTER = $('.sidebar-footer'),
  $LEFT_COL = $('.left_col'),
  $RIGHT_COL = $('.right_col'),
  $NAV_MENU = $('.nav_menu'),
  $FOOTER = $('footer');


  var $active_class = $BODY.hasClass('nav-md')?'active':'active-sm';
  $SIDEBAR_MENU.find('li.current-page').removeClass('current-page');
  $SIDEBAR_MENU.find('li.current-page-next').addClass('current-page').removeClass('current-page-next');

  $SIDEBAR_MENU.find('a').off('click').on('click', function(ev) {
      var $li = $(this).parent();
      if (!$li.is('.active') && !$li.is('.expanded')) {
          $SIDEBAR_MENU.find('li.'+$active_class)
              .removeClass($active_class)
              .removeClass('current-page-next')
              .find('ul.child-menu').slideUp()
              .parents('ul.child-menu').slideUp();
          $li.addClass($active_class)
              .addClass('current-page-next')
              .addClass('expanded')
              .parents('ul').slideDown(function() {
              setContentHeight();
          })
              .parent().addClass($active_class);
          $('ul:first', $li).slideDown(function() {
              setContentHeight();
          });
      } else if($li.is('.active.current-page-next')) {
          $li.removeClass('active')
              .removeClass('current-page-next')
              .removeClass('expanded')
              .find('ul:first').slideUp();
      } else if($li.is('.expanded')) {
          $li.removeClass('active')
              .removeClass('expanded')
              .find('ul:first').slideUp();
      }
  });

  // toggle small or large menu
  $MENU_TOGGLE.not('.menu_toggle_active').on('click', function() {

  		if ($BODY.hasClass('nav-md')) {
  			$SIDEBAR_MENU.find('li.active ul').hide();
  			$SIDEBAR_MENU.find('li.active').addClass('active-sm').removeClass('active');

  			$('.search-bar').hide();
  			$('#login-box').hide();
            $('#login-div').hide();
            $('.sidebar-footer.hidden-small').hide();

            $('#hidden-home-div').show();
            
            $('#sidebar-menu').css('margin-top','40px')

  		} else {
  			$SIDEBAR_MENU.find('li.active-sm ul').show();
  			$SIDEBAR_MENU.find('li.active-sm').addClass('active').removeClass('active-sm');

            $('.search-bar').show();
            $('#login-box').show();
            $('#login-div').show();
            $('.sidebar-footer.hidden-small').show();

            $('#hidden-home-div').hide();
            
            $('#sidebar-menu').css('margin-top','0px')
        }

  	$BODY.toggleClass('nav-md nav-sm');

  	setContentHeight();
  }).addClass('menu_toggle_active');

  setContentHeight();

  $('.collapse-link').not('.collapse-link-active').on('click', function() {
      var $BOX_PANEL = $(this).closest('.x_panel'),
          $ICON = $(this).find('i'),
          $BOX_CONTENT = $BOX_PANEL.find('.x_content');

      // fix for some div with hardcoded fix class
      if ($BOX_PANEL.attr('style')) {
          $BOX_CONTENT.slideToggle(200, function(){
              $BOX_PANEL.removeAttr('style');
          });
      } else {
          $BOX_CONTENT.slideToggle(200);
          $BOX_PANEL.css('height', 'auto');
      }

      $ICON.toggleClass('fa-chevron-up fa-chevron-down');
  }).addClass('collapse-link-active');

  $('.close-link').not('.close-link-active').click(function () {
      var $BOX_PANEL = $(this).closest('.x_panel');

      $BOX_PANEL.remove();
  }).addClass('close-link-active');

});
