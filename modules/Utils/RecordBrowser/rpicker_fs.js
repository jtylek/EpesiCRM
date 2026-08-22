rpicker_fs_init = function(id,checked,path){
	checkbox = document.getElementById('leightbox_rpicker__'+id);
	if(checked==1) checkbox.checked = true;
		else checkbox.checked = false;
	jQuery(checkbox).on('click', function(e){
		jQuery.ajax('modules/Utils/RecordBrowser/RecordPickerFS/select.php', {
			method: 'post',
			data:{
				select: this.checked,
				row: id,
				path: JSON.stringify(path),
				cid: Epesi.client_id
			}
		});
	});
}
