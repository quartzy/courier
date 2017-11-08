<?php

declare(strict_types=1);

namespace Courier;

use PhpEmail\Email;

interface ConfirmingCourier extends Courier
{
    /**
     * Get the receipt from the latest.
     *
     * @param Email $email
     *
     * @return string
     */
    public function receiptFor(Email $email): string;
}
