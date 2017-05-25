function quick_jump_letters(id) {
   var j=jq('#quick_jump_letters_' + id);
   if(!j.is(':hidden')) j.fadeOut();
   else j.fadeIn();
}

