<?php

declare(strict_types=1);

namespace Courier\Test;

use Courier\Exceptions\ReceiptException;
use Courier\SavesReceipts;
use PhpEmail\Content\EmptyContent;
use PhpEmail\EmailBuilder;

/**
 * @covers \Courier\SavesReceipts
 */
class SavesReceiptsTest extends TestCase
{
    use SavesReceipts;

    /**
     * @testdox It should save the receipt for an email
     */
    public function savesReceipt()
    {
        $email = EmailBuilder::email()
            ->to('to@test.com')
            ->from('from@test.com')
            ->withSubject('Test Subject')
            ->withContent(new EmptyContent())
            ->build();

        $this->saveReceipt($email, 'first');

        self::assertEquals('first', $this->receiptFor($email));
    }

    /**
     * @testdox It should save each email's receipt separately
     */
    public function savesMultipleReceipts()
    {
        $first = EmailBuilder::email()
            ->to('to@test.com')
            ->from('from@test.com')
            ->withSubject('Test Subject')
            ->withContent(new EmptyContent())
            ->build();

        $this->saveReceipt($first, 'first');

        $second = EmailBuilder::email()
            ->to('to@test.com')
            ->from('from@test.com')
            ->withSubject('Test Subject')
            ->withContent(new EmptyContent())
            ->build();

        $this->saveReceipt($second, 'second');

        self::assertEquals('first', $this->receiptFor($first));
        self::assertEquals('second', $this->receiptFor($second));
    }

    /**
     * @testdox It should throw an error if there is no receipt for the email
     */
    public function throwsOnMissingReceipt()
    {
        self::expectException(ReceiptException::class);

        $email = EmailBuilder::email()
            ->to('to@test.com')
            ->from('from@test.com')
            ->withSubject('Test Subject')
            ->withContent(new EmptyContent())
            ->build();

        $this->receiptFor($email);
    }

}
