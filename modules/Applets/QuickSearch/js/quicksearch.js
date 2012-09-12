var searchTimeOut;
var criteria = "";
function setDelayOnSearch(crit){
	if(searchTimeOut != undefined){
		clearTimeout(searchTimeOut);
	}	
	criteria = crit;
	searchTimeOut = setTimeout(getRecords, 500);
}

function getRecords(){
	var txtVal = document.getElementById('query_text').value;
	new Ajax.Updater('tableID', 'modules/Applets/QuickSearch/getresult.php',
								{ method: 'get', parameters: {q:txtVal, crit:criteria}});
}
