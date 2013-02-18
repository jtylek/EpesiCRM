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
	new Ajax.Updater('select_field_to_search__from', 'modules/Applets/QuickSearch/getfields.php',
								{ method: 'get', parameters: {tbName:recordset, tbCaption:caption}, insertion: Insertion.Bottom } );
								
	new Ajax.Updater('search_field_for_identifier__from', 'modules/Applets/QuickSearch/getfields.php',
								{ method: 'get', parameters: {tbName:recordset, tbCaption:caption}, insertion: Insertion.Bottom } );								
								
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
		for(x=0; x <= returnto.length; x++){
			try{
				if(returnto[x].value.indexOf('[A]') != -1){
					var optionRemove = returnto[x];
					removeFieldOnListFrom(optionRemove.text, 'select_field_to_search__from');
					removeFieldOnListTo(optionRemove.text, 'select_field_to_search__to');
					removeFieldOnListFrom(optionRemove.text, 'search_field_for_identifier__from');
					removeFieldOnListTo(optionRemove.text, 'search_field_for_identifier__to');					
					
					removeOnResultFormat(optionRemove.value);
					returnto[x].value = returnto[x].value.substr(0, (returnto[x].value.length - 3));
					console.log(returnto[x].value);
				}
			}
			catch(err){}
		}
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
function removeOnResultFormat(keyword){

	/*
		/x5B/x25./x25/x5D/g
	*/
	var resultFormatText = document.getElementById('result_format');
	if(resultFormatText.value != ""){
		var strField = "[%"+ keyword.substr(0, (keyword.length - 3)) +":.*?%]";	
		console.log('strField ' + strField);	
		resultFormatText.value = resultFormatText.value.replace(strField, "");	
		console.log('Removing ' + resultFormatText.value);
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
	var elemFields = $('select_field_to_search__to').options;
	var resultFormat = $('result_format');
	var holder = resultFormat.value;		
	for(el = 0; el < elemFields.length; el++){
		if(elemFields[el].value.indexOf('[A]') == -1){
			var field = elemFields[el].value;						
			var recordset = elemFields[el].value.substr(0, elemFields[el].value.indexOf(':') + 1);
			if(mode == 'add'){
				elemFields[el].value = elemFields[el].value + '[A]';
				field = elemFields[el].value.substr(0,(elemFields[el].value.length - 3));															
				if(resultFormat.value.indexOf(recordset) != -1){	
					appendSearchTag(field);
					/*if(isFound){
						resultFormat.value += '{' + field + '} ';						
						isFound = true;					
						}							
						console.log("found "  + isFound);		
						*/	
				}					
				else{	
					appendSearchTag(field);
					//isFound = false;					
					//if(isFound == false){						
					//	if(resultFormat.value == "")							
						//	resultFormat.value += '<search>{' + field + '}</search>';						
					//	else							
					//		resultFormat.value += '{' + field + '}';						
					//		isFound = true;				
						//var sampleTag = document.getElementsByTagName("search");
						//alert(sampleTag.value);		
					//}						
					console.log("not found " + isFound);				
				}	
			}
			else{				
				if(elemFields[el].value.indexOf('[A]') == -1){
					//elemFields[el].value = elemFields[el].value + '[A]';
					//resultFormat.value += '{' + field + '} ';
					appendSearchTag(field);
				}	
				
			}
		}
	}
}

function call_js_add_result(mode){
	var elemFields = $('search_field_for_identifier__to').options;
	var resultFormat = $('result_format');
	var holder = resultFormat.value;		
	for(el = 0; el < elemFields.length; el++){
		if(elemFields[el].value.indexOf('[A]') == -1){
			var field = elemFields[el].value;						
			var recordset = elemFields[el].value.substr(0, elemFields[el].value.indexOf(':') + 1);
			if(mode == 'add'){
				elemFields[el].value = elemFields[el].value + '[A]';
				field = elemFields[el].value.substr(0,(elemFields[el].value.length - 3));															
				if(resultFormat.value.indexOf(recordset) != -1){	
					appendIdentifierTag(field);
					/*if(isFound){
						resultFormat.value += '{' + field + '} ';						
						isFound = true;					
						}							
						console.log("found "  + isFound);		
						*/	
				}					
				else{	
					appendIdentifierTag(field);
					//isFound = false;					
					//if(isFound == false){						
					//	if(resultFormat.value == "")							
						//	resultFormat.value += '<search>{' + field + '}</search>';						
					//	else							
					//		resultFormat.value += '{' + field + '}';						
					//		isFound = true;				
						//var sampleTag = document.getElementsByTagName("search");
						//alert(sampleTag.value);		
					//}						
					console.log("not found " + isFound);				
				}	
			}
			else{				
				if(elemFields[el].value.indexOf('[A]') == -1){
					//elemFields[el].value = elemFields[el].value + '[A]';
					//resultFormat.value += '{' + field + '} ';
					appendSearchTag(field);
				}	
				
			}
		}
	}
}

var searchTagValue = "";
var identifierTagValue = "";
function appendSearchTag(searchVal){
	var openSearchTag = "<search>", closeSearchTag = "</search>";
	
	var searchFormat = $('search_format');	
	searchTagValue += '{'+searchVal+'}';
	searchFormat.value =  openSearchTag + searchTagValue + closeSearchTag;

}

function appendIdentifierTag(identifierVal){
	var openResultTag = "\n<result>", closeResultTag = "</result>";
	
	var resultFormat = $('result_format');	
	identifierTagValue += '{'+identifierVal+'}';
	resultFormat.value =  openResultTag + identifierTagValue + closeResultTag;
}

function call_js_remove_recordset(){
	removeFromList('recordsets__from', 'recordsets__to');
}

function call_js_remove_fields(){
	console.log('remove fields');
	var resultFormatText = document.getElementById('result_format');
	if(document.getElementById('select_field_to_search__from').length > 0){
		var fieldsList = document.getElementById('select_field_to_search__from');
		for(field = 0; field < fieldsList.length; field++){
			if(fieldsList[field].value.indexOf('[A]') != -1){
				var strField = '[%' + fieldsList[field].value.substr(0, (fieldsList[field].value.length - 3)) + '%]';
				console.log('Fields Found =' +strField)
				resultFormatText.value = resultFormatText.value.replace(strField, "");
				console.log('Replaced ' + resultFormatText.value);
				fieldsList[field].value = fieldsList[field].value.substr(0, (fieldsList[field].length - 3))	
			}
		}
	}
	
	
}

function changeAddedRecordset(id){
	var select = $(id).options;
	for(b=0; b < select.length; b++){
		select[b].value += "[A]";	
	}
}
