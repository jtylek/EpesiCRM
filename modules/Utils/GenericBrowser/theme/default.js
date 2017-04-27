function quick_jump_letters(id) {
   var j=jq('#quick_jump_letters_' + id);
   if(!j.is(':hidden')) j.fadeOut();
   else j.fadeIn();
}

var toggled = 0;


$(document).on('click','button#search-button', function () {
    var width = $('div.nonselectable.clearfix').css('width');
    console.log(width);

    if(toggled == 0) {
        $(this).parent().parent().find('input#search-bar').css({display: 'block', width: '4%'});
        $(this).parent().parent().find('input#search-bar').animate({width: width}, 500, function () {
            $(this).parent().parent().find('input#search-bar').css({width: width});
            toggled++;
        });
    }
    else {
        $(this).parent().parent().find('input#search-bar').animate({width: '4%'}, 500, function () {
            $(this).parent().parent().find('input#search-bar').delay(500).css({display: 'none'});
            toggled--;
        });
    }
});

