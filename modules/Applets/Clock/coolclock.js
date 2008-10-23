/*
CoolClock by Simon Baird (simon dot baird at gmail dot com)
Version 1.0.6 (08-Jul-2008)
See http://simonbaird.com/coolclock/

Copyright (c) Simon Baird 2006-2008

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

Redistributions of source code must retain the above copyright notice, this
list of conditions and the following disclaimer.

Redistributions in binary form must reproduce the above copyright notice, this
list of conditions and the following disclaimer in the documentation and/or other
materials provided with the distribution.

Neither the name of the Simon Baird nor the names of other contributors may be
used to endorse or promote products derived from this software without specific
prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY
EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT
SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED
TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR
BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH
DAMAGE.
*/

window.CoolClock = function(canvasId,displayRadius,skinId,showSecondHand,gmtOffset) {
	return this.init(canvasId,displayRadius,skinId,showSecondHand,gmtOffset);
}

CoolClock.findAndCreateClocks = function() {
	var canvases = document.getElementsByTagName("canvas");
	for (var i=0;i<canvases.length;i++) {
		var fields = canvases[i].className.split(" ")[0].split(":");
		if (fields[0] == "CoolClock") {
			new CoolClock(canvases[i].id,fields[2],fields[1],fields[3]!="noSeconds",fields[4]);
		}
	}
}

// borrowed from behaviour.js
// actually doesn't work right unless it's at the end of html document
// hence can't have a body onload
// this is a bug. FIXME
// maybe have a setTimeout hack??
CoolClock.addLoadEvent = function(func){
	var oldonload = window.onload;
	if (typeof window.onload != 'function')
		window.onload = func;
	else
		window.onload = function() {
			oldonload();
			func();
		}
}

CoolClock.config = {
	clockTracker: {},
	tickDelay: 1000,
	longTickDelay: 15000,
	defaultRadius: 85,
	renderRadius: 100,
	defaultSkin: "swissRail",
	skins:	{
		// more skins in moreskins.js
		// try making your own...

	swissRail: {
		outerBorder: { lineWidth: 1, radius:95, color: "black", alpha: 1 },
		smallIndicator: { lineWidth: 2, startAt: 89, endAt: 93, color: "black", alpha: 1 },
		largeIndicator: { lineWidth: 4, startAt: 80, endAt: 93, color: "black", alpha: 1 },
		hourHand: { lineWidth: 8, startAt: -15, endAt: 50, color: "black", alpha: 1 },
		minuteHand: { lineWidth: 7, startAt: -15, endAt: 75, color: "black", alpha: 1 },
		secondHand: { lineWidth: 1, startAt: -20, endAt: 85, color: "red", alpha: 1 },
		secondDecoration: { lineWidth: 1, startAt: 70, radius: 4, fillColor: "red", color: "red", alpha: 1 }
	},

	chunkySwiss: {
		outerBorder: { lineWidth: 4, radius:97, color: "black", alpha: 1 },
		smallIndicator: { lineWidth: 4, startAt: 89, endAt: 93, color: "black", alpha: 1 },
		largeIndicator: { lineWidth: 8, startAt: 80, endAt: 93, color: "black", alpha: 1 },
		hourHand: { lineWidth: 12, startAt: -15, endAt: 60, color: "black", alpha: 1 },
		minuteHand: { lineWidth: 10, startAt: -15, endAt: 85, color: "black", alpha: 1 },
		secondHand: { lineWidth: 4, startAt: -20, endAt: 85, color: "red", alpha: 1 },
		secondDecoration: { lineWidth: 2, startAt: 70, radius: 8, fillColor: "red", color: "red", alpha: 1 }
	},

	chunkySwissOnBlack: {
		outerBorder: { lineWidth: 4, radius:97, color: "white", alpha: 1 },
		smallIndicator: { lineWidth: 4, startAt: 89, endAt: 93, color: "white", alpha: 1 },
		largeIndicator: { lineWidth: 8, startAt: 80, endAt: 93, color: "white", alpha: 1 },
		hourHand: { lineWidth: 12, startAt: -15, endAt: 60, color: "white", alpha: 1 },
		minuteHand: { lineWidth: 10, startAt: -15, endAt: 85, color: "white", alpha: 1 },
		secondHand: { lineWidth: 4, startAt: -20, endAt: 85, color: "red", alpha: 1 },
		secondDecoration: { lineWidth: 2, startAt: 70, radius: 8, fillColor: "red", color: "red", alpha: 1 }
	},

	fancy: {
		outerBorder: { lineWidth: 5, radius:95, color: "green", alpha: 0.7 },
		smallIndicator: { lineWidth: 1, startAt: 80, endAt: 93, color: "black", alpha: 0.4 },
		largeIndicator: { lineWidth: 1, startAt: 30, endAt: 93, color: "black", alpha: 0.5 },
		hourHand: { lineWidth: 8, startAt: -15, endAt: 50, color: "blue", alpha: 0.7 },
		minuteHand: { lineWidth: 7, startAt: -15, endAt: 92, color: "red", alpha: 0.7 },
		secondHand: { lineWidth: 10, startAt: 80, endAt: 85, color: "blue", alpha: 0.3 },
		secondDecoration: { lineWidth: 1, startAt: 30, radius: 50, fillColor: "blue", color: "red", alpha: 0.15 }
	},

	machine: {
		outerBorder: { lineWidth: 60, radius:55, color: "#dd6655", alpha: 1 },
		smallIndicator: { lineWidth: 4, startAt: 80, endAt: 95, color: "white", alpha: 1 },
		largeIndicator: { lineWidth: 14, startAt: 77, endAt: 92, color: "#dd6655", alpha: 1 },
		hourHand: { lineWidth: 18, startAt: -15, endAt: 40, color: "white", alpha: 1 },
		minuteHand: { lineWidth: 14, startAt: 24, endAt: 100, color: "#771100", alpha: 0.5 },
		secondHand: { lineWidth: 3, startAt: 22, endAt: 83, color: "green", alpha: 0 },
		secondDecoration: { lineWidth: 1, startAt: 52, radius: 26, fillColor: "#ffcccc", color: "red", alpha: 0.5 }
	},

	// these three created by bonstio from http://bonstio.net
	classic/*was gIG*/: {
		outerBorder: { lineWidth: 185, radius:1, color: "#E5ECF9", alpha: 1 },
		smallIndicator: { lineWidth: 2, startAt: 89, endAt: 94, color: "#3366CC", alpha: 1 },
		largeIndicator: { lineWidth: 4, startAt: 83, endAt: 94, color: "#3366CC", alpha: 1 },
		hourHand: { lineWidth: 5, startAt: 0, endAt: 60, color: "black", alpha: 1 },
		minuteHand: { lineWidth: 4, startAt: 0, endAt: 80, color: "black", alpha: 1 },
		secondHand: { lineWidth: 1, startAt: -20, endAt: 85, color: "red", alpha: .85 },
		secondDecoration: { lineWidth: 3, startAt: 0, radius: 2, fillColor: "black", color: "black", alpha: 1 }
	},

	modern/*was gIG2*/: {
		outerBorder: { lineWidth: 185, radius:1, color: "#E5ECF9", alpha: 1 },
		smallIndicator: { lineWidth: 5, startAt: 88, endAt: 94, color: "#3366CC", alpha: 1 },
		largeIndicator: { lineWidth: 5, startAt: 88, endAt: 94, color: "#3366CC", alpha: 1 },
		hourHand: { lineWidth: 8, startAt: 0, endAt: 60, color: "black", alpha: 1 },
		minuteHand: { lineWidth: 8, startAt: 0, endAt: 80, color: "black", alpha: 1 },
		secondHand: { lineWidth: 5, startAt: 80, endAt: 85, color: "red", alpha: .85 },
		secondDecoration: { lineWidth: 3, startAt: 0, radius: 4, fillColor: "black", color: "black", alpha: 1 }
	},

	simple/*was gIG3*/: {
		outerBorder: { lineWidth: 185, radius:1, color: "#E5ECF9", alpha: 1 },
		smallIndicator: { lineWidth: 10, startAt: 90, endAt: 94, color: "#3366CC", alpha: 1 },
		largeIndicator: { lineWidth: 10, startAt: 90, endAt: 94, color: "#3366CC", alpha: 1 },
		hourHand: { lineWidth: 8, startAt: 0, endAt: 60, color: "black", alpha: 1 },
		minuteHand: { lineWidth: 8, startAt: 0, endAt: 80, color: "black", alpha: 1 },
		secondHand: { lineWidth: 5, startAt: 80, endAt: 85, color: "red", alpha: .85 },
		secondDecoration: { lineWidth: 3, startAt: 0, radius: 4, fillColor: "black", color: "black", alpha: 1 }
	},

	// these two by securephp
	securephp: {
		outerBorder: { lineWidth: 100, radius:0.45, color: "#669900", alpha: 0.3 },
		smallIndicator: { lineWidth: 2, startAt: 80, endAt:90 , color: "green", alpha: 1 },
		largeIndicator: { lineWidth: 8.5, startAt: 20, endAt:40 , color: "green", alpha: 0.4 },
		hourHand: { lineWidth: 3, startAt: 0, endAt: 60, color: "black", alpha: 1 },
		minuteHand: { lineWidth: 2, startAt: 0, endAt: 75, color: "black", alpha: 1 },
		secondHand: { lineWidth: 1, startAt: -10, endAt: 80, color: "blue", alpha: 0.8 },
		secondDecoration: { lineWidth: 1, startAt: 70, radius: 4, fillColor: "blue", color: "red", alpha: 1 }
	},

	Tes2: {
		outerBorder: { lineWidth: 4, radius:95, color: "black", alpha: 0.5 },
		smallIndicator: { lineWidth: 1, startAt: 10, endAt:50 , color: "#66CCFF", alpha: 1 },
		largeIndicator: { lineWidth: 8.5, startAt: 60, endAt: 70, color: "#6699FF", alpha: 1 },
		hourHand: { lineWidth: 5, startAt: -15, endAt: 60, color: "black", alpha: 0.7 },
		minuteHand: { lineWidth: 3, startAt: -25, endAt: 75, color: "black", alpha: 0.7 },
		secondHand: { lineWidth: 1.5, startAt: -20, endAt: 88, color: "red", alpha: 1 },
		secondDecoration: { lineWidth: 1, startAt: 20, radius: 4, fillColor: "blue", color: "red", alpha: 1 }
	},


	Lev: {
	outerBorder: { lineWidth:10, radius:95, color: "#CCFF33", alpha: 0.65 },
	smallIndicator: { lineWidth: 5, startAt: 84, endAt: 90, color: "#996600", alpha: 1 },
	largeIndicator: { lineWidth: 40, startAt: 25, endAt: 95, color: "#336600", alpha: 0.55 },
	hourHand: { lineWidth: 4, startAt: 0, endAt: 65, color: "black", alpha: 0.9 },
	minuteHand: { lineWidth: 3, startAt: 0, endAt: 80, color: "black", alpha: 0.85 },
	secondHand: { lineWidth: 1, startAt: 0, endAt: 85, color: "black", alpha: 1 },
	secondDecoration: { lineWidth: 2, startAt: 5, radius: 10, fillColor: "black", color: "black", alpha: 1 }
	},

	Sand: {
	outerBorder: { lineWidth:1, radius:70, color: "black", alpha: 0.5 },
	smallIndicator: { lineWidth: 3, startAt: 50, endAt: 70, color: "#0066FF", alpha: 0.5 },
	largeIndicator: { lineWidth: 200, startAt: 80, endAt: 95, color: "#996600", alpha: 0.75 },
	hourHand: { lineWidth: 4, startAt: 0, endAt: 65, color: "black", alpha: 0.9 },
	minuteHand: { lineWidth: 3, startAt: 0, endAt: 80, color: "black", alpha: 0.85 },
	secondHand: { lineWidth: 1, startAt: 0, endAt: 85, color: "black", alpha: 1 },
	secondDecoration: { lineWidth: 2, startAt: 5, radius: 10, fillColor: "black", color: "black", alpha: 1 }
	},

	Sun: {
	outerBorder: { lineWidth:100, radius:140, color: "#99FFFF", alpha: 0.2 },
	smallIndicator: { lineWidth: 300, startAt: 50, endAt: 70, color: "black", alpha: 0.1 },
	largeIndicator: { lineWidth: 5, startAt: 80, endAt: 95, color: "black", alpha: 0.65 },
	hourHand: { lineWidth: 4, startAt: 0, endAt: 65, color: "black", alpha: 0.9 },
	minuteHand: { lineWidth: 3, startAt: 0, endAt: 80, color: "black", alpha: 0.85 },
	secondHand: { lineWidth: 1, startAt: 0, endAt: 90, color: "black", alpha: 1 },
	secondDecoration: { lineWidth: 2, startAt: 5, radius: 10, fillColor: "black", color: "black", alpha: 1 }
	},

	Tor: {
	outerBorder: { lineWidth:10, radius:88, color: "#996600", alpha: 0.9 },
	smallIndicator: { lineWidth: 6, startAt: -10, endAt: 73, color: "green", alpha: 0.3 },
	largeIndicator: { lineWidth: 6, startAt: 73, endAt: 100, color: "black", alpha: 0.65 },
	hourHand: { lineWidth: 4, startAt: 0, endAt: 65, color: "black", alpha: 1 },
	minuteHand: { lineWidth: 3, startAt: 0, endAt: 80, color: "black", alpha: 1 },
	secondHand: { lineWidth: 1, startAt: -73, endAt: 73, color: "black", alpha: 0.8 },
	secondDecoration: { lineWidth: 2, startAt: 5, radius: 10, fillColor: "black", color: "black", alpha: 1 }
	},

	Cold: {
	outerBorder: { lineWidth:15, radius:90, color: "black", alpha: 0.3 },
	smallIndicator: { lineWidth: 15, startAt: -10, endAt: 95, color: "blue", alpha: 0.1 },
	largeIndicator: { lineWidth: 3, startAt: 80, endAt: 95, color: "blue", alpha: 0.65 },
	hourHand: { lineWidth: 4, startAt: 0, endAt: 65, color: "black", alpha: 1 },
	minuteHand: { lineWidth: 3, startAt: 0, endAt: 80, color: "black", alpha: 1 },
	secondHand: { lineWidth: 1, startAt: 0, endAt: 85, color: "black", alpha: 0.8 },
	secondDecoration: { lineWidth: 5, startAt: 30, radius: 10, fillColor: "black", color: "black", alpha: 1 }
	},

	Babosa: {
	outerBorder: { lineWidth: 100, radius:25, color: "blue", alpha: 0.25 },
	smallIndicator: { lineWidth: 3, startAt: 90, endAt: 95, color: "#3366CC", alpha: 1 },
	largeIndicator: { lineWidth: 4, startAt: 75, endAt: 95, color: "#3366CC", alpha: 1 },
	hourHand: { lineWidth: 4, startAt: 0, endAt: 60, color: "black", alpha: 1 },
	minuteHand: { lineWidth: 3, startAt: 0, endAt: 85, color: "black", alpha: 1 },
	secondHand: { lineWidth: 12, startAt: 75, endAt: 90, color: "red", alpha: 0.8 },
	secondDecoration: { lineWidth: 3, startAt: 0, radius: 4, fillColor: "black", color: "black", alpha: 1 }
	},

	Tumb: {
	outerBorder: { lineWidth:105, radius:5, color: "green", alpha: 0.4 },
	smallIndicator: { lineWidth: 1, startAt: 93, endAt: 98, color: "green", alpha: 1 },
	largeIndicator: { lineWidth: 50, startAt: 0, endAt: 89, color: "red", alpha: 0.14 },
	hourHand: { lineWidth: 4, startAt: 0, endAt: 65, color: "black", alpha: 1 },
	minuteHand: { lineWidth: 3, startAt: 0, endAt: 80, color: "black", alpha: 1 },
	secondHand: { lineWidth: 1, startAt: 0, endAt: 85, color: "black", alpha: 0.8 },
	secondDecoration: { lineWidth: 5, startAt: 50, radius: 90, fillColor: "black", color: "black", alpha: 0.05 }
	},

	Stone: {
	outerBorder: { lineWidth:15, radius:80, color: "#339933", alpha: 0.5 },
	smallIndicator: { lineWidth: 2, startAt: 70, endAt: 90, color: "#FF3300", alpha: 0.7 },
	largeIndicator: { lineWidth: 15, startAt: 0, endAt: 29, color: "#FF6600", alpha: 0.3 },
	hourHand: { lineWidth: 4, startAt: 0, endAt: 65, color: "black", alpha: 1 },
	minuteHand: { lineWidth: 3, startAt: 0, endAt: 75, color: "black", alpha: 1 },
	secondHand: { lineWidth: 1, startAt: 0, endAt: 85, color: "black", alpha: 0.8 },
	secondDecoration: { lineWidth: 5, startAt: 50, radius: 90, fillColor: "black", color: "black", alpha: 0.05 }
	},

	Disc: {
	outerBorder: { lineWidth:105, radius:1, color: "#666600", alpha: 0.2 },
	smallIndicator: { lineWidth: 1, startAt: 58, endAt: 95, color: "#669900", alpha: 0.8 },
	largeIndicator: { lineWidth: 6, startAt: 25, endAt: 35, color: "#666600", alpha: 1 },
	hourHand: { lineWidth: 4, startAt: 0, endAt: 65, color: "black", alpha: 1 },
	minuteHand: { lineWidth: 3, startAt: 0, endAt: 75, color: "black", alpha: 1 },
	secondHand: { lineWidth: 1, startAt: -75, endAt: 75, color: "#99CC00", alpha: 0.8 },
	secondDecoration: { lineWidth: 5, startAt: 50, radius: 90, fillColor: "#00FF00", color: "green", alpha: 0.05 }
	}

	}
};

CoolClock.prototype = {
	init: function(canvasId,displayRadius,skinId,showSecondHand,gmtOffset) {
		this.canvasId = canvasId;
		this.displayRadius = displayRadius || CoolClock.config.defaultRadius;
		this.skinId = skinId || CoolClock.config.defaultSkin;
		this.showSecondHand = typeof showSecondHand == "boolean" ? showSecondHand : true;
		this.tickDelay = CoolClock.config[ this.showSecondHand ? "tickDelay" : "longTickDelay"];

		this.canvas = document.getElementById(canvasId);

		this.canvas.setAttribute("width",this.displayRadius*2);
		this.canvas.setAttribute("height",this.displayRadius*2);

		this.canvas.style.width = this.displayRadius*2 + "px";
		this.canvas.style.height = this.displayRadius*2 + "px";

		this.renderRadius = CoolClock.config.renderRadius; 

		this.scale = this.displayRadius / this.renderRadius;
		this.ctx = this.canvas.getContext("2d");
		this.ctx.scale(this.scale,this.scale);

		this.gmtOffset = gmtOffset != null ? parseFloat(gmtOffset) : gmtOffset;

		CoolClock.config.clockTracker[canvasId] = this;
		this.tick();
		return this;
	},

	fullCircle: function(skin) {
		this.fullCircleAt(this.renderRadius,this.renderRadius,skin);
	},

	fullCircleAt: function(x,y,skin) {
		with (this.ctx) {
			save();
			globalAlpha = skin.alpha;
			lineWidth = skin.lineWidth;
			if (!document.all)
				beginPath();
			if (document.all)
				// excanvas doesn't scale line width so we will do it here
				lineWidth = lineWidth * this.scale;
			arc(x, y, skin.radius, 0, 2*Math.PI, false);
			if (document.all)
				// excanvas doesn't close the circle so let's color in the gap
				arc(x, y, skin.radius, -0.1, 0.1, false);
			if (skin.fillColor) {
				fillStyle = skin.fillColor
				fill();
			}
			else {
				// XXX why not stroke and fill
				strokeStyle = skin.color;
				stroke();
			}
			restore();
		}
	},

	radialLineAtAngle: function(angleFraction,skin) {
		with (this.ctx) {
			save();
			translate(this.renderRadius,this.renderRadius);
			rotate(Math.PI * (2 * angleFraction - 0.5));
			globalAlpha = skin.alpha;
			strokeStyle = skin.color;
			lineWidth = skin.lineWidth;
			if (document.all)
				// excanvas doesn't scale line width so we will do it here
				lineWidth = lineWidth * this.scale;
			if (skin.radius) {
				this.fullCircleAt(skin.startAt,0,skin)
			}
			else {
				beginPath();
				moveTo(skin.startAt,0)
				lineTo(skin.endAt,0);
				stroke();
			}
			restore();
		}
	},

	render: function(hour,min,sec) {
		var skin = CoolClock.config.skins[this.skinId];
		this.ctx.clearRect(0,0,this.renderRadius*2,this.renderRadius*2);

		this.fullCircle(skin.outerBorder);

		for (var i=0;i<60;i++)
			this.radialLineAtAngle(i/60,skin[ i%5 ? "smallIndicator" : "largeIndicator"]);
				
		this.radialLineAtAngle((hour+min/60)/12,skin.hourHand);
		this.radialLineAtAngle((min+sec/60)/60,skin.minuteHand);
		if (this.showSecondHand) {
			this.radialLineAtAngle(sec/60,skin.secondHand);
			if (!document.all)
				// decoration doesn't render right in IE so lets turn it off
				this.radialLineAtAngle(sec/60,skin.secondDecoration);
		}
	},


	nextTick: function() {
		setTimeout("CoolClock.config.clockTracker['"+this.canvasId+"'].tick()",this.tickDelay);
	},

	stillHere: function() {
		return document.getElementById(this.canvasId) != null;
	},

	refreshDisplay: function() {
		var now = new Date();
		if (this.gmtOffset != null) {
			// use GMT + gmtOffset
			var offsetNow = new Date(now.valueOf() + (this.gmtOffset * 1000 * 60 * 60));
			this.render(offsetNow.getUTCHours(),offsetNow.getUTCMinutes(),offsetNow.getUTCSeconds());
		}
		else {
			// use local time
			this.render(now.getHours(),now.getMinutes(),now.getSeconds());
		}
	},

	tick: function() {
		if (this.stillHere()) {
			this.refreshDisplay()
			this.nextTick();
		}
	}
}

CoolClock.addLoadEvent(CoolClock.findAndCreateClocks);

