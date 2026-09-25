<?php

namespace App\Services\Modules;

use RuntimeException;

/**
 * Any refusal to install/validate a module. The message is shown verbatim to
 * the super_admin who triggered the action, so it must say what was wrong with
 * the archive, not just that something was.
 */
class ModuleException extends RuntimeException {}
