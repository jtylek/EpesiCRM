var SessionKeeper = {
    func: function(pe) {
        new Ajax.Request('modules/Tools/SessionKeeper/sk.php');
        SessionKeeper.time-=SessionKeeper.interval;
        if(SessionKeeper.time<=0) {
            pe.stop();
            new Ajax.Request('modules/Tools/SessionKeeper/logout.php');
        }
    },
    interval: 10,
    time: null,
    maxtime: null,
    id: null,
    load: function() {
        if(SessionKeeper.maxtime==null) return;
        SessionKeeper.time = SessionKeeper.maxtime;
        if(SessionKeeper.id!=null) SessionKeeper.id.stop();
        SessionKeeper.id = new PeriodicalExecuter(SessionKeeper.func,SessionKeeper.interval);
    }
};
document.observe("e:load", function() {
    SessionKeeper.load();
});
