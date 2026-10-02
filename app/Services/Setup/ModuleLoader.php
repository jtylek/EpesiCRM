<?php

namespace App\Services\Setup;

use App\Models\Module;
use App\Support\Modules\ModuleManifest;
use Composer\Autoload\ClassLoader;
use Epesi\Modules\RecordBrowser\Recordset\CollectionFields;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;

/**
 * Brings modules installed during this request into it, as the next request
 * would: their PSR-4 namespaces, service providers, and panel plugins. A
 * request that registers modules started without them, and whatever it does
 * next with them (saving records or running a module's own installer) needs
 * their code and resource definitions loaded.
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

        $pluginsAdded = false;

        foreach ($manifests as $manifest) {
            $class = $manifest->pluginClass;

            if (! $class || ! class_exists($class) || ! is_subclass_of($class, Plugin::class)) {
                continue;
            }

            foreach (Filament::getPanels() as $panel) {
                if (! in_array($panel->getId(), $manifest->panels, true)) {
                    continue;
                }

                $plugin = method_exists($class, 'make') ? $class::make() : app($class);

                if ($panel->hasPlugin($plugin->getId())) {
                    continue;
                }

                $panel->plugin($plugin);
                $pluginsAdded = true;
            }
        }

        if ($pluginsAdded) {
            CollectionFields::flush();
        }
    }
}
