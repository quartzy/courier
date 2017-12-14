<?php

declare(strict_types=1);

namespace Courier\Test;

use Courier\SwiftMailerCourier;
use Mockery;
use PhpEmail\Attachment\FileAttachment;
use PhpEmail\Content\SimpleContent;
use PhpEmail\EmailBuilder;
use Swift_Mailer;
use Swift_Message;

/**
 * @covers \Courier\SwiftMailerCourier
 */
class SwiftMailerCourierTest extends TestCase
{
    /**
     * @var string
     */
    private static $file = '/tmp/swift_mailer_attachment_test.txt';

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

    /**
     * @testdox It should send a Swift_Mailer email
     */
    public function sendsASwiftMailerEmail()
    {
        $mailer    = Mockery::mock(Swift_Mailer::class);
        $courier   = new SwiftMailerCourier($mailer);

        $email = EmailBuilder::email()
            ->from('from@test.com')
            ->to('to@test.com')
            ->withSubject('SwiftMailer Courier')
            ->withContent(new SimpleContent('Html body', 'Test body'))
            ->attach(new FileAttachment(self::$file, 'test.txt'))
            ->build();

        $mailer
            ->shouldReceive('send')
            ->once()
            ->with(Mockery::on(function (Swift_Message $message) {
                return $message->getFrom() === ['from@test.com' => null]
                && $message->getTo() === ['to@test.com' => null]
                && $message->getSubject() === 'SwiftMailer Courier'
                && $message->getBody() === 'Html body';
            }));

        $courier->deliver($email);
    }
}
