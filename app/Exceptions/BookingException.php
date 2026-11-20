<?php

namespace App\Exceptions;

use RuntimeException;

class BookingException extends RuntimeException
{
    /** ขาที่มีปัญหา: outbound | return | null */
    public function __construct(string $message, public readonly ?string $direction = null)
    {
        parent::__construct($message);
    }
}
