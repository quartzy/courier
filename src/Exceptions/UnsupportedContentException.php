<?php

declare(strict_types=1);

namespace Courier\Exceptions;

use Exception;
use PhpEmail\Content;

class UnsupportedContentException extends \InvalidArgumentException implements CourierException
{
    public function __construct(Content $provided, $code = 0, Exception $previous = null)
    {
        parent::__construct(sprintf('The content type %s is not supported.', get_class($provided)), $code, $previous);
    }
}
