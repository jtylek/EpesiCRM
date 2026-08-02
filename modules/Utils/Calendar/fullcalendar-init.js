// Glue between Epesi's old-style AJAX-push module system and the vendored
// FullCalendar bundle (libs/fullcalendar-<version>/index.global.min.js). See
// Utils_Calendar::fullcalendar() (PHP side) - a module prints one stable-id
// container div and queues a mount() call in the same body() invocation;
// Epesi.text()'s DOM patches always land before any queued eval_js/
// eval_js_once code runs (include/epesi.php's get_output() ordering), so the
// container is guaranteed to exist by the time this runs.
var EpesiFullCalendar = window.EpesiFullCalendar || (function () {
	var instances = {};

	// Event action attributes (viewAttrs/editAttrs/deleteAttrs in the JSON feed)
	// are verbatim ' href="javascript:void(0)" onClick="_chj(...);" ' fragments -
	// the exact same output Module::create_href()/create_record_href() produce
	// for the legacy grid's own event chips. Parsed via the browser's own HTML
	// parser (a detached <a>) rather than regex, then evaluated as a function
	// body - _chj (Epesi.href, include/epesi.js) is a core global, always
	// loaded before this file.
	function extractOnclick(attrString) {
		if (!attrString) return null;
		var div = document.createElement('div');
		div.innerHTML = '<a ' + attrString + '></a>';
		var a = div.firstElementChild;
		if (!a) return null;
		return a.getAttribute('onclick') || a.getAttribute('onClick');
	}

	function runOnclick(onclick, el) {
		if (!onclick) return;
		try { (new Function(onclick)).call(el); } catch (e) {}
	}

	// FullCalendar's function-based event source - re-fetches whenever the
	// visible range changes (view switch, prev/next/today, initial load).
	// start/end are sent as plain 'Y-m-d' strings, matching what
	// CRM_Calendar_EventCommon::get_all()/every handler's crm_event_get_all()
	// already expect - deliberately not FullCalendar's own ISO-with-offset
	// default, so there is exactly one place (Base_RegionalSettingsCommon::
	// time2reg() on the PHP side) that knows how to convert between them.
	function feed(url) {
		return function (info, success, failure) {
			var sep = url.indexOf('?') >= 0 ? '&' : '?';
			var full = url + sep + 'start=' + info.startStr.slice(0, 10) + '&end=' + info.endStr.slice(0, 10);
			var xhr = new XMLHttpRequest();
			xhr.open('GET', full, true);
			xhr.onload = function () {
				if (xhr.status < 200 || xhr.status >= 300) { failure(new Error('HTTP ' + xhr.status)); return; }
				var data;
				try { data = JSON.parse(xhr.responseText); } catch (e) { failure(e); return; }
				if (!data || !data.ok) { failure(new Error((data && data.error) || 'bad response')); return; }
				// Base_StatusBarCommon::message() (e.g. the "too many events"
				// notice CRM_Calendar_EventCommon::get_all() emits on hitting its
				// cap) is a server-render-cycle API with no client-side JS
				// equivalent to call from a plain fetch - surfaced to the
				// console rather than inventing new status-bar plumbing for
				// what is a rare edge case (100+ events in one visible range).
				if (data.notice) console.warn('[Calendar]', data.notice);
				success(data.events || []);
			};
			xhr.onerror = function () { failure(new Error('network error')); };
			xhr.send();
		};
	}

	// POSTs a drag-move/resize to CRM_CalendarCommon::fullcalendar_update().
	// X-Epesi-Calendar is light CSRF mitigation (forces a preflight, blocks a
	// simple cross-origin form POST) - ajax.php itself carries no CSRF token.
	function write(url, payload, onOk, onFail) {
		var xhr = new XMLHttpRequest();
		xhr.open('POST', url, true);
		xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		xhr.setRequestHeader('X-Epesi-Calendar', '1');
		xhr.onload = function () {
			var data = null;
			try { data = JSON.parse(xhr.responseText); } catch (e) {}
			if (xhr.status >= 200 && xhr.status < 300 && data && data.ok) onOk();
			else onFail();
		};
		xhr.onerror = onFail;
		var body = Object.keys(payload).map(function (k) {
			return encodeURIComponent(k) + '=' + encodeURIComponent(payload[k]);
		}).join('&');
		xhr.send(body);
	}

	// Builds the fullcalendar_update() POST body from a FullCalendar Event's
	// current (already-moved/resized) start/end/allDay. start is sent as a
	// naive 'Y-m-d\THH:mm:ss' local string, never event.start.toISOString()
	// (which is UTC) - the PHP side wraps its own parse in
	// Base_RegionalSettingsCommon::set_tz()/restore_tz() and interprets a
	// naive string as literal wall-clock time in the user's configured
	// regional timezone, matching how every other value this calendar
	// displays was produced (time2reg()) - the browser's own timezone is
	// never consulted, only the naive value.
	function pad2(n) { return (n < 10 ? '0' : '') + n; }
	function naiveLocal(d) {
		return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate()) +
			'T' + pad2(d.getHours()) + ':' + pad2(d.getMinutes()) + ':' + pad2(d.getSeconds());
	}
	function eventToPayload(event) {
		var duration = -1;
		if (!event.allDay && event.end) duration = Math.round((event.end.getTime() - event.start.getTime()) / 1000);
		return { id: event.id, start: naiveLocal(event.start), duration: duration, allDay: event.allDay ? 1 : 0 };
	}

	// Remembers the last view/date this calendar box was showing, so
	// navigating away (e.g. clicking an event) and back doesn't always land
	// back on the user's saved default_view/today - view switching and
	// prev/next/today navigation are entirely client-side (FullCalendar's
	// own toolbar, "no server-side grid math"), so the server has no way to
	// know about them on its own; this is the other half of that. Keyed by
	// mountId (stable per calendar box, see Utils_Calendar::fullcalendar())
	// and scoped to sessionStorage rather than localStorage - "remembered
	// for this browser session", not a sticky cross-session default (that's
	// what the actual default_view SETTING is for). Wrapped in try/catch:
	// sessionStorage can throw in some private-browsing configurations, and
	// losing the memory feature is a fine fallback, a broken calendar isn't.
	function viewStateKey(mountId) { return 'epesi-fc-view:' + mountId; }
	function saveViewState(mountId, view, date) {
		try { sessionStorage.setItem(viewStateKey(mountId), JSON.stringify({ view: view, date: date })); } catch (e) {}
	}
	function loadViewState(mountId) {
		try {
			var raw = sessionStorage.getItem(viewStateKey(mountId));
			return raw ? JSON.parse(raw) : null;
		} catch (e) { return null; }
	}

	// Epesi's content-diffing (Epesi::process()/Epesi::$content) only re-emits
	// a module's mount script when its rendered HTML/JS actually changed, so a
	// re-render of the SAME calendar instance (e.g. after switching CRM
	// Filters "Perspective", which re-runs CRM_Calendar::body() but keeps the
	// same container id) must not blindly construct a second live
	// FullCalendar.Calendar against the same element - it refetches the
	// existing instance instead. mountId must therefore be stable across
	// re-renders (derived from the module's own path on the PHP side, never
	// time()/uniqid()).
	// This runs as one statement inside Epesi's shared per-request append_js
	// blob (many modules' queued scripts joined and eval()'d as a single
	// string - see Epesi::get_output()) - same convention already established
	// in Base_Box's own shell scripts: an uncaught exception here would abort
	// every OTHER queued script that happens to be concatenated after this
	// one in the same response, not just this module's own initialization.
	function mount(mountId, config, feedUrl, writeUrl, newEventTemplate, toggleHoursLabels, suppressViewRestore) {
		try {
			mountUnsafe(mountId, config, feedUrl, writeUrl, newEventTemplate, toggleHoursLabels, suppressViewRestore);
		} catch (e) {
			console.error('EpesiFullCalendar.mount failed:', e);
		}
	}

	function mountUnsafe(mountId, config, feedUrl, writeUrl, newEventTemplate, toggleHoursLabels, suppressViewRestore) {
		var el = document.getElementById(mountId);
		if (!el) return; // container not in the DOM yet/anymore - nothing to do

		var existing = instances[mountId];
		if (existing && existing.el === el) {
			existing.cal.refetchEvents();
			return;
		}
		if (existing && existing.cal) {
			try { existing.cal.destroy(); } catch (e) {}
			delete instances[mountId];
		}

		config = config || {};
		// suppressViewRestore is true for a genuine deep link (Utils_Calendar's
		// 'explicit_navigation' setting) - that must always win over whatever
		// was remembered from before the user navigated away.
		if (!suppressViewRestore) {
			var remembered = loadViewState(mountId);
			if (remembered && remembered.view) {
				config.initialView = remembered.view;
				if (remembered.date) config.initialDate = remembered.date;
			}
		}
		// A time-grid week/day or a 7-column month grid is cramped past
		// usability on a phone-width screen - the list view reflows to a
		// single readable column instead, so it's used as the actual
		// starting view there regardless of the user's own default_view
		// setting (which stays what they configured for desktop use; this
		// only affects THIS render's initial view, not the setting itself,
		// and the user can still switch views manually afterward). 767.98px
		// matches Base_Box/theme_adminlte/default.css's own breakpoint.
		if (window.innerWidth <= 767.98 && config.initialView !== 'listWeek') config.initialView = 'listWeek';
		// events is a function reference, not JSON-serializable - built here
		// rather than passed through the (JSON-encoded) config object.
		if (feedUrl) config.events = feed(feedUrl);

		var origEventClick = config.eventClick;
		config.eventClick = function (arg) {
			arg.jsEvent.preventDefault();
			runOnclick(extractOnclick((arg.event.extendedProps || {}).viewAttrs), arg.el);
			if (origEventClick) origEventClick(arg);
		};

		var origEventDidMount = config.eventDidMount;
		config.eventDidMount = function (arg) {
			var props = arg.event.extendedProps || {};
			if (props.tooltip) arg.el.setAttribute('title', props.tooltip);
			if (props.viewAttrs) arg.el.style.cursor = 'pointer';
			// Small delete affordance appended into the rendered event -
			// FullCalendar has no built-in delete gesture, and deleteAttrs
			// (already ACL-gated - absent when the handler suppressed it,
			// see Utils_CalendarCommon::action_href()) is exactly the same
			// confirm-dialog href the legacy event chip's own delete icon
			// uses (Module::create_confirm_href()), so clicking it reuses
			// the app-wide styled confirm modal for free.
			if (props.deleteAttrs) {
				var del = document.createElement('span');
				del.className = 'epesi-fc-delete';
				del.setAttribute('role', 'button');
				del.setAttribute('aria-label', 'Delete');
				del.textContent = '×';
				del.addEventListener('click', function (ev) {
					ev.preventDefault();
					ev.stopPropagation();
					runOnclick(extractOnclick(props.deleteAttrs), del);
				});
				arg.el.appendChild(del);
			}
			if (origEventDidMount) origEventDidMount(arg);
		};

		if (writeUrl) {
			var origEventDrop = config.eventDrop;
			config.eventDrop = function (info) {
				write(writeUrl, eventToPayload(info.event), function () {}, function () { info.revert(); });
				if (origEventDrop) origEventDrop(info);
			};
			var origEventResize = config.eventResize;
			config.eventResize = function (info) {
				write(writeUrl, eventToPayload(info.event), function () {}, function () { info.revert(); });
				if (origEventResize) origEventResize(info);
			};
		}

		if (newEventTemplate) {
			var origSelect = config.select;
			config.select = function (info) {
				// info.start already reflects the browser's own "local"
				// reading of the selection - consistent with how this
				// calendar's timeZone:'local' mode treats every other date
				// here as a naive wall-clock value, not a UTC instant.
				var ts = Math.floor(info.start.getTime() / 1000);
				var f = newEventTemplate.replace('__TIME__', ts).replace('__TIMELESS__', info.allDay ? '1' : '0');
				try { (new Function(f))(); } catch (e) { console.error(e); }
				info.view.calendar.unselect();
				if (origSelect) origSelect(info);
			};
		}

		// Fires on initial render and on every navigation (view switch,
		// prev/next/today) - the only hook into FullCalendar's entirely
		// client-side navigation, so it's also the only place that can keep
		// the remembered view/date (see loadViewState() above) up to date.
		var origDatesSet = config.datesSet;
		config.datesSet = function (info) {
			var d = info.view.currentStart;
			saveViewState(mountId, info.view.type, d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate()));
			// timeGridDay's own day-of-week header is still a real FullCalendar
			// navLink (navLinks applies uniformly, there's no built-in "already
			// at this granularity" exception) - clicking it calls zoomTo() with
			// the view's own current date, a same-view/same-date no-op with no
			// visible effect. Rather than guess at FullCalendar's internal,
			// undocumented per-view class names (already got that wrong twice
			// this session), this class is entirely our own, driven by the
			// same documented info.view.type this code already uses for
			// saveViewState() - theme/fullcalendar.css scopes the "don't look
			// like a link" styling to it.
			el.classList.toggle('epesi-fc-no-day-drilldown', info.view.type === 'timeGridDay');
			if (origDatesSet) origDatesSet(info);
		};

		// Week/Day open collapsed to Utils_Calendar::fullcalendar()'s
		// slotMinTime/slotMaxTime (the user's "Start/End day at" setting,
		// already in config) - this button is the escape hatch, expanding to
		// the full 00:00-24:00 day on demand rather than an event outside
		// that range being permanently unreachable. cal is assigned below,
		// after this closure is built but before it can ever run (only a
		// click fires it) - config.height mirrors whatever
		// Utils_Calendar::fullcalendar() sent (normally 'auto', sized to the
		// short configured range); 650 is only used for the expanded 24h
		// state so timeGrid gets its own scroller instead of stretching the
		// page full-length.
		var cal;
		var hoursExpanded = false;
		var collapsedMin = config.slotMinTime, collapsedMax = config.slotMaxTime, collapsedHeight = config.height;
		// [collapsed-state label, expanded-state label] - same wording/icon
		// pair as Utils_GenericBrowser's own Expand All/Collapse All button
		// (theme_adminlte/default.tpl), not a calendar-specific one-off.
		var labels = toggleHoursLabels || ['Expand All', 'Collapse All'];
		function toggleHoursClick() {
			hoursExpanded = !hoursExpanded;
			cal.setOption('slotMinTime', hoursExpanded ? '00:00:00' : collapsedMin);
			cal.setOption('slotMaxTime', hoursExpanded ? '24:00:00' : collapsedMax);
			cal.setOption('height', hoursExpanded ? 650 : collapsedHeight);
			applyToggleHoursState();
		}
		// The label MUST go through cal.setOption('customButtons', ...), not
		// a direct btn.innerHTML write: FullCalendar re-renders the toolbar
		// on its own (e.g. every Day<->Week view switch, all client-side,
		// nothing this code is told about) using whatever it still thinks
		// customButtons.epesiToggleHours.text is - a direct DOM write it
		// doesn't know about got its own expected text re-inserted
		// alongside the manual one on the next such re-render ("Expand
		// AllExpand All"). The icon is CSS-only instead (theme/
		// fullcalendar.css, ::before keyed off this same stable
		// .fc-epesiToggleHours-button + toggled .fc-button-active) - a
		// classList toggle survives that same re-render fine, unlike a
		// text/HTML write.
		function applyToggleHoursState() {
			cal.setOption('customButtons', { epesiToggleHours: { text: hoursExpanded ? labels[1] : labels[0], click: toggleHoursClick } });
			var btn = el.querySelector('.fc-epesiToggleHours-button');
			if (btn) {
				btn.classList.toggle('fc-button-active', hoursExpanded);
				btn.setAttribute('aria-pressed', hoursExpanded ? 'true' : 'false');
			}
		}
		config.customButtons = { epesiToggleHours: { text: labels[0], click: toggleHoursClick } };

		cal = new FullCalendar.Calendar(el, config);
		cal.render();
		applyToggleHoursState();
		instances[mountId] = { cal: cal, el: el };
	}

	return { mount: mount, feed: feed, write: write, extractOnclick: extractOnclick, runOnclick: runOnclick };
})();
