utils_filestorage_get_remote_link = function(url) {
    jq.ajax(url, {
        method: 'post',
        success: function(t) {
            var msg = 'Url to this file (valid for 1 week):\n' + t;
            if (typeof epesi_alert === 'function') epesi_alert(msg); else prompt(msg, t);
        },
        error: function(xhr,status,t) {
            var msg = 'Failure ('+status+')';
            if (typeof epesi_alert === 'function') epesi_alert(msg); else alert(msg);
            Epesi.text(t,'error_box','p');
        }
    });
};