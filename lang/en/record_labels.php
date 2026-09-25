<?php

// Shared record vocabulary (App\Enums\Record*) whose words need a key of their
// own rather than the English text — see RecordStatus::getLabel().
return [
    'status' => [
        'open' => 'Open',
        'in_progress' => 'In Progress',
        'on_hold' => 'On Hold',
        'closed' => 'Closed',
        'canceled' => 'Canceled',
    ],
];
