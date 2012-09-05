var searchTimeOut;
function setDelayOnSearch(){
	if(searchTimeOut != undefined){
		clearTimeout(searchTimeOut);
	}	
	searchTimeOut = setTimeout(getRecords, 1000);
}

function getRecords(){
	var txtVal = document.getElementById('query_text').value;
	new Ajax.Updater('tableID', 'modules/Applets/QuickSearch/getresult.php',
								{ method: 'get', parameters: {q:txtVal}});
}
