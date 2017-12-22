<?php

declare(strict_types=1);

namespace Courier\Test;

use Courier\Exceptions\UnsupportedContentException;
use Courier\SwiftMailerCourier;
use Mockery;
use PhpEmail\Attachment\FileAttachment;
use PhpEmail\Attachment\ResourceAttachment;
use PhpEmail\Content\SimpleContent;
use PhpEmail\Content\TemplatedContent;
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
        $mailer    = Mockery::spy(Swift_Mailer::class);
        $courier   = new SwiftMailerCourier($mailer);

        $email = EmailBuilder::email()
            ->from('from@test.com')
            ->to('to@test.com')
            ->cc('cc@test.com')
            ->bcc('bcc@test.com', 'Bcc')
            ->replyTo('reply.to@test.com', 'Reply To')
            ->withSubject('SwiftMailer Courier')
            ->withContent(new SimpleContent('Html body', 'Text body'))
            ->attach(new FileAttachment(self::$file, 'test.txt'))
            ->attach(new ResourceAttachment(fopen(self::$file, 'r'), 'other.txt'))
            ->build();

        $mailer
            ->shouldReceive('send')
            ->once()
            ->with(Mockery::on(function (Swift_Message $message) {
                $fileAttachment     = $message->getChildren()[1];
                $resourceAttachment = $message->getChildren()[2];

                return $message->getFrom() === ['from@test.com' => null]
                && $message->getTo() === ['to@test.com' => null]
                && $message->getCc() === ['cc@test.com' => null]
                && $message->getBcc() === ['bcc@test.com' => 'Bcc']
                && $message->getReplyTo() === ['reply.to@test.com' => 'Reply To']
                && $message->getSubject() === 'SwiftMailer Courier'
                && $message->getBody() === 'Html body'
                // Verify the text body was added
                && $message->getChildren()[0]->getBody() === 'Text body'
                // Verify the attachment was added
                && $fileAttachment->getBody() === 'Attachment file'
                //Verify the resource attachment is added
                && $resourceAttachment->getHeaders()->get('Content-Disposition')->getFieldBody() === 'attachment; filename=other.txt';
            }));

        $courier->deliver($email);
    }

    /**
     * @testdox It should handle text only emails
     */
    public function supportsTextOnly()
    {
        $mailer    = Mockery::spy(Swift_Mailer::class);
        $courier   = new SwiftMailerCourier($mailer);

        $email = EmailBuilder::email()
            ->from('from@test.com')
            ->to('to@test.com')
            ->withSubject('SwiftMailer Courier')
            ->withContent(SimpleContent::text('Text body'))
            ->build();

        $mailer
            ->shouldReceive('send')
            ->once()
            ->with(Mockery::on(function (Swift_Message $message) {
                return $message->getFrom() === ['from@test.com' => null]
                    && $message->getTo() === ['to@test.com' => null]
                    && $message->getSubject() === 'SwiftMailer Courier'
                    && $message->getBody() === 'Text body'
                    && $message->getContentType() === 'text/plain';
            }));

        $courier->deliver($email);
    }

    /**
     * @testdox It should handle HTML only emails
     */
    public function supportsHtmlOnly()
    {
        $mailer    = Mockery::spy(Swift_Mailer::class);
        $courier   = new SwiftMailerCourier($mailer);

        $email = EmailBuilder::email()
            ->from('from@test.com')
            ->to('to@test.com')
            ->withSubject('SwiftMailer Courier')
            ->withContent(SimpleContent::html('HTML body'))
            ->build();

        $mailer
            ->shouldReceive('send')
            ->once()
            ->with(Mockery::on(function (Swift_Message $message) {
                return $message->getFrom() === ['from@test.com' => null]
                    && $message->getTo() === ['to@test.com' => null]
                    && $message->getSubject() === 'SwiftMailer Courier'
                    && $message->getBody() === 'HTML body'
                    && $message->getContentType() === 'text/html';
            }));

        $courier->deliver($email);
    }

    /**
     * @testdox It should not support templated content
     */
    public function doesNotSupportTemplatedContent()
    {
        self::expectException(UnsupportedContentException::class);

        $mailer    = Mockery::mock(Swift_Mailer::class);
        $courier   = new SwiftMailerCourier($mailer);

        $email = EmailBuilder::email()
            ->from('from@test.com')
            ->to('to@test.com')
            ->withSubject('SwiftMailer Courier')
            ->withContent(new TemplatedContent('test', []))
            ->build();

        $mailer->shouldNotReceive('send');

        $courier->deliver($email);
    }
}
