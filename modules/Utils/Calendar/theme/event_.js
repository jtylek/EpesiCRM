function event_menu(event_id) {
	var menu = document.getElementById('event_menu_' + event_id);
	var ev = document.getElementById('utils_calendar_event:'+event_id);
	if(menu.parentNode.id=='Utils_Calendar__event' || menu.parentNode.id=='Utils_Calendar__event_day') {
		menu.style.position = 'absolute';
		menu.style.zIndex = 21;
//		ev.parentNode.appendChild(menu);
	}
	jQuery(menu).clonePosition(ev,{setHeight: false, setWidth: false, offsetLeft: 20});

	if (menu.style.display === 'none') {
		menu.style.display = '';
		menu.style.opacity = '0';
		menu.style.transition = 'opacity 0.3s';
		requestAnimationFrame(function(){ menu.style.opacity = '1'; });
		setTimeout(function(){ menu.style.transition=''; }, 300);
	} else {
		menu.style.transition = 'opacity 0.3s';
		menu.style.opacity = '0';
		setTimeout(function(){ menu.style.display='none'; menu.style.opacity=''; menu.style.transition=''; }, 300);
	}
}
