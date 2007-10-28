var SessionKeeper = {
	func: function(pe) {
		new Ajax.Request('modules/Tools/SessionKeeper/sk.php');
		this.time-=this.interval;
		if(this.time<0) pe.stop();
	},
	interval: 10,
	time: null,
	maxtime: null,
	id: null,
	load: function() {
		if(this.maxtime==null) return;
		this.time = this.maxtime;
		this.id = new PeriodicalExecuter(this.func,this.interval);
	}
};
document.observe("e:load", function() {
	SessionKeeper.load();
});
