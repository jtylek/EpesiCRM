<?php

use App\Filament\Widgets\AgendaWidget;
use Epesi\Modules\CRM\PhoneCalls\Filament\Widgets\PhoneCallsWidget;
use Epesi\Modules\CRM\Tasks\Filament\Widgets\TasksWidget;
use Epesi\Modules\PriorityList\Filament\Widgets\PriorityListWidget;
use Epesi\Modules\Reminders\Filament\Widgets\MyRemindersWidget;
use Epesi\Modules\Shoutbox\Filament\Widgets\ShoutboxWidget;
use Epesi\Modules\StickyNotes\Filament\Widgets\StickyNotesWidget;

return [

    /*
     * The dashboard every user gets on their first visit, in place of
     * Epesi's administrator-made default dashboard: tab name => its three
     * columns, left to right, each a list of applets top to bottom. An
     * applet whose module isn't installed or enabled is left out, and a tab
     * left with none is skipped. Tab names go through __(). A tab named in
     * 'keys' is a system tab: every dashboard has it and it can't be deleted.
     */
    'keys' => [
        'Main' => 'main',
        'Agenda' => 'agenda',
        'Notes' => 'notes',
    ],

    'default' => [
        'Main' => [
            [PriorityListWidget::class],
            [ShoutboxWidget::class],
            [MyRemindersWidget::class],
        ],
        'Agenda' => [
            [AgendaWidget::class],
            [TasksWidget::class],
            [PhoneCallsWidget::class],
        ],
        // One applet that takes the whole row: small colored notes.
        'Notes' => [
            [StickyNotesWidget::class],
            [],
            [],
        ],
    ],

];
