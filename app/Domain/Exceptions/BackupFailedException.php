<?php

namespace App\Domain\Exceptions;

use RuntimeException;

/**
 * A backup or restore attempt failed. Always recorded on the ledger row before
 * it is rethrown — a failure that is only in a log line is a failure nobody
 * will find.
 */
class BackupFailedException extends RuntimeException {}
