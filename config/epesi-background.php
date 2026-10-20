<?php

return [
    'enabled' => (bool) env('EPESI_BACKGROUND_WORKER_ENABLED', false),
    'queue' => 'epesi-background',
    'retry_after' => (int) env('EPESI_BACKGROUND_RETRY_AFTER', 90),
];
