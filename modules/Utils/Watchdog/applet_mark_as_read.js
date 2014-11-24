watchdog_applet_mark_as_read = function(key) {
	var rows = document.getElementsByName("watchdog_table_row_"+key);
	for (i=0; i<rows.length; i++)
		rows[i].style.display="none";
	jq.ajax({
		type: 'POST',
		url: 'modules/Utils/Watchdog/mark_as_read.php', 
		data:{
			key: key,
			cid: Epesi.client_id
		}
	});
}