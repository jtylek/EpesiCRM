var ckeditors = {};
var ckeditors_hib = {};

// CKEDITOR.replace() (below, in the e:load handler) can return null instead
// of an editor instance - a known CKEditor 4 quirk more likely to surface on
// mobile/touch browsers - with nothing here ever having guarded against it.
// A null landing in ckeditors[key] used to sit there silently until the next
// e:submit_form/e:loading tried to call .destroy() on it and crashed with an
// uncaught TypeError, aborting whatever inline onclick handler triggered
// it - e.g. a RecordBrowser Save button - before it ever got to send its
// request: no error visible (no devtools on a phone), no network activity,
// just a click that appears to do nothing. Reported 2026-08-11 as "tapping
// Save does nothing" on Android Chrome. Guard every read of a tracked
// instance instead of assuming CKEDITOR.replace() always succeeded.
jQuery(document).on('e:submit_form', function(e, name) {
    for(key in ckeditors) {
        value = ckeditors[key];
		var textarea = document.getElementById(key);
		if(textarea && name==textarea.form.getAttribute("name")) {
			if(value) value.destroy();
			jQuery(textarea).hide();
			delete(ckeditors[key]);
		}
	}
});

jQuery(document).on('e:loading', function() {
    for(key in ckeditors) {
        value = ckeditors[key];
		if(value) {
			ckeditors_hib[key]=value.config;
			value.destroy();
		}
		jQuery(document.getElementById(key)).hide();
		delete(ckeditors[key]);
	}
});

jQuery(document).on('e:load', function() {
    for(key in ckeditors_hib) {
        value = ckeditors_hib[key];
		if(document.getElementById(key) && !ckeditors[key]) {
			ckeditors[key] = CKEDITOR.replace(key,value);
	    }
		delete(ckeditors_hib[key]);
	}
});

function ckeditor_reload(id) {
	if(document.getElementById(id) && ckeditors[id]) {
        var conf = ckeditors[id].config;
        ckeditors[id].destroy();
        ckeditors[id] = CKEDITOR.replace(id,conf)
    }
}

function ckeditor_reload_all() {
    for(id in ckeditors) {
        var ta = document.getElementById(id);
    	if(ta && ckeditors[id]) {
            var conf = ckeditors[id].config;
            ckeditors[id].destroy();
            ta.innerHTML = ""+ta.innerHTML+" "; //webkit workaround
            ckeditors[id] = CKEDITOR.replace(id,conf)
        }
	}
}
