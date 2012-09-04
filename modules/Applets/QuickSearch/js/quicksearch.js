function sayHello(str){
	var txtVal = document.getElementById('query_text');
	var id = setInterval(new Ajax.Updater('tableID', 'modules/Applets/QuickSearch/getresult.php',
								{ method: 'get', parameters: {q:txtVal.value}} ), 1000);
}

function sayHi(str){
	alert('Hi There');
}

function sayHello2(str){
	var txtVal = document.getElementById('query_text');
	var id = setInterval(sayHi(), 10000);
}

function sayHello3(str){
	var txtVal = document.getElementById('query_text');
	var id = setInterval(new Ajax.Updater('tableID', 'modules/Applets/QuickSearch/getresult.php',
								{ method: 'get', parameters: {q:txtVal.value}} ), 1000);
}