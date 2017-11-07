<?php

declare(strict_types=1);

namespace Courier\Exceptions;

use Exception;

class TransmissionException extends \RuntimeException implements CourierException
{
    public function __construct($code = 0, Exception $previous = null)
    {
        parent::__construct('There was an error communicating with the courier provider.', $code, $previous);
    }
}
