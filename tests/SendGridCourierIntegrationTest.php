<?php

declare(strict_types=1);

namespace Courier\Test;

use Courier\SendGridCourier;
use Courier\SparkPostCourier;
use PhpEmail\Attachment\FileAttachment;
use PhpEmail\Content\SimpleContent;
use PhpEmail\Content\TemplatedContent;
use PhpEmail\EmailBuilder;

/**
 * @covers \Courier\SendGridCourier
 * @large
 */
class SendGridCourierIntegrationTest extends IntegrationTestCase
{
    /**
     * @var string
     */
    private static $file = '/tmp/attachment_test.txt';

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

        $this->courier = new SendGridCourier(new \SendGrid(getenv('SEND_GRID_KEY')));
    }

    public function testSendsInlineEmail()
    {
        $subject = 'Courier SendGrid Integration Test ' . random_int(100000000, 999999999);

        $email = EmailBuilder::email()
            ->to($this->getTo())
            ->from(getenv('FROM_EMAIL'))
            ->withSubject($subject)
            ->withContent(SimpleContent::text('text')->addHtml('HTML<img src="cid:embed-test"/>'))
            ->cc($this->getCc())
            ->attach(new FileAttachment(self::$file, 'Attached File'))
            ->embed(new FileAttachment(self::$file, 'Embedded File', null, 'image/jpeg'), 'embed-test')
            ->addHeader('X-test-header', 'Test')
            ->build();

        $this->courier->deliver($email);

        $message = $this->getEmailDeliveredToTo($subject);

        self::assertEquals($subject, $message->getHeaderValue('subject'));
        self::assertEquals(getenv('FROM_EMAIL'), $message->getHeaderValue('from'));
        self::assertEquals($this->getTo(), $message->getHeaderValue('to'));
        self::assertEquals($this->getCc(), $message->getHeaderValue('cc'));
        self::stringStartsWith('HTML', $message->getHtmlContent());
        self::assertEquals('text', trim($message->getTextContent()));
        self::assertHasAttachmentWithContentId($message, 'embed-test');
        self::assertHasAttachmentWithName($message, 'Attached File');
        self::assertEquals('Test', $message->getHeaderValue('x-test-header'));

        $message = $this->getEmailDeliveredToCc($subject);

        self::assertEquals($subject, $message->getHeaderValue('subject'));
        self::assertEquals(getenv('FROM_EMAIL'), $message->getHeaderValue('from'));
        self::assertEquals($this->getTo(), $message->getHeaderValue('to'));
        self::assertEquals($this->getCc(), $message->getHeaderValue('cc'));
        self::stringStartsWith('HTML', $message->getHtmlContent());
        self::assertEquals('text', trim($message->getTextContent()));
        self::assertHasAttachmentWithContentId($message, 'embed-test');
        self::assertHasAttachmentWithName($message, 'Attached File');
        self::assertEquals('Test', $message->getHeaderValue('x-test-header'));
    }

    public function testSendsTemplatedEmail()
    {
        $subject = 'Courier SendGrid Integration Templated Test ' . random_int(100000000, 999999999);

        $email = EmailBuilder::email()
            ->from(getenv('FROM_EMAIL'))
            ->to($this->getTo(), 'To')
            ->cc($this->getCc(), 'CC')
            ->withSubject($subject)
            ->withContent(new TemplatedContent(
                getenv('SEND_GRID_TEMPLATE_ID'),
                [
                    '--html--' => 'HTML<img src="cid:embed-test"/>',
                    '--text--' => 'text',
                ]
            ))
            ->attach(new FileAttachment(self::$file, 'Attached File'))
            // @TODO There is a bug with SendGrid that makes templates with multiple attachments fail
            //->embed(new FileAttachment(self::$file, 'Embedded File'), 'embed-test')
            ->addHeader('X-test-header', 'Test')
            ->build();

        $this->courier->deliver($email);

        $message = $this->getEmailDeliveredToTo($subject);

        self::assertEquals($subject, $message->getHeaderValue('subject'));
        self::assertEquals($this->getTo(), $message->getHeaderValue('to'));
        self::assertEquals($this->getCc(), $message->getHeaderValue('cc'));
        self::stringStartsWith('HTML', $message->getHtmlContent());
        self::assertEquals('text', trim($message->getTextContent()));
        // @TODO There is a bug with SendGrid that makes templates with multiple attachments fail
        //self::assertHasEmbeddedWithContentId($message, 'embed-test');
        self::assertHasAttachmentWithName($message, 'Attached File');
        self::assertEquals('Test', $message->getHeaderValue('x-test-header'));

        $message = $this->getEmailDeliveredToCc($subject);

        self::assertEquals($subject, $message->getHeaderValue('subject'));
        self::assertEquals($this->getTo(), $message->getHeaderValue('to'));
        self::assertEquals($this->getCc(), $message->getHeaderValue('cc'));
        self::assertStringStartsWith('HTML', $message->getHtmlContent());
        self::assertEquals('text', trim($message->getTextContent()));
        // @TODO There is a bug with SendGrid that makes templates with multiple attachments fail
        //self::assertHasEmbeddedWithContentId($message, 'embed-test');
        self::assertHasAttachmentWithName($message, 'Attached File');
        self::assertEquals('Test', $message->getHeaderValue('x-test-header'));
    }

    /**
     * There appears to be a bug with SendGrid in whic the BCC is not included when both attachments and CC recipients are included.
     * When the email includes a BCC list, it can either support having an attachment, or it can support having a CC, but not both.
     * This test ensures our standard features still work when using a recipient as well as a BCC list.
     */
    public function testSendsInlineEmailWithBcc()
    {
        $subject = 'Courier SendGrid Integration Test ' . random_int(100000000, 999999999);

        $email = EmailBuilder::email()
            ->to($this->getTo())
            ->from(getenv('FROM_EMAIL'))
            ->withSubject($subject)
            ->withContent(SimpleContent::text('text')->addHtml('HTML<img src="cid:embed-test"/>'))
            ->bcc($this->getBcc())
            ->attach(new FileAttachment(self::$file, 'Attached File'))
            ->embed(new FileAttachment(self::$file, 'Embedded File', null, 'image/jpeg'), 'embed-test')
            ->addHeader('X-test-header', 'Test')
            ->build();

        $this->courier->deliver($email);

        $message = $this->getEmailDeliveredToTo($subject);

        self::assertEquals($subject, $message->getHeaderValue('subject'));
        self::assertEquals(getenv('FROM_EMAIL'), $message->getHeaderValue('from'));
        self::assertEquals($this->getTo(), $message->getHeaderValue('to'));
        self::stringStartsWith('HTML', $message->getHtmlContent());
        self::assertEquals('text', trim($message->getTextContent()));
        self::assertHasAttachmentWithContentId($message, 'embed-test');
        self::assertHasAttachmentWithName($message, 'Attached File');
        self::assertEquals('Test', $message->getHeaderValue('x-test-header'));

        $message = $this->getEmailDeliveredToBcc($subject);

        self::assertEquals($subject, $message->getHeaderValue('subject'));
        self::assertEquals(getenv('FROM_EMAIL'), $message->getHeaderValue('from'));
        self::assertEquals($this->getTo(), $message->getHeaderValue('to'));
        self::stringStartsWith('HTML', $message->getHtmlContent());
        self::assertEquals('text', trim($message->getTextContent()));
        self::assertHasAttachmentWithContentId($message, 'embed-test');
        self::assertHasAttachmentWithName($message, 'Attached File');
        self::assertEquals('Test', $message->getHeaderValue('x-test-header'));
    }

    /**
     * There appears to be a bug with SendGrid in whic the BCC is not included when both attachments and CC recipients are included.
     * When the email includes a BCC list, it can either support having an attachment, or it can support having a CC, but not both.
     * This test ensures our standard features still work when using a recipient as well as a BCC list.
     *
     * This test fails sporadically because the BCC does not get added to the email by SendGrid. Rerunning the test can generally cause it to pass.
     */
    public function testSendsTemplatedEmailWithBcc()
    {
        self::markTestSkipped('This test fails with enough frequency to make automated tests unreliable');

        $subject = 'Courier SendGrid Integration Templated Test ' . random_int(100000000, 999999999);

        $email = EmailBuilder::email()
            ->from(getenv('FROM_EMAIL'))
            ->to($this->getTo(), 'To')
            ->bcc($this->getBcc(), 'BCC')
            ->withSubject($subject)
            ->withContent(new TemplatedContent(
                getenv('SEND_GRID_TEMPLATE_ID'),
                [
                    '--html--' => 'HTML<img src="cid:embed-test"/>',
                    '--text--' => 'text',
                ]
            ))
            ->attach(new FileAttachment(self::$file, 'Attached File'))
            // @TODO There is a bug with SendGrid that makes templates with multiple attachments fail
            //->embed(new FileAttachment(self::$file, 'Embedded File'), 'embed-test')
            ->addHeader('X-test-header', 'Test')
            ->build();

        $this->courier->deliver($email);

        $message = $this->getEmailDeliveredToTo($subject);

        self::assertEquals($subject, $message->getHeaderValue('subject'));
        self::assertEquals($this->getTo(), $message->getHeaderValue('to'));
        self::stringStartsWith('HTML', $message->getHtmlContent());
        self::assertEquals('text', trim($message->getTextContent()));
        // @TODO There is a bug with SendGrid that makes templates with multiple attachments fail
        //self::assertHasEmbeddedWithContentId($message, 'embed-test');
        self::assertHasAttachmentWithName($message, 'Attached File');
        self::assertEquals('Test', $message->getHeaderValue('x-test-header'));

        $message = $this->getEmailDeliveredToBcc($subject);

        self::assertEquals($subject, $message->getHeaderValue('subject'));
        self::assertEquals($this->getTo(), $message->getHeaderValue('to'));
        self::assertStringStartsWith('HTML', $message->getHtmlContent());
        self::assertEquals('text', trim($message->getTextContent()));
        // @TODO There is a bug with SendGrid that makes templates with multiple attachments fail
        //self::assertHasEmbeddedWithContentId($message, 'embed-test');
        self::assertHasAttachmentWithName($message, 'Attached File');
        self::assertEquals('Test', $message->getHeaderValue('x-test-header'));
    }
}
