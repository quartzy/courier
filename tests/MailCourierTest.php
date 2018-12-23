<?php

declare(strict_types=1);

namespace Courier\Test;

use Courier\MailCourier;
use PhpEmail\Attachment\FileAttachment;
use PhpEmail\Content\SimpleContent;
use PhpEmail\EmailBuilder;
use ZBateson\MailMimeParser\MailMimeParser;
use ZBateson\MailMimeParser\Message;

class MailCourierTest extends TestCase
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
            // TODO Test custom headers
            // TODO Build templating functionality
            ->build();

        $courier = new MailCourier();

        $courier->deliver($email);

        $receivedEmail = $this->getEmail($subject);

        self::assertNotNull($receivedEmail, 'Email was not received');

        self::assertEquals('From Testerson', $receivedEmail->getHeader('From')->getParts()[0]->getName());
        self::assertEquals('from@test.com', $receivedEmail->getHeader('From')->getParts()[0]->getEmail());
        self::assertEquals('Reply Testerson', $receivedEmail->getHeader('Reply to')->getParts()[0]->getName());
        self::assertEquals('reply@test.com', $receivedEmail->getHeader('Reply to')->getParts()[0]->getEmail());
        self::assertEquals('Reply Two Testerson', $receivedEmail->getHeader('Reply to')->getParts()[1]->getName());
        self::assertEquals('reply2@test.com', $receivedEmail->getHeader('Reply to')->getParts()[1]->getEmail());
        self::assertEquals('To Testerson', $receivedEmail->getHeader('To')->getParts()[0]->getName());
        self::assertEquals('to@test.com', $receivedEmail->getHeader('To')->getParts()[0]->getEmail());
        self::assertEquals('CC Testerson', $receivedEmail->getHeader('Cc')->getParts()[0]->getName());
        self::assertEquals('cc@test.com', $receivedEmail->getHeader('Cc')->getParts()[0]->getEmail());
        self::assertEquals('CC Two Testerson', $receivedEmail->getHeader('Cc')->getParts()[1]->getName());
        self::assertEquals('cc2@test.com', $receivedEmail->getHeader('Cc')->getParts()[1]->getEmail());
        // There isn't a great way to verify the BCC's using MailHog
        // self::assertEquals('BCC Testerson', $receivedEmail->getHeader('Bcc')->getParts()[0]->getName());
        // self::assertEquals('bcc@test.com', $receivedEmail->getHeader('Bcc')->getParts()[0]->getEmail());
        self::assertEquals(2, $receivedEmail->getAttachmentCount());
        /** @var Message\Part\MimePart $attachment */
        $attachment = $receivedEmail->getAttachmentPart(0);
        self::assertEquals('Test Attachment', $attachment->getFilename());
        self::assertEquals('plain/text', $attachment->getContentType());
        self::assertEquals('attachment', $attachment->getContentDisposition());
        /** @var Message\Part\MimePart $attachment */
        $attachment = $receivedEmail->getAttachmentPart(1);
        self::assertEquals('beaker_pic', $attachment->getFilename());
        self::assertEquals('image/jpeg', $attachment->getContentType());
        self::assertEquals('inline', $attachment->getContentDisposition());
        self::assertEquals('Test Email', $receivedEmail->getTextContent());
        self::assertEquals('<b>Test Email</b><img src="cid:beaker" />', $receivedEmail->getHtmlContent());
        self::assertEquals($subject, $receivedEmail->getHeader('Subject')->getValue());
    }

    private function getEmail(string $subject): ?Message
    {
        sleep(2);

        $ch = curl_init('http://localhost:8025/api/v2/search?kind=containing&query=' . urlencode($subject));

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);

        if ($result === false) {
            throw new \Exception('Unable to access MailHog server');
        }

        $results = json_decode($result, true);

        if (!empty($results) && $results['count']) {
            $message = $results['items'][0];

            $parser = new MailMimeParser();

            $message = $parser->parse($message['Raw']['Data']);

            return $message;
        }

        return  null;
    }
}
