<?php

namespace Epesi\Modules\MigrationWizard\Cleanup;

use RuntimeException;

/** Cleanup refused or failed BEFORE it removed anything: nothing changed, so it can be run again once the cause is fixed. */
final class CleanupRefused extends RuntimeException {}
