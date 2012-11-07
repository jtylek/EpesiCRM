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

function getRecordFields(recordset, caption){
	new Ajax.Updater('fieldsfrom', 'modules/Applets/QuickSearch/getfields.php',
								{ method: 'get', parameters: {tbName:recordset, tbCaption:caption}, insertion: Insertion.Bottom } );
}

function removeRecordFields(recordset){
}

function addToList(selectFrom, selectTo, callAjax){
	if(document.getElementById(selectFrom)){
		var selected = document.getElementById(selectFrom);
		var copyto = document.getElementById(selectTo);
		for(x=0; x <= selected.length; x++){
			try{
				if(selected[x].selected){
					selected[x].selected = true;	
					var optionSelected = selected[x];
					copyto.appendChild(optionSelected);
					if(callAjax == true){
						getRecordFields(optionSelected.value, optionSelected.text);
					}
				}
			}
			catch(err){
			
			}
		}
		
	}
}

function removeFromList(selectFrom, selectTo){
	if(document.getElementById(selectFrom)){
		var selected = document.getElementById(selectTo);
		var returnto = document.getElementById(selectFrom);
		for(x=0; x <= selected.length; x++){
			try{
				if(selected[x].selected){
					var optionRemove = selected[x];
					optionRemove.selected = false;
					returnto.appendChild(optionRemove);
					removeFieldOnListFrom(optionRemove.text, 'fieldsfrom');
					removeFieldOnListTo(optionRemove.text, 'fieldsto');
				}
			}
			catch(err){}
		}
		selectAllFromList(selectFrom, selectTo);
	}
}

function selectAllFromList(selectFrom, selectTo){
	var selected = document.getElementById(selectTo);
	var returnto = document.getElementById(selectFrom);
	for(x=0; x <= selected.length; x++){
		selected[x].selected = true;
	}
}

function removeFromListFields(selectFrom, selectTo){
	if(document.getElementById(selectFrom)){
		var selected = document.getElementById(selectTo);
		var returnto = document.getElementById(selectFrom);
		for(x=0; x <= selected.length; x++){
			try{
				if(selected[x].selected){
					var optionRemove = selected[x];
					returnto.appendChild(optionRemove);
				}
			}
			catch(err){}
		}
		selectAllFromList(selectFrom, selectTo);
	}
}

function removeFieldOnListFrom(remField, listFieldId){
	if(remField != null || remField != ''){
		var removeList = document.getElementById(listFieldId);
		for(iOp = 0; iOp < removeList.length; iOp++){
			var removeOpt = removeList.options[iOp];
			if(removeOpt.text.indexOf(remField) != -1){
				removeList.remove(iOp);
				removeFieldOnListFrom(remField, listFieldId);
				break;
			}
		}
	}
}

function removeFieldOnListTo(remField, listFieldId){
	if(remField != null || remField != ''){
		var removeList = document.getElementById(listFieldId);
		for(iOp = 0; iOp < removeList.length; iOp++){
			var removeOpt = removeList.options[iOp];
			if(removeOpt.text.indexOf(remField) != -1){
				removeList.remove(iOp);
				removeFieldOnListTo(remField, listFieldId);
				break;
			}
		}
	}
}
