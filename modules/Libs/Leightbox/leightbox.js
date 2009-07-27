/*
Created By: Chris Campbell
Website: http://particletree.com
Date: 2/1/2006

Adapted By: Simon de Haan
Website: http://blog.eight.nl
Date: 21/2/2006

Adapted for epesi by: Paul Bukowski
Date: 01/06/2007

Inspired by the lightbox implementation found at http://www.huddletogether.com/projects/lightbox/
And the lightbox gone wild by ParticleTree at http://particletree.com/features/lightbox-gone-wild/

*/

/*-------------------------------GLOBAL VARIABLES------------------------------------*/

var detect = navigator.userAgent.toLowerCase();
var OS,browser,version,total,thestring;

/*-----------------------------------------------------------------------------------------------*/

//Browser detect script origionally created by Peter Paul Koch at http://www.quirksmode.org/

function getBrowserInfo() {
	if (checkIt('konqueror')) {
		browser = "Konqueror";
		OS = "Linux";
	}
	else if (checkIt('safari')) browser 	= "Safari"
	else if (checkIt('omniweb')) browser 	= "OmniWeb"
	else if (checkIt('opera')) browser 		= "Opera"
	else if (checkIt('webtv')) browser 		= "WebTV";
	else if (checkIt('icab')) browser 		= "iCab"
	else if (checkIt('msie')) browser 		= "Internet Explorer"
	else if (!checkIt('compatible')) {
		browser = "Netscape Navigator"
		version = detect.charAt(8);
	}
	else browser = "An unknown browser";

	if (!version) version = detect.charAt(place + thestring.length);

	if (!OS) {
		if (checkIt('linux')) OS 		= "Linux";
		else if (checkIt('x11')) OS 	= "Unix";
		else if (checkIt('mac')) OS 	= "Mac"
		else if (checkIt('win')) OS 	= "Windows"
		else OS 								= "an unknown operating system";
	}
}

function checkIt(string) {
	place = detect.indexOf(string) + 1;
	thestring = string;
	return place;
}

/*-----------------------------------------------------------------------------------------------*/

var leightbox = Class.create();

leightbox.prototype = {

	yPos : 0,

	initialize: function(ctrl) {
		this.content = ctrl.rel;
		Event.observe(ctrl, 'click', this.activate.bindAsEventListener(this), false);
		ctrl.onclick = function(){return false;};
	},

	// Turn everything on - mainly the IE fixes
	activate: function(){
		if (browser == 'Internet Explorer'){
			this.getScroll();
			this.prepareIE('100%', 'hidden');
			this.setScroll(0,0);
			this.hideSelects('hidden');
		}
		this.displayLeightbox("block");
	},

	// Ie requires height to 100% and overflow hidden or else you can scroll down past the leightbox
	prepareIE: function(height, overflow){
		bod = document.getElementsByTagName('body')[0];
		bod.style.height = height;
		bod.style.overflow = overflow;

		htm = document.getElementsByTagName('html')[0];
		htm.style.height = height;
		htm.style.overflow = overflow;
	},

	// In IE, select elements hover on top of the leightbox
	hideSelects: function(visibility){
		selects = document.getElementsByTagName('select');
		for(i = 0; i < selects.length; i++) {
			selects[i].style.visibility = visibility;
		}
	},

	// Taken from leightbox implementation found at http://www.huddletogether.com/projects/lightbox/
	getScroll: function(){
		if (self.pageYOffset) {
			this.yPos = self.pageYOffset;
		} else if (document.documentElement && document.documentElement.scrollTop){
			this.yPos = document.documentElement.scrollTop;
		} else if (document.body) {
			this.yPos = document.body.scrollTop;
		}
	},

	setScroll: function(x, y){
		window.scrollTo(x, y);
	},

	displayLeightbox: function(display){
		var c = $(this.content);
		var co = $('leightbox_overlay');
		var ccont = $('leightbox_container');
		if(display == 'none') {
		    var tag = $(this.content+'__tag');
		    if(tag) {
			tag.parentNode.insertBefore(c,tag);
			tag.parentNode.removeChild(tag);
		    } else {
		    	c.id = this.content+"__bak";
			var c2 = $(this.content);
			if(c2) c2.parentNode.removeChild(c2);
		    	c.id = this.content;
		    }
		} else {
		    var tag = document.createElement('div');
		    tag.id = this.content+'__tag';
		    c.parentNode.insertBefore(tag,c);
		    ccont.appendChild(c);
		    if(navigator.appName.indexOf('Explorer') != -1 ) {
			co.style.position="absolute";
			co.style.height = (document.documentElement.clientHeight < document.body.clientHeight ? document.documentElement.clientHeight : document.body.clientHeight) + 'px';
			c.style.position="absolute";
			c.style.top = (document.documentElement.scrollTop + document.documentElement.clientHeight/4) + 'px';
			c.style.left = (document.documentElement.scrollLeft + document.documentElement.clientWidth/6) + 'px';
			c.style.height = (document.documentElement.clientHeight/2) + 'px';
			c.style.width = (document.documentElement.clientWidth/1.5) + 'px';
		    }
		}
		co.style.display = display;
		c.style.display = display;
		if(display != 'none') this.actions();
	},

	// Search through new links within the lightbox, and attach click event
	actions: function(){
		lbActions = document.getElementsByClassName('lbAction');

		for(i = 0; i < lbActions.length; i++) {
			Event.observe(lbActions[i], 'click', this[lbActions[i].rel].bindAsEventListener(this), false);
			lbActions[i].onclick = function(){return false;};
		}

	},

	// Example of creating your own functionality once lightbox is initiated
	deactivate: function(){
		if (browser == "Internet Explorer"){
			this.setScroll(0,this.yPos);
			this.prepareIE("auto", "auto");
			this.hideSelects("visible");
		}

		this.displayLeightbox("none");
	}
}

/*-----------------------------------------------------------------------------------------------*/

var leightboxes = Array();
var leightbox_to_activate = '';

// Add in markup necessary to make this work. Basically two divs:
// Overlay holds the shadow
// Lightbox is the centered square that the content is put into.
function addLeightboxMarkup() {
	bod 				= document.getElementsByTagName('body')[0];

	leightbox_overlay 			= document.createElement('div');
	leightbox_overlay.style.display = 'none';
	leightbox_overlay.id			= 'leightbox_overlay';
	leightbox_overlay.className		= 'leightbox_overlay';
	bod.appendChild(leightbox_overlay);

	leightbox_container 			= document.createElement('div');
	leightbox_container.id			= 'leightbox_container';
	bod.appendChild(leightbox_container);
}

function leightbox_deactivate(name) {
for(i=0;i<leightboxes.length;i++)if(leightboxes[i].content==name){leightboxes[i].deactivate();break;}
}

function leightbox_activate(name) {
leightbox_to_activate = name;
}

addLeightboxMarkup();
getBrowserInfo();

document.observe("e:load", function() {
	lbox = document.getElementsByClassName('lbOn');
	for(i = 0; i < leightboxes.length; i++)
		delete(leightboxes[i]);
	for(i = 0; i < lbox.length; i++) {
		leightboxes[i] = new leightbox(lbox[i]);
		if (leightbox_to_activate==lbox[i].rel) {
			leightboxes[i].activate();
			leightbox_to_activate='';
		}
	}
});
