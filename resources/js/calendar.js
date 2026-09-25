import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin from '@fullcalendar/interaction';
import plLocale from '@fullcalendar/core/locales/pl';
import '../css/calendar.css';

// Called from resources/views/filament/pages/calendar.blade.php's x-init.
// $wire.getEvents(...) calls straight into App\Filament\Pages\Calendar::getEvents()
// (a Livewire component method) as FullCalendar's event source — see that
// class for why this replaces Epesi's separate JSON ajax.php endpoint.
window.initEpesiCalendar = function (el, wire) {
    const calendar = new Calendar(el, {
        plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
        initialView: 'dayGridMonth',
        // The page's language (config/app.php's available_locales); English
        // is FullCalendar's built-in default.
        locales: [plLocale],
        locale: el.dataset.locale || 'en',
        // Every date this calendar sends/receives is a floating wall-clock
        // value with no real timezone attached (see CalendarEvent::toArray()) —
        // 'UTC' here means "take these strings literally", not "convert to
        // UTC", which is what keeps a visiting browser's own timezone from
        // silently shifting displayed times or drag-reschedule writes.
        timeZone: 'UTC',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
        },
        height: 'auto',
        editable: true,
        events: (info, successCallback, failureCallback) => {
            wire.getEvents(info.startStr, info.endStr).then(successCallback).catch(failureCallback);
        },

        // Drag-move or resize: optimistically already moved by FullCalendar:
        // persist via App\Filament\Pages\Calendar::rescheduleEvent(), revert
        // on "not found"/"not authorized" (false) or a request failure.
        eventDrop: (info) => persistReschedule(wire, info),
        eventResize: (info) => persistReschedule(wire, info),

        // Empty-cell click: opens the "New event" type picker
        // (App\Filament\Pages\Calendar::createEventAction()). Clicking an
        // existing event still goes through FullCalendar's own url-based
        // navigation, unaffected — this only fires on empty cells.
        dateClick: (info) => {
            wire.mountAction('createEvent', { date: info.dateStr, allDay: info.allDay });
        },
    });

    calendar.render();
};

function persistReschedule(wire, info) {
    wire.rescheduleEvent(info.event.id, info.event.startStr, info.event.endStr || null, info.event.allDay)
        .then((ok) => {
            if (!ok) {
                info.revert();
            }
        })
        .catch(() => info.revert());
}
