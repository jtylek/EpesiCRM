<?php

namespace App\Console\Commands;

class EnableModule extends SetModuleState
{
    protected $signature = 'module:enable {module : module id, e.g. epesi/notes}';

    protected $description = 'Enable an installed module';

    protected function shouldEnable(): bool
    {
        return true;
    }
}
