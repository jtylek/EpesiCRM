var SessionKeeper = {
    func: function(pe) {
        jQuery.ajax('modules/Tools/SessionKeeper/sk.php', {method: 'post'});
        SessionKeeper.time-=SessionKeeper.interval;
        if(SessionKeeper.time<=0) {
            pe.stop();
            jQuery.ajax('modules/Tools/SessionKeeper/logout.php', {method: 'post', dataType: 'script'});
        }
    },
    interval: 10,
    time: null,
    maxtime: null,
    id: null,
    load: function() {
        if(SessionKeeper.maxtime==null) return;
        SessionKeeper.time = SessionKeeper.maxtime;
        if(SessionKeeper.id!=null) clearInterval(SessionKeeper.id);
        // Prototype's PeriodicalExecuter took its frequency in seconds and
        // passed itself (with a .stop() method) to the callback - replicated
        // minimally here since this is the only user of it in the codebase.
        SessionKeeper.id = setInterval(function() {
            SessionKeeper.func({stop: function() { clearInterval(SessionKeeper.id); }});
        }, SessionKeeper.interval*1000);
    }
};
jQuery(document).on("e:load", function() {
    SessionKeeper.load();
});
