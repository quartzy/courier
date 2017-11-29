<?php

declare(strict_types=1);

namespace Courier;

use Courier\Exceptions\ReceiptException;
use PhpEmail\Email;

trait SavesReceipts
{
    /**
     * @var string[]
     */
    protected $receipts = [];

    /**
     * @param Email  $email
     * @param string $receipt
     *
     * @return void
     */
    protected function saveReceipt(Email $email, string $receipt): void
    {
        $this->receipts[spl_object_hash($email)] = $receipt;
    }

    /**
     * @param Email $email
     *
     * @return string
     */
    public function receiptFor(Email $email): string
    {
        if ($receipt = $this->receipts[spl_object_hash($email)] ?? null) {
            return $receipt;
        }

        throw new ReceiptException();
    }
}
