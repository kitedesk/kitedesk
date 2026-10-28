<?php

namespace App\Domain\Support\Extensions;

use RuntimeException;

/**
 * A module cannot be switched on or off; the message is written for the admin.
 */
class ModuleException extends RuntimeException {}
