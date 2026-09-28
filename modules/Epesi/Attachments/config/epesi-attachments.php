<?php

return [
    /*
     * The legacy Epesi install's data/ directory, for `import:legacy
     * attachments`: files live there under data/Utils_FileStorage, content-
     * addressed the same way App\Services\FileStorage stores them here.
     * Shared with CRM/Mail's own legacy import (see epesi-mail.php).
     */
    'legacy_data_dir' => env('LEGACY_DATA_DIR'),
];
