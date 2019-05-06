utils_attachment_password = function(label,label_button,id,reload) {
    var elem = jq('#note_value_'+id);
    elem.html('<div>'+label+'<input type="password" id="attachment_pass_'+id+'" name="pass_'+id+'" style="max-width:200px"></input><input class="button" style="max-width:200px;height:auto;" type="button" value="'+label_button+'" id="attachment_submit_pass_'+id+'"/></div>');
    jq('#attachment_submit_pass_'+id).click(function() { utils_attachment_submit_password(id,reload); });
    jq('#attachment_pass_'+id).keypress(function (e) {  if (e.which == 13) { utils_attachment_submit_password(id,reload);e.preventDefault(); }});
}

function utils_attachment_submit_password(id,reload) {
    var pass = jq('#attachment_pass_'+id).val();
    if (pass!=null && pass!='') {
      new Ajax.Request("modules/Utils/Attachment/check_decrypt.php", {
        method: "post",
        parameters:{
            cid: Epesi.client_id,
            id: id,
            pass: pass
        },
        onSuccess:function(t) {
            result = t.responseText.evalJSON();
            if(typeof result.error != "undefined") return alert(result.error);
            if(reload) {
                _chj("","","queue");
            } else {
                Event.fire(document,'e:loading');
                if(typeof result.js != "undefined") {
                    eval(result.js);
                }
                $("note_value_"+id).innerHTML = result.note;
                Event.fire(document,'e:load');
            }
        }
      });
    }
    return false;
}