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
    const availableViews = new Set(['dayGridMonth', 'timeGridWeek', 'timeGridDay', 'listWeek']);
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

    const calendar = new Calendar(el, {
        plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
        initialView,
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
            right: 'listWeek,timeGridDay,timeGridWeek,dayGridMonth',
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
                el.setAttribute('x-tooltip', `{ content: ${JSON.stringify(tooltip)}, theme: $store.theme, allowHTML: true, placement: 'top', followCursor: true }`);
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
};

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
