<?php

namespace App\Exceptions;

use App\Enums\LoginErrorCode;
use Exception;

class LoginException extends Exception
{
    public function __construct(
        public LoginErrorCode $errorCode,
        public ?int $retryAfter = null,
    ) {
        parent::__construct($errorCode->message());
    }
}
