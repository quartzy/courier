<?php

declare(strict_types=1);

namespace Courier\Test;

use Courier\SparkPostCourier;
use GuzzleHttp\Client;
use Http\Adapter\Guzzle6\Client as GuzzleAdapter;
use PhpEmail\Attachment\FileAttachment;
use PhpEmail\Content\SimpleContent;
use PhpEmail\Content\TemplatedContent;
use PhpEmail\EmailBuilder;
use SparkPost\SparkPost;

/**
 * @covers \Courier\SparkPostCourier
 * @large
 */
class SparkPostCourierIntegrationTest extends IntegrationTestCase
{
    /**
     * @var string
     */
    private static $file = '/tmp/sparkpost_attachment_test.txt';

    /**
     * @var SparkPostCourier
     */
    private $courier;

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

    public function setUp(): void
    {
        parent::setUp();

        $this->courier = new SparkPostCourier(
            new SparkPost(new GuzzleAdapter(new Client()), ['key'=>getenv('SPARKPOST_KEY')])
        );
    }

    public function testSendsInlineEmail()
    {
        $subject = 'Courier Integration Test ' . random_int(100000000, 999999999);

        $email = EmailBuilder::email()
            ->to($this->getTo())
            ->from(getenv('FROM_EMAIL'))
            ->withSubject($subject)
            ->withContent(SimpleContent::text('Text')->addHtml('HTML'))
            ->cc($this->getCc())
            ->bcc($this->getBcc())
            ->attach(new FileAttachment(self::$file, 'Attached File'))
            ->embed(new FileAttachment(self::$file, 'Embedded File'), 'embed-test')
            ->addHeader('X-test-header', 'Test')
            ->build();

        $this->courier->deliver($email);

        $message = $this->getEmailDeliveredToTo($subject);

        self::assertEquals($subject, $message->getHeaderValue('subject'));
        self::assertEquals(getenv('FROM_EMAIL'), $message->getHeaderValue('from'));
        self::assertEquals($this->getTo(), $message->getHeaderValue('to'));
        self::assertEquals($this->getCc(), $message->getHeaderValue('cc'));
        self::assertEquals('Test', $message->getHeaderValue('x-test-header'));
        self::assertHasAttachmentWithContentId($message, 'embed-test');
        self::assertHasAttachmentWithName($message, 'Attached File');

        $message = $this->getEmailDeliveredToCc($subject);

        self::assertEquals($subject, $message->getHeaderValue('subject'));
        self::assertEquals(getenv('FROM_EMAIL'), $message->getHeaderValue('from'));
        self::assertEquals($this->getTo(), $message->getHeaderValue('to'));
        self::assertEquals($this->getCc(), $message->getHeaderValue('cc'));
        self::assertEquals('Test', $message->getHeaderValue('x-test-header'));
        self::assertHasAttachmentWithContentId($message, 'embed-test');
        self::assertHasAttachmentWithName($message, 'Attached File');

        $message = $this->getEmailDeliveredToCc($subject);

        self::assertEquals($subject, $message->getHeaderValue('subject'));
        self::assertEquals(getenv('FROM_EMAIL'), $message->getHeaderValue('from'));
        self::assertEquals($this->getTo(), $message->getHeaderValue('to'));
        self::assertEquals($this->getCc(), $message->getHeaderValue('cc'));
        self::assertEquals('Test', $message->getHeaderValue('x-test-header'));
        self::assertHasAttachmentWithContentId($message, 'embed-test');
        self::assertHasAttachmentWithName($message, 'Attached File');
    }

    public function testSendsTemplatedEmail()
    {
        $subject = 'Courier Integration Templated Test ' . random_int(100000000, 999999999);

        $email = EmailBuilder::email()
            ->from(getenv('FROM_EMAIL'))
            ->replyTo(getenv('FROM_EMAIL'))
            ->to($this->getTo(), 'To')
            ->cc($this->getCc(), 'CC')
            ->bcc($this->getBcc(), 'BCC')
            ->withSubject($subject)
            ->withContent(new TemplatedContent(
                getenv('SPARKPOST_TEMPLATE_ID'),
                [
                    'html' => 'HTML<img src="cid:embed-test"/>',
                    'text' => 'text',
                ]
            ))
            ->attach(new FileAttachment(self::$file, 'Attached File'))
            ->embed(new FileAttachment(self::$file, 'Embedded File'), 'embed-test')
            ->addHeader('X-test-header', 'Test')
            ->build();

        $this->courier->deliver($email);

        $message = $this->getEmailDeliveredToTo($subject);

        self::assertEquals($subject, $message->getHeaderValue('subject'));
        self::assertEquals($this->getTo(), $message->getHeaderValue('to'));
        self::assertEquals($this->getCc(), $message->getHeaderValue('cc'));
        self::stringStartsWith('HTML', $message->getHtmlContent());
        self::assertHasAttachmentWithContentId($message, 'embed-test');
        self::assertHasAttachmentWithName($message, 'Attached File');
        self::assertEquals('Test', $message->getHeaderValue('x-test-header'));

        $message = $this->getEmailDeliveredToCc($subject);

        self::assertEquals($subject, $message->getHeaderValue('subject'));
        self::assertEquals($this->getTo(), $message->getHeaderValue('to'));
        self::assertEquals($this->getCc(), $message->getHeaderValue('cc'));
        self::assertStringStartsWith('HTML', $message->getHtmlContent());
        self::assertEquals('text', trim($message->getTextContent()));
        self::assertHasAttachmentWithContentId($message, 'embed-test');
        self::assertHasAttachmentWithName($message, 'Attached File');
        self::assertEquals('Test', $message->getHeaderValue('x-test-header'));

        $message = $this->getEmailDeliveredToBcc($subject);

        self::assertEquals($subject, $message->getHeaderValue('subject'));
        self::assertEquals($this->getTo(), $message->getHeaderValue('to'));
        self::assertEquals($this->getCc(), $message->getHeaderValue('cc'));
        self::assertStringStartsWith('HTML', $message->getHtmlContent());
        self::assertEquals('text', trim($message->getTextContent()));
        self::assertHasAttachmentWithContentId($message, 'embed-test');
        self::assertHasAttachmentWithName($message, 'Attached File');
        self::assertEquals('Test', $message->getHeaderValue('x-test-header'));
    }
}
