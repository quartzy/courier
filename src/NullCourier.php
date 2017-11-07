<?php

declare(strict_types=1);

namespace Courier;

use Courier\Exceptions\TransmissionException;
use Courier\Exceptions\UnsupportedContentException;
use PhpEmail\Email;

/**
 * A courier that accepts an email but does nothing with it. This implementation should be used in testing.
 *
 * @codeCoverageIgnore
 */
class NullCourier implements Courier
{
    /**
     * @param Email $email
     *
     * @throws TransmissionException
     * @throws UnsupportedContentException
     *
     * @return void
     */
    public function deliver(Email $email)
    {
    }
}
