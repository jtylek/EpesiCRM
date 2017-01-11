function event_menu(event_id) {
	var menu = jq('#event_menu_' + event_id);
	var ev = jq('#utils_calendar_event:'+event_id);
	if(menu.parent().attr('id')=='Utils_Calendar__event' || menu.parent().attr('id')=='Utils_Calendar__event_day') {
		menu.css('position', 'absolute');
		menu.css('zIndex', 21);
	}
	var ev_pos = ev.position();
	menu.css({top:ev_pos.top,left:20+ev_pos.left});

        if(menu.is(':hidden')) menu.fadeIn();
        else menu.fadeOut();
}
