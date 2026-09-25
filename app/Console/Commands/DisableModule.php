<?php

namespace App\Console\Commands;

class DisableModule extends SetModuleState
{
    protected $signature = 'module:disable {module : module id, e.g. epesi/notes}';

    protected $description = 'Disable an installed module without removing it';

    protected function shouldEnable(): bool
    {
        return false;
    }
}
