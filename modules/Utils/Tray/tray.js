function Utils_Tray__resize() {
	if (typeof Utils_Tray__trays!=='function') return;
	var trays=Utils_Tray__trays();

	jq.each(trays, function(id, slots) {
		if (id=='title') return;
		jq('#'+id+' tr:not(:first)').remove();
		jq('#'+id+' tr:first').children().remove();
		var maxWidth=jq('#'+id).closest('.Utils_Tray__group_table').width();
		
		var slotWidth = 62;
		var width = 0;
		jq.each(slots, function(i, html) {	
			width += slotWidth;		
			if (width >= maxWidth) {
				width = slotWidth;
				jq('#'+id+' tr:last').after('<tr></tr>');
			}
			jq('#'+id+' tr:last').append(html);			
		});
	});
}
