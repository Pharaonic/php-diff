<?php

declare(strict_types=1);

namespace Pharaonic\Diff\Exceptions;

use Throwable;

/**
 * Implemented by every exception this package throws, so callers can catch them all at once.
 */
interface DiffException extends Throwable {}
