<?php

namespace Courier;

use Courier\Exceptions\TransmissionException;
use Courier\Exceptions\UnsupportedContentException;
use PhpEmail\Email;

interface Courier
{
    /**
     * @param Email $email
     *
     * @throws TransmissionException
     * @throws UnsupportedContentException
     *
     * @return void
     */
    public function deliver(Email $email);
}
