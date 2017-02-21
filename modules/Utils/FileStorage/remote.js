utils_filestorage_get_remote_link = function(url) {
    jq.ajax(url, {
        method: 'post',
        success: function(t) {
            prompt('Url to this file (valid for 1 week)',t);
        },
        error: function(xhr,status,t) {
            alert('Failure ('+status+')');
            Epesi.text(t,'error_box','p');
        }
    });
};