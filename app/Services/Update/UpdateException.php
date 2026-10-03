<?php

namespace App\Services\Update;

use RuntimeException;

/** A core update that was refused or failed; the message is meant for the administrator. */
class UpdateException extends RuntimeException {}
