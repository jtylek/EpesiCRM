import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin from '@fullcalendar/interaction';
import deLocale from '@fullcalendar/core/locales/de';
import esLocale from '@fullcalendar/core/locales/es';
import frLocale from '@fullcalendar/core/locales/fr';
import plLocale from '@fullcalendar/core/locales/pl';
import '../css/calendar.css';

// Formats a floating (UTC-literal) date with a PHP date() format string, the
// kind RegionalSetting::dateFormat() returns. Covers the date characters;
// anything else is kept as-is, backslash escapes the next character.
function formatPhpDate(format, date, locale) {
    const pad = (n) => String(n).padStart(2, '0');
    const name = (options) => new Intl.DateTimeFormat(locale, { timeZone: 'UTC', ...options }).format(date);
    const map = {
        d: () => pad(date.getUTCDate()),
        j: () => String(date.getUTCDate()),
        D: () => name({ weekday: 'short' }),
        l: () => name({ weekday: 'long' }),
        N: () => String(date.getUTCDay() || 7),
        w: () => String(date.getUTCDay()),
        m: () => pad(date.getUTCMonth() + 1),
        n: () => String(date.getUTCMonth() + 1),
        M: () => name({ month: 'short' }),
        F: () => name({ month: 'long' }),
        y: () => pad(date.getUTCFullYear() % 100),
        Y: () => String(date.getUTCFullYear()),
    };

    let out = '';
    for (let i = 0; i < format.length; i++) {
        const c = format[i];
        if (c === '\\' && i + 1 < format.length) {
            out += format[++i];
        } else {
            out += map[c] ? map[c]() : c;
        }
    }

    return out;
}

// Called from resources/views/filament/pages/calendar.blade.php's x-init.
// $wire.getEvents(...) calls straight into App\Filament\Pages\Calendar::getEvents()
// (a Livewire component method) as FullCalendar's event source — see that
// class for why this replaces Epesi's separate JSON ajax.php endpoint.
window.initEpesiCalendar = function (el, wire) {
    const viewStorageKey = 'epesi.calendar.view';
    const availableViews = new Set(['dayGridMonth', 'timeGridWeek', 'timeGridDay', 'timeGridSevenDay', 'listWeek']);
    const requestedView = new URLSearchParams(window.location.search).get('view');
    let initialView = availableViews.has(requestedView) ? requestedView : 'timeGridWeek';

    if (!availableViews.has(requestedView)) {
        try {
            const savedView = window.sessionStorage.getItem(viewStorageKey);
            if (availableViews.has(savedView)) {
                initialView = savedView;
            }
        } catch {
        }
    }

    // Working hours: Day/Week views show only these in full; the hours before
    // and after collapse into a bar with an event count (see initWorkingHours).
    const work = {
        start: parseInt(el.dataset.workStart, 10),
        end: parseInt(el.dataset.workEnd, 10),
        expanded: { morning: false, evening: false },
        labels: {},
    };
    try {
        work.expanded = { ...work.expanded, ...JSON.parse(el.dataset.expanded || '{}') };
        work.labels = JSON.parse(el.dataset.labels || '{}');
    } catch {}
    const hh = (h) => String(h).padStart(2, '0') + ':00:00';

    const calendar = new Calendar(el, {
        slotMinTime: work.expanded.morning || !(work.start > 0) ? '00:00:00' : hh(work.start),
        slotMaxTime: work.expanded.evening || !(work.end < 24) ? '24:00:00' : hh(work.end),
        // When the outside hours are shown, a dark line marks where the
        // working hours start and end (the slot at that hour gets a top border).
        slotLaneClassNames: slotBoundaryClasses(work),
        slotLabelClassNames: slotBoundaryClasses(work),
        plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
        initialView,
        // The List view's period, from the user's Calendar settings.
        views: {
            // Seven days starting today (not the calendar week).
            timeGridSevenDay: {
                type: 'timeGrid',
                duration: { days: 7 },
                buttonText: work.labels.sevenDays || '7 days',
            },
            listWeek: {
                duration: {
                    '1d': { days: 1 }, '3d': { days: 3 }, '7d': { days: 7 },
                    '2w': { weeks: 2 }, '1m': { months: 1 },
                }[el.dataset.listRange] || { days: 7 },
            },
        },
        // The page's language (config/app.php's available_locales); English
        // is FullCalendar's built-in default.
        locales: [deLocale, esLocale, frLocale, plLocale],
        locale: el.dataset.locale || 'en',
        // Every date this calendar sends/receives is a floating wall-clock
        // value with no real timezone attached (see CalendarEvent::toArray()) —
        // 'UTC' here means "take these strings literally", not "convert to
        // UTC", which is what keeps a visiting browser's own timezone from
        // silently shifting displayed times or drag-reschedule writes.
        timeZone: 'UTC',
        // ...so "now" (today's highlight, the now indicator) has to be the
        // wall clock in the user's regional-settings timezone, expressed the
        // same floating way, not the browser's or UTC's.
        now: () => {
            try {
                const parts = Object.fromEntries(
                    new Intl.DateTimeFormat('en-GB', {
                        timeZone: el.dataset.timezone || 'UTC',
                        hourCycle: 'h23',
                        year: 'numeric', month: '2-digit', day: '2-digit',
                        hour: '2-digit', minute: '2-digit', second: '2-digit',
                    }).formatToParts(new Date()).map((part) => [part.type, part.value]),
                );

                return new Date(Date.UTC(parts.year, parts.month - 1, parts.day, parts.hour, parts.minute, parts.second));
            } catch {
                return new Date();
            }
        },
        // The user's 12h/24h choice for the time labels.
        eventTimeFormat: { hour: 'numeric', minute: '2-digit', hour12: el.dataset.hour12 === '1' },
        slotLabelFormat: { hour: 'numeric', minute: '2-digit', hour12: el.dataset.hour12 === '1' },
        // List view day rows: weekday, then the date in the user's own format.
        listDayFormat: { weekday: 'long' },
        listDaySideFormat: ({ date }) => formatPhpDate(
            el.dataset.dateFormat || 'Y-m-d',
            date.marker,
            el.dataset.locale || 'en',
        ),
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'listWeek,timeGridDay,timeGridWeek,timeGridSevenDay,dayGridMonth',
        },
        height: 'auto',
        editable: true,
        // Month/week view day numbers become links to that day's view.
        navLinks: true,
        datesSet: ({ view }) => {
            try {
                window.sessionStorage.setItem(viewStorageKey, view.type);
            } catch {}
        },
        events: (info, successCallback, failureCallback) => {
            wire.getEvents(info.startStr, info.endStr).then(successCallback).catch(failureCallback);
        },
        eventContent: (info) => {
            const content = document.createElement('span');
            content.className = 'epesi-calendar-event-content';

            if (info.timeText) {
                const time = document.createElement('span');
                time.className = 'fc-event-time';
                time.textContent = info.timeText;
                content.append(time);
            }

            const iconHtml = info.event.extendedProps.typeIconHtml;
            if (iconHtml) {
                const template = document.createElement('template');
                template.innerHTML = iconHtml;

                const icon = template.content.firstElementChild;
                if (icon) {
                    icon.classList.add('epesi-calendar-event-type-icon');
                    content.append(icon);
                }
            }

            const title = document.createElement('span');
            title.className = 'epesi-calendar-event-title';
            title.textContent = info.event.title;
            content.append(title);

            return { domNodes: [content] };
        },
        eventDidMount: ({ el, event }) => {
            const tooltip = event.extendedProps.tooltip;

            if (tooltip) {
                el.setAttribute('x-tooltip', `{ content: ${JSON.stringify(tooltip)}, theme: $store.theme, allowHTML: true }`);
            }
        },

        // Drag-move or resize: optimistically already moved by FullCalendar:
        // persist via App\Filament\Pages\Calendar::rescheduleEvent(), revert
        // on "not found"/"not authorized" (false) or a request failure.
        eventDrop: (info) => persistReschedule(wire, info),
        eventResize: (info) => persistReschedule(wire, info),

        // Empty-cell click: opens the "New event" type picker
        // (App\Filament\Pages\Calendar::createEventAction()).
        dateClick: (info) => {
            wire.mountAction('createEvent', { date: info.dateStr, allDay: info.allDay });
        },

        // An event is a link to its record. Followed like the panel's own
        // links (its SPA mode), so the page isn't reloaded and full screen
        // lasts. A modified click (new tab or window) is left to the browser.
        eventClick: (info) => {
            const e = info.jsEvent;

            if (!info.event.url || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) {
                return;
            }

            e.preventDefault();
            window.Livewire.navigate(info.event.url);
        },
    });

    calendar.render();

    initTitleDatePicker(el, calendar);
    initWorkingHours(el, calendar, wire, work);
};

// Heroicons (outline) chevron-down / chevron-up.
const ICON_EXPAND = 'm19.5 8.25-7.5 7.5-7.5-7.5';
const ICON_COLLAPSE = 'm4.5 15.75 7.5-7.5 7.5 7.5';

function icon(path) {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('fill', 'none');
    svg.setAttribute('stroke', 'currentColor');
    svg.setAttribute('stroke-width', '1.5');
    svg.setAttribute('aria-hidden', 'true');
    const p = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    p.setAttribute('d', path);
    p.setAttribute('stroke-linecap', 'round');
    p.setAttribute('stroke-linejoin', 'round');
    svg.append(p);

    return svg;
}

function slotBoundaryClasses(work) {
    return ({ date }) => {
        if (date.getUTCMinutes() !== 0) {
            return [];
        }

        const hour = date.getUTCHours();

        if (hour === work.start && work.start > 0 && work.expanded.morning) {
            return ['epesi-work-boundary'];
        }

        if (hour === work.end && work.end < 24 && work.expanded.evening) {
            return ['epesi-work-boundary'];
        }

        return [];
    };
}

// Collapses the hours outside the working hours in the Day and Week views.
// FullCalendar can't fold part of its time axis, so the slot range shrinks to
// the working hours and a bar above (morning) and below (evening) the grid
// stands in for what's hidden: it counts the events there and expands them.
function initWorkingHours(el, calendar, wire, work) {
    const harness = el.querySelector('.fc-view-harness');

    if (!harness || !(work.start > 0 || work.end < 24)) {
        return;
    }

    const hour12 = el.dataset.hour12 === '1';
    const timeLabel = (h) => new Intl.DateTimeFormat(el.dataset.locale || 'en', {
        timeZone: 'UTC', hour: 'numeric', minute: '2-digit', hour12,
    }).format(new Date(Date.UTC(2000, 0, 1, h % 24)));
    const hasMorning = work.start > 0;
    const hasEvening = work.end < 24;

    const makeBar = (part) => {
        const bar = document.createElement('div');
        bar.className = 'epesi-calendar-collapsed-hours';
        const text = document.createElement('span');
        const button = document.createElement('button');
        button.type = 'button';
        button.addEventListener('click', () => {
            work.expanded[part] = !work.expanded[part];
            wire.rememberExpanded(work.expanded.morning, work.expanded.evening);
            refresh();
        });
        bar.append(text, button);
        bar.text = text;
        bar.button = button;

        return bar;
    };

    const morningBar = makeBar('morning');
    const eveningBar = makeBar('evening');
    harness.after(eveningBar);

    // The morning bar is a row of the grid's own table, between the all-day
    // row and the time slots. FullCalendar rebuilds the table on a view
    // change, so the row is put back whenever it has been displaced.
    const morningRow = document.createElement('tr');
    morningRow.className = 'epesi-calendar-collapsed-row';
    const morningCell = document.createElement('td');
    morningCell.colSpan = 1;
    morningCell.append(morningBar);
    morningRow.append(morningCell);

    const placeMorningRow = () => {
        const slots = harness.querySelector('.fc-timegrid-body')?.closest('tr');

        if (slots && slots.previousElementSibling !== morningRow) {
            slots.before(morningRow);
        }
    };

    new MutationObserver(placeMorningRow).observe(harness, { childList: true, subtree: true });

    // Events in the visible range that touch [from, to) hours of any day.
    const countOutside = (fromHour, toHour) => {
        const { activeStart, activeEnd } = calendar.view;
        const day = 86400000;
        const hour = 3600000;
        const ids = new Set();

        calendar.getEvents().forEach((event) => {
            if (event.allDay || !event.start) {
                return;
            }

            const s = event.start.getTime();
            const e = Math.max(event.end ? event.end.getTime() : s, s + 1);

            for (let d = activeStart.getTime(); d < activeEnd.getTime(); d += day) {
                if (s < d + toHour * hour && e > d + fromHour * hour) {
                    ids.add(event.id || event._instance.instanceId);
                    break;
                }
            }
        });

        return ids.size;
    };

    const fill = (bar, part, label, count) => {
        const open = work.expanded[part];
        bar.text.textContent = `${label} · ${(count === 1 ? work.labels.one : work.labels.other).replace(':count', count)}`;
        bar.button.replaceChildren(
            icon(open ? ICON_COLLAPSE : ICON_EXPAND),
            document.createTextNode(open ? work.labels.collapse : work.labels.expand),
        );
        bar.classList.toggle('is-expanded', open);
    };

    const refresh = () => {
        const timeGrid = ['timeGridDay', 'timeGridSevenDay', 'timeGridWeek'].includes(calendar.view.type);

        calendar.setOption('slotMinTime', work.expanded.morning || !hasMorning ? '00:00:00' : hh2(work.start));
        calendar.setOption('slotMaxTime', work.expanded.evening || !hasEvening ? '24:00:00' : hh2(work.end));

        placeMorningRow();
        morningRow.hidden = !timeGrid || !hasMorning;
        morningBar.hidden = morningRow.hidden;
        eveningBar.hidden = !timeGrid || !hasEvening;

        if (timeGrid) {
            fill(morningBar, 'morning', work.labels.before.replace(':time', timeLabel(work.start)), countOutside(0, work.start));
            fill(eveningBar, 'evening', work.labels.after.replace(':time', timeLabel(work.end)), countOutside(work.end, 24));
        }
    };

    const hh2 = (h) => String(h).padStart(2, '0') + ':00:00';

    calendar.on('datesSet', refresh);
    calendar.on('eventsSet', refresh);
    refresh();
}

// Makes the toolbar title (e.g. "September 2026") a date picker: click it to
// jump the calendar (whatever view is active) to any month/week/day.
function initTitleDatePicker(el, calendar) {
    const titleEl = el.querySelector('.fc-toolbar-title');

    if (!titleEl) {
        return;
    }

    const input = document.createElement('input');
    input.type = 'date';
    input.className = 'epesi-calendar-title-picker';
    input.setAttribute('aria-hidden', 'true');
    input.tabIndex = -1;

    titleEl.classList.add('epesi-calendar-title-clickable');
    titleEl.setAttribute('role', 'button');
    titleEl.setAttribute('tabindex', '0');
    titleEl.appendChild(input);

    const open = () => {
        input.value = calendar.getDate().toISOString().slice(0, 10);

        if (typeof input.showPicker === 'function') {
            input.showPicker();
        } else {
            input.focus();
        }
    };

    input.addEventListener('change', () => {
        if (input.value) {
            calendar.gotoDate(input.value);
        }
    });

    titleEl.addEventListener('click', open);
    titleEl.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            open();
        }
    });
}

function persistReschedule(wire, info) {
    wire.rescheduleEvent(info.event.id, info.event.startStr, info.event.endStr || null, info.event.allDay)
        .then((ok) => {
            if (!ok) {
                info.revert();
            }
        })
        .catch(() => info.revert());
}
