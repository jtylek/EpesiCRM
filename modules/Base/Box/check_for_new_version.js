check_for_new_version = function() {
    var v = jq("#epesi_new_version");
	if (v.length>0) {
		if (v.attr("done")=="1") return;
		v.attr("done","1");
		jq.post("modules/Base/Box/check_for_new_version.php", {
				cid: Epesi.client_id
			},
			function(t) {
				v.html(t);
			}
		);
	}
}
