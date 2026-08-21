<?php

namespace App\Domain\Exceptions;

use RuntimeException;

/**
 * No usable backup destination could be resolved.
 *
 * Deliberately an exception and not a null return. Every other infrastructure
 * type treats "nothing configured" as "carry on with the global default", and
 * copying that reflex here would mean a tenant silently stops being backed up
 * — a failure nobody notices until the day a restore is needed, which is the
 * worst possible day to find out.
 */
class BackupDestinationException extends RuntimeException {}
