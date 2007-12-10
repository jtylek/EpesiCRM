Utils_Calendar = {
add_event:function(id,title) {
	var dest = $(id);
	if(!dest) return;
	dest.innerHTML += title+'<hr>';
}
}