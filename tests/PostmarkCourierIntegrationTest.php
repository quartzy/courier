<?php

declare(strict_types=1);

namespace Courier\Test;

use Courier\PostmarkCourier;
use Courier\SparkPostCourier;
use PhpEmail\Attachment\FileAttachment;
use PhpEmail\Content\SimpleContent;
use PhpEmail\Content\TemplatedContent;
use PhpEmail\EmailBuilder;
use Postmark\PostmarkClient;

/**
 * @covers \Courier\PostmarkCourier
 * @large
 */
class PostmarkCourierIntegrationTest extends IntegrationTestCase
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

        $this->courier = new PostmarkCourier(new PostmarkClient(getenv('POSTMARK_KEY')));
    }

    public function testSendsInlineEmail()
    {
        $inbox    = $this->createInbox();
        $ccInbox  = $this->createInbox();
        $bccInbox = $this->createInbox();

        $email = EmailBuilder::email()
            ->to($inbox->getAddress())
            ->from(getenv('POSTMARK_SENDER'))
            ->withSubject('Courier Integration Test')
            ->withContent(SimpleContent::text('text')->addHtml('HTML<img src="cid:embed-test"/>'))
            ->cc($ccInbox->getAddress())
            ->bcc($bccInbox->getAddress())
            ->attach(new FileAttachment(self::$file, 'Attached File'))
            ->embed(new FileAttachment(self::$file, 'Embedded File', null, 'image/jpeg'), 'embed-test')
            ->addHeader('X-test-header', 'Test')
            ->build();

        $this->courier->deliver($email);

        $message = $this->getLatestEmail($inbox);

        self::assertEquals('Courier Integration Test', $message->getHeaderValue('subject'));
        self::assertEquals(getenv('POSTMARK_SENDER'), $message->getHeaderValue('from'));
        self::assertEquals($inbox->getAddress(), $message->getHeaderValue('to'));
        self::assertEquals($ccInbox->getAddress(), $message->getHeaderValue('cc'));
        self::stringStartsWith('HTML', $message->getHtmlContent());
        self::assertEquals('text', trim($message->getTextContent()));
        self::assertHasAttachmentWithContentId($message, 'embed-test');
        self::assertHasAttachmentWithName($message, 'Attached File');
        self::assertEquals('Test', $message->getHeaderValue('x-test-header'));

        $message = $this->getLatestEmail($ccInbox);

        self::assertEquals('Courier Integration Test', $message->getHeaderValue('subject'));
        self::assertEquals(getenv('POSTMARK_SENDER'), $message->getHeaderValue('from'));
        self::assertEquals($inbox->getAddress(), $message->getHeaderValue('to'));
        self::assertEquals($ccInbox->getAddress(), $message->getHeaderValue('cc'));
        self::stringStartsWith('HTML', $message->getHtmlContent());
        self::assertEquals('text', trim($message->getTextContent()));
        self::assertHasAttachmentWithContentId($message, 'embed-test');
        self::assertHasAttachmentWithName($message, 'Attached File');
        self::assertEquals('Test', $message->getHeaderValue('x-test-header'));

        // @TODO MailSlurp doesn't yet support BCC, but will soon //$message = $this->getLatestEmail($bccInbox);
    }

    public function testSendsTemplatedEmail()
    {
        $inbox    = $this->createInbox();
        $ccInbox  = $this->createInbox();
        $bccInbox = $this->createInbox();

        $email = EmailBuilder::email()
            ->from(getenv('POSTMARK_SENDER'))
            ->to($inbox->getAddress(), 'To')
            ->cc($ccInbox->getAddress(), 'CC')
            ->bcc($ccInbox->getAddress(), 'BCC')
            ->withSubject('Templated')
            ->withContent(new TemplatedContent(
                getenv('POSTMARK_TEMPLATE_ID'),
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

        $message = $this->getLatestEmail($inbox);

        self::assertEquals('Templated', $message->getHeaderValue('subject'));
        self::assertEquals($inbox->getAddress(), $message->getHeaderValue('to'));
        self::assertEquals($ccInbox->getAddress(), $message->getHeaderValue('cc'));
        self::stringStartsWith('HTML', $message->getHtmlContent());
        self::assertEquals('text', trim($message->getTextContent()));
        self::assertHasAttachmentWithContentId($message, 'embed-test');
        self::assertHasAttachmentWithName($message, 'Attached File');
        self::assertEquals('Test', $message->getHeaderValue('x-test-header'));

        $message = $this->getLatestEmail($ccInbox);

        self::assertEquals('Templated', $message->getHeaderValue('subject'));
        self::assertEquals($inbox->getAddress(), $message->getHeaderValue('to'));
        self::assertEquals($ccInbox->getAddress(), $message->getHeaderValue('cc'));
        self::assertStringStartsWith('HTML', $message->getHtmlContent());
        self::assertEquals('text', trim($message->getTextContent()));
        self::assertHasAttachmentWithContentId($message, 'embed-test');
        self::assertHasAttachmentWithName($message, 'Attached File');
        self::assertEquals('Test', $message->getHeaderValue('x-test-header'));

        // @TODO MailSlurp doesn't yet support BCC, but will soon
    }
}
