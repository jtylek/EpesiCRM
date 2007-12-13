function event_menu() {
    //Effect.toggle(document.getElementById("event_menu"), 'slide', {duration:0.3});
    if(document.getElementById("event_menu").style.display == 'none') {
        document.getElementById("event_title").style.display = 'none';
        document.getElementById("event_menu").style.display = 'block';
    }
    else {
        document.getElementById("event_menu").style.display = 'none';
        document.getElementById("event_title").style.display = 'block';
    }
}
