<?php

namespace App\Exceptions;

use RuntimeException;

class DuplicateReservationException extends RuntimeException
{
    public function __construct(string $message = 'This reservation has already been made.')
    {
        parent::__construct($message);
    }
}
