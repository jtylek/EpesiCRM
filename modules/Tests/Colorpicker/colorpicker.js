// Vanilla-JS replacement for the original Prototype/script.aculo.us
// Control.Slider-based implementation, which stopped working outright once
// Prototype/Scriptaculous were removed app-wide (2026-08-06, see
// AI-shared/legacy-js-migration.md) - Control.Slider/$R() no longer exist,
// and the bare $('color_red') calls resolved to jQuery's tag-name selector
// instead of an ID lookup (the exact bug shape CLAUDE.md's Rendering section
// warns about), so every element access here already silently returned an
// empty collection. Uses document.getElementById() throughout for that
// reason, not jQuery's $().
(function () {
	var colors = { red: 0, green: 0, blue: 0 };

	function clamp(v, min, max) {
		return Math.max(min, Math.min(max, v));
	}

	function updateReadout() {
		document.getElementById('color_red').textContent = colors.red;
		document.getElementById('color_green').textContent = colors.green;
		document.getElementById('color_blue').textContent = colors.blue;
		var hex = '#' + ['red', 'green', 'blue'].map(function (name) {
			var h = colors[name].toString(16);
			return h.length < 2 ? '0' + h : h;
		}).join('');
		document.getElementById('color_html').textContent = hex;
		document.getElementById('color_preview').style.background = hex;
	}

	function setHandlePosition(name, value) {
		var track = document.getElementById('track_' + name);
		var handle = document.getElementById('handle_' + name);
		if (!track || !handle) return;
		var usable = track.clientHeight - handle.offsetHeight;
		var ratio = value / 255;
		handle.style.top = (usable - ratio * usable) + 'px';
	}

	// Value increases towards the top of the track, matching the original
	// alignY:1 Control.Slider config.
	function valueFromClientY(track, handle, clientY) {
		var rect = track.getBoundingClientRect();
		var usable = rect.height - handle.offsetHeight;
		var y = clamp(clientY - rect.top - handle.offsetHeight / 2, 0, usable);
		return Math.round((1 - y / usable) * 255);
	}

	function initSlider(name) {
		var track = document.getElementById('track_' + name);
		var handle = document.getElementById('handle_' + name);
		if (!track || !handle) return;

		function apply(clientY) {
			colors[name] = valueFromClientY(track, handle, clientY);
			setHandlePosition(name, colors[name]);
			updateReadout();
		}
		function clientYOf(e) {
			return e.touches ? e.touches[0].clientY : e.clientY;
		}
		function onMove(e) {
			apply(clientYOf(e));
		}
		function onUp() {
			document.removeEventListener('mousemove', onMove);
			document.removeEventListener('mouseup', onUp);
			document.removeEventListener('touchmove', onMove);
			document.removeEventListener('touchend', onUp);
		}
		function onDown(e) {
			e.preventDefault();
			apply(clientYOf(e));
			document.addEventListener('mousemove', onMove);
			document.addEventListener('mouseup', onUp);
			document.addEventListener('touchmove', onMove, { passive: false });
			document.addEventListener('touchend', onUp);
		}

		handle.addEventListener('mousedown', onDown);
		handle.addEventListener('touchstart', onDown, { passive: false });
		track.addEventListener('mousedown', function (e) {
			if (e.target !== handle) onDown(e);
		});

		setHandlePosition(name, colors[name]);
	}

	['red', 'green', 'blue'].forEach(initSlider);
	updateReadout();
})();
