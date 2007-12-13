function event_menu() {
    // Effect.toggle(document.getElementById("event_menu"), 'slide', {duration:0.3});
    if(document.getElementById("event_menu").style.display == 'none') {
        // document.getElementById("event_grab").style.display = 'none';
        // document.getElementById("event_time").style.display = 'none';
        // document.getElementById("event_title").style.display = 'none';
        document.getElementById("event_menu").style.display = 'block';
    }
    else {
        document.getElementById("event_menu").style.display = 'none';
        // document.getElementById("event_grab").style.display = 'block';
        // document.getElementById("event_time").style.display = 'block';
        // document.getElementById("event_title").style.display = 'block';
    }
}
