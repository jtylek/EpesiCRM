import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin from '@fullcalendar/interaction';
import deLocale from '@fullcalendar/core/locales/de';
import esLocale from '@fullcalendar/core/locales/es';
import frLocale from '@fullcalendar/core/locales/fr';
import faLocale from '@fullcalendar/core/locales/fa';
import heLocale from '@fullcalendar/core/locales/he';
import plLocale from '@fullcalendar/core/locales/pl';
import '../css/calendar.css';

const UMM_AL_QURA_MIN_YEAR = 1300;
const UMM_AL_QURA_MAX_YEAR = 1600;

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

function calendarType(el) {
    if (el.dataset.calendarSystem === 'jalali') {
        return 'persian';
    }

    if (el.dataset.calendarSystem === 'hijri') {
        return el.dataset.hijriVariant === 'civil' ? 'islamic-civil' : 'islamic-umalqura';
    }

    return null;
}

function calendarDateParts(date, type) {
    const parts = Object.fromEntries(new Intl.DateTimeFormat(`en-u-ca-${type}`, {
        timeZone: 'UTC', year: 'numeric', month: 'numeric', day: 'numeric',
    }).formatToParts(date).map((part) => [part.type, part.value]));

    return { year: Number(parts.year), month: Number(parts.month), day: Number(parts.day) };
}

function supportsCalendarYear(year, type) {
    return type !== 'islamic-umalqura' || (year >= UMM_AL_QURA_MIN_YEAR && year <= UMM_AL_QURA_MAX_YEAR);
}

function calendarDateInputValue(date, type) {
    const parts = calendarDateParts(date, type);

    if (!supportsCalendarYear(parts.year, type)) {
        return null;
    }

    return `${String(parts.year).padStart(4, '0')}-${String(parts.month).padStart(2, '0')}-${String(parts.day).padStart(2, '0')}`;
}

function normalizeCalendarDigits(value) {
    return value.replace(/[۰-۹٠-٩]/g, (digit) => {
        const codePoint = digit.codePointAt(0);

        return String(codePoint - (codePoint >= 0x06f0 ? 0x06f0 : 0x0660));
    });
}

function gregorianDateForCalendarDate(year, month, day, type) {
    if (!supportsCalendarYear(year, type)) {
        return null;
    }

    const approximateYear = type === 'persian'
        ? year + 621
        : Math.floor(year * 354.367 / 365.2425 + 622);
    const center = Date.UTC(approximateYear, 6, 1);

    for (let offset = -380; offset <= 380; offset++) {
        const candidate = new Date(center + offset * 86400000);
        const parts = calendarDateParts(candidate, type);

        if (parts.year === year && parts.month === month && parts.day === day) {
            return candidate;
        }
    }

    return null;
}

function alternateMonthRange(date, type) {
    const parts = calendarDateParts(date, type);

    if (!supportsCalendarYear(parts.year, type)) {
        return undefined;
    }

    const start = gregorianDateForCalendarDate(parts.year, parts.month, 1, type);
    const nextYear = parts.month === 12 ? parts.year + 1 : parts.year;
    const nextMonth = parts.month === 12 ? 1 : parts.month + 1;
    const end = gregorianDateForCalendarDate(nextYear, nextMonth, 1, type);

    if (!start || !end) {
        return undefined;
    }

    return { start: start.toISOString().slice(0, 10), end: end.toISOString().slice(0, 10) };
}

function alternateDateLabel(date, el, options) {
    const type = calendarType(el);

    if (type && !supportsCalendarYear(calendarDateParts(date, type).year, type)) {
        return null;
    }

    return type
        ? new Intl.DateTimeFormat(`${el.dataset.locale || 'en'}-u-ca-${type}`, { timeZone: 'UTC', ...options }).format(date)
        : null;
}

function moveAlternateMonth(calendar, type, direction) {
    const parts = calendarDateParts(calendar.getDate(), type);
    const navigationType = supportsCalendarYear(parts.year, type) ? type : 'islamic-civil';
    let year = parts.year;
    let month = parts.month + direction;

    if (month < 1) {
        month = 12;
        year--;
    } else if (month > 12) {
        month = 1;
        year++;
    }

    const conversionType = supportsCalendarYear(year, type) ? type : navigationType;
    const date = gregorianDateForCalendarDate(year, month, 1, conversionType);
    if (date) {
        calendar.gotoDate(date);
    }
}

// Called from resources/views/filament/pages/calendar.blade.php's x-init.
// $wire.getEvents(...) calls straight into App\Filament\Pages\Calendar::getEvents()
// (a Livewire component method) as FullCalendar's event source — see that
// class for why this replaces Epesi's separate JSON ajax.php endpoint.
window.initEpesiCalendar = function (el, wire) {
    const alternateCalendar = calendarType(el);
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

    const mobileQuery = window.matchMedia('(max-width: 767px)');
    const coarsePointer = window.matchMedia('(pointer: coarse)');
    let rangeNotice = null;

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
                // Phones: just the start time.
                displayEventEnd: !mobileQuery.matches,
                duration: {
                    '1d': { days: 1 }, '3d': { days: 3 }, '7d': { days: 7 },
                    '2w': { weeks: 2 }, '1m': { months: 1 },
                }[el.dataset.listRange] || { days: 7 },
            },
            dayGridMonth: {
                fixedWeekCount: false,
                ...(alternateCalendar ? { visibleRange: (date) => alternateMonthRange(date, alternateCalendar) } : {}),
            },
        },
        // The page's language (config/app.php's available_locales); English
        // is FullCalendar's built-in default.
        locales: [deLocale, esLocale, faLocale, frLocale, heLocale, plLocale],
        locale: el.dataset.locale || 'en',
        // Every date this calendar sends/receives is a floating wall-clock
        // value with no real timezone attached (see CalendarEvent::toArray()) —
        // 'UTC' here means "take these strings literally", not "convert to
        // UTC", which is what keeps a visiting browser's own timezone from
        // silently shifting displayed times or drag-reschedule writes.
        timeZone: 'UTC',
        // Tasks and phone calls have no end: one slot (one line of text) tall
        // in the time grids, instead of FullCalendar's default hour.
        defaultTimedEventDuration: '00:30',
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
        listDaySideFormat: ({ date }) => alternateCalendar
            ? alternateDateLabel(date.marker, el, mobileQuery.matches
                ? { year: 'numeric', month: 'long', day: 'numeric' }
                : { year: 'numeric', month: 'short', day: 'numeric' })
            : mobileQuery.matches
            // Phones: the full date, with the month spelled out and the year.
            ? new Intl.DateTimeFormat(el.dataset.locale || 'en', { timeZone: 'UTC', year: 'numeric', month: 'long', day: 'numeric' }).format(date.marker)
            : formatPhpDate(el.dataset.dateFormat || 'Y-m-d', date.marker, el.dataset.locale || 'en'),
        dayHeaderContent: ({ date, text, view }) => {
            // Phones: first letter of the weekday, and on a second row only
            // the day of the month.
            if (['listWeek', 'timeGridDay'].includes(view.type)) {
                const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };

                return alternateCalendar
                    ? alternateDateLabel(date, el, options) ?? text
                    : new Intl.DateTimeFormat(el.dataset.locale || 'en', { timeZone: 'UTC', ...options }).format(date);
            }

            if (mobileQuery.matches) {
                const letter = new Intl.DateTimeFormat(el.dataset.locale || 'en', { timeZone: 'UTC', weekday: 'narrow' }).format(date);
                if (view.type === 'dayGridMonth') {
                    return letter;
                }
                const day = alternateCalendar
                    ? alternateDateLabel(date, el, { day: 'numeric' }) ?? date.getUTCDate()
                    : date.getUTCDate();
                const box = document.createElement('span');
                box.className = 'epesi-calendar-mobile-day-header';
                const top = document.createElement('span');
                top.textContent = letter;
                const bottom = document.createElement('span');
                bottom.textContent = day;
                box.append(top, bottom);

                return { domNodes: [box] };
            }

            return alternateCalendar && view.type !== 'dayGridMonth'
                ? alternateDateLabel(date, el, { weekday: 'short', day: 'numeric' }) ?? text
                : text;
        },
        dayCellContent: ({ date }) => {
            const day = alternateDateLabel(date, el, { day: 'numeric' });
            if (day === null) {
                return undefined;
            }

            const label = document.createElement('span');
            label.textContent = day;

            return { domNodes: [label] };
        },
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
            if (alternateCalendar) {
                const currentParts = calendarDateParts(calendar.getDate(), alternateCalendar);
                if (!supportsCalendarYear(currentParts.year, alternateCalendar)) {
                    if (!rangeNotice) {
                        rangeNotice = document.createElement('p');
                        rangeNotice.className = 'epesi-calendar-range-notice';
                        rangeNotice.setAttribute('role', 'status');
                        el.prepend(rangeNotice);
                    }
                    rangeNotice.textContent = work.labels.ummalquraRange || 'Umm al-Qura dates are supported for Hijri years 1300 through 1600.';
                } else if (rangeNotice) {
                    rangeNotice.remove();
                    rangeNotice = null;
                }

                const title = el.querySelector('.fc-toolbar-title');
                const textNode = [...(title?.childNodes ?? [])].find((node) => node.nodeType === Node.TEXT_NODE);
                const lastDate = new Date(view.currentEnd.getTime() - 86400000);
                let label = null;

                if (view.type === 'dayGridMonth') {
                    label = alternateDateLabel(view.currentStart, el, { month: 'long', year: 'numeric' });
                } else if (view.type === 'timeGridDay') {
                    label = alternateDateLabel(view.currentStart, el, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
                } else {
                    const first = alternateDateLabel(view.currentStart, el, { month: 'short', day: 'numeric', year: 'numeric' });
                    const last = alternateDateLabel(lastDate, el, { month: 'short', day: 'numeric', year: 'numeric' });
                    label = first && last ? `${first} - ${last}` : null;
                }

                if (title && label) {
                    if (textNode) {
                        textNode.textContent = label;
                    } else {
                        title.insertBefore(document.createTextNode(label), title.firstChild);
                    }
                }
            }
            try {
                window.sessionStorage.setItem(viewStorageKey, view.type);
            } catch {}

            // The page's URL says which view it is in, so that going back to
            // it (history) returns to this view, not the one it was opened with
            // (the dashboard's agenda links to ?view=listWeek).
            try {
                const url = new URL(window.location.href);

                if (url.searchParams.get('view') !== view.type) {
                    url.searchParams.set('view', view.type);
                    window.history.replaceState(window.history.state, '', url);
                }
            } catch {}
        },
        events: (info, successCallback, failureCallback) => {
            // The month grid draws a timed event as a dot + text row unless
            // the event itself says 'block' (a view-level eventDisplay
            // option does not reach it).
            wire.getEvents(info.startStr, info.endStr)
                .then((events) => successCallback(events.map((event) => ({
                    ...event,
                    // Light mode: very light tint of the event color behind very
                    // dark text of the same hue. Dark mode: the color at low
                    // opacity over the page, with light text of the same hue.
                    // Either way the color itself is the border.
                    ...(event.color ? (document.documentElement.classList.contains('dark') ? {
                        backgroundColor: event.finished
                            ? 'color-mix(in srgb, white 10%, var(--gray-950))'
                            : `color-mix(in srgb, ${event.color} 22%, var(--gray-950))`,
                        borderColor: event.finished ? 'rgb(255 255 255 / 0.25)' : event.color,
                        textColor: event.finished
                            ? '#d1d5db'
                            : `color-mix(in srgb, ${event.color} 35%, white)`,
                    } : {
                        backgroundColor: event.finished ? '#f3f4f6' : `color-mix(in srgb, ${event.color} 14%, white)`,
                        borderColor: event.color,
                        textColor: `color-mix(in srgb, ${event.color} 30%, black)`,
                    }) : {}),
                    ...(calendar.view.type === 'dayGridMonth' ? { display: 'block' } : {}),
                }))))
                .catch(failureCallback);
        },
        eventContent: (info) => {
            const content = document.createElement('span');
            content.className = 'epesi-calendar-event-content';

            // Phones, week and month views: the columns are too narrow for
            // text, so an event is just a bar with its type icon.
            const bubble = mobileQuery.matches && ['timeGridWeek', 'timeGridSevenDay', 'dayGridMonth'].includes(info.view.type);

            if (bubble) {
                content.classList.add('epesi-calendar-event-bubble');
            }

            if (info.timeText && !bubble) {
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

            if (!bubble || !iconHtml) {
                const title = document.createElement('span');
                title.className = 'epesi-calendar-event-title';
                title.textContent = info.event.title;
                content.append(title);
            }

            return { domNodes: [content] };
        },
        eventDidMount: ({ el, event, view }) => {
            // List rows are plain table rows: give them the same tint and text
            // color as the event blocks of the other views.
            if (view.type.startsWith('list') && event.backgroundColor) {
                el.style.backgroundColor = event.backgroundColor;
                el.style.color = event.textColor;
                el.querySelectorAll('td').forEach((cell) => {
                    cell.style.backgroundColor = event.backgroundColor;
                    cell.style.color = event.textColor;
                });
                el.querySelectorAll('a').forEach((link) => { link.style.color = event.textColor; });
            }

            const tooltip = event.extendedProps.tooltip;
            const listView = view.type.startsWith('list');

            // The whole row is the link, not just the title text.
            // Handled here rather than by eventClick, which doesn't fire for a
            // click on the list row's empty cells.
            if (listView && event.url) {
                el.style.cursor = 'pointer';
                el.addEventListener('click', (e) => {
                    if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey || e.button !== 0) {
                        return;
                    }

                    e.preventDefault();
                    e.stopPropagation();
                    window.Livewire.navigate(event.url);
                });
            }

            if (tooltip && !(listView && coarsePointer.matches)) {
                // Touch: a tap shows the tooltip (like hovering), a tap on the
                // tooltip opens the record, a tap anywhere else closes it.
                const touch = coarsePointer.matches && event.url;
                const content = touch
                    ? `<div class="epesi-calendar-tooltip-link" data-event-url="${event.url.replace(/"/g, '&quot;')}">${tooltip}</div>`
                    : tooltip;

                el.setAttribute('x-tooltip', `{ content: ${JSON.stringify(content)}, theme: $store.theme, allowHTML: true, appendTo: document.body, zIndex: 100000${touch ? ', interactive: true' : ''} }`);
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

            if (!info.event.url || info.view.type.startsWith('list') || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) {
                return;
            }

            e.preventDefault();

            // A tap on a touch screen only shows the tooltip; tapping the
            // tooltip (see eventDidMount) opens the record. The list view has
            // no tooltip on touch: a tap on a row opens the record.
            if (coarsePointer.matches && !info.view.type.startsWith('list')) {
                return;
            }

            window.Livewire.navigate(info.event.url);
        },
    });

    // Coming back to the page (history) Livewire restores its cached HTML,
    // calendar included: start from an empty element so that stale markup of
    // the previous view isn't adopted by the new calendar.
    el.replaceChildren();
    calendar.render();

    // Event colors differ between light and dark mode: refetch on a theme switch.
    let wasDark = document.documentElement.classList.contains('dark');
    new MutationObserver(() => {
        const isDark = document.documentElement.classList.contains('dark');
        if (isDark !== wasDark) {
            wasDark = isDark;
            calendar.refetchEvents();
        }
    }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

    // The tooltip of an event on a touch screen is a link to its record.
    if (!window.epesiCalendarTooltipLinks) {
        window.epesiCalendarTooltipLinks = true;
        document.addEventListener('click', (e) => {
            const link = e.target.closest('.epesi-calendar-tooltip-link');

            if (link?.dataset.eventUrl) {
                window.Livewire.navigate(link.dataset.eventUrl);
            }
        });
    }

    if (alternateCalendar) {
        el.addEventListener('click', (event) => {
            const button = event.target.closest('.fc-prev-button, .fc-next-button');
            if (!button || calendar.view.type !== 'dayGridMonth') {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();
            moveAlternateMonth(calendar, alternateCalendar, button.classList.contains('fc-prev-button') ? -1 : 1);
        }, true);
    }

    initMobileToolbar(el, calendar, mobileQuery);
    initTitleDatePicker(el, calendar, alternateCalendar);
    initWorkingHours(el, calendar, wire, work);
};

// Phones only (the CSS hides the prev/next buttons and the view buttons
// there): the views become a select, and a horizontal swipe on the grid goes
// to the previous / next period.
function initMobileToolbar(el, calendar, mobileQuery) {
    const buttons = [...el.querySelectorAll('.fc-toolbar-chunk .fc-button-group > button')]
        .filter((button) => /fc-(\w+)-button/.test(button.className) && !/fc-(prev|next|today)-button/.test(button.className));

    if (buttons.length) {
        const select = document.createElement('select');
        select.className = 'epesi-calendar-view-select';
        const viewOf = (button) => button.className.match(/fc-(\w+)-button/)[1];

        buttons.forEach((button) => {
            const option = document.createElement('option');
            option.value = viewOf(button);
            option.textContent = button.textContent.trim();
            select.append(option);
        });

        select.value = calendar.view.type;
        select.addEventListener('change', () => calendar.changeView(select.value));
        calendar.on('datesSet', ({ view }) => { select.value = view.type; });

        buttons[0].closest('.fc-toolbar-chunk').prepend(select);
    }

    // Header cells are built per rendering: redo them when the breakpoint flips.
    mobileQuery.addEventListener('change', () => {
        const fn = calendar.getOption('dayHeaderContent');
        calendar.setOption('dayHeaderContent', (arg) => fn(arg));
        const views = calendar.getOption('views');
        calendar.setOption('views', { ...views, listWeek: { ...views.listWeek, displayEventEnd: !mobileQuery.matches } });
        const content = calendar.getOption('eventContent');
        calendar.setOption('eventContent', (arg) => content(arg));
    });

    let start = null;
    el.addEventListener('touchstart', (e) => {
        start = mobileQuery.matches && e.touches.length === 1
            ? { x: e.touches[0].clientX, y: e.touches[0].clientY }
            : null;
    }, { passive: true });
    el.addEventListener('touchend', (e) => {
        if (!start) {
            return;
        }
        const dx = e.changedTouches[0].clientX - start.x;
        const dy = e.changedTouches[0].clientY - start.y;
        start = null;

        if (Math.abs(dx) > 60 && Math.abs(dx) > Math.abs(dy) * 1.5) {
            // Swipe left shows the next period, swipe right the previous.
            dx < 0 ? calendar.next() : calendar.prev();
        }
    }, { passive: true });
}

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
function initTitleDatePicker(el, calendar, alternateCalendar) {
    const titleEl = el.querySelector('.fc-toolbar-title');

    if (!titleEl) {
        return;
    }

    const input = document.createElement('input');
    input.type = alternateCalendar ? 'text' : 'date';
    if (alternateCalendar) {
        input.placeholder = 'YYYY-MM-DD';
    }
    input.className = 'epesi-calendar-title-picker';
    input.setAttribute('aria-hidden', 'true');
    input.tabIndex = -1;

    titleEl.classList.add('epesi-calendar-title-clickable');
    titleEl.setAttribute('role', 'button');
    titleEl.setAttribute('tabindex', '0');
    titleEl.appendChild(input);

    const open = () => {
        const date = calendar.getDate();
        if (alternateCalendar) {
            input.value = calendarDateInputValue(date, alternateCalendar) ?? '';
        } else {
            input.value = date.toISOString().slice(0, 10);
        }

        if (typeof input.showPicker === 'function') {
            input.showPicker();
        } else {
            input.focus();
        }
    };

    input.addEventListener('change', () => {
        if (input.value) {
            if (!alternateCalendar) {
                calendar.gotoDate(input.value);

                return;
            }

            input.value = normalizeCalendarDigits(input.value);
            const match = input.value.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
            const date = match
                ? gregorianDateForCalendarDate(Number(match[1]), Number(match[2]), Number(match[3]), alternateCalendar)
                : null;

            if (date) {
                calendar.gotoDate(date);
                input.setCustomValidity('');
            } else {
                input.setCustomValidity('Enter a valid date in YYYY-MM-DD format.');
                input.reportValidity();
            }
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
