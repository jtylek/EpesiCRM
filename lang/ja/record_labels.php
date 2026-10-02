<?php

// Shared record vocabulary (App\Enums\Record*) whose words need a key of their
// own rather than the English text — see RecordStatus::getLabel().
//
// Machine translation (DeepL): "open" here needs a native-speaker check. It is
// the state of a record (an adjective, like Polish's "Otwarte"), but DeepL kept
// giving the verb ("to open" / an Open button) despite the context hint, the
// same confusion the Polish table in AI-shared/Epesi-Laravel-Translations.md
// records for this exact word.
return [
    'status' => [
        'open' => '開く',
        'in_progress' => '進行中',
        'on_hold' => '保留中',
        'closed' => '完了',
        'canceled' => 'キャンセル済み',
    ],
];
