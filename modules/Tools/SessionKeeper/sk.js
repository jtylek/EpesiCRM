var SessionKeeper = {
    func: function() {
        jq.ajax('modules/Tools/SessionKeeper/sk.php');
        SessionKeeper.time-=SessionKeeper.interval;
        if(SessionKeeper.time<=0) {
            clearInterval(SessionKeeper.id);
            jq.ajax('modules/Tools/SessionKeeper/logout.php');
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
        SessionKeeper.id = setInterval(SessionKeeper.func,SessionKeeper.interval*1000);
    }
};
jq(document).on("e:load", function() {
    SessionKeeper.load();
});
