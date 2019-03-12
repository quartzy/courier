<?php

declare(strict_types=1);

namespace Courier\Test;

use Courier\LoggingCourier;
use PhpEmail\Attachment\FileAttachment;
use PhpEmail\Content\SimpleContent;
use PhpEmail\Content\TemplatedContent;
use PhpEmail\EmailBuilder;
use Psr\Log\NullLogger;

class LoggingCourierTest extends TestCase
{
    /**
     * @var string
     */
    private static $file = '/tmp/mail_attachment_test.txt';

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        file_put_contents(self::$file, 'Attachment file');
    }

    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();

        unlink(self::$file);
    }

    public function testSendsEmails()
    {
        $subject = 'Test Email ' . bin2hex(random_bytes(8));

        $email = EmailBuilder::email()
            ->withSubject($subject)
            ->from('from@test.com', 'From Testerson')
            ->to('to@test.com', 'To Testerson')
            ->withContent(SimpleContent::text('Test Email')->addHtml('<b>Test Email</b><img src="cid:beaker" />'))
            ->cc('cc@test.com', 'CC Testerson')
            ->cc('cc2@test.com', 'CC Two Testerson')
            ->bcc('bcc@test.com', 'BCC Testerson')
            ->replyTo('reply@test.com', 'Reply Testerson')
            ->replyTo('reply2@test.com', 'Reply Two Testerson')
            ->attach(FileAttachment::fromFile(self::$file, 'Test Attachment', null, 'plain/text'))
            ->embed(FileAttachment::fromFile(__DIR__ . '/beaker.jpg', 'beaker_pic', 'beaker'))
            ->addHeader('X-Test', 'test-value')
            ->addHeader('X-Other', 'other-value')
            ->build();

        $courier = new LoggingCourier(new NullLogger());

        $courier->deliver($email);

        self::assertTrue(true);
    }

    public function testSendsTemplatedEmail()
    {
        $subject = 'Test Email ' . bin2hex(random_bytes(8));

        $email = EmailBuilder::email()
            ->withSubject($subject)
            ->from('from@test.com', 'From Testerson')
            ->to('to@test.com', 'To Testerson')
            ->withContent(new TemplatedContent('test-template', ['one' => 1, 'two' => 2]))
            ->build();

        $courier = new LoggingCourier(new NullLogger());

        $courier->deliver($email);

        self::assertTrue(true);
    }
}
