var searchTimeOut;
var criteria = "";var isFound = false;
function setDelayOnSearch(crit){
	if(searchTimeOut != undefined){
		clearTimeout(searchTimeOut);
	}	
	criteria = crit;
	searchTimeOut = setTimeout(getRecords, 500);
}

function getRecords(){
	var txtVal = document.getElementById('query_text_'+criteria).value;
	var search_id = document.getElementById('search_id').value;
	new Ajax.Updater('tableID_'+criteria, 'modules/Applets/QuickSearch/getresult.php',
								{ method: 'get', parameters: {q:txtVal, crit:criteria}});
}

function getRecordFields(recordset, caption){
	new Ajax.Updater('select_field__from', 'modules/Applets/QuickSearch/getfields.php',
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
					}else{
						var format = document.getElementById('result_format');
						format.value += '{' + optionSelected.value + '} ,';
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

function call_js(){
	var elem = $('recordsets__to').options;
	for(el = 0; el < elem.length; el++){
		if(elem[el].value.indexOf('[A]') == -1){
			try{
				elem[el].value = elem[el].value + '[A]';
				var recordset = elem[el].value.substr(0,(elem[el].value.length - 3));
				//if(!checkRecordsetExist(recordset))
					getRecordFields(recordset, elem[el].text);
			}
			catch(error){}
		}
	}
}

function call_js_add_field(mode){
	var elemFields = $('select_field__to').options;
	var resultFormat = $('result_format');
	var holder = resultFormat.value;		
	for(el = 0; el < elemFields.length; el++){
		if(elemFields[el].value.indexOf('[A]') == -1){
			var field = elemFields[el].value;						var recordset = elemFields[el].value.substr(0, elemFields[el].value.indexOf(':') + 1);
			if(mode == 'add'){
				elemFields[el].value = elemFields[el].value + '[A]';
				field = elemFields[el].value.substr(0,(elemFields[el].value.length - 3));															if(resultFormat.value.indexOf(recordset) != -1){					if(isFound){
						resultFormat.value += '[%' + field + '%] ';						isFound = true;					}							console.log("found "  + isFound);				}					else{					isFound = false;					if(isFound == false){						if(resultFormat.value == "")							resultFormat.value += '[%' + field + '%]';						else							resultFormat.value += '\n[%' + field + '%]';						isFound = true;						}						console.log("not found " + isFound);				}	
			}
			else{				
				if(elemFields[el].value.indexOf('[A]') == -1){
					elemFields[el].value = elemFields[el].value + '[A]';
					resultFormat.value += '[%' + field + '%] ';
				}	
				
			}
		}
	}
}

function call_js_remove_recordset(){

}

function call_js_remove_fields(){

}

function changeAddedRecordset(id){
	var select = $(id).options;
	for(b=0; b < select.length; b++){
		select[b].value += "[A]";	
	}
}
