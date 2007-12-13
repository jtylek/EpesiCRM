function event_menu(event_id) {
    Effect.toggle('event_menu_' + event_id, 'appear', {duration:0.3});
    /*
    if(document.getElementById("event_menu_" + event_id).style.display == 'none') {
        // document.getElementById("event_grab").style.display = 'none';
        // document.getElementById("event_time").style.display = 'none';
        // document.getElementById("event_title").style.display = 'none';
        document.getElementById("event_menu_" + event_id).style.display = 'block';
    }
    else {
        document.getElementById("event_menu_" + event_id).style.display = 'none';
        // document.getElementById("event_grab").style.display = 'block';
        // document.getElementById("event_time").style.display = 'block';
        // document.getElementById("event_title").style.display = 'block';
    }
    */
}
