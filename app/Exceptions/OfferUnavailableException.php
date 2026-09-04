<?php

namespace App\Exceptions;

use RuntimeException;

class OfferUnavailableException extends RuntimeException
{
    public function __construct(string $message = 'This offer is no longer available.')
    {
        parent::__construct($message);
    }
}
