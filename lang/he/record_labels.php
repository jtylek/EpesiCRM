<?php

// Shared record vocabulary (App\Enums\Record*) whose words need a key of their
// own rather than the English text — see RecordStatus::getLabel().
return [
    'status' => [
        'open' => 'פתוח',
        'in_progress' => 'בטיפול',
        'on_hold' => 'בהמתנה',
        'closed' => 'סגור',
        'canceled' => 'בוטל',
    ],
];
