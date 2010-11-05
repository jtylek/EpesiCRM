check_for_new_version = function() {
	if ($("epesi_new_version")) {
		if ($("epesi_new_version").done==true) return;
		$("epesi_new_version").done = true;
		new Ajax.Request("modules/Base/Box/check_for_new_version.php", {
			method: "post",
			parameters:{
				cid: Epesi.client_id
			},
			onSuccess:function(t) {
				$("epesi_new_version").innerHTML = t.responseText;
			}
		});
	}
}
