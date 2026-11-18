<?php

namespace Platform\ManagementBook\Exceptions;

use RuntimeException;

/** Fachlicher Fehler im Management-Book-Service (Validierung, ungültiger Übergang). */
class ManagementBookException extends RuntimeException
{
    public function __construct(string $message, public readonly string $errorCode = 'VALIDATION_ERROR')
    {
        parent::__construct($message);
    }
}
