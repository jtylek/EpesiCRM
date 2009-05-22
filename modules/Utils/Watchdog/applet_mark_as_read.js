watchdog_applet_mark_as_read = function(key) {
	var rows = document.getElementsByName("watchdog_table_row_"+key);
	for (i=0; i<rows.length; i++)
		rows[i].style.display="none";
	new Ajax.Request('modules/Utils/Watchdog/mark_as_read.php', {
		method: 'post',
		parameters:{
			key: key,
			cid: Epesi.client_id
		}
	});
}