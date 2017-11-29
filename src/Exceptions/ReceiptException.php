<?php

declare(strict_types=1);

namespace Courier\Exceptions;

use Throwable;

class ReceiptException extends \RuntimeException implements CourierException
{
    public function __construct($code = 0, Throwable $previous = null)
    {
        parent::__construct('Unable able to find receipt for email.', $code, $previous);
    }
}
