function event_menu(event_id) {
	var menu = $('event_menu_' + event_id);
	var ev = $('utils_calendar_event:'+event_id);
	if(menu.parentNode.id=='Utils_Calendar__event' || menu.parentNode.id=='Utils_Calendar__event_day') {
		menu.style.position = 'absolute';
		menu.style.zIndex = 21;
//		ev.parentNode.appendChild(menu);
	}
	menu.clonePosition(ev,{setHeight: false, setWidth: false, offsetLeft: 10});

	Effect.toggle(menu, 'appear', {duration:0.3});
}
