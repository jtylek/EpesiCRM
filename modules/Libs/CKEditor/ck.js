var ckeditors = {};
var ckeditors_hib = {};

jq(document).on('e:submit_form', function(e,name) {
    for(key in ckeditors) {
        value = ckeditors[key];
		var textarea = jq('#'+key).get(0);
		if(name==textarea.form.getAttribute("name")) {
			value.destroy();
			jq(textarea).hide();
			delete(ckeditors[key]);
//			alert(textarea.innerHTML);
		}
	}
});

jq(document).on('e:loading', function() {
    for(key in ckeditors) {
//        alert('hib '+key);
        value = ckeditors[key];
		ckeditors_hib[key]=value.config;
		value.destroy();
		jq('#'+key).hide();
		delete(ckeditors[key]);
	}
});

jq(document).on('e:load', function() {
    for(key in ckeditors_hib) {
//      alert('unhib '+key);
        value = ckeditors_hib[key];
		if(document.getElementById(key) && typeof ckeditors[key]=="undefined") {
//			alert(document.getElementById(key).innerHTML);
			ckeditors[key] = CKEDITOR.replace(key,value);
//			alert('replaced');
	    }
		delete(ckeditors_hib[key]);
	}
});

function ckeditor_reload(id) {
	if(document.getElementById(id) && typeof ckeditors[id]!="undefined") {
        var conf = ckeditors[id].config;
        ckeditors[id].destroy();
        ckeditors[id] = CKEDITOR.replace(id,conf)
    }
}

function ckeditor_reload_all() {
    for(id in ckeditors) {
        var ta = document.getElementById(id);
    	if(ta && typeof ckeditors[id]!="undefined") {
            var conf = ckeditors[id].config;
            ckeditors[id].destroy();
            ta.innerHTML = ""+ta.innerHTML+" "; //webkit workaround
            ckeditors[id] = CKEDITOR.replace(id,conf)
        }
	}
}
