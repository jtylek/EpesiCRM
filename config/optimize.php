<?php

return [

    /*
     * Laravel's config, event and route caches, Filament's component cache
     * and Blade Icons' manifest, built and kept current by cron
     * (`epesi:optimize`, App\Support\Optimize\FrameworkCaches). They make
     * every page 100-150 ms faster.
     *
     * On by default for an installation: production, and not a git checkout,
     * where a cached config would also outlive phpunit.xml and send the
     * tests to the real database. EPESI_OPTIMIZE=true or false decides
     * otherwise.
     */
    'enabled' => (bool) env('EPESI_OPTIMIZE', env('APP_ENV') === 'production' && ! is_dir(base_path('.git'))),

];
