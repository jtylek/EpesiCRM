rpicker_fs_init = function(id,checked,path){
	checkbox = jq('#leightbox_rpicker__'+id);
	if(checked==1) checkbox.prop("checked",true);
		else checkbox.prop("checked", false);
	checkbox.click(function(e){
		jq.ajax('modules/Utils/RecordBrowser/RecordPickerFS/select.php', {
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
