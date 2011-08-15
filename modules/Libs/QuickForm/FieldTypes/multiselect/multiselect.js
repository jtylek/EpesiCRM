ms_remove_selected = function(myName, list_sep){
	var tolist = document.getElementsByName(myName+"to[]")[0];
	var fromlist = document.getElementsByName(myName+"from[]")[0];
	var list_result = "";
	var k = 0;
	var i = 0;
	while (k!=tolist.options.length) {
		if (tolist.options[k].selected) {
			while (i!=fromlist.options.length && fromlist.options[i].value<tolist.options[k].value) 
				i++;
			jj = fromlist.length;
			fromlist.options[jj] = new Option();
			for( j = jj; j > i; j-- ) {
				fromlist.options[j].text = fromlist.options[j-1].text;
				fromlist.options[j].value = fromlist.options[j-1].value;
			}
			fromlist.options[i].value = tolist.options[k].value;
			fromlist.options[i].text = tolist.options[k].text;
		} else {
			list_result += list_sep+tolist.options[k].value;
		}
		k++;
	}
	for(i = (tolist.length-1); i >= 0; i--) {
		if(tolist.options[i].selected == true) {
			tolist.options[i] = null;
		}
	}
	document.getElementsByName(myName)[0].value=list_result;
};

ms_add_selected = function(myName, list_sep){ 
	var tolist = document.getElementsByName(myName+"to[]")[0];
	var fromlist = document.getElementsByName(myName+"from[]")[0];
	var list_result = "";
	var k = 0;
	var i = 0;
	while (k!=fromlist.length) {
		if (fromlist.options[k].selected) {
			while(i < tolist.length && tolist.options[i].value<fromlist.options[k].value) 
				i++;
			jj = tolist.length;
			tolist.options[jj] = new Option();
			for( j = jj; j > i; j-- ) {
				tolist.options[j].value = tolist.options[j-1].value;
				tolist.options[j].text = tolist.options[j-1].text;
			}
			tolist.options[i].value = fromlist.options[k].value;
			tolist.options[i].text = fromlist.options[k].text;
		}
		k++;
	}
	for(i = (fromlist.length-1); i >= 0; i--) {
		if(fromlist.options[i].selected == true) {
			fromlist.options[i] = null;
		}
	}
	k = 0;
	while (k!=tolist.length) { 
		list_result += list_sep+tolist.options[k].value; 
		k++; 
	}
	document.getElementsByName(myName)[0].value=list_result; 
};

ms_remove_all = function(myName, list_sep){ 
	var tolist = document.getElementsByName(myName+"to[]")[0];
	var fromlist = document.getElementsByName(myName+"from[]")[0];
	var list_result = "";
	var k = 0;
	var i = 0;
	while (k!=tolist.options.length) {
		while (i!=fromlist.options.length && fromlist.options[i].value<tolist.options[k].value) 
			i++;
		jj = fromlist.length;
		fromlist.options[jj] = new Option();
		for( j = jj; j > i; j-- ) {
			fromlist.options[j].text = fromlist.options[j-1].text;
			fromlist.options[j].value = fromlist.options[j-1].value;
		}
		fromlist.options[i].value = tolist.options[k].value;
		fromlist.options[i].text = tolist.options[k].text;
		k++;
	}
	for(i = (tolist.length-1); i >= 0; i--) {
		tolist.options[i] = null;
	}
	document.getElementsByName(myName)[0].value=list_result;
};

ms_add_all = function(myName, list_sep){ 
	var tolist = document.getElementsByName(myName+"to[]")[0];
	var fromlist = document.getElementsByName(myName+"from[]")[0];
	var k = 0;
	var i = 0;
	var list_result = "";
	while (k!=fromlist.length) {
		while(i < tolist.length && tolist.options[i].value<fromlist.options[k].value) 
			i++;
		jj = tolist.length;
		tolist.options[jj] = new Option();
		for( j = jj; j > i; j-- ) {
			tolist.options[j].value = tolist.options[j-1].value;
			tolist.options[j].text = tolist.options[j-1].text;
		}
		tolist.options[i].value = fromlist.options[k].value;
		tolist.options[i].text = fromlist.options[k].text;
		k++;
	}
	for(i = (fromlist.length-1); i >= 0; i--) {
		fromlist.options[i] = null;
	}
	k = 0;
	while (k!=tolist.length) { 
		list_result += list_sep+tolist.options[k].value; 
		k++; 
	}
	document.getElementsByName(myName)[0].value=list_result; 
};
