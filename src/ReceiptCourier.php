<?php

declare(strict_types=1);

namespace Courier;

use PhpEmail\Email;

interface ReceiptCourier extends Courier
{
    /**
     * Get the receipt from the latest.
     *
     * @param Email $email
     *
     * @return string
     */
    public function receipt(Email $email): string;
}
