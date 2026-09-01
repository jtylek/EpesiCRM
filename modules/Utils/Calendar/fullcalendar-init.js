// Glue between Epesi's old-style AJAX-push module system and the vendored
// FullCalendar bundle (libs/fullcalendar-<version>/index.global.min.js). See
// Utils_Calendar::fullcalendar() (PHP side) - a module prints one stable-id
// container div and queues a mount() call in the same body() invocation;
// Epesi.text()'s DOM patches always land before any queued eval_js/
// eval_js_once code runs (include/epesi.php's get_output() ordering), so the
// container is guaranteed to exist by the time this runs.
var EpesiFullCalendar = window.EpesiFullCalendar || (function () {
	var instances = {};
	// mountId -> {'Y-m-d': "<regionally-formatted date>"}, refreshed on every
	// feed() response - see applyListDecorations() below.
	var dayLabelsByMount = {};

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
	function feed(url, mountId) {
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
				// Stashed even for a view that isn't Agenda right now - cheap,
				// and it's exactly what applyListDecorations() needs the next
				// time the Agenda/list view is the one settling.
				dayLabelsByMount[mountId] = data.dayLabels || {};
				success(data.events || []);
			};
			xhr.onerror = function () { failure(new Error('network error')); };
			xhr.send();
		};
	}

	// Agenda/listWeek-only cosmetic pass, run once the event store has
	// settled (config.eventsSet - fires after every successful feed(), even
	// an empty one, so the day-header rows this walks are guaranteed to
	// already exist in the DOM by the time this runs). A no-op for every
	// other view: neither a '.fc-toolbar-title' rewrite nor a '.fc-list-day'
	// row exists to touch there, so the two DOM queries below just find
	// nothing.
	//
	// 1) Prefixes the toolbar title with "Agenda: " (localized - reuses
	//    config.buttonText.list, the same translated string already on the
	//    Agenda toolbar button, rather than a second translation of the same
	//    word) in front of FullCalendar's own native date-range title -
	//    left otherwise untouched, so it still reads correctly for whatever
	//    span agenda_days is currently set to.
	// 2) Reformats each day-group's right-hand date (FullCalendar's own
	//    listDaySideFormat, English-only - Epesi's Regional Settings date
	//    format has no equivalent FullCalendar option) using the label the
	//    server already computed with Base_RegionalSettingsCommon::
	//    time2reg() for that exact 'Y-m-d' (see fullcalendar_events()'s
	//    'dayLabels'), keyed off the row's own data-date attribute so it
	//    can't drift from what the row is actually showing.
	function applyListDecorations(mountId) {
		var inst = instances[mountId];
		if (!inst) return;
		var isList = inst.cal && inst.cal.view.type === 'listWeek';
		// A sibling node placed BEFORE '.fc-toolbar-title', never text written
		// INTO it - that title element is Preact-owned (FullCalendar's own
		// toolbar), and overwriting its textContent directly fought its own
		// re-render on the next navigation/view-switch: this code and
		// FullCalendar's vdom diff each think they own the single text node
		// inside it, and whichever one runs second doesn't necessarily REPLACE
		// the other's content, so an in-place textContent write here was
		// observed to sometimes STACK ("Agenda: Sep 1 - 3, 2026Sep 4 - 6,
		// 2026") instead of overwrite. A plain sibling has no such owner to
		// fight - added/removed wholesale, never mutated in place, so no vdom
		// state to get out of sync with.
		var titleEl = inst.el.querySelector('.fc-toolbar-title');
		var prefixEl = inst.el.querySelector('.epesi-agenda-title-prefix');
		if (isList && titleEl) {
			if (!prefixEl) {
				prefixEl = document.createElement('span');
				prefixEl.className = 'epesi-agenda-title-prefix';
				titleEl.parentNode.insertBefore(prefixEl, titleEl);
			}
			prefixEl.textContent = inst.agendaLabel + ': ';
		} else if (prefixEl) {
			prefixEl.remove();
		}
		if (!isList) return;
		var labels = dayLabelsByMount[mountId] || {};
		var rows = inst.el.querySelectorAll('.fc-list-day[data-date]');
		for (var i = 0; i < rows.length; i++) {
			var label = labels[rows[i].getAttribute('data-date')];
			var sideEl = rows[i].querySelector('.fc-list-day-side-text');
			if (label && sideEl) sideEl.textContent = label;
		}
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
	function mount(mountId, config, feedUrl, writeUrl, newEventTemplate, toggleHoursLabels, suppressViewRestore, dayClickTemplate, titleClickForwardSelector) {
		try {
			mountUnsafe(mountId, config, feedUrl, writeUrl, newEventTemplate, toggleHoursLabels, suppressViewRestore, dayClickTemplate, titleClickForwardSelector);
		} catch (e) {
			console.error('EpesiFullCalendar.mount failed:', e);
		}
	}

	function mountUnsafe(mountId, config, feedUrl, writeUrl, newEventTemplate, toggleHoursLabels, suppressViewRestore, dayClickTemplate, titleClickForwardSelector) {
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
		if (feedUrl) config.events = feed(feedUrl, mountId);

		var origEventsSet = config.eventsSet;
		config.eventsSet = function (evs) {
			applyListDecorations(mountId);
			// A month with enough events grows the page past the viewport
			// height, which summons the page's own vertical scrollbar AFTER
			// FullCalendar already laid out its internal "scrollgrid" (the
			// header row and the day-grid body are two SEPARATE elements,
			// deliberately kept width-matched by FullCalendar itself so the
			// header stays visually aligned with the body's columns while
			// only the body scrolls). That resync only re-runs on a real
			// window 'resize' event; cal.updateSize() (FullCalendar's own
			// documented resize API) does NOT trigger it - confirmed
			// empirically, it left the header (measured pre-scrollbar) still
			// wider than the body's own clipped scroll area, so the
			// rightmost column's day number rendered past the clipped edge
			// with nothing there to scroll to. A synthetic resize event is
			// the same fix FullCalendar's own issue tracker points to for
			// this category of desync. Dispatched on the whole window, not
			// scoped to this instance - harmless (every other mounted
			// instance just redoes the same cheap reflow) and simpler than
			// reaching into FullCalendar's internals for a narrower hook.
			window.dispatchEvent(new Event('resize'));
			if (origEventsSet) origEventsSet(evs);
		};

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
			// "What kind of record is this" glyph in front of the title, the
			// same one the Agenda applet and the legacy day/week/month chips
			// show (biIcon is a bare "bi-..." class from
			// Utils_CalendarCommon::event_to_fullcalendar()). Built here
			// rather than server-side because it is view-dependent, and only
			// the client knows which view is on screen:
			//   multiMonthYear - skipped in the GRID (a year cell has no
			//                    room), but not in its "+N more" popover,
			//                    which is roomy - see inPopover below
			//   dayGridMonth   - the smaller variant, matching what the
			//                    legacy month grid does via bi_icon_small
			// The title node differs per view (.fc-event-title in the grids,
			// .fc-list-event-title in the Agenda list), hence the pair of
			// selectors. currentColor, not a fixed muted grey: these chips
			// are painted in the seven Epesi event colors.
			//
			// A "+N more" popover row is the same event rendered again, in a
			// context that has room for a full-size glyph whatever the view
			// behind it is. closest() rather than a flag off arg: FullCalendar
			// gives eventDidMount no "you are in a popover" signal, but the
			// row is already inside the popover's own subtree by the time this
			// runs (Preact mounts children before their parent), so walking up
			// works even before that subtree is attached to the document.
			var viewType = (arg.view || {}).type;
			var inPopover = !!(arg.el.closest && arg.el.closest('.fc-popover'));
			if (props.biIcon && (inPopover || viewType !== 'multiMonthYear')) {
				var ic = document.createElement('i');
				ic.className = 'bi ' + props.biIcon + ' fc-epesi-type-icon' +
					(!inPopover && viewType === 'dayGridMonth' ? ' fc-epesi-type-icon--sm' : '');
				if (inPopover) {
					// The glyph REPLACES the generic colored bullet here
					// rather than joining it - two leading markers on one
					// short row, one of which says nothing the chip's own
					// color doesn't already say, just crowds the line. Only
					// ever dropped when there is a real glyph to put in its
					// place, so an event source that declares no icon keeps
					// its bullet.
					var dot = arg.el.querySelector('.fc-daygrid-event-dot');
					if (dot && !arg.el.querySelector('.fc-epesi-type-icon')) {
						dot.parentNode.replaceChild(ic, dot);
					} else if (!arg.el.querySelector('.fc-epesi-type-icon')) {
						arg.el.insertBefore(ic, arg.el.firstChild);
					}
				} else {
					var titleEl = arg.el.querySelector('.fc-event-title, .fc-list-event-title');
					if (titleEl && !titleEl.querySelector('.fc-epesi-type-icon'))
						titleEl.insertBefore(ic, titleEl.firstChild);
				}
			}
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

		if (dayClickTemplate) {
			// Compact-mode navigation (Applets_MonthView) - replaces navLinks
			// (disabled by Utils_Calendar::fullcalendar() whenever this
			// template is supplied) with a real navigation to the caller's own
			// choice of destination (e.g. CRM_Calendar's Day view), same
			// __PLACEHOLDER__ substitution convention as newEventTemplate above.
			var origDateClick = config.dateClick;
			config.dateClick = function (info) {
				var ts = Math.floor(info.date.getTime() / 1000);
				var f = dayClickTemplate.replace('__DATE__', ts);
				try { (new Function(f))(); } catch (e) { console.error(e); }
				if (origDateClick) origDateClick(info);
			};
		}

		// Year view's toolbar title is otherwise plain, unclickable text
		// (just "2026") with no way to reach a year more than one prev/next
		// step away. Swapped for a native <select> of nearby years while
		// multiMonthYear is the active view - a sibling node inserted before
		// the title and added/removed wholesale on every render, never a
		// mutation of the title node itself, for the same reason
		// applyListDecorations()'s prefix span above is a sibling rather than
		// a textContent write: that node is Preact-owned (FullCalendar's own
		// toolbar), and a directly-written child fights its own re-render on
		// the next navigation. The real title is hidden (style.display, not
		// removed) rather than replaced, so FullCalendar keeps owning it
		// untouched and can still overwrite its text on every render as usual.
		function applyYearPicker(view, titleEl) {
			var isYear = view.type === 'multiMonthYear';
			var picker = el.querySelector('.epesi-fc-year-select');
			if (!isYear) {
				if (picker) picker.remove();
				if (titleEl) titleEl.style.display = '';
				return;
			}
			if (!titleEl) return;
			titleEl.style.display = 'none';
			var year = view.currentStart.getFullYear();
			if (!picker) {
				picker = document.createElement('select');
				picker.className = 'epesi-fc-year-select';
				picker.setAttribute('aria-label', 'Year');
				titleEl.parentNode.insertBefore(picker, titleEl);
				picker.addEventListener('change', function () {
					cal.gotoDate(new Date(parseInt(picker.value, 10), 0, 1));
				});
			}
			// Options are rebuilt around the current year on every render
			// (not just once at creation) so the range keeps following
			// wherever prev/next/today navigated to, rather than going stale
			// once the current year drifts outside whatever range was built
			// when the picker first appeared.
			var options = '';
			for (var y = year - 6; y <= year + 6; y++)
				options += '<option value="' + y + '"' + (y === year ? ' selected' : '') + '>' + y + '</option>';
			picker.innerHTML = options;
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
			var titleEl = el.querySelector('.fc-toolbar-title');
			applyYearPicker(info.view, titleEl);
			// Re-applied every render (assignment, not addEventListener - safe
			// against duplicate bindings whether FullCalendar reuses or
			// recreates the title node on navigation) - forwards a click on
			// the plain-text toolbar title to an already-wired trigger
			// elsewhere on the page (e.g. Applets_MonthView's own "jump to
			// date" popup-calendar link), since FullCalendar's title has no
			// click behavior of its own. Never wired for Year view - the
			// select above already owns that node's interaction there.
			if (titleClickForwardSelector && titleEl && info.view.type !== 'multiMonthYear') {
				titleEl.classList.add('epesi-fc-title-link');
				titleEl.style.cursor = 'pointer';
				titleEl.style.userSelect = 'none';
				// The trigger this forwards to is one shared Utils_PopupCalendar
				// instance (CRM_Calendar::body()'s "calendar_selector"), reused by
				// every view's title - so without this it drew whatever
				// month/day/decade grid it last happened to be left showing,
				// independent of which view its title was actually clicked from.
				// Stashed as data-* on the target rather than baked into a fixed
				// PHP-side mode: the view can change client-side (view switch,
				// prev/next) without any server round-trip, so only this handler -
				// re-assigned on every datesSet, closing over the CURRENT info -
				// knows what view/date the click was actually made from.
				// getMonth() is already 0-based, matching Utils_PopupCalendar's
				// own month-index convention (main2.js's monthName[]).
				var d = info.view.currentStart;
				var viewType = info.view.type;
				titleEl.onclick = function (ev) {
					ev.preventDefault();
					var target = document.querySelector(titleClickForwardSelector);
					if (!target) return;
					target.setAttribute('data-fc-view-type', viewType);
					target.setAttribute('data-fc-year', d.getFullYear());
					target.setAttribute('data-fc-month', d.getMonth());
					target.setAttribute('data-fc-day', d.getDate());
					target.click();
				};
			} else if (titleEl) {
				titleEl.classList.remove('epesi-fc-title-link');
			}
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
		instances[mountId] = { cal: cal, el: el, agendaLabel: (config.buttonText && config.buttonText.list) || 'Agenda' };
	}

	// Client-side "jump to date" for a Day/Week/Month/Agenda toolbar title
	// wired to a Utils_PopupCalendar trigger (CRM_Calendar's own
	// title_click_forward_selector setup - see Calendar_0.php) - calls
	// straight into the live FullCalendar instance instead of a server
	// round-trip, matching how prev/next/today already navigate this engine
	// with no page reload. A no-op if the instance is gone (e.g. the calendar
	// box was popped) rather than throwing into Utils_PopupCalendar's own
	// onClick handler.
	function gotoDate(mountId, date) {
		var inst = instances[mountId];
		if (inst && inst.cal) inst.cal.gotoDate(date);
	}

	return { mount: mount, feed: feed, write: write, extractOnclick: extractOnclick, runOnclick: runOnclick, gotoDate: gotoDate };
})();
