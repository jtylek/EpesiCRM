rpicker_fs_init = function(id,checked,path){
	checkbox = $('leightbox_rpicker__'+id);
	if(checked==1) checkbox.checked = true;
		else checkbox.checked = false;
	checkbox.observe('click', function(e){
		new Ajax.Request('modules/Utils/RecordBrowser/RecordPickerFS/select.php', {
			method: 'post',
			parameters:{
				select: this.checked,
				row: id,
				path: Object.toJSON(path),
				cid: Epesi.client_id
			}
		});
	});
}
