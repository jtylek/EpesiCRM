var discarded_messages = {};
var client_messages_frame_id;

function set_client_messages_frame_id(id) {
    client_messages_frame_id = id;
    jq('#'+id+"_hide").click(function() {
        toggle_client_messages_frame();
    });
    jq('#'+id+"_show_discarded").click(function() {
        show_all_client_messages();
    });
    client_messages_add_discard_buttons();
    show_client_messages();
}

function client_messages_add_discard_buttons() {
    var messages_frame = jq('#'+client_messages_frame_id + "_content").get(0);
    var childs = messages_frame.children;
    for(var i = 0; i < childs.length; i++) {
        if(!childs[i].hasClassName("popup_notice"))
            continue;
        var single_messages = childs[i].childElements();
        for(var j = 0; j < single_messages.length; j++) {
            var div = document.createElement("div");
            div.innerHTML = ess_client_messages_discard_label;
            div.addClassName("popup_notice_frame_close_button");
            div.onclick = function() {
                hide_client_message(this.parentNode);
            }
            single_messages[j].appendChild(div)
        }
    }

}

function toggle_client_messages_frame() {
    if(!client_messages_frame_id)
        return;
    var obj = jq('#'+client_messages_frame_id + "_content");
    if(!obj.is(':hidden')) {
        obj.hide();
        jq('#'+client_messages_frame_id+"_hide").html(ess_client_messages_show_button_label);
    } else {
        obj.show();
        jq('#'+client_messages_frame_id+"_hide").html(ess_client_messages_hide_button_label);
    }
    show_client_messages();
}

function hide_client_message(object) {
    if(!object)
        return;
    discarded_messages[object.innerHTML] = 1;
    show_client_messages();
}

function show_all_client_messages() {
    discarded_messages = {};
    show_client_messages();
}

function show_client_messages() {
    if(!client_messages_frame_id)
        return;
    var messages_frame = jq('#'+client_messages_frame_id + "_content");
    var childs = messages_frame.children();
    // hide buttons
    jq('#'+client_messages_frame_id+"_hide").hide();
    jq('#'+client_messages_frame_id+"_show_discarded").hide();
    if(!childs.length)
        return;
        
    var discarded = 0;
    var total_displayed = 0;
    for(var i = 0; i < childs.length; i++) {
        if(!childs[i].hasClass("popup_notice"))
            continue;
        var single_messages = childs[i].children();
        var total = single_messages.length;
        var displayed = 0;
        for(var j = 0; j < total; j++) {
            if(discarded_messages[single_messages[j].html()]) {
                single_messages[j].hide();
                discarded++;
            } else {
                single_messages[j].show();
                displayed++;
                total_displayed++;
            }
        }
        if(!displayed)
            childs[i].hide();
        else
            childs[i].show();
    }
    if(total_displayed)
        jq('#'+client_messages_frame_id+"_hide").show();

    if(messages_frame.visible() && discarded)
        jq('#'+client_messages_frame_id+"_show_discarded").show();
        
}