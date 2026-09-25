<?php

namespace App\Services\Setup;

use App\Models\Module;
use App\Support\Modules\ModuleManifest;
use Composer\Autoload\ClassLoader;

/**
 * Brings modules installed during this request into it, as the next request
 * would: their PSR-4 namespaces and service providers. A request that
 * registers modules started without them, and whatever it does next with
 * them (saving a record that needs the CRM morph aliases, running a module's
 * own installer) needs them loaded.
 */
class ModuleLoader
{
    /**
     * @param  iterable<ModuleManifest>  $manifests
     */
    public static function load(iterable $manifests): void
    {
        $loader = new ClassLoader;

        foreach ($manifests as $manifest) {
            $loader->addPsr4($manifest->namespace, Module::directoryFor($manifest->path).DIRECTORY_SEPARATOR.'src');
        }

        $loader->register();

        foreach ($manifests as $manifest) {
            if ($manifest->providerClass && class_exists($manifest->providerClass) && ! app()->getProvider($manifest->providerClass)) {
                app()->register($manifest->providerClass);
            }
        }
    }
}
