var charts = {};
var charts_hib = {};

// Same e:loading/e:load lifecycle this app's AJAX-push page-replacement model
// requires for any JS widget that owns real DOM state - see
// modules/Libs/Quill/qu.js's own comment for the full rationale (that file's
// e:submit_form/edit-state-preserving dance doesn't apply here: a report
// chart is a one-shot render straight from PHP-computed data, not an
// editable field with in-progress user content to carry across a
// destroy/recreate). e:loading just needs every outgoing chart destroyed so
// Chart.js drops its own resize-observer/event listeners on a canvas that's
// about to be removed, rather than leaking them.
jQuery(document).on('e:loading', function() {
	for (var key in charts) {
		if (charts[key]) charts[key].destroy();
		delete charts[key];
	}
});

jQuery(document).on('e:load', function() {
	for (var key in charts_hib) {
		var config = charts_hib[key];
		(function(key, config) {
			var canvas = document.getElementById(key);
			if (canvas && !charts[key]) {
				charts[key] = new Chart(canvas.getContext('2d'), config);
			}
		})(key, config);
		delete charts_hib[key];
	}
});
