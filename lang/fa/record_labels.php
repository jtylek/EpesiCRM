<?php

// Shared record vocabulary (App\Enums\Record*) whose words need a key of their
// own rather than the English text — see RecordStatus::getLabel().
return [
    'status' => [
        'open' => 'باز',
        'in_progress' => 'در حال انجام',
        'on_hold' => 'در انتظار',
        'closed' => 'بسته',
        'canceled' => 'لغو شده',
    ],
];
