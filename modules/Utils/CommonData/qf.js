var Utils_CommonData = function(id,cd_root,clear_str,prev) {
	if(clear_str!='') {
		eval('var clear = '+clear_str);
		alert(clear);
		$H(clear).each(function(x) {
			alert(x[0]+' '+x[1]);
		});
	}
};
