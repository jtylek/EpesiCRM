var saja = {
	procOn:0,
	indicator:'sajaStatus',
	updateIndicator: function(){
		var s = document.getElementById(saja.indicator);
		if(s)
			s.style.visibility = saja.procOn ? 'visible' : 'hidden';
	},
	updateIndicatorText: function(text){
		var s = document.getElementById(saja.indicator);
		s.innerHTML = text;
	},
	getReq: function(){
		if(window.XMLHttpRequest)
			try{return new XMLHttpRequest();}catch(e){}
		else if(window.ActiveXObject)
			try{return new ActiveXObject("Microsoft.XMLHTTP");}catch(e){
				try{return new ActiveXObject("Msxml2.XMLHTTP");}catch(e){}}
	},
	run: function(php, id, act, property, session_id, request_id){
		saja.procOn++;
		var requestString = 'req=' + (SAJA_HTTP_KEY ? encodeURIComponent(saja.rc4(SAJA_HTTP_KEY, php)) : php) + '<!SAJA!>' + session_id + '<!SAJA!>' + (request_id || '');
		requestString += '&rnd=' + Math.random();
		saja.SendRequest(id, act, property, requestString);
	},
	SendRequest: function(id, act, property, requestString){
		var req = saja.getReq();
		saja.updateIndicator();
		req.open('POST',SAJA_PATH + 'saja.process.php',true);
		req.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
		req.send(requestString);
		req.onreadystatechange=function(){
			if (req.readyState==4 && req.status==200){	
				actions = req.responseText.split('<saja_split>');
				if(id)
					saja.Put(actions[0], id, act, property)
				if(actions[1])
					eval(actions[1]);
				saja.procOn--;
				saja.updateIndicator();
			}
		}
	},
	Put: function(content, id, act, property){
		if(!id) return;
		if(property){
			try{var ob = document.getElementById(id);}catch(e){}
			if(act=='p')
				ob[property] = content + ob[property];
			else if(act=='a')
				ob[property] += content;
			else if(property.split('.')[0]=='style')
				ob.style[property.split('.')[1]] = content;
			else if(ob)
				ob[property] = content;
		}
		else
			window[id] = content;
	},
	Get: function(id, property){
		if(!property)
			return saja.phpSerialize(id);
		return saja.phpSerialize(document.getElementById(id)[property]);
	},
	phpSerialize: function(v){
		var ret = '';
		if(typeof(v)=='object'){
			var len = v.length;
			if(len){
				ret = 'a:' + len + ':{';
				for(var i=0; i<len; i++){
					ret += 'i:' + i +';'
					ret += 's:' + (v[i]+'').length + ':"' + encodeURIComponent(v[i]) +'";'
				}
				ret += '}';
			} else {
				len = 0;
				for(var i in v) len++;
				ret = 'a:' + len + ':{';
				for(var i in v){
					ret += 's:' + i.length + ':"' + i +'";'
					ret += 's:' + v[i].length + ':"' + encodeURIComponent(v[i]) +'";'
				}
				ret += '}';
			}
		} else {
			ret += 's:' + (v+'').length + ':"' + encodeURIComponent(v) +'";'
		}
		return ret;
	},
	SetStyle: function(ob, styleString){
		document.getElementById(ob).style.cssText = styleString;	
	},
	rc4: function(pwd, data){
		var pwd_length = pwd.length;
		var data_length = data.length;
		var key = []; var box = [];
		var cipher = '';
		var k;
		for (var i=0; i < 256; i++){
			key[i] = pwd.charCodeAt(i % pwd_length);
			box[i] = i;
		}
		for (var j = i = 0; i < 256; i++){
			j = (j + box[i] + key[i]) % 256;
			tmp = box[i];
			box[i] = box[j];
			box[j] = tmp;
		}
		for (var a = j = i = 0; i < data_length; i++){
			a = (a + 1) % 256;
			j = (j + box[a]) % 256;
			tmp = box[a];
			box[a] = box[j];
			box[j] = tmp;
			k = box[((box[a] + box[j]) % 256)];
			cipher += String.fromCharCode(data.charCodeAt(i) ^ k);
		}
		return cipher;
	},
	getForm: function(f){
		var vals = {};
		for(var i=0; i<f.length; i++)
			if(f[i].id)
				vals[f[i].id] = f[i].value;
		return vals;
	}
}